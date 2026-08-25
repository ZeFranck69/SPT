<?php

if (!class_exists('WPvivid_S3Lite_Client')):

class WPvivid_S3Lite_Client
{
    private $cfg;
    private $http;
    private $signer;
    private $multipart_threshold;

    public function __construct($cfg)
    {
        if (!is_array($cfg)) {
            throw new InvalidArgumentException('cfg must be array');
        }

        $defaults = array(
            'access_key' => '',
            'secret_key' => '',
            'session_token' => null,
            'region' => 'us-east-1',
            'endpoint' => '',
            'path_style' => true,
            'signature_version' => 'v4',      // v4 or v2
            'verify_ssl' => true,
            'ca_bundle' => null,
            'ca_path' => null,
            'timeout' => 90,
            'connect_timeout' => 60,
            'multipart_threshold' => 5242880, // 5MiB
            'part_size' => 5242880,           // 5MiB
            'user_agent' => 'WPvivid-S3Lite/1.0',
        );

        $this->cfg = $defaults;
        foreach ($cfg as $k => $v) $this->cfg[$k] = $v;

        $this->cfg['endpoint'] = rtrim((string)$this->cfg['endpoint'], '/');
        if ($this->cfg['endpoint'] === '') throw new InvalidArgumentException('endpoint required');
        if ($this->cfg['access_key'] === '' || $this->cfg['secret_key'] === '') throw new InvalidArgumentException('access_key/secret_key required');

        $this->multipart_threshold = (int)$this->cfg['multipart_threshold'];
        if ($this->multipart_threshold < 0) $this->multipart_threshold = 0;

        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-http-curl.php';
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-signer-v4.php';
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-signer-v2.php';
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-xml.php';

        $this->http = new WPvivid_S3Lite_Http($this->cfg);

        $sig = strtolower((string)$this->cfg['signature_version']);
        if ($sig === 'v2') {
            $this->signer = new WPvivid_S3Lite_Signer_V2($this->cfg);
        }
        else {
            $this->signer = new WPvivid_S3Lite_Signer_V4($this->cfg);
        }
    }

    public function getObjectEx($bucket, $key, $range, $ch)
    {
        $headers = array();
        if ($range !== null && $range !== '') $headers['Range'] = (string)$range;

        $req = $this->buildRequest('GET', (string)$bucket, (string)$key, array(), $headers);
        $signed = $this->signer->sign($req, '', 0);

        $resp=$this->http->request($signed, null, $ch);

        return $resp;
    }

    public function downloadAuto($bucket, $key, $local_file, $download_chunk_size, $start_offset, $current_file_name, $current_file_size, $download_info, $callback, $fh)
    {
        $time_limit = 30;
        $start_time = time();
        $last_time  = time();
        $last_size  = 0;

        $ch=$this->http->initCurl();

        $retry_count = 3;
        $retry_delay = 5;
        while ($start_offset < $current_file_size)
        {
            $attempt = 0;
            $success = false;

            while($attempt < $retry_count && !$success)
            {
                $attempt++;
                $last_byte = min($start_offset + $download_chunk_size - 1, $current_file_size - 1);
                $headers['Range'] = "bytes=$start_offset-$last_byte";
                try{
                    $response = $this->getObjectEx($bucket, $key, $headers['Range'], $ch);
                    if (!$response)
                    {
                        $this->http->closeCurl($ch);
                        return array('result' => WPVIVID_PRO_FAILED, 'error' => 'download ' . $key. ' failed.');
                    }

                    fwrite($fh,$response['body']);

                    clearstatcache();
                    $state = stat($local_file);
                    $start_offset = $state['size'];

                    if ((time() - $last_time) > 3)
                    {
                        if (is_callable($callback)) {
                            call_user_func_array($callback, array($start_offset, $current_file_name,
                                $current_file_size, $last_time, $last_size));
                        }
                        $last_size = $start_offset;
                        $last_time = time();
                    }

                    $success=true;
                }
                catch (RuntimeException $e){
                    if ($attempt < $retry_count) {
                        sleep($retry_delay);
                    } else {
                        $this->http->closeCurl($ch);
                        return array('result' => WPVIVID_PRO_FAILED, 'error' => 'Failed after ' . $retry_count . ' retries.');
                    }
                }
            }

            $time_taken = microtime(true) - $start_time;
            if($time_taken >= $time_limit)
            {
                @fclose($fh);
                $this->http->closeCurl($ch);
                $result['result']='success';
                $result['finished']=0;
                $result['offset']=$start_offset;
                return $result;
            }

        }

        @fclose($fh);
        clearstatcache();

        if(filesize($local_file) != $current_file_size)
        {
            $this->http->closeCurl($ch);
            @unlink($local_file);
            return array('result' => 'failed', 'error' => 'Downloading ' . basename($local_file) . ' failed. ' . basename($local_file) . ' might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.');
        }
        else
        {
            rename($local_file, $download_info['root_path'].$download_info['file_name']);
            $this->http->closeCurl($ch);
            $result['result']='success';
            $result['finished']=1;
            $result['offset']=$current_file_size;
            return $result;
        }
    }

