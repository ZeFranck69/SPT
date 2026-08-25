<?php
if (!class_exists('WPvivid_S3Lite_Multipart')):

class WPvivid_S3Lite_Multipart
{
    private $cfg;
    private $http;
    private $signer;

    public function __construct($cfg, $http, $signer)
    {
        $this->cfg = is_array($cfg)?$cfg:array();
        $this->http = $http;
        $this->signer = $signer;
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-xml.php';
    }

    /**
     * @throws \Exception
     * @throws \RuntimeException
     */
    public function uploadResume($bucket, $key, $file_path, $part_size, $callback, $state, $extra_headers, $persist_cb = null)
    {
        $bucket = (string)$bucket; $key = (string)$key;
        $file_size = @filesize($file_path);
        if ($file_size === false) throw new RuntimeException('Failed to stat file: '.$file_path);
        $file_size = (int)$file_size;

        $upload_id = '';
        if (is_array($state) && !empty($state['upload_id'])) $upload_id = (string)$state['upload_id'];

        $existing = array();
        if ($upload_id !== '') {
            $existing = $this->listPartsSafe($bucket, $key, $upload_id);
            if ($existing === null) {
                // NoSuchUpload or other failure => recreate
                $upload_id = '';
                $existing = array();
            }
        }
        if ($upload_id === '') {
            $upload_id = $this->createMultipartUpload($bucket, $key, $extra_headers);
            $existing = array();
        }

        $fp = @fopen($file_path, 'rb');
        if (!$fp) throw new RuntimeException('Failed to open file: '.$file_path);

        $parts = array();
        foreach ($existing as $pn => $etag) $parts[(int)$pn] = $etag;

        if (is_callable($persist_cb)) {
            try {
                call_user_func($persist_cb, array(
                    'upload_id' => $upload_id,
                    'parts' => $parts,
                    'file_path' => $file_path,
                    'file_size' => $file_size,
                    'part_size' => $part_size,
                    'phase' => 'init'
                ));
            } catch (Exception $ignore) {}
        }

        $last_time = time();
        $last_size = 0;
        $offset = 0;

        $pn = 1;
        while (isset($parts[$pn])) {
            $offset += $part_size;
            $pn++;
        }
        if ($offset > $file_size) $offset = $file_size;

        if ($offset > 0) {
            fseek($fp, $offset);
            $last_size = $offset;
        }

        try {
            $part_number = $pn;
            $ch=$this->http->initCurl();
            while ($offset < $file_size) {
                $remaining = $file_size - $offset;
                $len = ($remaining > $part_size) ? $part_size : $remaining;

                if (isset($parts[$part_number])) {
                    $offset += $len;
                    fseek($fp, $offset);
                    $part_number++;
                    continue;
                }

                $resp = $this->uploadPartStream($bucket, $key, $upload_id, $part_number, $fp, $len, $ch);
                if (empty($resp['ok'])) {
                    // Handle NoSuchUpload -> recreate once
                    $code = WPvivid_S3Lite_Xml::error_code(isset($resp['body'])?$resp['body']:'');
                    if ($code === 'NoSuchUpload') {
                        $upload_id = $this->createMultipartUpload($bucket, $key, $extra_headers);
                        $parts = array();
                        $offset = 0;
                        fseek($fp, 0);
                        $part_number = 1;
                        $last_time = time();
                        $last_size = 0;
                        continue;
                    }
                    throw new RuntimeException('UploadPart failed HTTP='.$resp['status'].' body='.WPvivid_S3Lite_Utils::safe_substr(isset($resp['body'])?$resp['body']:'', 400));
                }

                $etag = isset($resp['headers']['etag']) ? $resp['headers']['etag'] : '';
                if ($etag === '') {
                    throw new RuntimeException('UploadPart missing ETag (part='.$part_number.')');
                }
                $parts[$part_number] = $etag;

                if (is_callable($persist_cb)) {
                    try {
                        call_user_func($persist_cb, array(
                            'upload_id' => $upload_id,
                            'parts' => $parts,
                            'file_path' => $file_path,
                            'file_size' => $file_size,
                            'part_size' => $part_size,
                            'offset' => $offset + $len,
                            'phase' => 'part',
                            'part_number' => $part_number
                        ));
                    } catch (Exception $ignore) {}
                }

                $offset += $len;
                $part_number++;

                if (is_callable($callback)) {
                    $now = time();
                    if (($now - $last_time) >= 3 || $offset >= $file_size) {
                        call_user_func($callback, $offset, basename($file_path), $file_size, $last_time, $last_size);
                        $last_time = $now;
                        $last_size = $offset;
                    }
                }
            }
            $this->http->closeCurl($ch);
            $complete = $this->completeMultipartUpload($bucket, $key, $upload_id, $parts);
            if (empty($complete['ok'])) {
                throw new RuntimeException('CompleteMultipartUpload failed HTTP='.$complete['status'].' body='.WPvivid_S3Lite_Utils::safe_substr(isset($complete['body'])?$complete['body']:'', 400));
            }

            if (is_callable($persist_cb)) {
                try {
                    call_user_func($persist_cb, array(
                        'upload_id' => $upload_id,
                        'parts' => $parts,
                        'file_path' => $file_path,
                        'file_size' => $file_size,
                        'part_size' => $part_size,
                        'phase' => 'complete'
                    ));
                } catch (Exception $ignore) {}
            }

            fclose($fp);

            return array(
                'ok' => true,
                'upload_id' => $upload_id,
                'parts' => $parts,
                'complete' => $complete,
            );

        } catch (Exception $e) {
            fclose($fp);
            try { $this->abortMultipartUpload($bucket, $key, $upload_id); } catch (Exception $ignore) {}
            throw $e;
        }
    }

