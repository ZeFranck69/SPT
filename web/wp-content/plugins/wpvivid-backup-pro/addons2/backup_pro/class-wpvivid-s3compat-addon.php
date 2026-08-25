<?php
/**
 * WPvivid addon: yes
 * Addon Name: wpvivid-backup-pro-all-in-one
 * Description: Pro
 * Version: 2.2.49
 * No_need_load: yes
 * Interface Name: Wpvivid_S3Compat_addon
 */
if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}
if(!defined('WPVIVID_REMOTE_S3COMPAT')){
    define('WPVIVID_REMOTE_S3COMPAT','s3compat');
}
if(!defined('WPVIVID_S3COMPAT_DEFAULT_FOLDER'))
    define('WPVIVID_S3COMPAT_DEFAULT_FOLDER','wpvividbackuppro');
if(!defined('WPVIVID_S3COMPAT_NEED_PHP_VERSION'))
    define('WPVIVID_S3COMPAT_NEED_PHP_VERSION','5.3.9');

use WPvividProAws\S3\S3Client;
use WPvividProAws\S3\Exception\S3Exception;

//require WPVIVID_BACKUP_PRO_PLUGIN_DIR.'vendor/autoload.php';
use WPvividProAws\Exception\AwsException;
use WPvividProAws\S3\MultipartUploader;
use WPvividProAws\Exception\MultipartUploadException;

class Wpvivid_S3Compat_addon extends WPvivid_Remote_addon
{
    private $options;
    private $bucket;
    private $region;

    private $upload_chunk_size = 5242880; // All parts except the last part must be no smaller than 5MB
    private $download_chunk_size = 2097152;

    private $offset = 0;
    private $task_id;

    public function __construct($options = array())
    {
        if(empty($options))
        {
            if(!defined('WPVIVID_INIT_STORAGE_TAB_AMS3C'))
            {
                add_action('wpvivid_add_storage_page',array($this,'wpvivid_add_storage_page_s3compat'), 13);
                add_action('wpvivid_edit_remote_page',array($this,'wpvivid_edit_storage_page_s3compat'), 13);
                add_filter('wpvivid_get_out_of_date_remote',array($this,'wpvivid_get_out_of_date_s3compat'),10,2);
                add_filter('wpvivid_storage_provider_tran',array($this,'wpvivid_storage_provider_s3compat'),9);
                add_filter('wpvivid_pre_add_remote',array($this, 'pre_add_remote'),10,2);
                add_filter('wpvivid_remote_register', array($this, 'init_remotes'),11);
                define('WPVIVID_INIT_STORAGE_TAB_AMS3C',1);
            }

        }else{
            $this -> options = $options;
        }
    }

    public function init_remotes($remote_collection)
    {
        $remote_collection[WPVIVID_REMOTE_S3COMPAT] = 'Wpvivid_S3Compat_addon';
        return $remote_collection;
    }

    public function pre_add_remote($remote,$id)
    {
        if($remote['type']==WPVIVID_REMOTE_S3COMPAT)
        {
            $remote['id']=$id;
        }

        $region = false;
        $region = get_option('wpvivid_add_s3_compat_tmp_region', $region);
        if($region !== false)
        {
            $remote['region']=$region;
        }
        delete_option('wpvivid_add_s3_compat_tmp_region');

        return $remote;
    }

    private function wpvivid_normalize_endpoint($ep)
    {
        $ep = trim((string)$ep);
        if ($ep === '') return '';

        $ep = preg_replace('#^https?://#i', '', $ep);

        $ep = rtrim($ep, '/');

        return strtolower($ep);
    }

    private function wpvivid_find_remote_id_by_options($opts)
    {
        if (!class_exists('WPvivid_Setting'))
        {
            return false;
        }

        $remoteslist=WPvivid_Setting::get_all_remote_options();
        if(isset($remoteslist) && !empty($remoteslist))
        {
            foreach ($remoteslist as $remote_id => $value)
            {
                if($remote_id === 'remote_selected')
                {
                    continue;
                }
                if (!isset($value['type']) || $value['type'] !== WPVIVID_REMOTE_S3COMPAT)
                {
                    continue;
                }

                $keys = array('name', 'access','bucket','path','endpoint','type');
                $ok = true;
                foreach ($keys as $key)
                {
                    $a = isset($opts[$key]) ? (string)$opts[$key] : '';
                    $b = isset($value[$key]) ? (string)$value[$key] : '';
                    if ($key === 'endpoint')
                    {
                        $a = $this->wpvivid_normalize_endpoint($a);
                        $b = $this->wpvivid_normalize_endpoint($b);
                    }
                    if ($a !== $b)
                    {
                        $ok = false;
                        break;
                    }
                }
                if ($ok)
                {
                    return $remote_id;
                }
            }
        }

        return false;
    }

    public function wpvivid_probe_and_persist_legacy_flags($secret, $path_style)
    {
        $signature_versions = array('v4', 'v2');

        $temp_file = 'wpvivid_probe_' . md5(wp_rand()) . '_' . time();
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        foreach ($signature_versions as $signature_version)
        {
            $client_args = array(
                'access_key' => $this->options['access'],
                'secret_key' => $secret,
                'region' => $this->region,
                'endpoint' => $this->options['endpoint'],
                'path_style' => $path_style,
                'signature_version' => $signature_version,
                'verify_ssl' => true,
                'multipart_threshold' => 5 * 1024 * 1024,
                'ca_bundle' => WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/resources/cacert.pem',
            );

            $s3compat = WPvivid_Pro_S3_Client_Factory::create($client_args);

            try
            {
                $result = $s3compat->putObject(
                    array(
                        'Bucket'=>$this->bucket,
                        'Key' =>  $root_path.'/'.$this->options['path'].'/'.$temp_file,
                        'Body' => $temp_file,
                    )
                );
                $etag = $result->get('ETag');
                if(!isset($etag))
                {
                    continue;
                }
                $result = $s3compat->deleteObject(array(
                    'Bucket' => $this -> bucket,
                    'Key'    => $root_path.'/'.$this -> options['path'].'/'.$temp_file,
                ));
                $this->options['signature_version'] = $signature_version;

                $remote_id = false;
                if (isset($this->options['remote_id'])) $remote_id = $this->options['remote_id'];
                if (!$remote_id) $remote_id = $this->wpvivid_find_remote_id_by_options($this->options);
                if ($remote_id)
                {
                    $saved = WPvivid_Setting::get_remote_option($remote_id);
                    if (is_array($saved))
                    {
                        $saved['signature_version'] = $signature_version;
                        WPvivid_Setting::update_remote_option($remote_id, $saved);
                    }
                }

                return;
            }
            catch(Exception $e)
            {
                continue;
            }
        }
    }

    public function getClient()
    {
        $res = $this -> compare_php_version();
        if($res['result'] == WPVIVID_PRO_FAILED)
            return $res;

        if(isset($this->options['s3directory']))
        {
            $path_temp = str_replace('s3generic://','',$this->options['s3directory'].$this -> options['path']);
            if (preg_match("#^/*([^/]+)/(.*)$#", $path_temp, $bmatches))
            {
                $this->bucket = $bmatches[1];
            } else {
                $this->bucket = $path_temp;
            }
            $this->options['path']=ltrim($this -> options['path'],'/');
            $endpoint_temp = str_replace('https://','',$this->options['endpoint']);
            $explodes = explode('.',$endpoint_temp);
            if(isset($this->options['region']))
            {
                $this -> region = $this->options['region'];
            }
            else
            {
                $this -> region = $explodes[0];
            }
            $this -> options['endpoint'] = 'https://'.trailingslashit($endpoint_temp);
        }
        else
        {
            $endpoint_temp = str_replace('https://','',$this->options['endpoint']);
            $explodes = explode('.',$endpoint_temp);
            if(isset($this->options['region']))
            {
                $this -> region = $this->options['region'];
            }
            else
            {
                $this -> region = $explodes[0];
            }
            $this -> options['endpoint'] = 'https://'.trailingslashit($endpoint_temp);
            $this -> bucket=$this->options['bucket'];
        }

        if(isset($this->options['is_encrypt']) && $this->options['is_encrypt'] == 1){
            $secret = base64_decode($this->options['secret']);
        }
        else {
            $secret = $this->options['secret'];
        }

        // Use factory to create client (PHP >= 8.1 uses Free S3Lite, otherwise fall back to Pro AWS SDK).
        if (!class_exists('WPvivid_Pro_S3_Client_Factory'))
        {
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'addons2/backup_pro/class-wpvivid-s3-client-factory.php';
        }

        $path_style = (isset($this->options['use_path_style_endpoint']) && $this->options['use_path_style_endpoint']) ? true : false;

        if (version_compare(PHP_VERSION, '8.1', '>=') )
        {
            if(!isset($this->options['signature_version']) || $this->options['signature_version'] === '')
            {
                $this->wpvivid_probe_and_persist_legacy_flags($secret, $path_style);
            }
        }

        $client_args = array(
            'access_key' => $this->options['access'],
            'secret_key' => $secret,
            'region' => $this->region,
            'endpoint' => $this->options['endpoint'],
            'path_style' => $path_style,
            'signature_version' => $this->options['signature_version'],
            'verify_ssl' => true,
            'multipart_threshold' => 5 * 1024 * 1024,
            'ca_bundle' => WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/resources/cacert.pem',
        );

        return WPvivid_Pro_S3_Client_Factory::create($client_args);
    }

