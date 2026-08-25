<?php
/**
 * WPvivid addon: yes
 * Addon Name: wpvivid-backup-pro-all-in-one
 * Description: Pro
 * Version: 2.2.49
 * Need_init: yes
 * Interface Name: WPvivid_Schedule_Event_Addon
 */

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Schedule_Event_Addon
{
    public function __construct()
    {
        add_action('wpvivid_clean_remote_schedule_event',array( $this,'clean_remote_schedule_event'));
        add_action('wpvivid_clean_remote_schedule_single_event', array($this, 'clean_remote_schedule_event'));

        add_action('wpvivid_check_incremental_schedule_exist_event',array( $this,'check_schedule_incremental_exist_event'));

        if(!defined( 'DOING_CRON' ))
        {
            if(wp_get_schedule('wpvivid_check_incremental_schedule_exist_event')===false)
            {
                wp_schedule_event(time()+3600, 'daily', 'wpvivid_check_incremental_schedule_exist_event');
            }
        }

        add_action('wpvivid_clean_local_storage_event', array($this, 'clean_local_storage_event'));
        add_action('wpvivid_calc_site_size_event', array($this, 'calc_site_size_event'));
        add_action('init', array($this, 'maybe_schedule_clean_local_storage_event'), 30);
        add_action('init', array($this, 'maybe_schedule_calc_site_size_event'), 30);
    }

    public function maybe_schedule_clean_local_storage_event()
    {
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        $options=get_option('wpvivid_common_setting',array());
        $enable = false;
        if(isset($options['clean_local_storage']))
        {
            if($options['clean_local_storage']['log'] || $options['clean_local_storage']['backup_cache']|| $options['clean_local_storage']['junk_files'])
            {
                $enable = true;
            }
            else
            {
                $enable = false;
            }
        }
        $recurrence = isset($options['clean_local_storage']['recurrence']) ? $options['clean_local_storage']['recurrence'] : 'wpvivid_weekly';

        if ($enable) {
            $current = wp_get_schedule('wpvivid_clean_local_storage_event');
            if ($current === false || $current !== $recurrence) {
                wp_clear_scheduled_hook('wpvivid_clean_local_storage_event');
                $offset = get_option('gmt_offset');
                $timestamp = strtotime('00:00') + $offset*60*60 + 300;
                if($timestamp <= time()){
                    $timestamp = time() + 300;
                }
                wp_schedule_event($timestamp, $recurrence, 'wpvivid_clean_local_storage_event');
            }
        }
        else {
            if (wp_get_schedule('wpvivid_clean_local_storage_event') !== false) {
                wp_clear_scheduled_hook('wpvivid_clean_local_storage_event');
            }
        }
    }

    public function maybe_schedule_calc_site_size_event()
    {
        $options = get_option('wpvivid_common_setting', array());

        $enable = isset($options['auto_calc_site_size']) ? intval($options['auto_calc_site_size']) : 0;
        $recurrence = isset($options['auto_calc_site_size_interval']) ? $options['auto_calc_site_size_interval'] : 'wpvivid_weekly';
        $allowed = array(
            'wpvivid_daily',
            'wpvivid_2days',
            'wpvivid_3days',
            'wpvivid_4days',
            'wpvivid_5days',
            'wpvivid_6days',
            'wpvivid_weekly',
        );
        if(!in_array($recurrence, $allowed, true)){
            $recurrence = 'wpvivid_weekly';
        }

        if(!defined('DOING_CRON'))
        {
            if($enable)
            {
                $current = wp_get_schedule('wpvivid_calc_site_size_event');
                if($current === false || $current !== $recurrence)
                {
                    wp_clear_scheduled_hook('wpvivid_calc_site_size_event');

                    $offset = get_option('gmt_offset');
                    $timestamp = strtotime('00:00') + $offset*60*60 + 300;

                    if($timestamp <= time()){
                        $timestamp = time() + 300;
                    }

                    wp_schedule_event($timestamp, $recurrence, 'wpvivid_calc_site_size_event');
                }
            }
            else
            {
                if(wp_get_schedule('wpvivid_calc_site_size_event') !== false)
                {
                    wp_clear_scheduled_hook('wpvivid_calc_site_size_event');
                }
            }
        }
    }

    public function clean_local_storage_event()
    {
        global $wpvivid_backup_pro;
        $backup_list=new WPvivid_New_BackupList();

        $delete_files = array();
        $delete_folder=array();

        $options=get_option('wpvivid_common_setting',array());

        if(isset($options['clean_local_storage']))
        {
            $options=$options['clean_local_storage'];
        }
        else
        {
            die();
        }

        if($options['log']==1)
        {
            $log_dir=$wpvivid_backup_pro->wpvivid_pro_log->GetSaveLogFolder();
            $error_log_dir=$log_dir.DIRECTORY_SEPARATOR.'error';
            $log_files=array();
            $temp=array();
            $this -> get_dir_files($log_files,$temp,$log_dir,array('file' => '&wpvivid-&'),array(),array(),0,false);
            $this -> get_dir_files($log_files,$temp,$error_log_dir,array('file' => '&wpvivid-&'),array(),array(),0,false);
            foreach ($log_files as $file)
            {
                $file_name=basename($file);
                $id=substr ($file_name,0,21);
                if($backup_list->get_backup_by_id($id)===false)
                {
                    $delete_files[]=$file;
                }
            }
        }

        if($options['backup_cache']==1)
        {
            $remote_backups=$backup_list->get_all_remote_backup();
            foreach ($remote_backups as $id=>$backup)
            {
                $backup_item = new WPvivid_New_Backup_Item($backup);
                $backup_item->cleanup_local_backup();
            }

            WPvivid_tools::clean_junk_cache();
        }

        if($options['junk_files']==1)
        {
            $list=$backup_list->get_all_backup();
            $files=array();
            foreach ($list as $backup_id => $backup)
            {
                $backup_item = new WPvivid_New_Backup_Item($backup);
                $file=$backup_item->get_files(false);
                foreach ($file as $filename)
                {
                    $files[]=$filename;
                }
            }

            $dir=WPvivid_Custom_Interface_addon::wpvivid_get_local_backup_abspath();
            $path=str_replace('/',DIRECTORY_SEPARATOR,$wpvivid_backup_pro->wpvivid_pro_log->GetSaveLogFolder());
            if(substr($path, -1) == DIRECTORY_SEPARATOR)
            {
                $path = substr($path, 0, -1);
            }
            $folder[]= $path;
            $except_regex['file'][]='&wpvivid-&';
            $except_regex['file'][]='&wpvivid_temp-&';
            $except_regex['file'][]='&'.apply_filters('wpvivid_white_label_file_prefix', 'wpvivid').'-&';
            $except_regex['file'][]='&'.apply_filters('wpvivid_white_label_file_prefix', 'wpvivid').'_temp-&';
            $this -> get_dir_files($delete_files,$delete_folder,$dir,$except_regex,$files,$folder,0,false);
        }

        if(!empty($delete_files))
        {
            foreach ($delete_files as $file)
            {
                if(file_exists($file))
                    @unlink($file);
            }
        }

        if(!empty($delete_folder))
        {
            foreach ($delete_folder as $folder)
            {
                if(file_exists($folder))
                    WPvivid_tools::deldir($folder,'',true);
            }
        }

        die();
    }

    public function get_dir_files(&$files,&$folder,$path,$except_regex,$exclude_files=array(),$exclude_folder=array(),$exclude_file_size=0,$flag = true)
    {
        $handler=opendir($path);
        if($handler===false)
            return;
        while(($filename=readdir($handler))!==false)
        {
            if($filename != "." && $filename != "..")
            {
                $dir=str_replace('/',DIRECTORY_SEPARATOR,$path.DIRECTORY_SEPARATOR.$filename);


                if(in_array($dir,$exclude_folder))
                {
                    continue;
                }
                else if(is_dir($path.DIRECTORY_SEPARATOR.$filename))
                {
                    if($except_regex!==false)
                    {
                        if($this -> regex_match($except_regex['file'],$path.DIRECTORY_SEPARATOR.$filename,$flag)){
                            continue;
                        }
                        $folder[]=$path.DIRECTORY_SEPARATOR.$filename;
                    }else
                    {
                        $folder[]=$path.DIRECTORY_SEPARATOR.$filename;
                    }
                    $this->get_dir_files($files ,$folder, $path.DIRECTORY_SEPARATOR.$filename,$except_regex,$exclude_folder);
                }else {
                    if($except_regex===false||!$this -> regex_match($except_regex['file'] ,$path.DIRECTORY_SEPARATOR.$filename,$flag))
                    {
                        if(in_array($filename,$exclude_files))
                        {
                            continue;
                        }
                        if($exclude_file_size==0)
                        {
                            $files[] = $path.DIRECTORY_SEPARATOR.$filename;
                        }
                        else if(filesize($path.DIRECTORY_SEPARATOR.$filename)<$exclude_file_size*1024*1024)
                        {
                            $files[] = $path.DIRECTORY_SEPARATOR.$filename;
                        }
                    }
                }
            }
        }
        if($handler)
            @closedir($handler);

    }

    private function regex_match($regex_array,$filename,$flag){
        if($flag){
            if(empty($regex_array)){
                return false;
            }
            if(is_array($regex_array)){
                foreach ($regex_array as $regex)
                {
                    if(preg_match($regex,$filename))
                    {
                        return true;
                    }
                }
            }else{
                if(preg_match($regex_array,$filename))
                {
                    return true;
                }
            }
            return false;
        }else{
            if(empty($regex_array)){
                return true;
            }
            if(is_array($regex_array)){
                foreach ($regex_array as $regex)
                {
                    if(preg_match($regex,$filename))
                    {
                        return false;
                    }
                }
            }else{
                if(preg_match($regex_array,$filename))
                {
                    return false;
                }
            }
            return true;
        }
    }

    public function clean_remote_schedule_event($backup_count=0,$db_count=0)
    {
        $load=new WPvivid_Load_Admin_Remote();
        $load->load();

        $remoteslist=WPvivid_Setting::get_all_remote_options();
        foreach ($remoteslist as $key=>$remote_option)
        {
            if($key=='remote_selected')
            {
                continue;
            }
            if(in_array($key, $remoteslist['remote_selected']))
            {
                set_time_limit(300);
                global $wpvivid_plugin;

                $remote_collection=new WPvivid_Remote_collection_addon();
                $remote = $remote_collection->get_remote($remote_option);
                try
                {
                    if (method_exists($remote, 'delete_old_backup_ex'))
                    {
                        $backup_count=$this->get_backup_retain_count('Manual',$remote_option,false);
                        $db_count=$this->get_backup_db_retain_count('Manual',$remote_option,false);
                        do_action('wpvivid_schedule_scan_remote_backup', $key, 'Manual', $backup_count, $db_count);

                        $backup_count=$this->get_backup_retain_count('Cron',$remote_option,false);
                        $db_count=$this->get_backup_db_retain_count('Cron',$remote_option,false);
                        do_action('wpvivid_schedule_scan_remote_backup', $key, 'Cron', $backup_count, $db_count);

                        $backup_count=$this->get_backup_retain_count('Rollback',$remote_option,false);
                        $db_count=$this->get_backup_db_retain_count('Rollback',$remote_option,false);
                        $remote->delete_old_backup_ex('Rollback',$backup_count,$db_count);

                        $backup_count=$this->get_backup_retain_count('Incremental',$remote_option,false);
                        $db_count=$this->get_backup_db_retain_count('Incremental',$remote_option,false);
                        $remote->delete_old_backup_ex('Incremental',$backup_count,$db_count);
                    }
                    else if(method_exists($remote, 'delete_old_backup'))
                    {
                        $option=WPvivid_Setting::get_option('wpvivid_common_setting');
                        if(isset($remote_option['backup_retain']))
                            $backup_count = $remote_option['backup_retain'];
                        else if (isset($option['max_remote_backup_count']))
                            $backup_count = $option['max_remote_backup_count'];
                        else
                            $backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;

                        if(isset($remote_option['backup_db_retain']))
                            $db_count = $remote_option['backup_db_retain'];
                        else if(isset($option['max_remote_backup_db_count']))
                            $db_count = $option['max_remote_backup_db_count'];
                        else
                            $db_count = 30;

                        $backup_count = intval($backup_count);
                        $db_count = intval($db_count);
                        $remote->delete_old_backup($backup_count,$db_count);
                    }
                }
                catch (Exception $e)
                {
                    continue;
                }
            }
        }
        die();
    }

    public function get_backup_retain_count($type,$remote_option,$force_reduce=false)
    {
        $option=get_option('wpvivid_common_setting');

        if($type=='Manual')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_retain']))
                {
                    $backup_count = $remote_option['backup_retain'];
                }
                else
                {
                    $backup_count = 30;
                }
            }
            else if(isset($option['manual_max_remote_backup_count']))
            {
                $backup_count = $option['manual_max_remote_backup_count'];
            }
            else if(isset($option['max_remote_backup_count']))
            {
                $backup_count = $option['max_remote_backup_count'];
            }
            else
            {
                $backup_count = 30;
            }
            if($backup_count==0)
            {
                $backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Cron')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_retain']))
                {
                    $backup_count = $remote_option['backup_retain'];
                }
                else
                {
                    $backup_count = 30;
                }
            }
            else if(isset($option['schedule_max_remote_backup_count']))
            {
                $backup_count = $option['schedule_max_remote_backup_count'];
            }
            else if(isset($option['max_remote_backup_count']))
            {
                $backup_count = $option['max_remote_backup_count'];
            }
            else
            {
                $backup_count = 30;
            }
            if($backup_count==0)
            {
                $backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Rollback')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_rollback_retain']))
                {
                    $backup_count = $remote_option['backup_rollback_retain'];
                }
                else
                {
                    $backup_count = 30;
                }
            }
            else if(isset($option['rollback_max_remote_backup_count']))
            {
                $backup_count = $option['rollback_max_remote_backup_count'];
            }
            else
            {
                $backup_count = 30;
            }
            if($backup_count==0)
            {
                $backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Incremental')
        {
            $incremental_remote_backup_count = WPvivid_Setting::get_option('wpvivid_incremental_remote_backup_count_addon', 3);

            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_incremental_retain']))
                {
                    $backup_count = $remote_option['backup_incremental_retain'];
                }
                else
                {
                    $backup_count = $incremental_remote_backup_count;
                }
            }
            else if(isset($option['incremental_max_remote_backup_count']))
            {
                $backup_count = $option['incremental_max_remote_backup_count'];
            }
            else
            {
                $backup_count = $incremental_remote_backup_count;
            }
        }
        else
        {
            $backup_count=0;
        }

        if($force_reduce)
        {
            if ($backup_count - 1 > 0)
            {
                $backup_count = $backup_count - 1;
            }
        }

        return $backup_count;
    }

    public function get_backup_db_retain_count($type,$remote_option,$force_reduce=false)
    {
        $option=get_option('wpvivid_common_setting');

        if($type=='Manual')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_db_retain']))
                {
                    $db_count = $remote_option['backup_db_retain'];
                }
                else
                {
                    $db_count = 30;
                }
            }
            else if(isset($option['manual_max_remote_backup_db_count']))
            {
                $db_count = $option['manual_max_remote_backup_db_count'];
            }
            else if(isset($option['max_remote_backup_db_count']))
            {
                $db_count = $option['max_remote_backup_db_count'];
            }
            else
            {
                $db_count = 30;
            }
            if($db_count==0)
            {
                $db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Cron')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_db_retain']))
                {
                    $db_count = $remote_option['backup_db_retain'];
                }
                else
                {
                    $db_count = 30;
                }
            }
            else if(isset($option['schedule_max_remote_backup_db_count']))
            {
                $db_count = $option['schedule_max_remote_backup_db_count'];
            }
            else if(isset($option['max_remote_backup_db_count']))
            {
                $db_count = $option['max_remote_backup_db_count'];
            }
            else
            {
                $db_count = 30;
            }
            if($db_count==0)
            {
                $db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Rollback')
        {
            if(isset($remote_option['use_remote_retention']) && $remote_option['use_remote_retention'] == '1')
            {
                if(isset($remote_option['backup_rollback_retain']))
                {
                    $db_count = $remote_option['backup_rollback_retain'];
                }
                else
                {
                    $db_count = 30;
                }
            }
            else if(isset($option['rollback_max_remote_backup_count']))
            {
                $db_count = $option['rollback_max_remote_backup_count'];
            }
            else
            {
                $db_count = 30;
            }
            if($db_count==0)
            {
                $db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
            }
        }
        else if($type=='Incremental')
        {
            $db_count=0;
        }
        else
        {
            $db_count=0;
        }

        if($force_reduce)
        {
            if ($db_count - 1 > 0)
            {
                $db_count = $db_count - 1;
            }
        }

        return $db_count;
    }

    public function check_schedule_incremental_exist_event()
    {
        $enable_incremental_schedules=WPvivid_Setting::get_option('wpvivid_enable_incremental_schedules', false);
        if($enable_incremental_schedules)
        {
            $incremental_schedules=WPvivid_Setting::get_option('wpvivid_incremental_schedules');
            $schedule_data=array_shift($incremental_schedules);

            if(!wp_get_schedule($schedule_data['files_schedule_id'], array($schedule_data['id'])))
            {
                if(wp_schedule_event($schedule_data['files_start_time'], $schedule_data['incremental_files_recurrence'], $schedule_data['files_schedule_id'],array($schedule_data['id']))===false)
                {
                    $ret['result']='failed';
                    $ret['error']=__('Failed to create a schedule. Please try again later.', 'wpvivid-backup-pro');
                }
            }

            if(!wp_get_schedule($schedule_data['db_schedule_id'], array($schedule_data['id'])))
            {
                if(wp_schedule_event($schedule_data['db_start_time'], $schedule_data['incremental_db_recurrence'], $schedule_data['db_schedule_id'],array($schedule_data['id']))===false)
                {
                    $ret['result']='failed';
                    $ret['error']=__('Failed to create a schedule. Please try again later.', 'wpvivid-backup-pro');
                }
            }
        }
    }

    public function calc_site_size_event()
    {
        if(get_transient('wpvivid_calc_site_size_lock')){
            return;
        }
        set_transient('wpvivid_calc_site_size_lock', 1, 30 * 60);
        try{
            if (!class_exists('WPvivid_New_Backup_Page_addon')) {
                require_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'addons2/backup_pro/class-wpvivid-new-backup-addon.php';
            }

            if(!class_exists('WPvivid_New_Backup_Page_addon')){
                delete_transient('wpvivid_calc_site_size_lock');
                return;
            }

            $ref = new ReflectionClass('WPvivid_New_Backup_Page_addon');
            $addon = $ref->newInstanceWithoutConstructor();
            $json = array();
            $json['custom_dirs'] = array(
                'database_check'=>'1','core_check'=>'1','content_check'=>'1',
                'themes_check'=>'1','plugins_check'=>'1','uploads_check'=>'1',
                'other_check'=>'0','additional_database_check'=>'0',
            );

            $manual_backup_history = WPvivid_Pro_Option::get_manual_backup_history();
            $json['exclude_files'] = isset($manual_backup_history['exclude_files']) ? $manual_backup_history['exclude_files'] : array();
            $type='general';
            $website_size = get_option('wpvivid_custom_select_website_size_ex', array());
            if(empty($website_size)) $website_size=array();
            if(!isset($website_size[$type])) $website_size[$type]=array();

            // database
            $database_exclude_list = isset($json['custom_dirs']['exclude-tables']) ? $json['custom_dirs']['exclude-tables'] : array();
            $db_ret = $addon->_get_custom_database_size(true, $database_exclude_list, true);
            $website_size[$type]['database_size'] = isset($db_ret['database_size']) ? intval($db_ret['database_size']) : 0;

            // core
            if(!function_exists('get_home_path')) require_once(ABSPATH.'wp-admin/includes/file.php');
            $home_path = str_replace('\\','/', get_home_path());
            $tmp_core_path = str_replace('\\','/', untrailingslashit($home_path).'/');
            $core_folder_exclude_list = array($tmp_core_path.'wp-admin', $tmp_core_path.'wp-includes', $tmp_core_path.'lotties');
            $core_file_exclude_list = array(
                $tmp_core_path.'.htaccess', $tmp_core_path.'index.php', $tmp_core_path.'license.txt', $tmp_core_path.'readme.html',
                $tmp_core_path.'wp-activate.php',$tmp_core_path.'wp-blog-header.php',$tmp_core_path.'wp-comments-post.php',
                $tmp_core_path.'wp-config.php',$tmp_core_path.'wp-config-sample.php',$tmp_core_path.'wp-cron.php',
                $tmp_core_path.'wp-links-opml.php',$tmp_core_path.'wp-load.php',$tmp_core_path.'wp-login.php',
                $tmp_core_path.'wp-mail.php',$tmp_core_path.'wp-settings.php',$tmp_core_path.'wp-signup.php',
                $tmp_core_path.'wp-trackback.php',$tmp_core_path.'xmlrpc.php'
            );
            $website_size[$type]['core_size'] = intval(WPvivid_New_Backup_Page_addon::get_custom_path_size(
                'core', $home_path, $core_folder_exclude_list, $core_file_exclude_list
            ));

            // content
            $content_path = str_replace('\\','/', WP_CONTENT_DIR).'/';
            $local_setting = get_option('wpvivid_local_setting', array());
            if(!empty($local_setting)){
                $content_folder_exclude_list = array(
                    $content_path.'plugins',$content_path.'themes',$content_path.'uploads',
                    $content_path.'wpvividbackups',$content_path.$local_setting['path'],$content_path.'wpvivid_image_optimization'
                );
            }else{
                $content_folder_exclude_list = array(
                    $content_path.'plugins',$content_path.'themes',$content_path.'uploads',
                    $content_path.'wpvividbackups',$content_path.'wpvivid_image_optimization'
                );
            }
            $content_file_exclude_list = array();
            $addon->get_exclude_list($json,'content',$content_folder_exclude_list,$content_file_exclude_list);
            $website_size[$type]['content_size'] = intval(WPvivid_New_Backup_Page_addon::get_custom_path_size(
                'content', $content_path, $content_folder_exclude_list, $content_file_exclude_list
            ));

            // themes
            $themes_path = str_replace('\\','/', get_theme_root()).'/';
            $themes_folder_exclude_list = array();
            $themes_file_exclude_list = array();
            $addon->get_exclude_list($json,'themes',$themes_folder_exclude_list,$themes_file_exclude_list);
            $website_size[$type]['themes_size'] = intval(WPvivid_New_Backup_Page_addon::get_custom_path_size(
                'themes', $themes_path, $themes_folder_exclude_list, $themes_file_exclude_list
            ));

            // plugins
            $plugins_path = str_replace('\\','/', WP_PLUGIN_DIR).'/';
            $plugins_folder_exclude_list = array();
            $plugins_file_exclude_list = array();
            $addon->get_exclude_list($json,'plugins',$plugins_folder_exclude_list,$plugins_file_exclude_list);
            $website_size[$type]['plugins_size'] = intval(WPvivid_New_Backup_Page_addon::get_custom_path_size(
                'plugins', $plugins_path, $plugins_folder_exclude_list, $plugins_file_exclude_list
            ));

            // uploads
            $upload_dir = wp_upload_dir();
            $uploads_path = str_replace('\\','/',$upload_dir['basedir']).'/';
            $uploads_folder_exclude_list = array();
            $uploads_file_exclude_list = array();
            $addon->get_exclude_list($json,'uploads',$uploads_folder_exclude_list,$uploads_file_exclude_list);
            $website_size[$type]['uploads_size'] = intval(WPvivid_New_Backup_Page_addon::get_custom_path_size(
                'uploads', $uploads_path, $uploads_folder_exclude_list, $uploads_file_exclude_list
            ));

            $website_size[$type]['calctime'] = time();
            update_option('wpvivid_custom_select_website_size_ex', $website_size, 'no');
        }
        catch(Throwable $e){

        }

        delete_transient('wpvivid_calc_site_size_lock');
    }
}