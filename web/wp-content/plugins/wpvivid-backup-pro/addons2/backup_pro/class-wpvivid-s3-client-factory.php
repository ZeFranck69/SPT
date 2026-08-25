<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

if (!class_exists('WPvivid_Compat_Result'))
{
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100)
    {
        // PHP 8.1+ : Use strict method signatures to eliminate Deprecated warnings
        class WPvivid_Compat_Result implements \ArrayAccess
        {
            private $data;

            public function __construct($data)
            {
                $this->data = is_array($data) ? $data : array();
            }

            public function get($key)
            {
                return isset($this->data[$key]) ? $this->data[$key] : null;
            }

            public function toArray()
            {
                return $this->data;
            }

            public function offsetExists(mixed $offset): bool
            {
                return isset($this->data[$offset]);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return isset($this->data[$offset]) ? $this->data[$offset] : null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                if ($offset === null) {
                    $this->data[] = $value;
                } else {
                    $this->data[$offset] = $value;
                }
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->data[$offset]);
            }
        }

    }
    else
    {
        // Older PHP versions: avoid using mixed types or return type declarations to prevent syntax errors on PHP 7.x and below
        class WPvivid_Compat_Result implements \ArrayAccess
        {
            private $data;

            public function __construct($data)
            {
                $this->data = is_array($data) ? $data : array();
            }

            public function get($key)
            {
                return isset($this->data[$key]) ? $this->data[$key] : null;
            }

            public function toArray()
            {
                return $this->data;
            }

            public function offsetExists($offset)
            {
                return isset($this->data[$offset]);
            }

            public function offsetGet($offset)
            {
                return isset($this->data[$offset]) ? $this->data[$offset] : null;
            }

            public function offsetSet($offset, $value)
            {
                if ($offset === null) {
                    $this->data[] = $value;
                } else {
                    $this->data[$offset] = $value;
                }
            }

            public function offsetUnset($offset)
            {
                unset($this->data[$offset]);
            }
        }
    }
}

class WPvivid_S3_Client_Adapter
{
    private $raw;
    private $type;

