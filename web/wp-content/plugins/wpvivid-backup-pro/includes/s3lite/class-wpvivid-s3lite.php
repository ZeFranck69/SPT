<?php

if (!class_exists('WPvivid_S3Lite_Compat')) {

require_once dirname(__FILE__) . '/class-wpvivid-s3lite-client.php';
require_once dirname(__FILE__) . '/class-wpvivid-s3lite-multipart.php';
require_once dirname(__FILE__) . '/class-wpvivid-s3lite-state.php';


class WPvivid_S3Lite_Result implements ArrayAccess, IteratorAggregate
{
    private $data = array();

    public function __construct($data = array())
    {
        if (is_array($data)) {
            $this->data = $data;
        }
    }

    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function toArray()
    {
        return $this->data;
    }

    // ArrayAccess
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset === null) return;
        $this->data[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }
}

class WPvivid_S3Lite_Compat
{
    private $client;
    private $cfg;
    private $store;
    private $multipart;

    public function __construct($cfg)
    {
        $this->cfg = is_array($cfg) ? $cfg : array();
        $this->client = new WPvivid_S3Lite_Client($cfg);
        $this->store = new WPvivid_S3Lite_State_Store();

        $http = new WPvivid_S3Lite_Http($this->cfg);
        $sig = isset($this->cfg['signature_version']) ? strtolower((string)$this->cfg['signature_version']) : 'v4';
        if ($sig === 'v2') {
            $signer = new WPvivid_S3Lite_Signer_V2($this->cfg);
        } else {
            $signer = new WPvivid_S3Lite_Signer_V4($this->cfg);
        }
        $this->multipart = new WPvivid_S3Lite_Multipart($this->cfg, $http, $signer);

    }

    public function downloadAuto($bucket, $key, $local_file, $download_chunk_size, $start_offset, $current_file_name, $current_file_size, $download_info, $callback, $fh)
    {
        $r = $this->client->downloadAuto($bucket, $key, $local_file, $download_chunk_size, $start_offset, $current_file_name, $current_file_size, $download_info, $callback, $fh);
        return $r;
    }

    public function uploadAuto($bucket, $key, $filePath, $partSize = 5242880, $extraHeaders = array(), $callback = null, $state = array())
    {
        $size = @filesize($filePath);
        if ($size === false) {
            throw new RuntimeException('Failed to stat file: '.$filePath);
        }
        $size = (int)$size;

        $threshold = isset($this->cfg['multipart_threshold']) ? (int)$this->cfg['multipart_threshold'] : 5242880;
        if ($threshold < 0) $threshold = 5242880;

        if ($size <= $threshold) {
            $r = $this->client->putObject($bucket, $key, $filePath, $extraHeaders);
            if (is_callable($callback)) {
                $now = time();
                call_user_func($callback, (int)$size, basename($filePath), (int)$size, $now-1, 0);
            }

            $this->store->delete($this->store->make_state_key($bucket, $key));
            return array('mode'=>'put','file_size'=>$size,'result'=>$r);
        }

        $state_key = $this->store->make_state_key($bucket, $key);
        $stored = $this->store->load($state_key);
        $sig = $this->store->file_signature($filePath);

        if (is_array($stored) && isset($stored['file']) && is_array($stored['file'])) {
            if ((int)$stored['file']['size'] !== (int)$sig['size'] || (int)$stored['file']['mtime'] !== (int)$sig['mtime']) {
                $this->store->delete($state_key);
                $stored = null;
            }
        }

        $use_state = (is_array($state) && !empty($state)) ? $state : (is_array($stored) ? $stored : array());

        $persist_cb = function($info) use ($state_key, $sig, $stored, $filePath) {
            if (!is_array($info) || empty($info['upload_id'])) return;

            static $last_save_ts = 0;
            $now = time();
            $phase = isset($info['phase']) ? (string)$info['phase'] : '';
            if ($phase === 'part' && ($now - $last_save_ts) < 3) {
                return;
            }

            $save = array(
                'upload_id' => (string)$info['upload_id'],
                'parts' => (isset($info['parts']) && is_array($info['parts'])) ? $info['parts'] : array(),
                'file' => $sig,
                'part_size' => isset($info['part_size']) ? (int)$info['part_size'] : null,
                'created' => (is_array($stored) && isset($stored['created'])) ? (int)$stored['created'] : time(),
                'updated' => $now,
            );

            $this->store->save($state_key, $save);
            $last_save_ts = $now;

            if ($phase === 'complete') {
                $this->store->delete($state_key);
            }
        };

        $r = $this->client->multipartUploadResume($bucket, $key, $filePath, $callback, $use_state, $extraHeaders, $persist_cb);
        if (is_wp_error($r)) {
            $this->store->delete($state_key);
            return array(
                'result'    => WPVIVID_PRO_FAILED,
                'error'     => $r->get_error_message(),
                'error_code'=> $r->get_error_code(),
                'mode'      => 'multipart',
                'file_size' => $size,
            );
        }

        if (is_array($r) && !empty($r['upload_id']) && !empty($r['parts'])) {
            $save = array(
                'upload_id' => (string)$r['upload_id'],
                'parts' => $r['parts'],
                'file' => $sig,
                'part_size' => isset($this->cfg['part_size']) ? (int)$this->cfg['part_size'] : null,
                'created' => (is_array($stored) && isset($stored['created'])) ? (int)$stored['created'] : time(),
            );
            $this->store->save($state_key, $save);

            if (!empty($r['complete'])) {
                $this->store->delete($state_key);
            }
        }

        return array('mode'=>'multipart','file_size'=>$size,'result'=>$r);
    }

