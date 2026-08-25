<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Google_Compat_Ex
{
    public static function load_autoloader()
    {
        if (method_exists('WPvivid_Custom_Interface_addon', 'get_vendor_mode')) {
            $vendor_mode = WPvivid_Custom_Interface_addon::get_vendor_mode();
            if($vendor_mode === 'modern') {
                include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'vendor/autoload.php';
            }
            else{
                include_once WPVIVID_PLUGIN_DIR . '/vendor/autoload.php';
            }
        }
        else {
            include_once WPVIVID_PLUGIN_DIR . '/vendor/autoload.php';
        }
    }

    public static function cls($short)
    {
        $new = 'WPvividGoogle_' . $short;
        if (class_exists($new)) {
            return $new;
        }
        return 'WPvivid_Google_' . $short;
    }

    public static function ensure_refresh_token_prompt($client)
    {
        if (method_exists($client, 'setPrompt')) {
            $client->setPrompt('consent');
        } elseif (method_exists($client, 'setApprovalPrompt')) {
            $client->setApprovalPrompt('force');
        }
    }

    public static function drive_scope_const()
    {
        $candidates = array(
            'WPvividGoogle\\Service\\Drive',
            'WPvividGoogle_Service_Drive',
            'WPvivid_Google_Service_Drive',
        );

        foreach ($candidates as $svc) {
            if (class_exists($svc)) {
                return constant($svc . '::DRIVE_FILE');
            }
        }
    }

    public static function media_apply_resume_state($media, $state)
    {
        if (!is_array($state)) return;

        if (method_exists($media, 'setResumeUri') && !empty($state['resumeUri'])) {
            $media->setResumeUri($state['resumeUri']);
        }
        if (method_exists($media, 'setProgress') && isset($state['progress'])) {
            $media->setProgress((int)$state['progress']);
        }
    }

    public static function media_extract_resume_state($media)
    {
        $out = array();
        if (method_exists($media, 'getProgress')) {
            $out['progress'] = (int)$media->getProgress();
        }
        if (method_exists($media, 'getResumeUri')) {
            $out['resumeUri'] = (string)$media->getResumeUri();
        }
        return $out;
    }

    public static function is_service_exception($e)
    {
        $exClass = self::cls('Service_Exception');
        return ($e instanceof $exClass);
    }
}