    public function createMultipartUpload($bucket, $key, $extra_headers)
    {
        $req = WPvivid_S3Lite_Utils::build_request($this->cfg['endpoint'], 'POST', $bucket, $key, array('uploads'=>''), $extra_headers, !empty($this->cfg['path_style']));
        $signed = $this->signer->sign($req, '', 0);
        $ch=$this->http->initCurl();
        $resp = $this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);
        if (empty($resp['ok'])) {
            throw new RuntimeException('CreateMultipartUpload failed HTTP='.$resp['status'].' body='.WPvivid_S3Lite_Utils::safe_substr(isset($resp['body'])?$resp['body']:'', 400));
        }
        $upload_id = WPvivid_S3Lite_Xml::first(isset($resp['body'])?$resp['body']:'', 'UploadId');
        if (!$upload_id) throw new RuntimeException('CreateMultipartUpload missing UploadId');
        return (string)$upload_id;
    }

    private function listPartsSafe($bucket, $key, $upload_id)
    {
        $req = WPvivid_S3Lite_Utils::build_request($this->cfg['endpoint'], 'GET', $bucket, $key, array('uploadId'=>$upload_id), array(), !empty($this->cfg['path_style']));
        $signed = $this->signer->sign($req, '', 0);
        $ch=$this->http->initCurl();
        $resp = $this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);
        if (empty($resp['ok'])) {
            $code = WPvivid_S3Lite_Xml::error_code(isset($resp['body'])?$resp['body']:'');
            if ($code === 'NoSuchUpload') return null;
            return null;
        }

        $xml = isset($resp['body'])?$resp['body']:'';

        $parts = array();
        if (!empty($xml)) {
            $sx = @simplexml_load_string($xml);
            if ($sx !== false) {
                $ns = $sx->getNamespaces(true);
                if (!empty($ns)) {
                    $sx->registerXPathNamespace('s3', reset($ns));
                    $nodes = $sx->xpath('//s3:Part');
                }
                else {
                    $nodes = $sx->xpath('//Part');
                }

                if (is_array($nodes)) {
                    foreach ($nodes as $p) {
                        $pn = (int)$p->PartNumber;
                        $etag = (string)$p->ETag;
                        $etag = html_entity_decode($etag, ENT_QUOTES);
                        $etag = trim($etag, "\" \t\r\n");

                        if ($pn > 0 && $etag !== '') {
                            $parts[$pn] = $etag;
                        }
                    }
                }
            }
        }
        return $parts;
    }

    /**
     * @throws \Exception
     * @throws \RuntimeException
     */
    public function uploadPart($bucket, $key, $upload_id, $part_number, $body, $extraHeaders = array())
    {
        $fp = null;
        $len = 0;

        if (is_resource($body)) {
            $fp = $body;
            $pos = @ftell($fp);
            if ($pos !== false) {
                @fseek($fp, 0, SEEK_END);
                $end = @ftell($fp);
                if ($end !== false) $len = $end - $pos;
                @fseek($fp, $pos, SEEK_SET);
            }
        } elseif (is_string($body) && $body !== '' && @file_exists($body)) {
            $fp = @fopen($body, 'rb');
            if ($fp === false) {
                throw new Exception('Failed to open file: '.$body);
            }
            $len = @filesize($body);
        } else {
            $data = is_string($body) ? $body : '';
            $len = strlen($data);
            $fp = fopen('php://temp', 'w+b');
            if ($len > 0) {
                fwrite($fp, $data);
                rewind($fp);
            }
        }

        try {
            $ch=$this->http->initCurl();
            $resp = $this->uploadPartStream($bucket, $key, $upload_id, $part_number, $fp, $len, $ch);
            $this->http->closeCurl($ch);
            if (empty($resp['ok'])) {
                throw new RuntimeException('UploadPart failed HTTP='.(isset($resp['status'])?$resp['status']:'').' body='.WPvivid_S3Lite_Utils::safe_substr(isset($resp['body'])?$resp['body']:'', 400));
            }
            $etag = null;
            if (isset($resp['headers']['etag'])) $etag = $resp['headers']['etag'];
            if (isset($resp['headers']['ETag'])) $etag = $resp['headers']['ETag'];
            if ($etag === null) {
                if (isset($resp['headers']) && is_array($resp['headers'])) {
                    foreach ($resp['headers'] as $hk => $hv) {
                        if (strtolower($hk) === 'etag') { $etag = $hv; break; }
                    }
                }
            }
            if ($etag === null) throw new RuntimeException('UploadPart missing ETag');
            return (string)$etag;
        }
        finally {
            if (!is_resource($body) && is_resource($fp)) {
                @fclose($fp);
            }
        }
    }

    /**
     * @throws \Exception
     * @throws \RuntimeException
     */
    function uploadPartStream($bucket, $key, $upload_id, $part_number, $fp, $len, $ch)
    {
        $qs = array('partNumber'=>(string)(int)$part_number, 'uploadId'=>(string)$upload_id);
        $headers = array('Content-Type'=>'application/octet-stream');

        $req = WPvivid_S3Lite_Utils::build_request($this->cfg['endpoint'], 'PUT', $bucket, $key, $qs, $headers, !empty($this->cfg['path_style']));
        $signed = $this->signer->sign($req, '', (int)$len);
        $start_pos = ftell($fp);
        if ($start_pos === false) $start_pos = null;

        $max_retry = isset($this->cfg['part_retry']) ? (int)$this->cfg['part_retry'] : 3;
        if ($max_retry < 1) $max_retry = 1;
        if ($max_retry > 8) $max_retry = 8;

        $last_err = null;
        for ($i = 0; $i < $max_retry; $i++) {
            if ($start_pos !== null) {
                @fseek($fp, $start_pos);
            }

            try {
                $resp = $this->http->requestStreamUpload($signed, $fp, (int)$len, $ch);
                if (!is_array($resp)) {
                    throw new RuntimeException('uploadPartStream: invalid response');
                }

                $status = isset($resp['status']) ? (int)$resp['status'] : 0;
                $ok = isset($resp['ok']) ? (bool)$resp['ok'] : ($status > 0 && $status < 400);

                if ($ok) {
                    return $resp;
                }

                if (in_array($status, array(0, 408, 429, 500, 502, 503, 504), true)) {
                    throw new RuntimeException('HTTP '.$status);
                }

                return $resp;
            }
            catch (RuntimeException $e) {
                $last_err = $e;
                $msg = $e->getMessage();
                $retryable = false;
                if (strpos($msg, 'cURL error:') !== false) {
                    // Common transient curl errors
                    if (strpos($msg, 'Operation timed out') !== false) $retryable = true;
                    if (strpos($msg, 'Recv failure') !== false) $retryable = true;
                    if (strpos($msg, 'Connection was reset') !== false) $retryable = true;
                    if (strpos($msg, 'Failed to connect') !== false) $retryable = true;
                    if (strpos($msg, 'Empty reply from server') !== false) $retryable = true;
                }
                if (strpos($msg, 'HTTP 0') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 408') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 429') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 500') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 502') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 503') !== false) $retryable = true;
                if (strpos($msg, 'HTTP 504') !== false) $retryable = true;

                if (!$retryable || $i === $max_retry - 1) {
                    throw $e;
                }

                $sleep = 1 << $i;
                if ($sleep > 8) $sleep = 8;
                @sleep($sleep);
            }
        }

        if ($last_err) {
            throw $last_err;
        }

        $ch=$this->http->initCurl();
        $resp = $this->http->requestStreamUpload($signed, $fp, (int)$len, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function completeMultipartUpload($bucket, $key, $upload_id, $parts, $extraHeaders = array())
    {
        $xml = '<CompleteMultipartUpload>';
        $pns = array_keys($parts);
        sort($pns);
        foreach ($pns as $pn) {
            $etag = (string)$parts[$pn];
            $etag = trim($etag);
            if ($etag !== '' && $etag[0] !== '"') {
                $etag = '"' . trim($etag, '"') . '"';
            }
            $xml .= '<Part><PartNumber>' . (int)$pn . '</PartNumber><ETag>' . str_replace(array('&','<','>'), array('&amp;','&lt;','&gt;'), $etag) . '</ETag></Part>';
        }
        $xml .= '</CompleteMultipartUpload>';

        $req = WPvivid_S3Lite_Utils::build_request($this->cfg['endpoint'], 'POST', $bucket, $key, array('uploadId'=>(string)$upload_id), array('Content-Type'=>'application/xml'), !empty($this->cfg['path_style']));
        $signed = $this->signer->sign($req, $xml, 0);
        $ch=$this->http->initCurl();
        $resp = $this->http->request($signed, $xml, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function abortMultipartUpload($bucket, $key, $upload_id, $extraHeaders = array())
    {
        $req = WPvivid_S3Lite_Utils::build_request($this->cfg['endpoint'], 'DELETE', $bucket, $key, array('uploadId'=>(string)$upload_id), array(), !empty($this->cfg['path_style']));
        $signed = $this->signer->sign($req, '', 0);
        $ch=$this->http->initCurl();
        $resp = $this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }
}

endif;