    public function putObject($bucket, $key = null, $filePath = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null && $filePath === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';

            $tmpFile = null;
            if (isset($args['SourceFile']) && $args['SourceFile'] !== '') {
                $filePath = $args['SourceFile'];
            }
            elseif (array_key_exists('Body', $args)) {
                $body = $args['Body'];
                if (is_string($body) && $body !== '' && !@file_exists($body)) {
                    $tmpFile = @tempnam(sys_get_temp_dir(), 's3lite_body_');
                    if ($tmpFile !== false) {
                        @file_put_contents($tmpFile, $body);
                        $filePath = $tmpFile;
                    } else {
                        $filePath = $body;
                    }
                } else {
                    $filePath = $body;
                }
            }
            else {
                $filePath = null;
            }

            if (isset($args['ContentType'])) $extraHeaders['Content-Type'] = $args['ContentType'];
            if (isset($args['ACL'])) $extraHeaders['x-amz-acl'] = $args['ACL'];
            if (isset($args['CacheControl'])) $extraHeaders['Cache-Control'] = $args['CacheControl'];
            if (isset($args['ContentDisposition'])) $extraHeaders['Content-Disposition'] = $args['ContentDisposition'];
            if (isset($args['ContentEncoding'])) $extraHeaders['Content-Encoding'] = $args['ContentEncoding'];
        }