    public function __construct($raw, $type)
    {
        $this->raw  = $raw;
        $this->type = $type;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * Result wrapper to provide AWS Result-like access (get()) while still allowing array-style reads.
     * This lets Pro/legacy code keep using $result->get('ETag') etc.
     */
    private function wrapResult($res, $defaultKeyForScalar = null)
    {
        if (is_object($res) && method_exists($res, 'get'))
        {
            return $res;
        }

        if (!is_array($res))
        {
            // scalar -> pack into array
            if ($defaultKeyForScalar)
            {
                $res = array($defaultKeyForScalar => $res);
            }
            else
            {
                $res = array('raw' => $res);
            }
        }

        return new WPvivid_Compat_Result($res);
    }

    public function putObject($args)
    {
        if (is_array($args) && isset($args['SourceFile']) && !isset($args['Body']))
        {
            if (is_object($this->raw) && (is_a($this->raw, 'WPvivid_S3Lite_Compat') || method_exists($this->raw, 'uploadAuto')))
            {
                $sf = $args['SourceFile'];
                if (is_string($sf) && file_exists($sf))
                {
                    $args['Body'] = file_get_contents($sf);
                }
                unset($args['SourceFile']);
            }
        }

        $res = $this->raw->putObject($args);
        if ($res === false || $res === null)
        {
            $failed = defined('WPVIVID_FAILED') ? WPVIVID_FAILED : 'failed';
            return array('result' => $failed, 'error' => 'putObject failed', 'raw' => $res);
        }

        return $this->wrapResult($res);
    }

    public function getObject($args)
    {
        $res = $this->raw->getObject($args);
        return $this->wrapResult($res, 'Body');
    }

    public function headObject($args)
    {
        if (method_exists($this->raw, 'headObject'))
        {
            $res = $this->raw->headObject($args);
            return $this->wrapResult($res);
        }

        $args2 = $args;
        $args2['Range'] = 'bytes=0-0';
        $res = $this->raw->getObject($args2);
        return $this->wrapResult($res);
    }

    public function listObjects($args)
    {
        $res = $this->raw->listObjects($args);
        return $this->wrapResult($res);
    }

    public function listObjectsV2($args)
    {
        if (method_exists($this->raw, 'listObjectsV2'))
        {
            $res = $this->raw->listObjectsV2($args);
        }
        else
        {
            $res = $this->raw->listObjects($args);
        }
        return $this->wrapResult($res);
    }

    public function deleteObject($args)
    {
        return $this->raw->deleteObject($args);
    }

    private function normalizeResult($res)
    {
        if (is_array($res)) return $res;

        if (is_object($res)) {
            if (method_exists($res, 'getAll'))
            {
                $all = $res->getAll();
                if (is_array($all))
                {
                    $all['raw'] = $res;
                    return $all;
                }
            }

            if (method_exists($res, 'toArray'))
            {
                $arr = $res->toArray();
                if (is_array($arr))
                {
                    $arr['raw'] = $res;
                    return $arr;
                }
            }

            if ($res instanceof \ArrayAccess)
            {
                $out = ['raw' => $res];
                foreach (['UploadId','ETag'] as $k)
                {
                    if (isset($res[$k])) $out[$k] = $res[$k];
                }
                if (count($out) > 1) return $out;
            }

            if (method_exists($res, 'get'))
            {
                $out = ['raw' => $res];
                foreach (['UploadId','ETag'] as $k)
                {
                    $v = $res->get($k);
                    if (!empty($v)) $out[$k] = $v;
                }
                if (count($out) > 1) return $out;
            }
        }

        return $res;
    }

    private function pick($normalized, $key)
    {
        if (is_array($normalized) && isset($normalized[$key])) return $normalized[$key];

        if (is_object($normalized) && method_exists($normalized, 'get'))
        {
            return $normalized->get($key);
        }

        return null;
    }

    public function createMultipartUpload($args)
    {
        $res = $this->raw->createMultipartUpload($args);
        $norm = $this->normalizeResult($res);
        $uploadId = $this->pick($norm, 'UploadId');
        if (!empty($uploadId))
        {
            if (!is_array($norm)) $norm = [];
            $norm['UploadId'] = $uploadId;
            return $norm;
        }
        return $norm;
    }

    public function uploadPart($args)
    {
        $res = $this->raw->uploadPart($args);
        $norm = $this->normalizeResult($res);
        $etag = $this->pick($norm, 'ETag');
        if (!empty($etag))
        {
            if (!is_array($norm)) $norm = [];
            $norm['ETag'] = $etag;
            return $norm;
        }
        return $norm;
    }

    public function completeMultipartUpload($args)
    {
        if (isset($args['MultipartUpload']))
        {
            $res = $this->raw->completeMultipartUpload($args);
            return $this->normalizeResult($res);
        }

        if (isset($args['Parts']) && !isset($args['MultipartUpload']))
        {
            if ($this->type === 'aws_legacy')
            {
                $res = $this->raw->completeMultipartUpload($args);
                return $this->normalizeResult($res);
            }
            else
            {
                $parts = $args['Parts'];
                unset($args['Parts']);
                $args['MultipartUpload'] = array('Parts' => $parts);
                $res = $this->raw->completeMultipartUpload($args);
                return $this->normalizeResult($res);
            }
        }

        $res = $this->raw->completeMultipartUpload($args);
        return $this->normalizeResult($res);
    }

    public function downloadAuto($bucket, $key, $local_file, $download_chunk_size, $start_offset, $current_file_name, $current_file_size, $download_info, $callback, $fh)
    {
        if (method_exists($this->raw, 'downloadAuto'))
        {
            return $this->raw->downloadAuto($bucket, $key, $local_file, $download_chunk_size, $start_offset, $current_file_name, $current_file_size, $download_info, $callback, $fh);
        }

        $time_limit = 30;
        $start_time = time();
        $last_time  = time();
        $last_size  = 0;

        while ($start_offset < $current_file_size)
        {
            $last_byte = min($start_offset + $download_chunk_size - 1, $current_file_size - 1);
            $headers['Range'] = "bytes=$start_offset-$last_byte";

            $args=array(
                'Bucket' => $bucket,
                'Key'    => $key,
                'Range'  => $headers['Range']
            );

            $response = $this->raw->getObject($args);
            if (!$response)
                return array('result' => WPVIVID_PRO_FAILED, 'error' => 'download ' . $key. ' failed.');

            fwrite($fh,$response['Body']);

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

            $time_taken = microtime(true) - $start_time;
            if($time_taken >= $time_limit)
            {
                @fclose($fh);
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
            @unlink($local_file);
            return array('result' => 'failed', 'error' => 'Downloading ' . basename($local_file) . ' failed. ' . basename($local_file) . ' might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.');
        }
        else
        {
            rename($local_file, $download_info['root_path'].$download_info['file_name']);

            $result['result']='success';
            $result['finished']=1;
            $result['offset']=$current_file_size;
            return $result;
        }
    }

    public function uploadAuto($bucket, $key, $local_file, $chunk_size, $opts, $callback, $extra)
    {
        if (method_exists($this->raw, 'uploadAuto'))
        {
            return $this->raw->uploadAuto($bucket, $key, $local_file, $chunk_size, $opts, $callback, $extra);
        }

        $result = $this->createMultipartUpload(array(
            'Bucket' => $bucket,
            'Key'    => $key,
        ));

        if (!is_array($result) || !isset($result['UploadId']))
        {
            return array('result' => WPVIVID_FAILED, 'error' => 'Creating upload task failed. Please try again.');
        }

        $uploadId = $result['UploadId'];
        $fh = fopen($local_file, 'rb');
        if (!$fh)
        {
            return array('result' => WPVIVID_FAILED, 'error' => 'Open local file failed: ' . $local_file);
        }

        $parts = array();
        $partNumber = 1;
        $offset = 0;
        $file_size = filesize($local_file);
        $last_time = time();
        $last_size = 0;

        while (!feof($fh)) {
            $data = fread($fh, $chunk_size);
            if ($data === '' || $data === false)
            {
                break;
            }

            $ok = false;
            for ($i = 0; $i < WPVIVID_REMOTE_CONNECT_RETRY_TIMES; $i++)
            {
                $ret = $this->uploadPart(array(
                    'Bucket'     => $bucket,
                    'Key'        => $key,
                    'UploadId'   => $uploadId,
                    'PartNumber' => $partNumber,
                    'Body'       => $data,
                ));

                if (is_array($ret) && isset($ret['ETag']))
                {
                    $parts[] = array('ETag' => $ret['ETag'], 'PartNumber' => $partNumber);
                    $ok = true;
                    break;
                }
            }

            if (!$ok)
            {
                fclose($fh);
                return array('result' => WPVIVID_FAILED, 'error' => 'Multipart upload failed (part ' . $partNumber . ').');
            }

            $partNumber++;
            $offset += $chunk_size;

            if((time() - $last_time) >3)
            {
                if (is_callable($callback))
                {
                    call_user_func_array($callback, array(
                        min($offset, $file_size),
                        basename($local_file),
                        $file_size,
                        $last_time,
                        $last_size
                    ));
                }
                $last_time = time();
                $last_size = $offset;
            }
        }

        fclose($fh);

        $ret = $this->completeMultipartUpload(array(
            'Bucket'   => $bucket,
            'Key'      => $key,
            'UploadId' => $uploadId,
            'Parts'    => $parts,
        ));

        if (!is_array($ret) || (!isset($ret['Location']) && !isset($ret['ETag']) && !isset($ret['Key'])))
        {
            return array('result' => WPVIVID_FAILED, 'error' => 'Merging multipart failed. File name: ' . basename($local_file));
        }

        return array('result' => WPVIVID_SUCCESS);
    }

    public function deleteObjects($bucket, $keys)
    {
        $try1 = array(
            'Bucket' => $bucket,
            'Delete' => array(
                'Objects' => $keys,
                'Quiet'   => true,
            ),
        );

        try
        {
            return $this->raw->deleteObjects($try1);
        }
        catch (Exception $e)
        {
            $try2 = array(
                'Bucket'  => $bucket,
                'Objects' => $keys,
            );
            try
            {
                return $this->raw->deleteObjects($try2);
            }
            catch (Exception $e2)
            {
                $try3 = array(
                    'Bucket' => $bucket,
                    'Delete' => array(
                        'Objects' => $keys,
                    ),
                );
                return $this->raw->deleteObjects($try3);
            }
        }
    }

    public function __call($name, $arguments)
    {
        if (is_object($this->raw) && method_exists($this->raw, $name)) {
            return call_user_func_array(array($this->raw, $name), $arguments);
        }
        return new WP_Error('s3_method_not_supported', 'S3 backend method not supported: '.$name);
    }
}

if (!class_exists('WPvivid_Pro_S3_Client_Factory'))
{
    class WPvivid_Pro_S3_Client_Factory
    {
        /**
         * Create an S3 compatible client instance.
         *
         * For PHP >= 8.1: prefer WPvivid Backup Free's S3Lite implementation (via WPvivid_S3_Client_Adapter).
         * For PHP < 8.1 or when S3Lite is not available: fall back to Pro bundled AWS SDK (WPvividProAws).
         *
         * @param array $args {
         *   @type string $access_key
         *   @type string $secret_key
         *   @type string $region
         *   @type string $endpoint
         *   @type bool   $path_style
         *   @type string $ca_bundle
         *   @type bool   $verify_ssl
         *   @type int    $multipart_threshold
         * }
         * @return object S3 client instance
         */
        public static function create($args)
        {
            $php_id = defined('PHP_VERSION_ID') ? PHP_VERSION_ID : 0;

            if ($php_id >= 80100)
            {
                $client = self::create_s3lite($args);
                if ($client !== false)
                {
                    return $client;
                }
            }
            return self::create_pro_aws($args);
        }

        private static function create_s3lite($args)
        {
            if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
            {
                return false;
            }

            $s3lite_file = WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/s3lite/class-wpvivid-s3lite.php';
            if (file_exists($s3lite_file))
            {
                require_once $s3lite_file;
            }

            if (!class_exists('WPvivid_S3Lite_Compat') || !class_exists('WPvivid_S3_Client_Adapter'))
            {
                return false;
            }

            $endpoint = isset($args['endpoint']) ? trim((string)$args['endpoint']) : '';
            if ($endpoint === '')
            {
                return false;
            }

            if (!preg_match('#^https?://#i', $endpoint))
            {
                // Default to https when scheme is omitted
                $endpoint = 'https://' . $endpoint;
            }
            $endpoint = rtrim($endpoint, '/');

            $signature_version = isset($args['signature_version']) ? strtolower($args['signature_version']) : 'v4';
            if ($signature_version !== 'v2' && $signature_version !== 'v4') $signature_version = 'v4';
            $raw = new WPvivid_S3Lite_Compat(array(
                'access_key' => $args['access_key'],
                'secret_key' => $args['secret_key'],
                'region' => $args['region'],
                'endpoint' => $endpoint,
                'path_style' => !empty($args['path_style']) ? true : false,
                'signature_version' => $signature_version,
                'multipart_threshold' => !empty($args['multipart_threshold']) ? intval($args['multipart_threshold']) : 5 * 1024 * 1024,
                'verify_ssl' => isset($args['verify_ssl']) ? (bool)$args['verify_ssl'] : true,
                'ca_bundle' => isset($args['ca_bundle']) ? $args['ca_bundle'] : '',
            ));

            return new WPvivid_S3_Client_Adapter($raw, 's3lite');
        }

        private static function create_pro_aws($args)
        {
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . '/legacy/vendor/autoload.php';

            $credentials = new WPvividProAws\Credentials\Credentials($args['access_key'], $args['secret_key']);

            $options = array(
                'credentials' => $credentials,
                'version' => 'latest',
                'region' => $args['region'],
                'endpoint' => $args['endpoint'],
                'http' => array(
                    'verify' => isset($args['ca_bundle']) ? $args['ca_bundle'] : ''
                )
            );

            if (!empty($args['path_style']))
            {
                $options['use_path_style_endpoint'] = true;
            }

            return new WPvividProAws\S3\S3Client($options);
        }
    }
}