    public function get_test_connect_client($region)
    {
        $res = $this -> compare_php_version();
        if($res['result'] == WPVIVID_PRO_FAILED)
            return $res;

        if(isset($this->options['s3directory']))
        {
            $path_temp = str_replace('s3generic://','',$this->options['s3directory'].$this -> options['path']);
            if (preg_match("#^/*([^/]+)/(.*)$#", $path_temp, $bmatches))
            {
                $this->bucket = $bmatches[1];
            } else {
                $this->bucket = $path_temp;
            }
            $this->options['path']=ltrim($this -> options['path'],'/');
            $endpoint_temp = str_replace('https://','',$this->options['endpoint']);
            //$explodes = explode('.',$endpoint_temp);
            //$this -> region = $explodes[0];
            $this -> options['endpoint'] = 'https://'.trailingslashit($endpoint_temp);
        }
        else
        {
            $endpoint_temp = str_replace('https://','',$this->options['endpoint']);
            //$explodes = explode('.',$endpoint_temp);
            //$this -> region = $explodes[0];

            $this -> options['endpoint'] = 'https://'.trailingslashit($endpoint_temp);
            $this -> bucket=$this->options['bucket'];
        }

        if(isset($this->options['is_encrypt']) && $this->options['is_encrypt'] == 1){
            $secret = base64_decode($this->options['secret']);
        }
        else {
            $secret = $this->options['secret'];
        }

        // Use factory to create client (PHP >= 8.1 uses Free S3Lite, otherwise fall back to Pro AWS SDK).
        if (!class_exists('WPvivid_Pro_S3_Client_Factory'))
        {
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'addons2/backup_pro/class-wpvivid-s3-client-factory.php';
        }

        $path_style = (isset($this->options['use_path_style_endpoint']) && $this->options['use_path_style_endpoint']) ? true : false;

        $client_args = array(
            'access_key' => $this->options['access'],
            'secret_key' => $secret,
            'region' => $region,
            'endpoint' => $this->options['endpoint'],
            'path_style' => $path_style,
            'signature_version' => $this->options['signature_version'],
            'verify_ssl' => true,
            'multipart_threshold' => 5 * 1024 * 1024,
            'ca_bundle' => WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/resources/cacert.pem',
        );

        return WPvivid_Pro_S3_Client_Factory::create($client_args);
    }