        $this->store->delete($this->store->make_state_key($bucket, $key));
        try {
            $resp = $this->client->putObject($bucket, $key, $filePath, $extraHeaders);
            $etag = null;
            if (is_array($resp) && isset($resp['headers'])) {
                if (isset($resp['headers']['etag'])) $etag = $resp['headers']['etag'];
                if (isset($resp['headers']['ETag'])) $etag = $resp['headers']['ETag'];
            }
            $data = array(
                'Bucket' => $bucket,
                'Key' => $key,
                'ETag' => $etag,
                'ObjectURL' => $this->client->buildObjectUrl($bucket, $key),
                '@metadata' => array('statusCode' => (is_array($resp) && isset($resp['code'])) ? $resp['code'] : null),
                '_raw' => $resp,
            );
            return new WPvivid_S3Lite_Result($data);
        }
        finally {
            if (isset($tmpFile) && $tmpFile && @file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }


    public function multipartUpload($bucket, $key, $filePath, $partSize = 5242880, $extraHeaders = array(), $callback = null, $state = array())
    {
        $state_key = $this->store->make_state_key($bucket, $key);
        $stored = $this->store->load($state_key);
        $sig = $this->store->file_signature($filePath);

        if (is_array($stored) && isset($stored['file']) && is_array($stored['file'])) {
            if ((int)$stored['file']['size'] !== (int)$sig['size'] || (int)$stored['file']['mtime'] !== (int)$sig['mtime']) {
                $stored = null;
            }
        }

        $use_state = (is_array($state) && !empty($state)) ? $state : (is_array($stored) ? $stored : array());

        $r = $this->client->multipartUploadResume($bucket, $key, $filePath, $callback, $use_state, $extraHeaders);

        if (is_array($r) && !empty($r['upload_id']) && !empty($r['parts'])) {
            $save = array(
                'upload_id' => (string)$r['upload_id'],
                'parts' => $r['parts'],
                'file' => $sig,
                'part_size' => isset($this->cfg['part_size']) ? (int)$this->cfg['part_size'] : null,
                'created' => (is_array($stored) && isset($stored['created'])) ? (int)$stored['created'] : time(),
            );
            $this->store->save($state_key, $save);

            if (!empty($r['complete'])) {
                $this->store->delete($state_key);
            }
        }

        return $r;
    }

    public function getObject($bucket, $key = null, $saveTo = null, $range = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : (isset($args['bucket']) ? $args['bucket'] : '');
            $key    = isset($args['Key']) ? $args['Key'] : (isset($args['key']) ? $args['key'] : '');

            // Some SDKs use SaveAs / SaveTo / save_to
            if ($saveTo === null && isset($args['SaveAs'])) $saveTo = $args['SaveAs'];
            if ($saveTo === null && isset($args['SaveTo'])) $saveTo = $args['SaveTo'];
            if ($saveTo === null && isset($args['save_to'])) $saveTo = $args['save_to'];

            if ($range === null && isset($args['Range'])) $range = $args['Range'];
            if ($range === null && isset($args['range'])) $range = $args['range'];

            if (isset($args['headers']) && is_array($args['headers'])) {
                $extraHeaders = array_merge($extraHeaders, $args['headers']);
            }
            if (isset($args['@http']['headers']) && is_array($args['@http']['headers'])) {
                $extraHeaders = array_merge($extraHeaders, $args['@http']['headers']);
            }
        }

        $resp = $this->client->getObject($bucket, $key, $saveTo, $range, $extraHeaders);

        if ($saveTo !== null && $saveTo !== '') {
            return new WPvivid_S3Lite_Result(array(
                'Bucket' => $bucket,
                'Key' => $key,
                '@metadata' => array('statusCode' => (is_array($resp) && isset($resp['status'])) ? (int)$resp['status'] : null),
                '_raw' => $resp,
            ));
        }

        $headers = (is_array($resp) && isset($resp['headers']) && is_array($resp['headers'])) ? $resp['headers'] : array();
        $body = (is_array($resp) && array_key_exists('body', $resp)) ? $resp['body'] : null;

        $data = array(
            'Body' => ($body === null ? '' : $body),
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentLength' => isset($headers['content-length']) ? (int)$headers['content-length'] : null,
            'ContentRange' => isset($headers['content-range']) ? $headers['content-range'] : null,
            'ETag' => isset($headers['etag']) ? $headers['etag'] : null,
            'LastModified' => isset($headers['last-modified']) ? $headers['last-modified'] : null,
            '@metadata' => array('statusCode' => (is_array($resp) && isset($resp['status'])) ? (int)$resp['status'] : null),
            '_raw' => $resp,
        );

        return new WPvivid_S3Lite_Result($data);
    }

    public function listObjectsV2($bucket, $prefix = null)
    {
        if (is_array($bucket) && $prefix === null)
        {
            $args = $bucket;
        }
        else
        {
            $args = array('Bucket' => $bucket, 'Prefix' => $prefix);
        }

        $resp = $this->client->listObjectsV2($args);
        if (!is_array($resp) || empty($resp['ok']))
        {
            $body = is_array($resp) && isset($resp['body']) ? (string)$resp['body'] : '';
            $code = is_array($resp) && isset($resp['status']) ? (int)$resp['status'] : 0;
            $err  = WPvivid_S3Lite_Xml::error_message($body);
            if (!$err) $err = 'ListObjects failed';
            return new WPvivid_S3Lite_Result(array(
                'result' => 'failed',
                'error' => $err,
                '@metadata' => array('statusCode' => $code),
                '_raw' => $resp,
            ));
        }

        $xml = (string)$resp['body'];
        $contents = array();
        if (preg_match_all('#<Contents>(.*?)</Contents>#s', $xml, $ms))
        {
            foreach ($ms[1] as $chunk)
            {
                $k = WPvivid_S3Lite_Xml::first($chunk, 'Key');
                if ($k === null) continue;
                $contents[] = array(
                    'Key' => $k,
                    'LastModified' => WPvivid_S3Lite_Xml::first($chunk, 'LastModified'),
                    'ETag' => WPvivid_S3Lite_Xml::first($chunk, 'ETag'),
                    'Size' => (int)WPvivid_S3Lite_Xml::first($chunk, 'Size'),
                );
            }
        }

        $is_truncated = WPvivid_S3Lite_Xml::first($xml, 'IsTruncated');
        $next_token = WPvivid_S3Lite_Xml::first($xml, 'NextContinuationToken');

        $data = array(
            'Contents' => $contents,
            'IsTruncated' => ($is_truncated === 'true' || $is_truncated === '1'),
            'NextContinuationToken' => $next_token,
            '@metadata' => array('statusCode' => (int)$resp['status']),
            '_raw' => $resp,
        );

        return new WPvivid_S3Lite_Result($data);
    }