    public function uploadAuto($bucket, $key, $file_path, $callback = null, $state = array())
    {
        $size = @filesize($file_path);
        if ($size === false) {
            throw new RuntimeException('Failed to stat file: '.$file_path);
        }

        if ($size <= $this->multipart_threshold) {
            $r = $this->putObject($bucket, $key, $file_path);
            if (is_callable($callback)) {
                $now = time();
                call_user_func($callback, (int)$size, basename($file_path), (int)$size, $now-1, 0);
            }
            return array('mode'=>'put','file_size'=>(int)$size,'result'=>$r);
        }

        $r = $this->multipartUploadResume($bucket, $key, $file_path, $callback, $state);
        return array('mode'=>'multipart','file_size'=>(int)$size,'result'=>$r);
    }

    public function putObject($bucket, $key, $file_path, $extra_headers = array())
    {
        $bucket = (string)$bucket; $key = (string)$key;
        $headers = array_merge(array(
            'Content-Type' => 'application/octet-stream',
        ), is_array($extra_headers) ? $extra_headers : array());

        $retry_count = 3;
        $retry_delay = 5;
        $attempt = 0;
        while ($attempt < $retry_count) {
            $attempt++;
            $req = $this->buildRequest('PUT', $bucket, $key, array(), $headers);

            $fp = @fopen($file_path, 'rb');
            if (!$fp) throw new RuntimeException('Failed to open file: '.$file_path);
            $len = (int)@filesize($file_path);
            if ($len < 0) $len = 0;

            $signed = $this->signer->sign($req, '', $len);
            $ch=$this->http->initCurl();

            try{
                $resp = $this->http->requestStreamUpload($signed, $fp, $len, $ch);
                $this->http->closeCurl($ch);
                fclose($fp);

                return $resp;
            }
            catch (RuntimeException $e){
                $errorMsg = $e->getMessage();
                $this->http->closeCurl($ch);
                fclose($fp);
                if (strpos($errorMsg, 'Operation timed out') !== false && $attempt < $retry_count) {
                    usleep($retry_delay);
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    public function getObject($bucket, $key, $save_to = null, $range = null, $extra_headers = array())
    {
        $headers = is_array($extra_headers) ? $extra_headers : array();
        if ($range !== null && $range !== '') $headers['Range'] = (string)$range;

        $req = $this->buildRequest('GET', (string)$bucket, (string)$key, array(), $headers);
        $signed = $this->signer->sign($req, '', 0);

        if ($save_to !== null && $save_to !== '') {
            $ch=$this->http->initCurl();
            $resp=$this->http->requestStreamDownload($signed, (string)$save_to, $ch);
            $this->http->closeCurl($ch);

            return $resp;
        }

        $ch=$this->http->initCurl();
        $resp=$this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function listObjectsV2($args)
    {
        if (!is_array($args)) $args = array();
        $bucket = isset($args['Bucket']) ? (string)$args['Bucket'] : (isset($args['bucket']) ? (string)$args['bucket'] : '');
        if ($bucket === '') throw new InvalidArgumentException('Bucket required');

        $query = array('list-type' => '2');
        foreach (array('Prefix'=>'prefix','Delimiter'=>'delimiter','MaxKeys'=>'max-keys','ContinuationToken'=>'continuation-token','StartAfter'=>'start-after') as $k=>$qk)
        {
            if (isset($args[$k]) && $args[$k] !== '' && $args[$k] !== null)
            {
                $query[$qk] = (string)$args[$k];
            }
        }

        $headers = array();
        if (isset($args['RequestPayer'])) $headers['x-amz-request-payer'] = (string)$args['RequestPayer'];

        $req = $this->buildRequest('GET', $bucket, '', $query, $headers);
        $signed = $this->signer->sign($req, '', 0);
        $ch=$this->http->initCurl();
        $resp=$this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function listObjects($args)
    {
        return $this->listObjectsV2($args);
    }

    public function deleteObjects($bucket, $keys, $extra_headers = array())
    {
        $bucket = (string)$bucket;
        $headers = is_array($extra_headers) ? $extra_headers : array();

        $list = array();
        if (is_array($keys)) {
            foreach ($keys as $k) {
                if ($k === null) continue;
                $k = (string)$k;
                if ($k === '') continue;
                $list[] = $k;
            }
        }

        if ($bucket === '' || empty($list)) {
            return array('ok' => true, 'status' => 200, 'headers' => array(), 'body' => '');
        }

        $xml = '<Delete><Quiet>true</Quiet>';
        foreach ($list as $k) {
            $xml .= '<Object><Key>' . htmlspecialchars($k, ENT_XML1) . '</Key></Object>';
        }
        $xml .= '</Delete>';

        $headers['Content-Type'] = 'application/xml';
        $headers['Content-Length'] = strlen($xml);

        $req = $this->buildRequest('POST', $bucket, '', array('delete' => ''), $headers);
        $signed = $this->signer->sign($req, $xml, strlen($xml));
        $ch=$this->http->initCurl();
        $resp=$this->http->request($signed, $xml, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function deleteObject($bucket, $key, $extra_headers = array())
    {
        $req = $this->buildRequest('DELETE', (string)$bucket, (string)$key, array(), is_array($extra_headers)?$extra_headers:array());
        $signed = $this->signer->sign($req, '', 0);
        $ch=$this->http->initCurl();
        $resp=$this->http->request($signed, null, $ch);
        $this->http->closeCurl($ch);

        return $resp;
    }

    public function multipartUploadResume($bucket, $key, $file_path, $callback = null, $state = array(), $extra_headers = array(), $persist_cb = null)
    {
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-multipart.php';
        $mp = new WPvivid_S3Lite_Multipart($this->cfg, $this->http, $this->signer);

        $part_size = isset($this->cfg['part_size']) ? (int)$this->cfg['part_size'] : 5242880;
        if ($part_size < 5*1024*1024) $part_size = 5*1024*1024;

        try {
            return $mp->uploadResume($bucket, $key, $file_path, $part_size, $callback, $state, $extra_headers, $persist_cb);
        }
        catch (Exception $e) {
            return new WP_Error('s3lite_multipart_resume_failed', $e->getMessage());
        }
    }

    private function buildRequest($method, $bucket, $key, $query, $headers)
    {
        $endpoint = $this->cfg['endpoint'];
        $path_style = !empty($this->cfg['path_style']);

        return WPvivid_S3Lite_Utils::build_request($endpoint, $method, $bucket, $key, $query, $headers, $path_style);
    }

    public function buildObjectUrl($bucket, $key)
    {
        $key = ltrim($key, '/');

        $scheme = isset($this->use_ssl) && $this->use_ssl ? 'https' : 'http';
        $host = isset($this->host) ? $this->host : (isset($this->endpoint) ? $this->endpoint : '');
        $host = preg_replace('#^https?://#i', '', $host);

        $path_style = isset($this->path_style) ? (bool)$this->path_style : false;
        if ($path_style) {
            return $scheme . '://' . $host . '/' . rawurlencode($bucket) . '/' . str_replace('%2F','/',rawurlencode($key));
        } else {
            return $scheme . '://' . rawurlencode($bucket) . '.' . $host . '/' . str_replace('%2F','/',rawurlencode($key));
        }
    }
}

endif;
