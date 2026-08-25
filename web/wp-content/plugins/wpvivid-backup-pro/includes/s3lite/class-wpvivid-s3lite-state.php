<?php

if (!class_exists('WPvivid_S3Lite_State_Store')) {

class WPvivid_S3Lite_State_Store
{
    private $prefix = 'wpvivid_s3lite_upload_';

    public function __construct($prefix = null)
    {
        if (!empty($prefix)) $this->prefix = (string)$prefix;
    }

    public function make_state_key($bucket, $object_key)
    {
        $bucket = (string)$bucket;
        $object_key = (string)$object_key;
        return $this->prefix . md5($bucket . '|' . $object_key);
    }

    public function file_signature($file_path)
    {
        $size = @filesize($file_path);
        $mtime = @filemtime($file_path);
        if ($size === false || $mtime === false) return array('size'=>null,'mtime'=>null);
        return array('size'=>(int)$size,'mtime'=>(int)$mtime);
    }

    public function load($state_key)
    {
        $state_key = (string)$state_key;
        if (function_exists('get_option')) {
            $v = get_option($state_key, null);
            if (!is_array($v)) return null;
            return $v;
        }
        $path = $this->file_path($state_key);
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') return null;
        $v = @unserialize($raw);
        return is_array($v) ? $v : null;
    }

    public function save($state_key, $value)
    {
        $state_key = (string)$state_key;
        if (!is_array($value)) return false;

        $value['updated'] = time();

        if (function_exists('update_option')) {
            return update_option($state_key, $value, false);
        }
        $path = $this->file_path($state_key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return @file_put_contents($path, serialize($value)) !== false;
    }

    public function delete($state_key)
    {
        $state_key = (string)$state_key;
        if (function_exists('delete_option')) {
            return delete_option($state_key);
        }
        $path = $this->file_path($state_key);
        if (is_file($path)) @unlink($path);
        return true;
    }

    private function file_path($state_key)
    {
        $base = sys_get_temp_dir();
        $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'wpvivid_s3lite';
        return $dir . DIRECTORY_SEPARATOR . $state_key . '.state';
    }
}

}