    public function test_connect_ex($region)
    {
        $s3compat = $this -> get_test_connect_client($region);
        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $temp_file = md5(rand());

        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        try
        {
            $result = $s3compat->putObject(
                array(
                    'Bucket'=>$this->bucket,
                    'Key' =>  $root_path.'/'.$this->options['path'].'/'.$temp_file,
                    'Body' => $temp_file,
                )
            );
            $etag = $result->get('ETag');
            if(!isset($etag))
            {
                return array('result'=>WPVIVID_PRO_FAILED,'error'=>__('We successfully accessed the bucket, but create test file failed.','wpvivid-backup-pro'));
            }
            $result = $s3compat->deleteObject(array(
                'Bucket' => $this -> bucket,
                'Key'    => $root_path.'/'.$this -> options['path'].'/'.$temp_file,
            ));
            if(empty($result))
            {
                return array('result'=>WPVIVID_PRO_FAILED,'error'=>__('We successfully accessed the bucket, and create test file succeed, but delete test file failed.','wpvivid-backup-pro'));
            }
        }
        catch(Exception $e)
        {
            if(preg_match('/cURL error 6: Could not resolve host.*$/', $e -> getMessage()))
            {
                return array('result' => WPVIVID_PRO_FAILED,'error'=>__('Could not resolve host, please check the path-style option and try it again.','wpvivid-backup-pro'));
            }
            else
            {
                $aws_code = '';
                if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                    $aws_code = $e->getAwsErrorCode();
                }
                return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
            }
        }
        return array('result' => WPVIVID_PRO_SUCCESS);
    }

    public function test_connect()
    {
        $first_test = true;
        $need_retry = false;
        $res = array();
        do {
            $endpoint_temp = str_replace('https://','',$this->options['endpoint']);

            if(isset($this->options['use_region']) && $this->options['use_region'] == '1')
            {
                $region = $this->options['region'];
            }
            else
            {
                $explodes = explode('.',$endpoint_temp);
                if($first_test)
                {
                    $region = $explodes[0];
                }
                else
                {
                    $region = $explodes[1];
                }
            }

            $res = $this->test_connect_ex($region);
            if($res['result'] === WPVIVID_PRO_SUCCESS)
            {
                $need_retry = false;
                update_option('wpvivid_add_s3_compat_tmp_region', $region, 'no');
            }
            else if($res['result'] === WPVIVID_PRO_FAILED && !preg_match('/Could not resolve host, please check the path-style option and try it again.*$/', $res['error']))
            {
                if($first_test)
                {
                    $need_retry = true;
                }
                else
                {
                    $need_retry = false;
                }
            }
            $first_test = false;
        } while ($need_retry);

        return $res;
    }

    public function upload($task_id, $files, $callback = '')
    {
        global $wpvivid_plugin;
        global $wpvivid_backup_pro;
        $s3compat = $this -> getClient();
        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $upload_job=WPvivid_taskmanager::get_backup_sub_task_progress($task_id,'upload',$this->options['id']);
        if(empty($upload_job))
        {
            $job_data=array();
            foreach ($files as $file)
            {
                $file_data['size']=filesize($file);
                $file_data['uploaded']=0;
                $job_data[basename($file)]=$file_data;
            }
            WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',$this->options['id'],WPVIVID_UPLOAD_UNDO,'Start uploading',$job_data);
            $upload_job=WPvivid_taskmanager::get_backup_sub_task_progress($task_id,'upload',$this->options['id']);
        }

        foreach ($files as $file)
        {
            if(is_array($upload_job['job_data'])&&array_key_exists(basename($file),$upload_job['job_data']))
            {
                if($upload_job['job_data'][basename($file)]['uploaded']==1)
                    continue;
            }
            $this->last_time = time();
            $this->last_size = 0;
            $wpvivid_backup_pro->wpvivid_pro_log->WriteLog('Start uploading '.basename($file),'notice');
            $wpvivid_plugin->set_time_limit($task_id);
            if(!file_exists($file)){
                $wpvivid_backup_pro->wpvivid_pro_log->WriteLog('Uploading '.basename($file).' failed.','notice');
                return array('result' =>WPVIVID_PRO_FAILED,'error' =>$file.' not found. The file might has been moved, renamed or deleted. Please reload the list and verify the file exists.');
            }
            $result = $this->_put($task_id,$s3compat,$file,$callback);
            if($result['result'] !==WPVIVID_PRO_SUCCESS)
            {
                $wpvivid_backup_pro->wpvivid_pro_log->WriteLog('Uploading '.basename($file).' failed.','notice');
                return $result;
            }
            else
            {
                WPvivid_Custom_Interface_addon::wpvivid_reset_backup_retry_times($task_id);
            }
            $upload_job['job_data'][basename($file)]['uploaded']=1;
            $wpvivid_backup_pro->wpvivid_pro_log->WriteLog('Finished uploading '.basename($file),'notice');
            WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',$this->options['id'],WPVIVID_UPLOAD_UNDO,'Uploading '.basename($file).' completed.',$upload_job['job_data']);
        }
        WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',$this->options['id'],WPVIVID_UPLOAD_SUCCESS,'Uploading completed.',$upload_job['job_data']);
        return array('result' => WPVIVID_PRO_SUCCESS);
    }

    public function wpvivid_before_initiate()
    {
    }

    public function wpvivid_before_complete()
    {
    }

    public function _put($task_id,$s3compat,$file,$callback)
    {
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        $path = $root_path.'/'.$this->options['path'].'/'.basename($file);
        $this->current_file_size = filesize($file);
        $this->current_file_name = basename($file);

        try
        {
            if($this->current_file_size > $this->upload_chunk_size)
            {
                if (is_object($s3compat) && method_exists($s3compat, 'uploadAuto'))
                {
                    $ret = $s3compat->uploadAuto($this->bucket, $path, $file, $this->upload_chunk_size, array(), $callback, array());
                    if (is_array($ret) && isset($ret['result']) && $ret['result'] === WPVIVID_PRO_FAILED)
                    {
                        return $ret;
                    }

                    if(is_callable($callback))
                    {
                        call_user_func_array($callback, array(
                            $this->current_file_size,
                            $this->current_file_name,
                            $this->current_file_size,
                            $this->last_time,
                            $this->last_size
                        ));
                    }
                    return array('result' => WPVIVID_PRO_SUCCESS);
                }
                else
                {
                    $source = $file;
                    $this->offset = 0;
                    $this->task_id = $task_id;
                    $uploader = new MultipartUploader($s3compat, $source, [
                        'bucket' => $this -> bucket,
                        'key' => $path,
                        'before_upload' => function () {
                            $this->offset += $this -> upload_chunk_size;
                            $job_data=array();
                            $upload_data=array();
                            $upload_data['offset']=min($this->offset,$this -> current_file_size);
                            $upload_data['current_name']=$this -> current_file_name;
                            $upload_data['current_size']=$this->current_file_size;
                            $upload_data['last_time']=$this -> last_time;
                            $upload_data['last_size']=$this -> last_size;
                            $upload_data['descript']='Uploading '.$this -> current_file_name;

                            if((time() - $this -> last_time) >3)
                            {
                                $v =( $upload_data['offset'] - $this -> last_size ) / (time() - $this -> last_time);
                                $v /= 1000;
                                $v=round($v,2);

                                global $wpvivid_plugin;
                                global $wpvivid_backup_pro;
                                $backup_task=new WPvivid_New_Backup_Task($this->task_id);
                                $backup_task->check_cancel_backup();

                                $message='Uploading '.$this -> current_file_name.' Total size: '.size_format($this->current_file_size,2).' Uploaded: '.size_format($upload_data['offset'],2).' speed:'.$v.'kB/s';
                                $wpvivid_backup_pro->wpvivid_pro_log->WriteLog($message,'notice');
                                $progress=intval(($upload_data['offset']/$this->current_file_size)*100);
                                WPvivid_taskmanager::update_backup_main_task_progress($this->task_id,'upload',$progress,0);
                                WPvivid_taskmanager::update_backup_sub_task_progress($this->task_id,'upload','',WPVIVID_UPLOAD_UNDO,$message, $job_data, $upload_data);

                                $this -> last_size = $this->offset;
                                $this -> last_time = time();
                            }
                        },
                        'before_initiate' => array($this, 'wpvivid_before_initiate'),
                        'before_complete' => array($this, 'wpvivid_before_complete'),
                    ]);
                    try {
                        $result = $uploader->upload();
                        $result = array('result' => WPVIVID_PRO_SUCCESS);
                    } catch (MultipartUploadException $e) {
                        return array('result' => WPVIVID_PRO_FAILED,'error' => $e -> getMessage());
                    }
                }
            }
            else {
                $res = $s3compat ->putObject(
                    array(
                        'Bucket'=>$this -> bucket,
                        'Key' =>  $path,
                        'SourceFile' => $file,
                    )
                );
                $etag = $res -> get('ETag');
                if(isset($etag))
                {
                    $result = array('result' => WPVIVID_PRO_SUCCESS);
                }else {
                    $result = array('result' => WPVIVID_PRO_FAILED , 'error' => 'upload '.$this -> current_file_name.' failed.');
                }
            }
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
        return $result;
    }

    public function _upload_loop($s3compat,$uploadId,$path,$data,$partNumber,&$parts)
    {
        $last_e=false;
        for($i =0;$i <WPVIVID_PRO_REMOTE_CONNECT_RETRY_TIMES;$i ++)
        {
            try
            {
                $ret = $s3compat ->uploadPart(array(
                    'Bucket'     => $this ->bucket,
                    'Key'        => $path,
                    'UploadId'   => $uploadId,
                    'PartNumber' => $partNumber,
                    'Body'       => $data,
                ));

                if (is_object($ret) && method_exists($ret, 'get') && '' != $ret->get('ETag'))
                {
                    $parts[] = array(
                        'ETag' => $ret->get('ETag'),
                        'PartNumber' => $partNumber,
                    );
                    return array('result' => WPVIVID_PRO_SUCCESS);
                }
            }
            catch(S3Exception $e)
            {
                $last_e=$e;
            }
            catch(Exception $e)
            {
                $last_e=$e;
            }

        }
        if($last_e!==false)
        {
            if(is_a($last_e,'S3Exception'))
            {
                return array('result' => WPVIVID_PRO_FAILED,'error' => $last_e -> getAwsErrorCode().$last_e -> getMessage());
            }
            else
            {
                return array('result' => WPVIVID_PRO_FAILED,'error' => $last_e -> getMessage());
            }
        }
        else
        {
            return array('result' => WPVIVID_PRO_FAILED,'error' =>'Multipart upload failed. File name: '.$this -> current_file_name);
        }
    }

    public function chunk_download($download_info,$callback)
    {
        try
        {
            $this -> current_file_name = $download_info['file_name'];
            $this -> current_file_size = $download_info['size'];
            $local_path = $download_info['local_path'];
            $fh = fopen($local_path, 'a');

            if(filesize($local_path) ==  $this -> current_file_size)
            {
                @fclose($fh);
                rename($local_path, $download_info['root_path'].$download_info['file_name']);

                $result['result']='success';
                $result['finished']=1;
                $result['offset']=$this -> current_file_size;
                return $result;
            }

            if(isset($this->options['chunk_size']))
                $this->download_chunk_size = $this->options['chunk_size'];

            $s3compat = $this->getClient();
            if (is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
            {
                return $s3compat;
            }


            $root_path='wpvividbackuppro';
            if(isset($this->options['root_path']))
            {
                $root_path=$this->options['root_path'];
            }

            $url=$root_path.'/'.$this->options['path'].'/'. $this -> current_file_name;

            $start_offset = file_exists($local_path) ? filesize($local_path) : 0;

            if (is_object($s3compat) && method_exists($s3compat, 'downloadAuto'))
            {
                $ret = $s3compat->downloadAuto($this->bucket, $url, $local_path, $this->download_chunk_size, $start_offset, $this->current_file_name, $this->current_file_size, $download_info, $callback, $fh);
                return $ret;
            }
            else
            {
                $time_limit = 30;
                $start_time = time();

                while ($start_offset < $this->current_file_size)
                {
                    $last_byte = min($start_offset + $this->download_chunk_size - 1, $this->current_file_size - 1);
                    $headers['Range'] = "bytes=$start_offset-$last_byte";

                    $args=array(
                        'Bucket' => $this -> bucket,
                        'Key'    => $url,
                        'Range'  => $headers['Range']
                    );

                    $response = $s3compat->getObject($args);
                    if (!$response)
                        return array('result' => WPVIVID_PRO_FAILED, 'error' => 'download ' . $url. ' failed.');

                    $bodyStream = $response->get('Body');
                    if (is_resource($bodyStream)) {
                        $bytes_written = stream_copy_to_stream($bodyStream, $fh);
                    } elseif (method_exists($bodyStream, 'detach')) {
                        $raw = $bodyStream->detach();
                        $bytes_written = stream_copy_to_stream($raw, $fh);
                    } else {
                        $bytes_written = fwrite($fh, (string)$bodyStream);
                    }
                    if ($bytes_written === false || $bytes_written === 0) {
                        return array('result' => WPVIVID_PRO_FAILED, 'error' => 'Write failed at offset ' . $start_offset);
                    }

                    clearstatcache();

                    $state = stat($local_path);
                    $start_offset = $state['size'];

                    if ((time() - $this->last_time) > 3)
                    {
                        if (is_callable($callback)) {
                            call_user_func_array($callback, array($start_offset, $this->current_file_name,
                                $this->current_file_size, $this->last_time, $this->last_size));
                        }
                        $this->last_size = $start_offset;
                        $this->last_time = time();
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
            }

            @fclose($fh);
            clearstatcache();

            if(filesize($local_path) != $this -> current_file_size)
            {
                @unlink($local_path);
                return array('result' => 'failed', 'error' => 'Downloading ' . basename($local_path) . ' failed. ' . basename($local_path) . ' might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.');
            }
            else
            {
                rename($local_path, $download_info['root_path'].$download_info['file_name']);

                $result['result']='success';
                $result['finished']=1;
                $result['offset']=$this -> current_file_size;
                return $result;
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array('result'=>WPVIVID_PRO_FAILED, 'error'=>$message);
        }
    }

    public function download($file, $local_path, $callback = '')
    {
        try {
            global $wpvivid_plugin;
            $this->current_file_name = $file['file_name'];
            $this->current_file_size = $file['size'];
            $file_path = trailingslashit($local_path) . $this->current_file_name;
            $wpvivid_plugin->wpvivid_download_log->WriteLog('Get s3compat client.','notice');
            $s3compat = $this->getClient();
            if (is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED) {
                return $s3compat;
            }

            $start_offset = file_exists($file_path) ? filesize($file_path) : 0;
            $wpvivid_plugin->wpvivid_download_log->WriteLog('Create local file.','notice');
            $fh = fopen($file_path, 'a');
            $wpvivid_plugin->wpvivid_download_log->WriteLog('Downloading file ' . $file['file_name'] . ', Size: ' . $file['size'] ,'notice');
            while ($start_offset < $this->current_file_size)
            {
                $last_byte = min($start_offset + $this->download_chunk_size - 1, $this->current_file_size - 1);
                $range = "bytes=$start_offset-$last_byte";
                $response = $this->_download_loop($file,$s3compat, $range, $fh);
                if ($response['result'] === WPVIVID_PRO_FAILED)
                {
                    return $response;
                }

                clearstatcache();
                $state = stat($file_path);
                $start_offset = $state['size'];
                if ((time() - $this->last_time) > 3)
                {
                    if (is_callable($callback)) {
                        call_user_func_array($callback, array($start_offset, $this->current_file_name,
                            $this->current_file_size, $this->last_time, $this->last_size));
                    }
                    $this->last_size = $start_offset;
                    $this->last_time = time();
                }
            }
            @fclose($fh);

            if(filesize($file_path) == $file['size'])
            {
                if($wpvivid_plugin->wpvivid_check_zip_valid())
                {
                    $res = TRUE;
                }
                else{
                    $res = FALSE;
                }
            }
            else{
                $res = FALSE;
            }

            if ($res !== TRUE) {
                @unlink($file_path);
                return array('result' => WPVIVID_PRO_FAILED, 'error' => 'Downloading ' . $file['file_name'] . ' failed. ' . $file['file_name'] . ' might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.');
            }

            return array('result' => WPVIVID_PRO_SUCCESS);
        }
        catch (Exception $e){
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function _download_loop($file,$s3compat,$range,$fh)
    {
        try
        {
            $root_path='wpvividbackuppro';
            if(isset($this->options['root_path']))
            {
                $root_path=$this->options['root_path'];
            }
            if(isset($file['remote_path']))
            {
                $path=$root_path.'/'.$this->options['path'].'/'. $file['remote_path'].'/'.$this -> current_file_name;
            }
            else
            {
                $path=$root_path.'/'.$this->options['path'].'/'. $this -> current_file_name;
            }

            for($i =0;$i <WPVIVID_PRO_REMOTE_CONNECT_RETRY_TIMES;$i ++)
            {
                $response = $s3compat -> getObject(array(
                    'Bucket' => $this -> bucket,
                    'Key'    => $path,
                    'Range'  => $range
                ));


                if(isset($response['Body']) && fwrite($fh,$response['Body'])) {
                    return array('result' => WPVIVID_PRO_SUCCESS);
                }
            }
            return array('result'=>WPVIVID_PRO_FAILED, 'error' => 'download '.$this -> current_file_name.' failed.');
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function upload_rollback($file,$folder,$slug,$version)
    {
        $s3compat = $this -> getClient();
        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        if(isset($this->options['chunk_size']))
            $this->upload_chunk_size = $this->options['chunk_size'];

        $this -> current_file_size = filesize($file);
        $this -> current_file_name = basename($file);

        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $path=$root_path.'/'.$this->options['path'].'/rollback_ex/'.$folder.'/'.$slug.'/'.$version.'/'.basename($file);

        $res = $s3compat ->putObject(
            array(
                'Bucket'=>$this -> bucket,
                'Key' =>  $path,
                'SourceFile' => $file,
            )
        );
        $etag = $res -> get('ETag');
        if(isset($etag))
        {
            $result = array('result' => WPVIVID_PRO_SUCCESS);
        }
        else
        {
            $result = array('result' => WPVIVID_PRO_FAILED , 'error' => 'upload '.$this -> current_file_name.' failed.');
        }

        return $result;
    }

    public function download_rollback($download_info)
    {
        $s3compat = $this->getClient();
        if (is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $this -> current_file_name = $download_info['file_name'];
        $this -> current_file_size = $download_info['size'];

        $type=$download_info['type'];
        $slug=$download_info['slug'];
        $version=$download_info['version'];

        $local_path = $download_info['local_path'];

        $start_offset = file_exists($local_path) ? filesize($local_path) : 0;
        $fh = fopen($local_path, 'a');

        if(filesize($local_path) ==  $this -> current_file_size)
        {
            @fclose($fh);
            rename($local_path, $download_info['root_path'].$download_info['file_name']);

            $result['result']='success';
            $result['finished']=1;
            $result['offset']=$this -> current_file_size;
            return $result;
        }

        if(isset($this->options['chunk_size']))
            $this->download_chunk_size = $this->options['chunk_size'];

        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        $url=$root_path.'/'.$this->options['path'].'/rollback_ex/'.$type.'/'.$slug.'/'.$version.'/'.$this -> current_file_name;

        $time_limit = 30;
        $start_time = time();

        while ($start_offset < $this->current_file_size)
        {
            $last_byte = min($start_offset + $this->download_chunk_size - 1, $this->current_file_size - 1);
            $headers['Range'] = "bytes=$start_offset-$last_byte";

            $args=array(
                'Bucket' => $this -> bucket,
                'Key'    => $url,
                'Range'  => $headers['Range']
            );

            $response = $s3compat->getObject($args);
            if (!$response)
                return array('result' => WPVIVID_PRO_FAILED, 'error' => 'download ' . $url. ' failed.');

            fwrite($fh,$response['Body']);

            clearstatcache();
            $state = stat($local_path);
            $start_offset = $state['size'];

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

        if(filesize($local_path) != $this -> current_file_size)
        {
            @unlink($local_path);
            return array('result' => 'failed', 'error' => 'Downloading ' . basename($local_path) . ' failed. ' . basename($local_path) . ' might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.');
        }
        else
        {
            rename($local_path, $download_info['root_path'].$download_info['file_name']);

            $result['result']='success';
            $result['finished']=1;
            $result['offset']=$this -> current_file_size;
            return $result;
        }
    }

    public function cleanup($files)
    {
        $s3compat = $this -> getClient();
        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED){
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        foreach ($files as $file)
        {
            if(is_array($file))
            {
                if(isset($file['remote_path']))
                {
                    $key= $root_path.'/'.$this -> options['path'].'/'. $file['remote_path'].'/'.basename($file['file_name']);
                }
                else
                {
                    $key= $root_path.'/'.$this -> options['path'].'/'.basename($file['file_name']);
                }
            }
            else
            {
                $key= $root_path.'/'.$this -> options['path'].'/'.basename($file);
            }
            try{
                $result = $s3compat -> deleteObject(array(
                    'Bucket' => $this -> bucket,
                    'Key'    => $key
                ));
                //$s3compat->deleteMatchingObjects($this -> bucket, basename($file['file_name']));

            }catch (Exception $e){}
        }

        return array('result'=>WPVIVID_PRO_SUCCESS);
    }

    public function cleanup_rollback($type,$slug,$version)
    {
        $s3compat = $this -> getClient();
        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED){
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $key=$root_path.'/'.$this->options['path'].'/rollback_ex/'.$type.'/'.$slug.'/'.$version.'/'.$slug.'.zip';
        $result = $s3compat -> deleteObject(array(
            'Bucket' => $this -> bucket,
            'Key'    => $key
        ));

        return array('result'=>WPVIVID_PRO_SUCCESS);
    }

    public function wpvivid_add_storage_page_s3compat(){
        global $wpvivid_backup_pro;
        ?>
        <div id="storage_account_s3compat" class="storage-account-page" xmlns="http://www.w3.org/1999/html">
            <div style="padding: 0 10px 10px 0;">
                <strong><?php esc_html_e('Enter Your S3 Compatible Storage Account', 'wpvivid-backup-pro'); ?></strong>
            </div>
            <div style="padding: 0 10px 10px 0;">
                <span>
                    <?php esc_html_e('Note: WPvivid uses the WPvivid S3Lite library on PHP 8.1 and later, and the AWS SDK for PHP on older versions.', 'wpvivid-backup-pro'); ?>
                </span>
            </div>
            <table class="wp-list-table widefat plugins" style="width:100%;">
                <tbody>
                <form>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="name" placeholder="Enter a unique alias: e.g. DOS-001" onkeyup="value=value.replace(/[^a-zA-Z0-9\-_]/g,'')" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('A name to help you identify the storage if you have multiple remote storage connected.', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="password" class="regular-text" autocomplete="new-password" option="s3compat" name="access" placeholder="S3 access key" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter your S3 access key', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="password" class="regular-text" autocomplete="new-password" option="s3compat" name="secret" placeholder="S3 secret key" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter your S3 secret key', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="bucket" placeholder="Bucket Name(e.g. test)" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Enter an existing Bucket in which you want to create a parent folder for holding %s folders.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid backup')); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="root_path" value="<?php echo esc_attr(apply_filters('wpvivid_white_label_remote_root_path', 'wpvividbackuppro')); ?>" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Customize a parent folder in the Bucket for holding %s folders.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid backup')); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="path" placeholder="Custom Path" value="<?php echo esc_attr($wpvivid_backup_pro->func->swtich_domain_to_folder_name(home_url())); ?>" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Customize the name of folder under the parent folder where you want to store %s backups.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', WPVIVID_PRO_PLUGIN_SLUG)); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="endpoint" placeholder="region.digitaloceanspaces.com" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter the service Endpoint for the storage', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td colspan=2>
                            <label><input class="s3compat" type="checkbox" option="s3compat" name="use_region" onclick="wpvivid_check_special_region(this);"><?php esc_html_e('Enter the bucket region(if any)', 'wpvivid-backup-pro'); ?>
                        </td>
                    </tr>

                    <tr class="wpvivid-region-tr-s3compat" style="display: none;">
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="s3compat" name="region" placeholder="region, e,g., ru-1" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter the region of the s3 bucket.', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>

                    <?php do_action('wpvivid_remote_storage_backup_retention', 's3compat', 'add'); ?>

                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-select">
                                <label>
                                    <input type="checkbox" option="s3compat" name="use_path_style_endpoint" /><?php esc_html_e('Use path-style access.', 'wpvivid-backup-pro'); ?>
                                </label>
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Use path-style to indicate to an S3-compatible storage.', 'wpvivid-backup-pro'); ?> <a href="https://docs.wpvivid.com/path-style-access-to-s3-compatible-storage.html" target='_blank'><?php esc_html_e('learn more...', 'wpvivid-backup-pro'); ?></a></i>
                            </div>
                        </td>
                    </tr>

                    <?php
                    if (version_compare(PHP_VERSION, '8.1', '>=')) {
                        ?>
                        <tr>
                            <td class="plugin-title column-primary">
                                <div class="wpvivid-storage-select">
                                    <fieldset>
                                        <select option="s3compat" name="signature_version">
                                            <option value="v4" selected="selected">v4 (AWS4-HMAC-SHA256)</option>
                                            <option value="v2">v2 (HMAC-SHA1)</option>
                                        </select>
                                    </fieldset>
                                </div>
                            </td>
                            <td class="column-description desc">
                                <div class="wpvivid-storage-form-desc">
                                    <i><?php esc_html_e('Signature version.', 'wpvivid-backup-pro'); ?></i>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>

                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-select">
                                <label>
                                    <input type="checkbox" option="s3compat" name="default" checked /> <?php esc_html_e('Set as the default remote storage.', 'wpvivid-backup-pro'); ?>
                                </label>
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Once checked, backups sent to a remote storage destination will be uploaded to this storage by default.', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                </form>
                <tr>
                    <td class="plugin-title column-primary">
                        <div class="wpvivid-storage-form">
                            <input class="button-primary" option="add-remote" type="submit" value="<?php esc_attr_e('Test and Add', 'wpvivid-backup-pro'); ?>" />
                        </div>
                    </td>
                    <td class="column-description desc">
                        <div class="wpvivid-storage-form-desc">
                            <i><?php esc_html_e('Click the button to connect to the storage and add it to the storage list below.', 'wpvivid-backup-pro'); ?></i>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <script>
            function wpvivid_check_special_region(obj)
            {
                var class_name = jQuery(obj).attr('class');
                if(jQuery(obj).prop('checked'))
                {
                    jQuery('.wpvivid-region-tr-'+class_name).show();
                }
                else
                {
                    jQuery('.wpvivid-region-tr-'+class_name).hide();
                }
            }
        </script>
        <?php
    }

    public function wpvivid_edit_storage_page_s3compat()
    {
        ?>
        <div id="remote_storage_edit_s3compat">
            <div style="padding: 0 10px 10px 0;">
                <strong><?php esc_html_e('Enter Your S3 Compatible Storage Account', 'wpvivid-backup-pro'); ?></strong>
            </div>
            <div style="padding: 0 10px 10px 0;">
                <span>
                    <?php esc_html_e('Note: WPvivid uses the WPvivid S3Lite library on PHP 8.1 and later, and the AWS SDK for PHP on older versions.', 'wpvivid-backup-pro'); ?>
                </span>
            </div>
            <table class="wp-list-table widefat plugins" style="width:100%;">
                <tbody>
                <form>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="name" placeholder="Enter a unique alias: e.g. DOS-001" onkeyup="value=value.replace(/[^a-zA-Z0-9\-_]/g,'')" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('A name to help you identify the storage if you have multiple remote storage connected.', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="password" class="regular-text" autocomplete="new-password" option="edit-s3compat" name="access" placeholder="S3 access key" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter your S3 access key', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="password" class="regular-text" autocomplete="new-password" option="edit-s3compat" name="secret" placeholder="S3 secret key" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter your S3 secret key', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="bucket" placeholder="Bucket Name(e.g. test)" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Enter an existing Bucket in which you want to create a parent folder for holding %s folders.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid backup')); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="root_path" value="<?php echo esc_attr(apply_filters('wpvivid_white_label_remote_root_path', 'wpvividbackuppro')); ?>" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Customize a parent folder in the Bucket for holding %s folders.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid backup')); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="path" placeholder="Custom Path" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><span><?php echo sprintf(__('Customize the name of folder under the parent folder where you want to store %s backups.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', WPVIVID_PRO_PLUGIN_SLUG)); ?></span></i>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="endpoint" placeholder="region.digitaloceanspaces.com" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter the service Endpoint for the storage', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td colspan=2>
                            <label><input class="edit-s3compat" type="checkbox" option="edit-s3compat" name="use_region" onclick="wpvivid_check_special_edit_region(this);"><?php esc_html_e('Enter the bucket region(if any)', 'wpvivid-backup-pro'); ?>
                        </td>
                    </tr>

                    <tr class="wpvivid-region-tr-edit-s3compat" style="display: none;">
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-form">
                                <input type="text" class="regular-text" autocomplete="off" option="edit-s3compat" name="region" placeholder="region, e,g., ru-1" />
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Enter the region of the s3 bucket.', 'wpvivid-backup-pro'); ?></i>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="plugin-title column-primary">
                            <div class="wpvivid-storage-select">
                                <label>
                                    <input type="checkbox" option="edit-s3compat" name="use_path_style_endpoint"/><?php esc_html_e('Use path-style access.', 'wpvivid-backup-pro'); ?>
                                </label>
                            </div>
                        </td>
                        <td class="column-description desc">
                            <div class="wpvivid-storage-form-desc">
                                <i><?php esc_html_e('Use path-style to indicate to an S3-compatible storage.', 'wpvivid-backup-pro'); ?> <a href="https://docs.wpvivid.com/path-style-access-to-s3-compatible-storage.html" target='_blank'><?php esc_html_e('learn more...', 'wpvivid-backup-pro'); ?></a></i>
                            </div>
                        </td>
                    </tr>

                    <?php
                    if (version_compare(PHP_VERSION, '8.1', '>=')) {
                        ?>
                        <tr>
                            <td class="plugin-title column-primary">
                                <div class="wpvivid-storage-select">
                                    <fieldset>
                                        <select option="edit-s3compat" name="signature_version">
                                            <option value="v4" selected="selected">v4 (AWS4-HMAC-SHA256)</option>
                                            <option value="v2">v2 (HMAC-SHA1)</option>
                                        </select>
                                    </fieldset>
                                </div>
                            </td>
                            <td class="column-description desc">
                                <div class="wpvivid-storage-form-desc">
                                    <i><?php esc_html_e('Signature version.', 'wpvivid-backup-pro'); ?></i>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>

                    <?php do_action('wpvivid_remote_storage_backup_retention', 's3compat', 'edit'); ?>

                </form>
                <tr>
                    <td class="plugin-title column-primary">
                        <div class="wpvivid-storage-form">
                            <input class="button-primary" option="edit-remote" type="submit" value="<?php esc_attr_e('Save Changes', 'wpvivid-backup-pro'); ?>" />
                        </div>
                    </td>
                    <td class="column-description desc">
                        <div class="wpvivid-storage-form-desc">
                            <i><?php esc_html_e('Click the button to save the changes.', 'wpvivid-backup-pro'); ?></i>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <script>
            function wpvivid_check_special_edit_region(obj)
            {
                var class_name = jQuery(obj).attr('class');
                if(jQuery(obj).prop('checked'))
                {
                    jQuery('.wpvivid-region-tr-'+class_name).show();
                }
                else
                {
                    jQuery('.wpvivid-region-tr-'+class_name).hide();
                }
            }
        </script>
        <?php
    }

    public function sanitize_options($skip_name='')
    {
        $ret['result']=WPVIVID_PRO_FAILED;
        if(!isset($this->options['name']))
        {
            $ret['error']=__('Warning: An alias for remote storage is required.','wpvivid-backup-pro');
            return $ret;
        }

        $this->options['name']=sanitize_text_field($this->options['name']);

        if(empty($this->options['name']))
        {
            $ret['error']=__('Warning: An alias for remote storage is required.','wpvivid-backup-pro');
            return $ret;
        }

        $remoteslist=WPvivid_Setting::get_all_remote_options();
        foreach ($remoteslist as $key=>$value)
        {
            if(isset($value['name'])&&$value['name'] == $this->options['name']&&$skip_name!=$value['name'])
            {
                $ret['error']=__('Warning: The alias already exists in storage list.','wpvivid-backup-pro');
                return $ret;
            }
        }

        if(!isset($this->options['access']))
        {
            $ret['error']=__('Warning: The access key for S3-Compatible is required.','wpvivid-backup-pro');
            return $ret;
        }

        $this->options['access']=sanitize_text_field($this->options['access']);

        if(empty($this->options['access']))
        {
            $ret['error']=__('Warning: The access key for S3-Compatible is required.','wpvivid-backup-pro');
            return $ret;
        }

        if(!isset($this->options['secret']))
        {
            $ret['error']=__('Warning: The storage secret key is required.','wpvivid-backup-pro');
            return $ret;
        }

        $this->options['secret']=sanitize_text_field($this->options['secret']);

        if(empty($this->options['secret']))
        {
            $ret['error']=__('Warning: The storage secret key is required.','wpvivid-backup-pro');
            return $ret;
        }
        $this->options['secret'] = base64_encode($this->options['secret']);
        $this->options['is_encrypt'] = 1;

        if(empty($this->options['bucket']))
        {
            $ret['error']=__('Warning: A Digital Space is required.','wpvivid-backup-pro');
            return $ret;
        }

        if(!isset($this->options['root_path']))
        {
            $ret['error']=__('Warning: A root path is required.','wpvivid-backup-pro');
            return $ret;
        }
        $this->options['root_path']=sanitize_text_field($this->options['root_path']);
        if(empty($this->options['root_path']))
        {
            $ret['error']=__('Warning: A root path is required.','wpvivid-backup-pro');
            return $ret;
        }

        if($this->options['root_path'] == '/')
        {
            $ret['error']=__('The backup folder name cannot be \'/\'','wpvivid-backup-pro');
            return $ret;
        }

        if(!isset($this->options['path']))
        {
            $ret['error']=__('Warning: A directory name is required.','wpvivid-backup-pro');
            return $ret;
        }

        $this->options['path']=sanitize_text_field($this->options['path']);

        if(empty($this->options['path']))
        {
            $ret['error']=__('Warning: A directory name is required.','wpvivid-backup-pro');
            return $ret;
        }

        if($this->options['path'] == '/')
        {
            $ret['error']=__('The backup folder name cannot be \'/\'','wpvivid-backup-pro');
            return $ret;
        }

        if(!isset($this->options['endpoint']))
        {
            $ret['error']=__('Warning: The end-point is required.','wpvivid-backup-pro');
            return $ret;
        }

        $this->options['endpoint']=sanitize_text_field($this->options['endpoint']);

        if(empty($this->options['endpoint']))
        {
            $ret['error']=__('Warning: The end-point is required.','wpvivid-backup-pro');
            return $ret;
        }

        if(isset($this->options['use_remote_retention']) && $this->options['use_remote_retention'] == '1')
        {
            if (!isset($this->options['backup_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            $this->options['backup_retain'] = sanitize_text_field($this->options['backup_retain']);

            if (empty($this->options['backup_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            if (!isset($this->options['backup_db_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            $this->options['backup_db_retain'] = sanitize_text_field($this->options['backup_db_retain']);

            if (empty($this->options['backup_db_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            if (!isset($this->options['backup_incremental_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            $this->options['backup_incremental_retain'] = sanitize_text_field($this->options['backup_incremental_retain']);

            if (empty($this->options['backup_incremental_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            if (!isset($this->options['backup_rollback_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }

            $this->options['backup_rollback_retain'] = sanitize_text_field($this->options['backup_rollback_retain']);

            if (empty($this->options['backup_rollback_retain'])) {
                $ret['error'] = __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.','wpvivid-backup-pro');
                return $ret;
            }
        }

        if(isset($this->options['use_region']) && $this->options['use_region'] == '1')
        {
            if (!isset($this->options['region'])) {
                $ret['error'] = __('Please enter the region of the s3 bucket.','wpvivid-backup-pro');
                return $ret;
            }

            $this->options['region'] = sanitize_text_field($this->options['region']);

            if (empty($this->options['region'])) {
                $ret['error'] = __('Please enter the region of the s3 bucket.','wpvivid-backup-pro');
                return $ret;
            }
        }

        if (isset($this->options['signature_version'])) {
            $sv = strtolower(sanitize_text_field($this->options['signature_version']));
            if ($sv !== 'v2' && $sv !== 'v4') $sv = 'v4';
            $this->options['signature_version'] = $sv;
        }
        else {
            $this->options['signature_version'] = 'v4';
        }

        $ret['result']=WPVIVID_PRO_SUCCESS;
        $ret['options']=$this->options;
        return $ret;
    }

    public function wpvivid_get_out_of_date_s3compat($out_of_date_remote, $remote)
    {
        if($remote['type'] == WPVIVID_REMOTE_S3COMPAT)
        {
            if(isset($remote['s3directory']))
                $out_of_date_remote = $remote['s3directory'].$remote['path'];
            else
                $out_of_date_remote = $remote['path'];
        }
        return $out_of_date_remote;
    }

    public function wpvivid_storage_provider_s3compat($storage_type)
    {
        if($storage_type == WPVIVID_REMOTE_S3COMPAT){
            $storage_type = 'S3 Compatible Storage';
        }
        return $storage_type;
    }
    private function compare_php_version(){
        if(version_compare(WPVIVID_WASABI_NEED_PHP_VERSION,phpversion()) > 0){
            return array('result' => WPVIVID_PRO_FAILED,'error' => 'The required PHP version is higher than '.WPVIVID_S3COMPAT_NEED_PHP_VERSION.'. After updating your PHP version, please try again.');
        }
        return array('result' => WPVIVID_PRO_SUCCESS);
    }

    public function scan_folder_backup($folder_type)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        if($folder_type === 'Common')
        {
            if(!isset($this->options['path']))
            {
                $ret['result']='failed';
                $ret['error']='test error';
                return $ret;
            }

            $this->options['path']=ltrim($this -> options['path'],'/');
            $path=$root_path.'/'.$this->options['path'];
            $ret_type='remote';
        }
        else if($folder_type === 'Migrate')
        {
            $path=$root_path.'/migrate';
            $ret_type='migrate';
        }
        else if($folder_type === 'Rollback')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback';
            $ret_type='rollback';
        }
        else
        {
            $ret['result']='failed';
            $ret['error']='test error';
            return $ret;
        }

        $response=$this->_scan_folder_backup($path,$s3compat);

        if($response['result']==WPVIVID_PRO_SUCCESS)
        {
            $ret['result']=WPVIVID_PRO_SUCCESS;
            $ret[$ret_type]= $response['backup'];
            $ret['test']=$response['test'];
            $ret['path']=$response['path'];
            return $ret;
        }
        else
        {
            return $response;
        }

    }

    public function scan_child_folder_backup($sub_path)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        $path=$root_path.'/'.$this->options['path'];
        $ret_type='remote';

        $response=$this->_scan_child_folder_backup($path,$sub_path,$s3compat);

        if($response['result']==WPVIVID_PRO_SUCCESS)
        {
            $ret['result']=WPVIVID_PRO_SUCCESS;
            $ret[$ret_type]= $response['backup'];
            $ret['test']=$response['test'];
            return $ret;
        }
        else
        {
            return $response;
        }

    }

    public function get_backup_use_remote_request($response, $path)
    {
        if ( !class_exists("DOMDocument") )
        {
            return array('result' => WPVIVID_PRO_FAILED,'error' => 'The DOM extension for PHP is not installed.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadXML( $response['body'] );
        libxml_clear_errors();
        $xml = simplexml_import_dom( $dom );
        if ( ! $xml ) {
            return array('result' => WPVIVID_PRO_FAILED,'error' => 'Failed to loadXML.');
        }

        $ret['result']=WPVIVID_PRO_SUCCESS;
        $ret['backup']=array();
        $ret['path']=array();
        $ret['test']=array();
        $files=array();
        $new_path=untrailingslashit($this->options['bucket']).'/'.$path;
        $response = get_object_vars($xml);
        if(isset($response['Contents']) && !empty($response['Contents']))
        {
            if(gettype($response['Contents']) === 'object')
            {
                $response['Contents'] = get_object_vars($response['Contents']);
                if(isset($response['Contents']['Key']) && !empty($response['Contents']['Key']))
                {
                    $key = str_replace('%2F', '/', $response['Contents']['Key']);
                    if(dirname($key)==$new_path)
                    {
                        $file_data['file_name']=basename($key);
                        $file_data['size']=$response['Contents']['Size'];
                        $files[]=$file_data;
                    }
                    else
                    {
                        $sub_path=dirname($key);
                        if(!in_array(basename($sub_path),$ret['path']))
                        {
                            $ret['path'][]=basename($sub_path);
                        }

                    }
                }
            }
            else
            {
                foreach ($response['Contents'] as $object)
                {
                    $object = get_object_vars($object);
                    if(isset($object['Key']) && !empty($object['Key']))
                    {
                        $key = str_replace('%2F', '/', $object['Key']);
                        if(dirname($key)==$new_path)
                        {
                            $file_data['file_name']=basename($key);
                            $file_data['size']=$object['Size'];
                            $files[]=$file_data;
                        }
                        else
                        {
                            $sub_path=dirname($key);
                            if(!in_array(basename($sub_path),$ret['path']))
                            {
                                $ret['path'][]=basename($sub_path);
                            }
                        }
                    }
                }
            }
        }

        if(!empty($files))
        {
            global $wpvivid_backup_pro;
            $ret['backup']=$wpvivid_backup_pro->func->get_backup($files);
        }

        return $ret;
    }

    public function get_backup_use_listobject($path,$s3compat)
    {
        $response =$s3compat->listObjects(array(
            'Bucket'=>$this -> bucket,
            'Prefix'=>$path
        ));

        $ret['result']=WPVIVID_PRO_SUCCESS;
        $ret['backup']=array();
        $ret['path']=array();
        $ret['test']=$response['Contents'];
        $files=array();

        if(isset($response['Contents']) && !empty($response['Contents']))
        {
            foreach ($response['Contents']  as $object)
            {
                if(dirname($object['Key'])==$path)
                {
                    $file_data['file_name']=basename($object['Key']);
                    $file_data['size']=$object['Size'];
                    $files[]=$file_data;
                }
                else
                {
                    $sub_path=dirname($object['Key']);
                    if(!in_array(basename($sub_path),$ret['path']))
                        $ret['path'][]=basename($sub_path);
                }
            }
        }

        if(!empty($files))
        {
            global $wpvivid_backup_pro;
            $ret['backup']=$wpvivid_backup_pro->func->get_backup($files);
        }

        return $ret;
    }

    public function _scan_folder_backup($path,$s3compat)
    {
        try
        {
            if(isset($this -> options['use_path_style_endpoint'])&&$this -> options['use_path_style_endpoint'])
            {
                $url=$this->options['endpoint'].'?prefix='.untrailingslashit($this->options['bucket']).'/'.untrailingslashit($this->options['root_path']).'/'.untrailingslashit($this->options['path']).'&encoding-type=url';
                $args=array();
                $response = wp_remote_request( $url, $args );
                if($response['response']['code'] === 404 || $response['response']['code'] === 401 || $response['response']['code'] === 400)
                {
                    $ret=$this->get_backup_use_listobject($path, $s3compat);
                }
                else{
                    $ret=$this->get_backup_use_remote_request($response, $path);
                }
            }
            else
            {
                $ret=$this->get_backup_use_listobject($path, $s3compat);
            }

            return $ret;
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function get_child_folder_backup_use_remote_request($response, $path, $sub_path)
    {
        if ( !class_exists("DOMDocument") )
        {
            return array('result' => WPVIVID_PRO_FAILED,'error' => 'The DOM extension for PHP is not installed.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadXML( $response['body'] );
        libxml_clear_errors();
        $xml = simplexml_import_dom( $dom );
        if ( ! $xml ) {
            return array('result' => WPVIVID_PRO_FAILED,'error' => 'Failed to loadXML.');
        }

        $ret['result']=WPVIVID_PRO_SUCCESS;
        $ret['backup']=array();
        $ret['path']=array();
        $ret['test']=array();
        $ret['files']=array();

        $new_path=untrailingslashit($this->options['bucket']).'/'.$path.'/'.$sub_path;
        $response = get_object_vars($xml);
        if(isset($response['Contents']) && !empty($response['Contents']))
        {
            if(gettype($response['Contents']) === 'object')
            {
                $response['Contents'] = get_object_vars($response['Contents']);
                if(isset($response['Contents']['Key']) && !empty($response['Contents']['Key']))
                {
                    $key = str_replace('%2F', '/', $response['Contents']['Key']);
                    if(dirname($key)==$new_path)
                    {
                        $file_data['file_name']=basename($key);
                        $file_data['size']=$response['Contents']['Size'];
                        $file_data['remote_path']=basename($sub_path);
                        $ret['files'][]=$file_data;
                    }
                }
            }
            else
            {
                foreach ($response['Contents'] as $object)
                {
                    $object = get_object_vars($object);
                    if(isset($object['Key']) && !empty($object['Key']))
                    {
                        $key = str_replace('%2F', '/', $object['Key']);
                        if(dirname($key)==$new_path)
                        {
                            $file_data['file_name']=basename($key);
                            $file_data['size']=$object['Size'];
                            $file_data['remote_path']=basename($sub_path);
                            $ret['files'][]=$file_data;
                        }
                    }
                }
            }
        }

        if(!empty($ret['files']))
        {
            global $wpvivid_backup_pro;
            $ret['backup']=$wpvivid_backup_pro->func->get_backup($ret['files']);
        }

        return $ret;
    }

    public function get_child_folder_backup_use_listobject($path, $sub_path, $s3compat)
    {
        $response =$s3compat->listObjects(array(
            'Bucket'=>$this -> bucket,
            'Prefix'=>$path
        ));

        $ret['result']=WPVIVID_PRO_SUCCESS;
        $ret['backup']=array();
        $ret['test']=$response['Contents'];
        $ret['files']=array();

        if(isset($response['Contents']) && !empty($response['Contents']))
        {
            foreach ($response['Contents']  as $object)
            {
                if(dirname($object['Key'])==$path.'/'.$sub_path)
                {
                    $file_data['file_name']=basename($object['Key']);
                    $file_data['size']=$object['Size'];
                    $file_data['remote_path']=basename($sub_path);
                    $ret['files'][]=$file_data;
                }
            }
        }

        if(!empty($ret['files']))
        {
            global $wpvivid_backup_pro;
            $ret['backup']=$wpvivid_backup_pro->func->get_backup($ret['files']);
        }

        return $ret;
    }

    public function _scan_child_folder_backup($path,$sub_path,$s3compat)
    {
        try
        {
            if(isset($this -> options['use_path_style_endpoint'])&&$this -> options['use_path_style_endpoint'])
            {
                $url=$this->options['endpoint'].'?prefix='.untrailingslashit($this->options['bucket']).'/'.untrailingslashit($this->options['root_path']).'/'.untrailingslashit($this->options['path']).'&encoding-type=url';
                $args=array();
                $response = wp_remote_request( $url, $args );
                if($response['response']['code'] === 404)
                {
                    $ret=$this->get_child_folder_backup_use_listobject($path, $sub_path, $s3compat);
                }
                else{
                    $ret=$this->get_child_folder_backup_use_remote_request($response, $path, $sub_path);
                }
            }
            else
            {
                $ret=$this->get_child_folder_backup_use_listobject($path, $sub_path, $s3compat);
            }

            return $ret;
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function scan_folder_backup_ex($folder_type)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $path=array();
        if($folder_type=='all_backup')
        {
            $ret['result']='success';
            $ret['remote']=array();

            $response=$this->_get_common_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['remote']= $response['backup'];
                $path=$response['path'];
            }

            $ret['migrate']=array();

            $response=$this->_get_migrate_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['migrate']= $response['backup'];
            }

            $ret['rollback']=array();

            $response=$this->_get_rollback_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['rollback']= $response['backup'];
            }

            $ret['incremental']=array();

            if(!empty($path))
            {
                foreach ($path as $incremental_path)
                {
                    if (preg_match('/.*_.*_.*_to_.*_.*_.*$/', $incremental_path))
                    {
                        $response=$this->_get_incremental_backups($incremental_path,$s3compat);
                        if($response['result']==WPVIVID_PRO_SUCCESS)
                        {
                            $ret['incremental']= array_merge($ret['incremental'],$response['backup']);
                        }
                    }
                }
            }
        }
        else if($folder_type=='Manual'||$folder_type=='Cron')
        {
            $ret['result']='success';
            $ret['remote']=array();

            $response=$this->_get_common_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['remote']= $response['backup'];
            }
            else
            {
                return $response;
            }

            $ret['migrate']=array();
            $ret['rollback']=array();
        }
        else if($folder_type=='Migrate')
        {
            $ret['result']='success';
            $ret['migrate']=array();

            $response=$this->_get_migrate_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['migrate']= $response['backup'];
            }
            else
            {
                return $response;
            }
        }
        else if($folder_type=='Rollback')
        {
            $ret['result']='success';
            $ret['rollback']=array();

            $response=$this->_get_rollback_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['rollback']= $response['backup'];
            }
            else
            {
                return $response;
            }
        }
        else if($folder_type=='Incremental')
        {
            $ret['result']='success';

            $response=$this->_get_common_backups($s3compat);
            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $path=$response['path'];
            }
            else
            {
                return $response;
            }

            $ret['remote']=array();
            $ret['migrate']=array();
            $ret['rollback']=array();

            $ret['incremental']=array();

            if(!empty($path))
            {
                foreach ($path as $incremental_path)
                {
                    if (preg_match('/.*_.*_.*_to_.*_.*_.*$/', $incremental_path))
                    {
                        $response=$this->_get_incremental_backups($incremental_path,$s3compat);
                        $ret['incremental']= array_merge($ret['incremental'],$response['backup']);
                    }
                }
            }
        }
        else
        {
            $ret['result'] = 'failed';
            $ret['error'] = 'The selected remote storage does not support scanning.';
            return $ret;
        }

        return $ret;
    }

    public function _get_common_backups($s3compat)
    {
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $path=$root_path.'/'.$this->options['path'];

        return $this->_scan_folder_backup($path,$s3compat);
    }

    public function _get_migrate_backups($s3compat)
    {
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $path=$root_path.'/migrate';

        return $this->_scan_folder_backup($path,$s3compat);
    }

    public function _get_rollback_backups($s3compat)
    {
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $path=$root_path.'/'.$this->options['path'].'/rollback';

        return $this->_scan_folder_backup($path,$s3compat);
    }

    public function _get_incremental_backups($incremental_path,$s3compat)
    {
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        $path=$root_path.'/'.$this->options['path'].'/'.$incremental_path;

        $ret=$this->_scan_folder_backup($path,$s3compat);
        if($ret['result']==WPVIVID_PRO_SUCCESS)
        {
            foreach ($ret['backup'] as  $id=>$backup_data)
            {
                $ret['backup'][$id]['incremental_path']=$incremental_path;
            }
        }
        return $ret;
    }

    public function get_backup_info($backup_info_file,$folder_type,$incremental_path='')
    {
        try
        {
            $s3compat = $this->getClient();

            if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
            {
                return $s3compat;
            }

            $root_path='wpvividbackuppro';
            if(isset($this->options['root_path']))
            {
                $root_path=$this->options['root_path'];
            }

            if($folder_type=='Manual')
            {
                $url=$root_path.'/'.$this->options['path'].'/'. $backup_info_file;
            }
            else if($folder_type=='Migrate')
            {
                $url=$root_path.'/migrate/'. $backup_info_file;
            }
            else if($folder_type=='Rollback')
            {
                $url=$root_path.'/'.$this->options['path'].'/rollback/'. $backup_info_file;
            }
            else if($folder_type=='Incremental')
            {
                $url=$root_path.'/'.$this->options['path'].'/'.$incremental_path.'/'. $backup_info_file;
            }
            else
            {
                $ret['result'] = 'failed';
                $ret['error'] = 'The selected remote storage does not support scanning.';
                return $ret;
            }

            $args=array(
                'Bucket' => $this -> bucket,
                'Key'    => $url);
            $response = $s3compat->getObject($args);
            if (!$response)
                return array('result' => WPVIVID_PRO_FAILED, 'error' => 'download ' . $url. ' failed.');

            $body = $response->get('Body');
            $ret['result']='success';
            $ret['backup_info']=json_decode($body,1);
            return $ret;
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array('result'=>WPVIVID_PRO_FAILED, 'error'=>$message);
        }
    }

    public function scan_rollback($type)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        if($type === 'plugins')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback_ex/plugins';

            $response=$this->_scan_folder($path,$s3compat);

            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['rollback']= $response['path'];
            }
            else
            {
                $ret['rollback']=array();
            }
        }
        else if($type === 'themes')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback_ex/themes';

            $response=$this->_scan_folder($path,$s3compat);

            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['rollback']= $response['path'];
            }
            else
            {
                $ret['rollback']=array();
            }
        }

        $ret['result']='success';
        return $ret;
    }

    public function _scan_folder($path,$s3compat)
    {
        try
        {
            if(isset($this -> options['use_path_style_endpoint'])&&$this -> options['use_path_style_endpoint'])
            {
                $url=$this->options['endpoint'].'?prefix='.untrailingslashit($this->options['bucket']).'/'.$path.'&encoding-type=url';
                $args=array();
                $response = wp_remote_request( $url, $args );
                if ( !class_exists("DOMDocument") )
                {
                    return array('result' => WPVIVID_PRO_FAILED,'error' => 'The DOM extension for PHP is not installed.');
                }

                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                @$dom->loadXML( $response['body'] );
                libxml_clear_errors();
                $xml = simplexml_import_dom( $dom );
                if ( ! $xml ) {
                    return array('result' => WPVIVID_PRO_FAILED,'error' => 'Failed to loadXML.');
                }

                $ret['result']=WPVIVID_PRO_SUCCESS;
                $ret['path']=array();
                $ret['test']=array();
                $start_after = null;
                $response = get_object_vars($xml);

                if(isset($response['Contents']) && !empty($response['Contents']))
                {
                    if(gettype($response['Contents']) === 'object')
                    {
                        $response['Contents'] = get_object_vars($response['Contents']);
                        if(isset($response['Contents']['Key']) && !empty($response['Contents']['Key']))
                        {
                            $key = str_replace('%2F', '/', $response['Contents']['Key']);
                            $new_path=str_replace($path.'/','',$key);
                            $parts = explode('/',$new_path);
                            if(!empty($parts))
                            {
                                if(!in_array($parts[1],$ret['path']))
                                    $ret['path'][]=$parts[1];
                            }
                            $start_after = (string)$key;
                        }
                    }
                    else
                    {
                        foreach ($response['Contents'] as $object)
                        {
                            $object = get_object_vars($object);
                            if(isset($object['Key']) && !empty($object['Key']))
                            {
                                $key = str_replace('%2F', '/', $object['Key']);
                                $new_path=str_replace($path.'/','',$key);
                                $parts = explode('/',$new_path);
                                if(!empty($parts))
                                {
                                    if(!in_array($parts[1],$ret['path']))
                                        $ret['path'][]=$parts[1];
                                }
                                $start_after = (string)$key;
                            }
                        }
                    }
                }

                if (isset($response['MaxKeys']) && $start_after !== null && !empty($response['IsTruncated']))
                {
                    do{
                        $url=$this->options['endpoint'].'?prefix='.untrailingslashit($this->options['bucket']).'/'.$path.'&marker='.$start_after.'encoding-type=url';
                        $args=array();
                        $response = wp_remote_request( $url, $args );
                        if ( !class_exists("DOMDocument") )
                        {
                            return array('result' => WPVIVID_PRO_FAILED,'error' => 'The DOM extension for PHP is not installed.');
                        }

                        libxml_use_internal_errors(true);
                        $dom = new DOMDocument();
                        @$dom->loadXML( $response['body'] );
                        libxml_clear_errors();
                        $xml = simplexml_import_dom( $dom );
                        if ( ! $xml ) {
                            return array('result' => WPVIVID_PRO_FAILED,'error' => 'Failed to loadXML.');
                        }
                        $response = get_object_vars($xml);
                        if(empty($response['Contents']))
                        {
                            break;
                        }

                        if(gettype($response['Contents']) === 'object')
                        {
                            $response['Contents'] = get_object_vars($response['Contents']);
                            if(isset($response['Contents']['Key']) && !empty($response['Contents']['Key']))
                            {
                                $key = str_replace('%2F', '/', $response['Contents']['Key']);
                                $new_path=str_replace($path.'/','',$key);
                                $parts = explode('/',$new_path);
                                if(!empty($parts))
                                {
                                    if(!in_array($parts[1],$ret['path']))
                                        $ret['path'][]=$parts[1];
                                }
                                $start_after = (string)$key;
                            }
                        }
                        else
                        {
                            foreach ($response['Contents'] as $object)
                            {
                                $object = get_object_vars($object);
                                if(isset($object['Key']) && !empty($object['Key']))
                                {
                                    $key = str_replace('%2F', '/', $object['Key']);
                                    $new_path=str_replace($path.'/','',$key);
                                    $parts = explode('/',$new_path);
                                    if(!empty($parts))
                                    {
                                        if(!in_array($parts[1],$ret['path']))
                                            $ret['path'][]=$parts[1];
                                    }
                                    $start_after = (string)$key;
                                }
                            }
                        }
                    } while (!empty($response['Contents']) && !empty($response['IsTruncated']));
                }
            }
            else
            {
                $response =$s3compat->listObjects(array(
                    'Bucket'=>$this -> bucket,
                    'Prefix'=>$path
                ));

                $ret['result']=WPVIVID_PRO_SUCCESS;
                $ret['path']=array();
                $ret['test']=$response['Contents'];
                $start_after = null;

                if(isset($response['Contents']) && !empty($response['Contents']))
                {
                    foreach ($response['Contents']  as $object)
                    {
                        $new_path=str_replace($path.'/','',$object['Key']);
                        $parts = explode('/',$new_path);

                        if(!empty($parts))
                        {
                            if(!in_array($parts[0],$ret['path']))
                                $ret['path'][]=$parts[0];
                        }
                        $start_after = (string)$object['Key'];
                    }
                }

                if (isset($response['MaxKeys']) && $start_after !== null && !empty($response['IsTruncated']))
                {
                    do{
                        $response =$s3compat->listObjects(array(
                            'Bucket'=>$this -> bucket,
                            'Prefix'=>$path,
                            'Marker'=>$start_after
                        ));

                        if(empty($response['Contents']))
                        {
                            break;
                        }

                        foreach ($response['Contents']  as $object)
                        {
                            $new_path=str_replace($path.'/','',$object['Key']);
                            $parts = explode('/',$new_path);

                            if(!empty($parts))
                            {
                                if(!in_array($parts[0],$ret['path']))
                                    $ret['path'][]=$parts[0];
                            }
                            $start_after = (string)$object['Key'];
                        }

                    } while (!empty($response['Contents']) && !empty($response['IsTruncated']));
                }
            }

            return $ret;
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function get_rollback_data($type,$slug)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }

        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        if($type === 'plugins')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback_ex/plugins/'.$slug;

            $response=$this->_scan_folder($path,$s3compat);

            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['data']=array();
                $response_path= $response['path'];
                if(!empty($response_path))
                {
                    foreach ($response_path as $version)
                    {
                        $version_path=$path.'/'.$version;
                        $response=$this->_scan_file($version_path,$slug.'.zip',$s3compat);
                        if($response['result']=='success')
                        {
                            $ret['data']['version'][$version]['upload']=true;
                            $ret['data']['version'][$version]['file']['file_name']=$slug.'.zip';
                            $ret['data']['version'][$version]['file']['size']=$response['file']['size'];
                            $ret['data']['version'][$version]['file']['modified']=$response['file']['mtime'];
                        }
                    }
                }
            }
            else
            {
                $ret['data']=array();
            }
        }
        else if($type === 'themes')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback_ex/themes/'.$slug;

            $response=$this->_scan_folder($path,$s3compat);

            if($response['result']==WPVIVID_PRO_SUCCESS)
            {
                $ret['data']=array();
                $response_path= $response['path'];
                if(!empty($response_path))
                {
                    foreach ($response_path as $version)
                    {
                        $version_path=$path.'/'.$version;
                        $response=$this->_scan_file($version_path,$slug.'.zip',$s3compat);
                        if($response['result']=='success')
                        {
                            $ret['data']['version'][$version]['upload']=true;
                            $ret['data']['version'][$version]['file']['file_name']=$slug.'.zip';
                            $ret['data']['version'][$version]['file']['size']=$response['file']['size'];
                            $ret['data']['version'][$version]['file']['modified']=$response['file']['mtime'];
                        }
                    }
                }
            }
            else
            {
                $ret['data']=array();
            }
        }

        $ret['result']='success';
        return $ret;
    }

    public function _scan_file($path,$file,$s3compat)
    {
        try
        {
            if(isset($this -> options['use_path_style_endpoint'])&&$this -> options['use_path_style_endpoint'])
            {
                $url=$this->options['endpoint'].'?prefix='.untrailingslashit($this->options['bucket']).'/'.$path.'&encoding-type=url';
                $args=array();
                $response = wp_remote_request( $url, $args );
                if ( !class_exists("DOMDocument") )
                {
                    return array('result' => WPVIVID_PRO_FAILED,'error' => 'The DOM extension for PHP is not installed.');
                }

                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                @$dom->loadXML( $response['body'] );
                libxml_clear_errors();
                $xml = simplexml_import_dom( $dom );
                if ( ! $xml ) {
                    return array('result' => WPVIVID_PRO_FAILED,'error' => 'Failed to loadXML.');
                }

                $ret['result']=WPVIVID_PRO_SUCCESS;
                $ret['test']=array();
                $response = get_object_vars($xml);
                if(isset($response['Contents']) && !empty($response['Contents']))
                {
                    if(gettype($response['Contents']) === 'object')
                    {
                        $response['Contents'] = get_object_vars($response['Contents']);
                        if(isset($response['Contents']['Key']) && !empty($response['Contents']['Key']))
                        {
                            $key = str_replace('%2F', '/', $response['Contents']['Key']);
                            if(basename($key)==$file)
                            {
                                $file_data['file_name']=basename($key);
                                $file_data['size']=$response['Contents']['Size'];
                                $file_data['mtime']=strtotime($response['Contents']['LastModified']);
                                $ret['file']=$file_data;
                            }
                        }
                    }
                    else
                    {
                        foreach ($response['Contents'] as $object)
                        {
                            $object = get_object_vars($object);
                            if(isset($object['Key']) && !empty($object['Key']))
                            {
                                $key = str_replace('%2F', '/', $object['Key']);
                                if(basename($key)==$file)
                                {
                                    $file_data['file_name']=basename($key);
                                    $file_data['size']=$object['Size'];
                                    $file_data['mtime']=strtotime($object['LastModified']);
                                    $ret['file']=$file_data;
                                    break;
                                }
                            }
                        }
                    }
                }

                if(!isset($ret['file']))
                {
                    $ret['result']='failed';
                    $ret['error']='Failed to get file information.';
                    return $ret;
                }
            }
            else
            {
                $response =$s3compat->listObjects(array(
                    'Bucket'=>$this -> bucket,
                    'Prefix'=>$path
                ));

                $ret['result']=WPVIVID_PRO_SUCCESS;
                $ret['test']=$response['Contents'];

                if(isset($response['Contents']) && !empty($response['Contents']))
                {
                    foreach ($response['Contents']  as $object)
                    {
                        if(basename($object['Key'])==$file)
                        {
                            $file_data['file_name']=basename($object['Key']);
                            $file_data['size']=$object['Size'];
                            $file_data['mtime']=strtotime($object['LastModified']);
                            $ret['file']=$file_data;
                            break;
                        }
                    }
                }

                if(!isset($ret['file']))
                {
                    $ret['result']='failed';
                    $ret['error']='Failed to get file information.';
                    return $ret;
                }
            }

            return $ret;
        }
        catch(Exception $e)
        {
            $aws_code = '';
            if (is_object($e) && method_exists($e, 'getAwsErrorCode')) {
                $aws_code = $e->getAwsErrorCode();
            }
            return array('result' => WPVIVID_PRO_FAILED, 'error' => $aws_code . $e->getMessage());
        }
    }

    public function delete_old_backup($backup_count,$db_count)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        $path=$root_path.'/'.$this->options['path'];

        $response=$this->_scan_folder_backup($path,$s3compat);

        if(isset($response['backup']))
        {
            $backups=$response['backup'];
            $folders=$response['path'];

            global $wpvivid_backup_pro;
            $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);
            $folders_count=apply_filters('wpvivid_get_backup_folders_count',0);
            $folders=$wpvivid_backup_pro->func->get_old_backup_folders($folders,$folders_count);
            foreach ($folders as $folder)
            {
                $child_response=$this->_scan_child_folder_backup($path,$folder,$s3compat);
                if(isset($child_response['files']))
                {
                    $files=array_merge($files,$child_response['files']);
                }
            }
            if(!empty($files))
            {
                $this->cleanup($files);
            }
        }

        $ret['result']=WPVIVID_PRO_SUCCESS;
        return $ret;
    }

    public function check_old_backups($backup_count,$db_count)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return false;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        $path=$root_path.'/'.$this->options['path'];

        $response=$this->_scan_folder_backup($path,$s3compat);

        if(isset($response['backup']))
        {
            $backups=$response['backup'];

            global $wpvivid_backup_pro;
            $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);
            if(!empty($files))
            {
                return true;
            }
        }

        return false;
    }

    public function delete_old_backup_ex($type,$backup_count,$db_count)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return $s3compat;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }

        if($type=='Rollback')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback';

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['backup']))
            {
                $backups=$response['backup'];

                global $wpvivid_backup_pro;
                $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);

                if(!empty($files))
                {
                    global $wpvivid_backup_pro;
                    $backup_info_array=$wpvivid_backup_pro->func->get_backup($files);
                    if(isset($backup_info_array) && !empty($backup_info_array))
                    {
                        $backup_list=new WPvivid_New_BackupList();
                        foreach ($backup_info_array as $backup_id => $backup_info)
                        {
                            $backup_list->delete_backup($backup_id,$this->options['id']);
                        }
                    }
                    $this->cleanup($files);
                }
            }
        }
        else if($type=='Incremental')
        {
            $path=$root_path.'/'.$this->options['path'];

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['path']))
            {
                $folders=$response['path'];
                global $wpvivid_backup_pro;
                $files = array();
                $folders_count=$backup_count;
                $folders=$wpvivid_backup_pro->func->get_old_backup_folders($folders,$folders_count);
                foreach ($folders as $folder)
                {
                    $child_response=$this->_scan_child_folder_backup($path,$folder,$s3compat);
                    if(isset($child_response['files']))
                    {
                        $files=array_merge($files,$child_response['files']);
                    }
                }
                if(!empty($files))
                {
                    global $wpvivid_backup_pro;
                    $backup_info_array=$wpvivid_backup_pro->func->get_backup($files);
                    if(isset($backup_info_array) && !empty($backup_info_array))
                    {
                        $backup_list=new WPvivid_New_BackupList();
                        foreach ($backup_info_array as $backup_id => $backup_info)
                        {
                            $backup_list->delete_backup($backup_id,$this->options['id']);
                        }
                    }
                    $this->cleanup($files);
                }
            }
        }
        else
        {
            $path=$root_path.'/'.$this->options['path'];

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['backup']))
            {
                $backups=$response['backup'];

                global $wpvivid_backup_pro;
                $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);

                if(!empty($files))
                {
                    global $wpvivid_backup_pro;
                    $backup_info_array=$wpvivid_backup_pro->func->get_backup($files);
                    if(isset($backup_info_array) && !empty($backup_info_array))
                    {
                        $backup_list=new WPvivid_New_BackupList();
                        foreach ($backup_info_array as $backup_id => $backup_info)
                        {
                            $backup_list->delete_backup($backup_id,$this->options['id']);
                        }
                    }
                    $this->cleanup($files);
                }
            }
        }

        $ret['result']=WPVIVID_PRO_SUCCESS;
        return $ret;
    }

    public function check_old_backups_ex($type,$backup_count,$db_count)
    {
        $s3compat = $this->getClient();

        if(is_array($s3compat) && $s3compat['result'] == WPVIVID_PRO_FAILED)
        {
            return false;
        }
        $root_path='wpvividbackuppro';
        if(isset($this->options['root_path']))
        {
            $root_path=$this->options['root_path'];
        }
        if($type=='Rollback')
        {
            $path=$root_path.'/'.$this->options['path'].'/rollback';

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['backup']))
            {
                $backups=$response['backup'];

                global $wpvivid_backup_pro;
                $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);
                if(!empty($files))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }
        else if($type=='Incremental')
        {
            $path=$root_path.'/'.$this->options['path'];

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['path']))
            {
                $folders=$response['path'];
                global $wpvivid_backup_pro;
                $files = array();
                $folders_count=$backup_count;
                $folders=$wpvivid_backup_pro->func->get_old_backup_folders($folders,$folders_count);
                if(!empty($folders))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }
        else
        {
            $path=$root_path.'/'.$this->options['path'];

            $response=$this->_scan_folder_backup($path,$s3compat);

            if(isset($response['backup']))
            {
                $backups=$response['backup'];

                global $wpvivid_backup_pro;
                $files = $wpvivid_backup_pro->func->get_old_backup_files($backups,$backup_count,$db_count);
                if(!empty($files))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }

        return false;
    }
}