    public function listObjects($bucket, $prefix = null)
    {
        return $this->listObjectsV2($bucket, $prefix);
    }

    public function deleteObjects($bucket, $objects = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $objects === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? (string)$args['Bucket'] : '';

            if (isset($args['Delete']) && is_array($args['Delete']) &&
                isset($args['Delete']['Objects']) && is_array($args['Delete']['Objects'])) {
                $objects = $args['Delete']['Objects'];
            } elseif (isset($args['Objects']) && is_array($args['Objects'])) {
                $objects = $args['Objects'];
            } else {
                $objects = array();
            }
        }

        $keys = array();
        if (is_array($objects)) {
            foreach ($objects as $o) {
                if (is_array($o) && isset($o['Key'])) {
                    $keys[] = (string)$o['Key'];
                } elseif (is_string($o)) {
                    $keys[] = $o;
                }
            }
        }

        if ($bucket === '' || empty($keys)) {
            return new WPvivid_S3Lite_Result(array(
                'Deleted' => array(),
                'Errors'  => array(),
            ));
        }

        $resp = $this->client->deleteObjects($bucket, $keys, $extraHeaders);

        $deleted = array();
        $errors  = array();

        if (is_array($resp) && isset($resp['body']) && is_string($resp['body']) && $resp['body'] !== '') {
            $xml = @simplexml_load_string($resp['body']);
            if ($xml instanceof SimpleXMLElement) {

                if (isset($xml->Deleted)) {
                    foreach ($xml->Deleted as $d) {
                        if (isset($d->Key)) {
                            $deleted[] = array('Key' => (string)$d->Key);
                        }
                    }
                }

                if (isset($xml->Error)) {
                    foreach ($xml->Error as $e) {
                        $errors[] = array(
                            'Key'     => isset($e->Key) ? (string)$e->Key : '',
                            'Code'    => isset($e->Code) ? (string)$e->Code : '',
                            'Message' => isset($e->Message) ? (string)$e->Message : '',
                        );
                    }
                }
            }
        }

        return new WPvivid_S3Lite_Result(array(
            'Deleted'   => $deleted,
            'Errors'    => $errors,
            '@metadata' => array(
                'statusCode' => (is_array($resp) && isset($resp['status']))
                    ? (int)$resp['status']
                    : 200,
            ),
            '_raw' => $resp,
        ));
    }

    public function deleteObject($bucket, $key = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';
            if (isset($args['VersionId'])) $extraHeaders['versionId'] = $args['VersionId'];
        }

        if (isset($this->store) && $bucket !== '' && $key !== '') {
            $this->store->delete($this->store->make_state_key($bucket, $key));
        }

        $resp = $this->client->deleteObject($bucket, $key, $extraHeaders);

        $data = array(
            'Bucket' => $bucket,
            'Key' => $key,
            '@metadata' => array('statusCode' => (is_array($resp) && isset($resp['code'])) ? $resp['code'] : null),
            '_raw' => $resp,
        );
        return new WPvivid_S3Lite_Result($data);
    }

    public function createMultipartUpload($bucket, $key = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';
            $extraHeaders = array();
            if (isset($args['ACL'])) $extraHeaders['x-amz-acl'] = $args['ACL'];
            if (isset($args['ContentType'])) $extraHeaders['Content-Type'] = $args['ContentType'];
        }
        $uploadId = $this->multipart->createMultipartUpload($bucket, $key, $extraHeaders);
        return array('UploadId' => $uploadId);
    }

    public function uploadPart($bucket, $key = null, $uploadId = null, $partNumber = null, $body = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';
            $uploadId = isset($args['UploadId']) ? $args['UploadId'] : '';
            $partNumber = isset($args['PartNumber']) ? intval($args['PartNumber']) : 1;
            $body = isset($args['Body']) ? $args['Body'] : '';
            $extraHeaders = array();
        }

        try {
            $etag = $this->multipart->uploadPart($bucket, $key, $uploadId, (int)$partNumber, $body, $extraHeaders);
            return array('ETag' => $etag);
        }
        catch (Exception $e) {
            return new WP_Error('s3lite_upload_part_failed', $e->getMessage());
        }
    }

    public function completeMultipartUpload($bucket, $key = null, $uploadId = null, $parts = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';
            $uploadId = isset($args['UploadId']) ? $args['UploadId'] : '';
            if (isset($args['MultipartUpload']) && is_array($args['MultipartUpload']) && isset($args['MultipartUpload']['Parts'])) {
                $parts = $args['MultipartUpload']['Parts'];
            } elseif (isset($args['Parts'])) {
                $parts = $args['Parts'];
            } else {
                $parts = array();
            }
            $extraHeaders = array();
        }

        if (is_array($parts)) {
            $is_list = array_keys($parts) === range(0, count($parts) - 1);
            if ($is_list && isset($parts[0]) && is_array($parts[0])) {
                $map = array();
                foreach ($parts as $p) {
                    if (!is_array($p)) continue;
                    $pn = isset($p['PartNumber']) ? (int)$p['PartNumber'] : (isset($p['part_number']) ? (int)$p['part_number'] : 0);
                    $etag = isset($p['ETag']) ? $p['ETag'] : (isset($p['etag']) ? $p['etag'] : null);
                    if ($pn > 0 && $etag !== null) $map[$pn] = $etag;
                }
                $parts = $map;
            }
        }

        $resp = $this->multipart->completeMultipartUpload($bucket, $key, $uploadId, $parts, $extraHeaders);
        if (is_array($resp) && isset($resp['ok']) && !$resp['ok']) {
            $status = isset($resp['status']) ? $resp['status'] : '';
            $body = isset($resp['body']) ? WPvivid_S3Lite_Utils::safe_substr($resp['body'], 2000) : '';
            throw new RuntimeException('CompleteMultipartUpload failed HTTP='.$status.' body='.$body);
        }

        if (isset($this->store) && $bucket !== '' && $key !== '') {
            $this->store->delete($this->store->make_state_key($bucket, $key));
        }

        if (is_array($resp) && isset($resp['ok']) && $resp['ok']) {
            $body = isset($resp['body']) ? (string)$resp['body'] : '';
            $fields = array();

            if ($body !== '') {
                $map = array('Location','Bucket','Key','ETag');
                foreach ($map as $tag) {
                    if (preg_match('#<'.$tag.'>(.*?)</'.$tag.'>#s', $body, $mm)) {
                        $fields[$tag] = html_entity_decode($mm[1], ENT_QUOTES);
                    }
                }
            }

            if (!isset($fields['Bucket'])) $fields['Bucket'] = $bucket;
            if (!isset($fields['Key'])) $fields['Key'] = $key;

            $fields['UploadId'] = $uploadId;
            $fields['_raw'] = $resp;

            return new WPvivid_S3Lite_Result($fields);
        }

        return new WPvivid_S3Lite_Result(array('_raw' => $resp));
    }

    public function abortMultipartUpload($bucket, $key = null, $uploadId = null, $extraHeaders = array())
    {
        if (is_array($bucket) && $key === null) {
            $args = $bucket;
            $bucket = isset($args['Bucket']) ? $args['Bucket'] : '';
            $key = isset($args['Key']) ? $args['Key'] : '';
            $uploadId = isset($args['UploadId']) ? $args['UploadId'] : '';
            $extraHeaders = array();
        }

        $resp = $this->multipart->abortMultipartUpload($bucket, $key, $uploadId, $extraHeaders);

        if (isset($this->store) && $bucket !== '' && $key !== '') {
            $this->store->delete($this->store->make_state_key($bucket, $key));
        }

        return is_array($resp) ? $resp : array('result' => $resp);
    }
}

}
