<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_backup_pro
{
    public $addons_loader;
    public $dashboard;
    public $license;
    public $installation;
    public $updater;
    public $setting;
    public $mainwp;
    public $func;
    public $interface_mainwp;
    public $wpvivid_pro_log;

    public function __construct()
    {
        //
        $this->load_dependencies();

        $this->addons_loader=new WPvivid_addon_loader();

        if($this->should_load_addons())
        {
            $this->addons_loader->load_addons();

            $log_ex_addon_path = WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'addons2/backup_pro/class-wpvivid-log-ex-addon.php';
            if(file_exists($log_ex_addon_path))
            {
                if(!class_exists('WPvivid_Log_Ex_addon'))
                {
                    include WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'addons2/backup_pro/class-wpvivid-log-ex-addon.php';
                }
            }
            if (class_exists('WPvivid_Log_Ex_addon')) {
                $this->wpvivid_pro_log = new WPvivid_Log_Ex_addon();
            } else {
                $this->wpvivid_pro_log = null;
            }
        }
        else
        {
            $this->wpvivid_pro_log = null;
        }

        if(is_admin())
        {
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/display/class-wpvivid-dashboard-display.php';
            $this->dashboard=new WPvivid_Dashboard();
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/display/class-wpvivid-installation-display.php';
            $this->license=new WPvivid_Installation();
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/display/class-wpvivid-license-display.php';
            $this->license=new WPvivid_Pro_License();

            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-pro-setting.php';
            $this->setting=new WPvivid_Pro_Setting();

            //add_filter('wpvivid_get_main_admin_menus', array($this,'get_main_admin_menus'),9999);
            add_filter('wpvivid_get_admin_menus', array($this,'get_admin_menus'), 9999);
            add_filter('wpvivid_get_dashboard_menu', array($this, 'get_dashboard_menu'), 10, 2);
            add_filter('wpvivid_get_toolbar_menus', array($this, 'get_toolbar_menus'), 99);
            if (is_multisite())
            {
                add_action('network_admin_menu',array( $this,'add_dashboard_admin_menu'),9999);
            }
            else
            {
                add_action('admin_menu',array( $this,'add_dashboard_admin_menu'),9999);
            }

            add_action('admin_bar_menu',array( $this,'add_toolbar_items'),9999);

            add_filter('wpvivid_get_admin_url',array($this,'get_admin_url'),10);

            add_filter('wpvivid_get_screen_ids', array($this,'get_screen_ids'), 9999);

            add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'), 12);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 11);

            add_filter('wpvivid_get_dashboard_menu_slug',array($this, 'get_dashboard_menu_slug'));
            //wpvivid_white_label_slug

            add_action('admin_notices', array($this, 'check_wpvivid_free_version'));
            add_action('admin_notices', array($this, 'check_schedule_remote_storage'));
            add_action('admin_notices', array($this, 'check_remote_storage_values'));
            add_action('admin_notices', array($this, 'check_backup_free_install_active'));
            add_action('admin_notices', array($this, 'remote_password_crypt_import_notice'));

            add_filter( 'wpvivid_v2_collect_warnings', array( $this, 'check_wpvivid_free_version_ex' ) );
            add_filter( 'wpvivid_v2_collect_warnings', array( $this, 'check_schedule_remote_storage_ex' ) );
            add_filter( 'wpvivid_v2_collect_warnings', array( $this, 'check_remote_storage_values_ex' ) );
            add_filter( 'wpvivid_v2_collect_warnings', array( $this, 'check_backup_free_install_active_ex' ) );
            add_filter( 'wpvivid_v2_collect_warnings', array( $this, 'remote_password_crypt_import_notice_ex') );

            add_action('in_admin_header',array( $this,'hide_notices'), 99);
            add_action('wpvivid_v2_notice', array($this, 'wpvivid_v2_render_notices'), 10, 1);

            $this->set_locale();
        }

        $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . 'wpvivid-backup-pro.php' );
        add_filter('plugin_action_links_' . $plugin_basename, array( $this,'add_action_links'));
        add_filter( 'plugin_row_meta', array($this,'filter_plugin_row_meta'), 11, 4 );
        add_filter('wpvivid_export_setting_addon', array($this, 'export_dashboard_info'), 11);
        add_filter('wpvivid_get_dashboard_screens', array($this, 'get_dashboard_screens'), 10);

        add_filter('wpvivid_staging_get_main_admin_menus', array($this, 'staging_get_main_admin_menus'), 9999);
        add_filter('wpvivid_get_staging_screens', array($this, 'get_staging_screens'), 1000);
        add_filter('wp_mainwp_stream_record_array', array($this, 'wpvivid_mainwp_record_array'));

        add_filter('wpvivid_is_plugin_enabled', array($this, 'is_plugin_enabled'), 10, 2);

        add_action('wp_ajax_wpvivid_dismiss_remote_password_crypt_import_notice', array($this, 'dismiss_remote_password_crypt_import_notice'));

        $this->migrate_mu_plugins_exclude_compat();
        $this->set_auto_update();
    }

    /*
    public function get_main_admin_menus($menu)
    {
        $menu['page_title']=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');
        $menu['menu_title']=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');

        if( apply_filters('wpvivid_is_user_super_admin',true))
        {
            $menu['capability'] = 'administrator';
        }
        else
        {
            $menu['capability'] = 'wpvivid-can-use-plugins';
        }

        $menu['menu_slug'] =apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-dashboard');
        $menu['function']=array($this->dashboard, 'init_page');
        $menu['icon_url']='dashicons-cloud';
        $menu['position']=100;

        return $menu;
    }*/

    private function set_locale()
    {
        require_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . '/includes/class-wpvivid-pro-i18n.php';
        $plugin_i18n = new WPvivid_Pro_i18n();
        add_action('init',array( $plugin_i18n,'load_plugin_textdomain'));
    }

    private function should_load_addons()
    {
        if(is_admin())
        {
            return true;
        }

        if(function_exists('wp_doing_ajax') && wp_doing_ajax())
        {
            return true;
        }

        if(defined('DOING_AJAX') && DOING_AJAX)
        {
            return true;
        }

        if(function_exists('wp_doing_cron') && wp_doing_cron())
        {
            return true;
        }

        if(defined('DOING_CRON') && DOING_CRON)
        {
            return true;
        }

        if(defined('WP_CLI') && WP_CLI)
        {
            return true;
        }

        if(!empty($_POST['wpvivid_action']))
        {
            return true;
        }

        if(!empty($_REQUEST['action']))
        {
            if(function_exists('wp_unslash'))
            {
                $action = wp_unslash($_REQUEST['action']);
            }
            else
            {
                $action = $_REQUEST['action'];
            }

            if(function_exists('sanitize_key'))
            {
                $action = sanitize_key($action);
            }

            if(strpos($action, 'wpvivid') === 0)
            {
                return true;
            }
        }

        if ($this->should_load_frontend_image_addons())
        {
            return true;
        }

        return apply_filters('wpvivid_backup_pro_should_load_addons', false);
    }

    private function should_load_frontend_image_addons()
    {
        if (is_admin())
        {
            return false;
        }

        if (defined('DOING_CRON') && DOING_CRON)
        {
            return false;
        }

        if (defined('DOING_AJAX') && DOING_AJAX)
        {
            return false;
        }

        $options = get_option('wpvivid_optimization_options', array());

        if (!empty($options['webp']['display_enable']) && !empty($options['webp']['display']) && $options['webp']['display'] === 'pic')
        {
            return true;
        }

        if (!empty($options['cdn']['enable']))
        {
            return true;
        }

        if (!empty($options['lazyload']['enable']))
        {
            return true;
        }

        return false;
    }

    public function wpvivid_mainwp_record_array($record)
    {
        $fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action', 'meta' );
        $data   = array_intersect_key( $record, array_flip( $fields ) );

        if(isset($data['summary']))
        {
            if(strstr($data['summary'],'WPvivid Backup Plugin'))
            {
                $data['summary'] = apply_filters('wpvivid_white_label_display_ex', $data['summary']);
                $record['summary']=$data['summary'];
            }

            if(strstr($data['summary'],'WPvivid Plugins Pro') ||
                strstr($data['summary'],'WPvivid Staging') ||
                strstr($data['summary'],'WPvivid Imgoptim Free'))
            {
                $data['summary'] = apply_filters('wpvivid_white_label_display_pro_ex', $data['summary']);
                $record['summary']=$data['summary'];
            }
        }

        if(isset($data['meta']['name']))
        {
            if(strstr($data['meta']['name'],'WPvivid Backup Plugin'))
            {
                $data['meta']['name'] = apply_filters('wpvivid_white_label_display_ex', $data['meta']['name']);
                $record['meta']=$data['meta'];
            }

            if(strstr($data['meta']['name'],'WPvivid Plugins Pro') ||
                strstr($data['meta']['name'],'WPvivid Staging') ||
                strstr($data['meta']['name'],'WPvivid Imgoptim Free'))
            {
                $data['meta']['name'] = apply_filters('wpvivid_white_label_display_pro_ex', $data['meta']['name']);
                $record['meta']=$data['meta'];
            }
        }

        return $record;
    }

    public function add_dashboard_admin_menu()
    {
        if ( ! function_exists( 'is_plugin_active' ) )
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }

        if(is_plugin_active('wpvivid-backuprestore/wpvivid-backuprestore.php'))
        {
            remove_menu_page('WPvivid');
        }

        if(isset($_REQUEST['auto_backup'])&&$_REQUEST['auto_backup']==1)
        {
            $page_title=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');
            $menu_title=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');

            $capability = apply_filters("wpvivid_menu_capability","administrator","wpvivid-rollback");
            $menu_slug=apply_filters('wpvivid_white_label_slug', 'wpvivid').'-backup';
            $function=array($this, 'init_page');
            $icon_url='dashicons-cloud';
            $position=99.1;

            add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
        }
        else if(apply_filters('wpvivid_current_user_can',true,'wpvivid-can-use-plugins'))
        {
            $page_title=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');
            $menu_title=apply_filters('wpvivid_white_label_display', 'WPvivid Plugin');

            $capability = apply_filters("wpvivid_menu_capability","administrator","wpvivid-dashboard");
            $menu_slug =apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-dashboard');

            $function=array($this->dashboard, 'init_page');
            $icon_url='dashicons-cloud';
            $position=99.1;

            add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);

            //$submenus = apply_filters('wpvivid_get_admin_menus', array());
            $parent_slug=apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-dashboard');
            $submenus=apply_filters('wpvivid_get_dashboard_menu',array(),$parent_slug);

            usort($submenus, function ($a, $b)
            {
                if ($a['index'] == $b['index'])
                    return 0;

                if ($a['index'] > $b['index'])
                    return 1;
                else
                    return -1;
            });

            foreach ($submenus as $submenu)
            {
                add_submenu_page
                (
                    $submenu['parent_slug'],
                    $submenu['page_title'],
                    $submenu['menu_title'],
                    $submenu['capability'],
                    $submenu['menu_slug'],
                    $submenu['function']
                );
            }
        }

    }

    public function init_page()
    {
        do_action('wpvivid_before_setup_page');

        if(isset($_REQUEST['auto_backup'])&&$_REQUEST['auto_backup']==1)
        {
            return;
        }
        $slug = apply_filters('wpvivid_access_white_label_slug', 'wpvivid_white_label');
        if(isset($_REQUEST[$slug])&&$_REQUEST[$slug]==1)
        {
            return;
        }
    }

    public function get_admin_menus($submenus)
    {
        /*
        $parent_slug=apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-dashboard');
        $submenus=apply_filters('wpvivid_get_dashboard_menu',array(),$parent_slug);
        return $submenus;*/
        return array();
    }

    public function export_dashboard_info($json)
    {
        $dashboard_info = get_option('wpvivid_dashboard_info', array());
        $pro_user_info  = get_option('wpvivid_pro_user', false);

        $json['data']['wpvivid_dashboard_info'] = $dashboard_info;
        $json['data']['wpvivid_pro_user'] = $pro_user_info;
        return $json;
    }

    public function staging_get_main_admin_menus($menu)
    {
        return false;
    }

    public function get_staging_screens($screens)
    {
        $new_screens=array();

        $search = 'wpvividstg';
        $replace = 'wpvivid';
        foreach ($screens as $screen)
        {
            $label_slug = str_replace($search, $replace, $screen['menu_slug']);

            if($screen['is_top'])
            {
                $screen['screen_id']='wpvivid-plugin_page_'.$label_slug;
            }
            else
            {
                if(preg_match('/_page_.*/',$screen['screen_id'],$matches))
                {
                    $need_replace = $matches[0];
                    $label_prefix = str_replace($need_replace, '', $screen['screen_id']);
                    $screen['screen_id']=$label_prefix.'_page_'.$label_slug;
                }
            }
            $new_screens[]=$screen;
        }
        return $new_screens;
    }

    public function get_dashboard_menu($submenus,$parent_slug)
    {
        if ( ! function_exists( 'is_plugin_active' ) )
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if(!is_plugin_active('wpvivid-staging/wpvivid-staging.php'))
        {
            return $submenus;
        }


        if(!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $staging_slug='wpvivid-staging/wpvivid-staging.php';
        if (is_multisite())
        {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if(!empty($mu_active_plugins)){
                foreach ($mu_active_plugins as $plugin_name => $data){
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins=get_mu_plugins();
            if(count($plugins) == 0 || !isset($plugins[$staging_slug])){
                $plugins=get_plugins();
            }
        }
        else
        {
            $active_plugins = get_option('active_plugins');
            $plugins=get_plugins();
        }

        $use_new_staging_ui = false;
        if(!empty($plugins))
        {
            if(isset($plugins[$staging_slug]))
            {
                if(in_array($staging_slug, $active_plugins))
                {
                    if(version_compare($plugins[$staging_slug]['Version'], '2.0.12', '>'))
                    {
                        $use_new_staging_ui = true;
                    }
                }
            }
        }


        $display = apply_filters('wpvivid_get_menu_capability_addon', 'menu_staging');
        if($display)
        {
            $submenu['parent_slug'] = $parent_slug;
            $submenu['page_title'] = apply_filters('wpvivid_white_label_display', __('Staging sites', 'wpvivid-backup-pro'));
            $submenu['menu_title'] = __('Staging sites', 'wpvivid-backup-pro');
            $submenu['capability'] = 'administrator';
            if($use_new_staging_ui)
            {
                $submenu['menu_slug'] = strtolower(sprintf('%s-staging', apply_filters('wpvivid_white_label_slug', 'wpvivid')));
            }
            else
            {
                $submenu['menu_slug'] = strtolower(sprintf('%s-staging', apply_filters('wpvivid_white_label_slug', 'wpvividstg')));
            }
            $submenu['index'] = 9;
            $submenu['function'] = array($this, 'init_staging_page');
            $submenus[$submenu['menu_slug']] = $submenu;
        }
        return $submenus;
    }

    public function add_toolbar_items($wp_admin_bar)
    {
        if(apply_filters('wpvivid_current_user_can',true,'wpvivid-can-show-toolbar'))
        {

        }
        else
        {
            $wp_admin_bar->remove_menu('wpvivid_admin_menu');
        }
    }

    public function get_toolbar_menus($toolbar_menus)
    {
        if(isset($toolbar_menus['wpvivid_admin_menu']['child']['wpvivid_admin_menu_staging']) && !empty($toolbar_menus['wpvivid_admin_menu']['child']['wpvivid_admin_menu_staging'])){
            unset($toolbar_menus['wpvivid_admin_menu']['child']['wpvivid_admin_menu_staging']);
        }

        if ( ! function_exists( 'is_plugin_active' ) )
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if(!is_plugin_active('wpvivid-staging/wpvivid-staging.php'))
        {
            return $toolbar_menus;
        }


        if(!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $staging_slug='wpvivid-staging/wpvivid-staging.php';
        if (is_multisite())
        {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if(!empty($mu_active_plugins)){
                foreach ($mu_active_plugins as $plugin_name => $data){
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins=get_mu_plugins();
            if(count($plugins) == 0 || !isset($plugins[$staging_slug])){
                $plugins=get_plugins();
            }
        }
        else
        {
            $active_plugins = get_option('active_plugins');
            $plugins=get_plugins();
        }

        $use_new_staging_ui = false;
        if(!empty($plugins))
        {
            if(isset($plugins[$staging_slug]))
            {
                if(in_array($staging_slug, $active_plugins))
                {
                    if(version_compare($plugins[$staging_slug]['Version'], '2.0.12', '>'))
                    {
                        $use_new_staging_ui = true;
                    }
                }
            }
        }

        $display = apply_filters('wpvivid_get_menu_capability_addon', 'menu_staging');
        if($display)
        {
            $admin_url = apply_filters('wpvivid_get_admin_url', '');
            $menu['id'] = 'wpvivid_admin_menu_staging';
            $menu['parent'] = 'wpvivid_admin_menu';
            $menu['title'] = 'Staging sites';
            if($use_new_staging_ui)
            {
                $menu['tab'] = 'admin.php?page=' . apply_filters('wpvivid_white_label_plugin_name', 'wpvivid-staging');
                $menu['href'] = $admin_url . 'admin.php?page=' . apply_filters('wpvivid_white_label_plugin_name', 'wpvivid').'-staging';
            }
            else
            {
                $menu['tab'] = 'admin.php?page=' . apply_filters('wpvivid_white_label_plugin_name', 'wpvividstg-staging');
                $menu['href'] = $admin_url . 'admin.php?page=' . apply_filters('wpvivid_white_label_plugin_name', 'wpvividstg').'-staging';
            }
            $menu['capability'] = 'administrator';
            $menu['index'] = 9;
            $toolbar_menus[$menu['parent']]['child'][$menu['id']] = $menu;
        }
        return $toolbar_menus;
    }

    public function get_dashboard_screens($screens)
    {
        $screen['menu_slug']='wpvivid-staging';
        $screen['screen_id']='wpvivid-plugin_page_wpvivid-staging';
        $screen['is_top']=false;
        $screens[]=$screen;
        return $screens;
    }

    public function init_staging_page()
    {
        do_action('wpvivid_staging_create_page_display');
    }

    public function migrate_mu_plugins_exclude_compat()
    {
        $migrated = get_option('wpvivid_mu_plugins_exclude_compat_migrated', 0);
        if($migrated)
        {
            return;
        }

        $changed = false;
        $manual_backup_history = WPvivid_Pro_Option::get_manual_backup_history();
        $manual_backup_history = $this->migrate_mu_plugins_exclude_compat_data($manual_backup_history, $changed);
        if($changed)
        {
            WPvivid_Pro_Option::update_manual_backup_history($manual_backup_history);
        }

        $custom_backup_history = get_option('wpvivid_custom_backup_history', array());
        $custom_backup_changed = false;
        $custom_backup_history = $this->migrate_mu_plugins_exclude_compat_data($custom_backup_history, $custom_backup_changed);
        if($custom_backup_changed)
        {
            update_option('wpvivid_custom_backup_history', $custom_backup_history, 'no');
        }

        $incremental_backup_history = get_option('wpvivid_incremental_backup_history', array());
        $incremental_backup_changed = false;
        $incremental_backup_history = $this->migrate_mu_plugins_exclude_compat_data($incremental_backup_history, $incremental_backup_changed);
        if($incremental_backup_changed)
        {
            update_option('wpvivid_incremental_backup_history', $incremental_backup_history, 'no');
        }

        $incremental_schedules = get_option('wpvivid_incremental_schedules', array());
        $incremental_schedules_changed = false;
        $incremental_schedules = $this->migrate_mu_plugins_exclude_compat_data($incremental_schedules, $incremental_schedules_changed);
        if($incremental_schedules_changed)
        {
            update_option('wpvivid_incremental_schedules', $incremental_schedules, 'no');
        }

        $schedule_addon_setting = get_option('wpvivid_schedule_addon_setting', array());
        $schedule_addon_changed = false;
        $schedule_addon_setting = $this->migrate_mu_plugins_exclude_compat_data($schedule_addon_setting, $schedule_addon_changed);
        if($schedule_addon_changed)
        {
            update_option('wpvivid_schedule_addon_setting', $schedule_addon_setting, 'no');
        }

        update_option('wpvivid_mu_plugins_exclude_compat_migrated', 1, 'no');
    }

    private function migrate_mu_plugins_exclude_compat_data($data, &$changed)
    {
        if(!is_array($data) || empty($data))
        {
            return $data;
        }

        if(isset($data['exclude_files']) && is_array($data['exclude_files']))
        {
            $legacy_mu_plugins_path = untrailingslashit(str_replace('\\', '/', WP_CONTENT_DIR . '/mu-plugins'));
            $exclude_files = array();
            $legacy_mu_plugins_excluded = false;

            foreach ($data['exclude_files'] as $exclude_file)
            {
                if(isset($exclude_file['type']) && isset($exclude_file['path']) && $exclude_file['type'] === 'folder')
                {
                    $exclude_path = untrailingslashit(str_replace('\\', '/', $exclude_file['path']));
                    if($exclude_path === $legacy_mu_plugins_path)
                    {
                        $legacy_mu_plugins_excluded = true;
                        $changed = true;
                        continue;
                    }
                }

                $exclude_files[] = $exclude_file;
            }

            if($legacy_mu_plugins_excluded)
            {
                $data['exclude_files'] = array_values($exclude_files);

                if(isset($data['custom_dirs']) && is_array($data['custom_dirs']))
                {
                    if(!isset($data['custom_dirs']['mu_plugins_check']))
                    {
                        $data['custom_dirs']['mu_plugins_check'] = '0';
                    }
                }
            }
        }

        foreach ($data as $key => $value)
        {
            if(is_array($value))
            {
                $data[$key] = $this->migrate_mu_plugins_exclude_compat_data($value, $changed);
            }
        }

        return $data;
    }

    public function set_auto_update()
    {
        $default = false;
        $auto_update = get_option('wpvivid_auto_update_addon', $default);
        if(isset($auto_update) && $auto_update !== false)
        {
            if($auto_update == '1')
            {
                delete_option('wpvivid_auto_update_addon');
                update_option('wpvivid_dashboard_auto_update','on','no');
            }
        }
    }

    public function wpvivid_check_need_install_addon_mainwp()
    {
        $ret['need_update'] = true;
        $ret['list_addons'] = array();

        $dashboard_info=get_option('wpvivid_dashboard_info',array());
        if(empty($dashboard_info))
        {
            $ret['need_update'] = false;
        }

        $need_install_list = array();

        foreach ($dashboard_info['plugins'] as $slug=>$plugin)
        {
            if($this->addons_loader->is_plugin_install_available($plugin))
            {
                //check is installed
                $status=$this->addons_loader->get_plugin_status($plugin);
                if($status['status']=='Installed'&&$status['action']=='Up to date')
                {
                }
                else
                {
                    $need_install_list[] = $slug;
                }
            }
        }

        if(empty($need_install_list))
        {
            $ret['need_update'] = false;
            $ret['list_addons'] = array();
        }
        else{
            $ret['need_update'] = true;
            $ret['list_addons'] = $need_install_list;
        }
        return $ret;
    }

    public function add_action_links( $links )
    {
        if(!is_multisite())
        {
            if(apply_filters('wpvivid_show_dashboard_addons',true))
            {
                $settings_link = array(
                    '<a href="' . admin_url('admin.php?page='. strtolower(sprintf('%s-dashboard', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('Dashboard', 'wpvivid-backup-pro') . '</a>',
                    '<a href="' . admin_url('admin.php?page='. strtolower(sprintf('%s-license', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('License', 'wpvivid-backup-pro') . '</a>'
                );
            }
            else
            {
                $settings_link = array(
                    '<a href="' . admin_url('admin.php?page='. strtolower(sprintf('%s-dashboard', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('Dashboard', 'wpvivid-backup-pro') . '</a>'
                );
            }
        }
        else
        {
            if(apply_filters('wpvivid_show_dashboard_addons',true))
            {
                $settings_link = array(
                    '<a href="' . network_admin_url('admin.php?page='. strtolower(sprintf('%s-dashboard', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('Dashboard', 'wpvivid-backup-pro') . '</a>',
                    '<a href="' . network_admin_url('admin.php?page='. strtolower(sprintf('%s-license', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('License', 'wpvivid-backup-pro') . '</a>'
                );
            }
            else
            {
                $settings_link = array(
                    '<a href="' . network_admin_url('admin.php?page='. strtolower(sprintf('%s-dashboard', apply_filters('wpvivid_white_label_slug', 'wpvivid')))).'">' . __('Dashboard', 'wpvivid-backup-pro') . '</a>'
                );
            }
        }

        return array_merge(  $settings_link, $links );
    }

    public function filter_plugin_row_meta( array $plugin_meta, $plugin_file )
    {
        if(apply_filters('wpvivid_show_dashboard_addons',true))
        {
            if ( 'wpvivid-backup-pro/wpvivid-backup-pro.php' !== $plugin_file ) {
                return $plugin_meta;
            }
            $plugin_meta[] = sprintf(
                '<a href="https://wpvivid.com/wpvivid-backup-pro-changelog">Revision</a>'
            );
        }
        else
        {
            if ( 'wpvivid-backuprestore/wpvivid-backuprestore.php' !== $plugin_file && 'wpvivid-backup-pro/wpvivid-backup-pro.php' !== $plugin_file &&
                 'wpvivid-imgoptim/wpvivid-imgoptim.php' !== $plugin_file && 'wpvivid-staging/wpvivid-staging.php' !== $plugin_file &&
                 'wpvivid-snapshot-database/wpvivid-snapshot-database.php' !== $plugin_file) {
                return $plugin_meta;
            }

            if('wpvivid-staging/wpvivid-staging.php' === $plugin_file)
            {
                foreach ($plugin_meta as $index=>$meta)
                {
                    if(preg_match('/wpvivid-staging-changelog/',$meta,$matches))
                    {
                        unset($plugin_meta[$index]);
                        break;
                    }
                }
            }
        }

        foreach ($plugin_meta as $index=>$meta)
        {
            if(preg_match('/open-plugin-details-modal/',$meta,$matches))
            {
                unset($plugin_meta[$index]);
                break;
            }
        }

        return $plugin_meta;
    }

    public function need_update()
    {
        $dashboard_info=get_option('wpvivid_dashboard_info',array());
        if(empty($dashboard_info))
        {
            return false;
        }

        if(isset($dashboard_info['dashboard']))
        {
            if(version_compare(WPVIVID_BACKUP_PRO_VERSION,$dashboard_info['dashboard']['version'], '<'))
            {
                return true;
            }

            foreach ($dashboard_info['plugins'] as $slug=>$info)
            {
                $version=$this->addons_loader->get_plugin_version($info);

                if($version==='NULL')
                {
                    continue;
                }

                $latest_version=$this->addons_loader->get_plugin_latest_version($info);

                if(version_compare($latest_version,$version, '>'))
                {
                    return true;
                }
            }
        }

        return false;
    }

    public function handle_server_error($error)
    {
        if(isset($error['error_code']))
        {
            if($error['error_code']==109||$error['error_code']==108||$error['error_code']==107)
            {
                delete_option('wpvivid_pro_user');
                delete_option('wpvivid_dashboard_info');
            }
        }
    }

    public function get_dashboard_menu_slug($menu_slug)
    {
        $menu_slug=apply_filters('wpvivid_white_label_slug', 'wpvivid').'-dashboard';
        return $menu_slug;
    }

    public function get_admin_url($admin_url)
    {
        if(is_multisite())
        {
            $admin_url = network_admin_url();
        }
        else
        {
            $admin_url =admin_url();
        }

        return $admin_url;
    }

    public function get_screen_ids($screen_ids)
    {
        $screen_ids=array();
        if(isset($_REQUEST['auto_backup'])&&$_REQUEST['auto_backup']==1)
        {
            $screen['menu_slug']=apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-backup');
            $screen['screen_id']='toplevel_page_wpvivid-backup';
            $screen['is_top']=true;
        }
        else
        {
            $screen['menu_slug']=apply_filters('wpvivid_get_dashboard_menu_slug','wpvivid-dashboard');
            $screen['screen_id']='toplevel_page_wpvivid-dashboard';
            $screen['is_top']=true;
        }

        $screens[]=$screen;
        $screens=apply_filters('wpvivid_get_dashboard_screens',$screens);

        foreach ($screens as $screen)
        {
            $screen_ids[]=$screen['screen_id'];
            if(is_multisite())
            {
                if(substr($screen['screen_id'],-8)=='-network')
                    continue;
                $screen_ids[]=$screen['screen_id'].'-network';
            }
            else
            {
                $screen_ids[]=$screen['screen_id'];
            }
        }
        return $screen_ids;
    }

    public function enqueue_styles()
    {
        $screen_ids=array();
        $screen_ids=apply_filters('wpvivid_get_screen_ids',$screen_ids);
        if(in_array(get_current_screen()->id,$screen_ids))
        {
            wp_enqueue_style(WPVIVID_PRO_PLUGIN_SLUG.'jstree', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/jstree/dist/themes/default/style.min.css', array(), WPVIVID_BACKUP_PRO_VERSION, 'all');
            wp_enqueue_style(WPVIVID_PRO_PLUGIN_SLUG.'dashboard', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/css/wpvivid-dashboard-style-v2.css', array(), WPVIVID_BACKUP_PRO_VERSION, 'all');
            wp_enqueue_style(WPVIVID_PRO_PLUGIN_SLUG.'staging', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/css/wpvivid-staging-custom.css', array(), WPVIVID_BACKUP_PRO_VERSION, 'all');

            if (preg_match('/wpvivid-rollback/', get_current_screen()->id) ||
                preg_match('/'.strtolower(sprintf('%s-rollback', apply_filters('wpvivid_white_label_slug', 'wpvivid'))).'/', get_current_screen()->id))
            {
                wp_enqueue_style(WPVIVID_PRO_PLUGIN_SLUG.'rollback', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/css/wpvivid-rollback-custom.css', array(), WPVIVID_BACKUP_PRO_VERSION, 'all');
            }
        }
        else if (get_current_screen()->id=="plugins"||get_current_screen()->id=="themes")
        {
            wp_enqueue_style(WPVIVID_PRO_PLUGIN_SLUG.'dashboard', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/css/wpvivid-dashboard-style-v2.css', array(), WPVIVID_BACKUP_PRO_VERSION, 'all');
        }
    }

    public function get_localize_script_data()
    {
        return array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('wpvivid_ajax'),
            'i18n'       => array(
                'unknown_error'                    => __('An unknown error occurred. Please try again.', 'wpvivid-backup-pro'),
                'failed_to_post_remote_storage_id' => __('Failed to post remote storage id. Please try again.', 'wpvivid-backup-pro'),
                'failed_to_post_remote_storage_folder' => __('Failed to post remote storage folder. Please try again.', 'wpvivid-backup-pro'),
                'confirm_remove_backup'           => __('Are you sure to remove this backup? This backup will be deleted permanently.', 'wpvivid-backup-pro'),
                'confirm_remove_selected_backups'  => __('Are you sure to remove the selected backups? These backups will be deleted permanently.', 'wpvivid-backup-pro'),
                'select_at_least_one_item'         => __('Please select at least one item.', 'wpvivid-backup-pro'),
                'download_canceled'                 => __('Download canceled.', 'wpvivid-backup-pro'),
                'bulk_download_not_supported'       => __('We have detected that your browser does not support bulk downloading of files, please download the backup files one by one.', 'wpvivid-backup-pro'),
                'backup_already_exists_target_folder' => __('The backup already exists in target folder.', 'wpvivid-backup-pro'),
                'error'                             => __('error', 'wpvivid-backup-pro'),
                'send_succeeded'                    => __('Send succeeded.', 'wpvivid-backup-pro'),
                'alias_already_exists'              => __('Warning: The alias already exists in storage list.', 'wpvivid-backup-pro'),
                'import_completed_successfully'     => __('Import completed successfully.', 'wpvivid-backup-pro'),
                'import_failed'                     => __('Import failed.', 'wpvivid-backup-pro'),
                'replace_completed_successfully'    => __('Replace completed successfully.', 'wpvivid-backup-pro'),
                'replace_failed'                    => __('Replace failed.', 'wpvivid-backup-pro'),
                'rollback_plugin_completed_successfully' => __('Rolling back the plugin completed successfully.', 'wpvivid-backup-pro'),
                'rollback_theme_completed_successfully' => __('Rolling back the theme completed successfully.', 'wpvivid-backup-pro'),
                'rollback_completed_successfully'   => __('Rollback has completed successfully.', 'wpvivid-backup-pro'),
                'out_of_date_backups_removed'       => __('Out of date backups have been removed.', 'wpvivid-backup-pro'),
                'optimization_completed_successfully' => __('The optimization completed successfully.', 'wpvivid-backup-pro'),
                'wpvivid_reserved_backup_comment' => __('You can not use word \'wpvivid\' to comment the backup.', 'wpvivid-backup-pro'),
                'select_additional_files_option_required' => __('Please select at least one item under the additional files/folder option, or deselect the option.', 'wpvivid-backup-pro'),
                'select_additional_database_option_required' => __('Please select at least one item under the additional database option, or deselect the option.', 'wpvivid-backup-pro'),
                'select_wp_database_table_required' => __('Please select at least one table to back up. Or, deselect the option \'Tables In The WordPress Database\' under the option \'Databases Will Be Backed up\'.', 'wpvivid-backup-pro'),
                'please_select_at_least_one_additional_database_to_back_up' => __('Please select at least one additional database to back up. Or, deselect the option \'Include Additional Databases\' under the option \'Databases Will Be Backed up\'.', 'wpvivid-backup-pro'),
                'select_database_backup_option_required' => __('Please select at least one option from \'Tables In The WordPress Database\' and \'Additional Databases\' under the option \'Databases Will Be Backed up\'. Or, deselect the option \'Databases Will Be Backed up\'.', 'wpvivid-backup-pro'),
                'select_additional_file_or_folder_required' => __('Please select at least one additional file or folder under the option \'Files/Folders Will Be Backed up\', Or, deselect the option \'Non-WordPress Files/Folders\'.', 'wpvivid-backup-pro'),
                'select_files_backup_option_required' => __('Please select at least one option under the option \'Files/Folders Will Be Backed up\'. Or, deselect the option \'Files/Folders Will Be Backed up\'.', 'wpvivid-backup-pro'),
                'all_files_of_the_backup_have_been_downloaded_successfully' => __('All files of the backup have been downloaded successfully.', 'wpvivid-backup-pro'),
                'the_upload_has_completed' => __('The upload has completed.', 'wpvivid-backup-pro'),
                'test_encryption_before_adding_remote' => __('Please test the encryption successfully before adding this remote storage.', 'wpvivid-backup-pro'),
                'only_enter_numbers_from_1_999' => __('Only enter numbers from 1-999', 'wpvivid-backup-pro'),
                'login_failed_check_credentials' => __('Login Failed. Please check the credentials you entered and try again.', 'wpvivid-backup-pro'),
                'host_is_required' => __('Host is required.', 'wpvivid-backup-pro'),
                'user_name_is_required' => __('User Name is required.', 'wpvivid-backup-pro'),
                'warning_an_alias_for_remote_storage_is_required' => __('Warning: An alias for remote storage is required.', 'wpvivid-backup-pro'),
                'the_backup_folder_name_cannot_be_empty' => __('The backup folder name cannot be empty.', 'wpvivid-backup-pro'),
                'the_backup_folder_name_cannot_be' => __('The backup folder name cannot be \'/\'.', 'wpvivid-backup-pro'),
                'backup_retention_policy_required' => __('Warning: You have not set the backup retention policy for this storage. Please set the policy or uncheck the option.', 'wpvivid-backup-pro'),
                'wpvivid_reserved_post_comment' => __('You can not use word \'wpvivid\' to comment the post.', 'wpvivid-backup-pro'),
                'please_select_an_existing_author_to_start_importing' => __('Please select an existing author to start importing.', 'wpvivid-backup-pro'),
                'please_enter_the_url_you_want_to_search_for' => __('Please enter the url you want to search for.', 'wpvivid-backup-pro'),
                'please_enter_the_url_you_want_to_replace_with' => __('Please enter the url you want to replace with.', 'wpvivid-backup-pro'),
                'please_set_a_value_for_replace_execution_time' => __('Please set a value for replace execution time.', 'wpvivid-backup-pro'),
                'please_select_the_tables_you_want_to_search' => __('Please select the tables you want to search.', 'wpvivid-backup-pro'),
                'key_generated_by_this_site_cannot_be_added' => __('The key generated by this site cannot be added into this site.', 'wpvivid-backup-pro'),
                'the_ftp_password_is_required' => __('The FTP password is required.', 'wpvivid-backup-pro'),
                'please_enter_the_ftp_password_before_testing_encryption' => __('Please enter the FTP password before testing encryption.', 'wpvivid-backup-pro'),
                'warning_please_enter_an_upload_block_size' => __('Warning: Please enter an upload block size.', 'wpvivid-backup-pro'),
                'warning_please_enter_a_download_block_size' => __('Warning: Please enter a download block size.', 'wpvivid-backup-pro'),
                'only_a_number_from_1_30_is_allowed' => __('Only a number from 1-30 is allowed.', 'wpvivid-backup-pro'),
                'failed_to_generating_key' => __('Failed to generating key.', 'wpvivid-backup-pro'),
                'incremental_schedules_same_start_time' => __('You have set the same start time for the files incremental backup schedule and the database backup schedule. When there is a conflict of starting times for schedule tasks, only one task will be executed properly. Please make sure that the times are different.', 'wpvivid-backup-pro'),
                'please_choose_a_restore_point' => __('Please choose a restore point', 'wpvivid-backup-pro'),
                'backup_failed' => __('Backup failed', 'wpvivid-backup-pro'),
                'rollback_sync_has_completed_successfully' => __('Rollback sync has completed successfully.', 'wpvivid-backup-pro'),
                'please_select_at_least_one_item_to_perform_this_action' => __('Please select at least one item to perform this action on.', 'wpvivid-backup-pro'),
                'success_path_is_valid_and_accessible' => __('Success: Path is valid and accessible!', 'wpvivid-backup-pro'),
                'verify_directory_accessibility_test_path' => __('Please verify the directory accessibility by clicking the \'Test Path\' button. Untested paths may lead to backup failures.', 'wpvivid-backup-pro'),
                'ensure_directory_accessibility_test_path' => __('Please ensure the directory accessibility by clicking the \'Test Path\' button. Untested paths may lead to backup failures.', 'wpvivid-backup-pro'),
                'please_enter_a_password_for_database_encryption' => __('Please enter a password for database encryption.', 'wpvivid-backup-pro'),
                'files_per_zip_invalid_range' => __('\'The number of files compressed to the backup zip each time\' should be 10 - 10000.', 'wpvivid-backup-pro'),
                'db_tables_per_zip_invalid_range' => __('\'The number of database tables compressed to each zip\' should be larger than 100.', 'wpvivid-backup-pro'),
                'email_alreay_in_list' => __('Email alreay in list.', 'wpvivid-backup-pro'),
                'mail_is_required' => __('Mail is required.', 'wpvivid-backup-pro'),
                'choose_settings_file_to_import' => __('Choose a settings file and import it by clicking Import button.', 'wpvivid-backup-pro'),
                'the_plugin_settings_were_imported_successfully' => __('The plugin settings were imported successfully.', 'wpvivid-backup-pro'),
                'no_items_found' => __('No items found.', 'wpvivid-backup-pro'),
                'image_s_restored_successfully' => __('Image(s) restored successfully.', 'wpvivid-backup-pro'),
                'http_error' => __('http error: ', 'wpvivid-backup-pro'),
                'download_failed' => __('Download failed: ', 'wpvivid-backup-pro'),
                'delete_imported_files_in_folder' => __('Delete Imported Files in the Folder (%s)', 'wpvivid-backup-pro'),
                'download' => __('Download', 'wpvivid-backup-pro'),
                'next_step' => __('Next Step', 'wpvivid-backup-pro'),
                'return_to_import_page' => __('Return to import page', 'wpvivid-backup-pro'),
                'database_replace_times_out' => __('Database replace times out.', 'wpvivid-backup-pro'),
                'failed_to_scan_backup_zips' => __('Failed to scan %s backup zips, the zips can be corrupted during creation or download process. Please check the zips.', 'wpvivid-backup-pro'),
                'upload_file_error' => __('Upload %1$s error, error code: %2$s, %3$s', 'wpvivid-backup-pro'),
                'upload_file_failed' => __('upload %1$s failed, %2$s', 'wpvivid-backup-pro'),
                'error_with_message' => __('Error: ', 'wpvivid-backup-pro'),
                'delete_webp_images_success' => __('Delete webp images success', 'wpvivid-backup-pro'),
                'are_you_sure_you_want_to_cancel_optimization' => __('Are you sure you want to cancel optimization?', 'wpvivid-backup-pro'),
                'delete_images_backup_successfully' => __('Delete images backup successfully', 'wpvivid-backup-pro'),
                'role_capabilities_have_been_changed_successfully' => __('Role capabilities have been changed successfully.', 'wpvivid-backup-pro'),
                'user_capabilities_have_been_changed_successfully' => __('User capabilities have been changed successfully.', 'wpvivid-backup-pro'),
            ),
        );
    }

    public function enqueue_scripts()
    {
        $screen_ids=array();
        $screen_ids=apply_filters('wpvivid_get_screen_ids',$screen_ids);
        if(in_array(get_current_screen()->id,$screen_ids))
        {
            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jqdownload', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/jquery.binarytransport.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jqmd5', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/spark-md5.min.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jstree', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/jstree/dist/jstree.min.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/wpvivid-admin-addon.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_localize_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', 'wpvivid_ajax_object_addon', $this->get_localize_script_data());
        }
        else if (get_current_screen()->id=="plugins"||get_current_screen()->id=="themes")
        {
            wp_enqueue_style ( 'wp-jquery-ui-dialog' );
            wp_enqueue_script('jquery-ui-dialog');

            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/wpvivid-admin-addon.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_localize_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', 'wpvivid_ajax_object_addon', $this->get_localize_script_data());
        }
        else if (get_current_screen()->id === 'update-core')
        {
            wp_enqueue_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/js/wpvivid-admin-addon.js', array('jquery'), WPVIVID_BACKUP_PRO_VERSION, false);
            wp_localize_script(WPVIVID_PRO_PLUGIN_SLUG.'jsaddon', 'wpvivid_ajax_object_addon', $this->get_localize_script_data());
        }
    }

    public function load_dependencies()
    {
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-pro-option.php';
        if (class_exists('WPvivid_Pro_Option'))
        {
            WPvivid_Pro_Option::maybe_upgrade();
        }

        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/addon/class-wpvivid-addon-loader.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-crypt-addon.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-connect-server.php';

        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-custom-interface-addon.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-backup-pro-function.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-remote-addon.php';

        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-updater.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-mainwp.php';
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-interface-mainwp-addon.php';

        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-time.php';

        $this->mainwp=new WPvivid_MainWP_Function();

        $this->updater=new WPvivid_Updater();
        $this->func=new WPvivid_backup_pro_function();
        $this->interface_mainwp=new WPvivid_Interface_MainWP_addon();
    }

    public function get_network_toolbar_menus($toolbar_menus)
    {
        if(is_multisite())
        {
            $new_toolbar_menus=array();
            $admin_url = network_admin_url();
            foreach ($toolbar_menus as $menu)
            {
                if(isset($menu['child']))
                {
                    foreach ($menu['child'] as $child_menu)
                    {
                        $child_menu['href']=$admin_url. $child_menu['tab'];
                        $menu['child'][$child_menu['id']]=$child_menu;
                    }
                }
                $new_toolbar_menus[$menu['id']]=$menu;
            }
            return $new_toolbar_menus;
        }
        else
        {
            return $toolbar_menus;
        }
    }

    public static function get_network_admin_url($admin_url)
    {
        if (is_multisite())
        {
            return network_admin_url();
        }
        else
        {
            return admin_url();
        }
    }

    public function check_wpvivid_free_version()
    {
        if (is_multisite())
        {
            if(!is_network_admin())
            {
                return ;
            }
        }

        if(!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $free_wpvivid_slug='wpvivid-backuprestore/wpvivid-backuprestore.php';
        if (is_multisite())
        {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if(!empty($mu_active_plugins)){
                foreach ($mu_active_plugins as $plugin_name => $data){
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins=get_mu_plugins();
            if(count($plugins) == 0 || !isset($plugins[$free_wpvivid_slug])){
                $plugins=get_plugins();
            }
        }
        else
        {
            $active_plugins = get_option('active_plugins');
            $plugins=get_plugins();
        }

        if(!empty($plugins))
        {
            if(isset($plugins[$free_wpvivid_slug]))
            {
                if(in_array($free_wpvivid_slug, $active_plugins))
                {
                    if(version_compare('0.9.59',$plugins[$free_wpvivid_slug]['Version'],'>'))
                    {
                        ?>
                        <div class="notice notice-warning" style="padding: 11px 15px;">
                            <?php echo sprintf(__('We detected that you are using a lower version of %s Free, please update it to 0.9.59 or higher to ensure backing up to Google Drive works properly.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup Plugin')); ?> <a href="<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'plugins.php'); ?>"><?php esc_html_e('Update now', 'wpvivid-backup-pro'); ?></a>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    public function check_wpvivid_free_version_ex($warnings)
    {
        if (is_multisite())
        {
            if(!is_network_admin())
            {
                return $warnings;
            }
        }

        if(!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $free_wpvivid_slug='wpvivid-backuprestore/wpvivid-backuprestore.php';
        if (is_multisite())
        {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if(!empty($mu_active_plugins)){
                foreach ($mu_active_plugins as $plugin_name => $data){
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins=get_mu_plugins();
            if(count($plugins) == 0 || !isset($plugins[$free_wpvivid_slug])){
                $plugins=get_plugins();
            }
        }
        else
        {
            $active_plugins = get_option('active_plugins');
            $plugins=get_plugins();
        }

        if(!empty($plugins))
        {
            if(isset($plugins[$free_wpvivid_slug]))
            {
                if(in_array($free_wpvivid_slug, $active_plugins))
                {
                    if(version_compare('0.9.59',$plugins[$free_wpvivid_slug]['Version'],'>'))
                    {
                        $update_url = apply_filters( 'wpvivid_get_admin_url', '' ) . 'plugins.php';

                        $message = sprintf(
                            __( 'We detected that you are using a lower version of %s Free, please update it to 0.9.59 or higher to ensure backing up to Google Drive works properly.', 'wpvivid-backup-pro' ),
                            apply_filters( 'wpvivid_white_label_display', 'WPvivid Backup Plugin' )
                        );
                        $message .= ' <a href="' . esc_url( $update_url ) . '">' . esc_html__( 'Update now', 'wpvivid-backup-pro' ) . '</a>';

                        $warnings[] = array(
                            'type'       => 'warning',
                            'code'       => 'free_version_too_low',
                            'message'    => $message,
                            'allow_html' => true,
                        );
                    }
                }
            }
        }

        return $warnings;
    }

    public function check_dropbox()
    {
        $remoteslist=get_option('wpvivid_upload_setting');
        $options=get_option('wpvivid_user_history');
        if(isset($options) && !empty($options))
        {
            if(array_key_exists('remote_selected', $options))
            {
                $remoteslist['remote_selected'] = $options['remote_selected'];
            }
            else
            {
                $remoteslist['remote_selected'] = array();
            }
        }
        $need_dropbox_notice = false;
        if(isset($remoteslist) && !empty($remoteslist))
        {
            foreach ($remoteslist as $remote_id => $value)
            {
                if($remote_id === 'remote_selected')
                {
                    continue;
                }
                if($value['type'] == 'dropbox' && !isset($value['refresh_token']))
                {
                    $need_dropbox_notice = true;
                }
            }
        }
        if($need_dropbox_notice)
        {
            $notice_message = __('Because Dropbox has upgraded their API on September 30, 2021, the new API is no longer compatible with the previous app\'s settings. Please re-add your Dropbox storage to ensure that it works properly.', 'wpvivid-backup-pro');
            echo '<div class="notice notice-warning">
                        <p>' . $notice_message . '</p>
                   </div>';
        }
    }

    public function check_schedule_remote_storage()
    {
        if(current_user_can('administrator'))
        {
            $remoteslist = get_option('wpvivid_upload_setting', array());
            $options=get_option('wpvivid_user_history', array());
            if(array_key_exists('remote_selected', $options))
            {
                $remoteslist['remote_selected'] = $options['remote_selected'];
            }
            else
            {
                $remoteslist['remote_selected'] = array();
            }
            $enable_schedules_backups=apply_filters('wpvivid_get_general_schedule_status',false);
            if($enable_schedules_backups)
            {
                $is_displayed_remote_check = false;
                $is_displayed_schedule_check = false;
                $schedules = get_option('wpvivid_schedule_addon_setting', array());
                foreach ($schedules as $schedule)
                {
                    if(isset($schedule['status']) && $schedule['status'] === 'Active')
                    {
                        if(isset($schedule['backup']['remote']) && $schedule['backup']['remote']==1)
                        {
                            if(isset($schedule['backup']['remote_options']))
                            {
                                $find_remote_storage = false;
                                $remote_array = array_keys($schedule['backup']['remote_options']);
                                $remote_id = array_shift($remote_array);
                                foreach ($remoteslist as $key => $value)
                                {
                                    if($key === 'remote_selected')
                                    {
                                        continue;
                                    }
                                    else {
                                        if($key === $remote_id)
                                        {
                                            $find_remote_storage = true;
                                        }
                                    }
                                }
                                if(!$find_remote_storage && !$is_displayed_schedule_check)
                                {
                                    $is_displayed_schedule_check = true;
                                    echo '<div class="notice notice-warning">
                                        <p>The cloud storage you had specified for the schedule no longer exists. Please specify a new storage. <a href="'.apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule').'">specify a new storage</a></p>
                                       </div>';
                                }
                            }
                            else
                            {
                                $check_remote_storage = false;
                                foreach ($remoteslist as $key => $value)
                                {
                                    if($key === 'remote_selected')
                                    {
                                        if(!empty($value))
                                        {
                                            $check_remote_storage = true;
                                        }
                                    }
                                }
                                if(!$check_remote_storage && !$is_displayed_remote_check)
                                {
                                    $is_displayed_remote_check = true;
                                    echo '<div class="notice notice-warning">
                                        <p>There is unchecked storage in the cloud storage list, click here to check it(them) if you need. <a href="'.apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-remote', 'wpvivid-remote').'">click here</a></p>
                                       </div>';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function check_schedule_remote_storage_ex($warnings)
    {
        if(current_user_can('administrator'))
        {
            $remoteslist = get_option('wpvivid_upload_setting', array());
            $options=get_option('wpvivid_user_history', array());
            if(array_key_exists('remote_selected', $options))
            {
                $remoteslist['remote_selected'] = $options['remote_selected'];
            }
            else
            {
                $remoteslist['remote_selected'] = array();
            }
            $enable_schedules_backups=apply_filters('wpvivid_get_general_schedule_status',false);
            if($enable_schedules_backups)
            {
                $is_displayed_remote_check = false;
                $is_displayed_schedule_check = false;
                $schedules = get_option('wpvivid_schedule_addon_setting', array());
                foreach ($schedules as $schedule)
                {
                    if(isset($schedule['status']) && $schedule['status'] === 'Active')
                    {
                        if(isset($schedule['backup']['remote']) && $schedule['backup']['remote']==1)
                        {
                            if(isset($schedule['backup']['remote_options']))
                            {
                                $find_remote_storage = false;
                                $remote_array = array_keys($schedule['backup']['remote_options']);
                                $remote_id = array_shift($remote_array);
                                foreach ($remoteslist as $key => $value)
                                {
                                    if($key === 'remote_selected')
                                    {
                                        continue;
                                    }
                                    else {
                                        if($key === $remote_id)
                                        {
                                            $find_remote_storage = true;
                                        }
                                    }
                                }
                                if(!$find_remote_storage && !$is_displayed_schedule_check)
                                {
                                    $is_displayed_schedule_check = true;

                                    $link    = apply_filters( 'wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule' );
                                    $message = __( 'The cloud storage you had specified for the schedule no longer exists. Please specify a new storage.', 'wpvivid-backup-pro' );
                                    $message .= ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'specify a new storage', 'wpvivid-backup-pro' ) . '</a>';

                                    $warnings[] = array(
                                        'type'       => 'warning',
                                        'code'       => 'schedule_remote_missing_storage',
                                        'message'    => $message,
                                        'allow_html' => true,
                                    );
                                }
                            }
                            else
                            {
                                $check_remote_storage = false;
                                foreach ($remoteslist as $key => $value)
                                {
                                    if($key === 'remote_selected')
                                    {
                                        if(!empty($value))
                                        {
                                            $check_remote_storage = true;
                                        }
                                    }
                                }
                                if(!$check_remote_storage && !$is_displayed_remote_check)
                                {
                                    $is_displayed_remote_check = true;

                                    $link    = apply_filters( 'wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-remote', 'wpvivid-remote' );
                                    $message = __( 'There is unchecked storage in the cloud storage list, click here to check it(them) if you need.', 'wpvivid-backup-pro' );
                                    $message .= ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'click here', 'wpvivid-backup-pro' ) . '</a>';

                                    $warnings[] = array(
                                        'type'       => 'warning',
                                        'code'       => 'remote_storage_unchecked',
                                        'message'    => $message,
                                        'allow_html' => true,
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        return $warnings;
    }

    public function check_remote_storage_values()
    {
        if(is_multisite())
        {
            if(!is_main_site())
            {
                return;
            }
        }
        $remoteslist=get_option('wpvivid_upload_setting');
        if(!empty($remoteslist))
        {
            foreach ($remoteslist as $key=>$value)
            {
                if($key === 'remote_selected')
                {
                    continue;
                }
                $remote_type = '';
                if($value['type'] === 'googledrive')
                {
                    $remote_type = 'Google Drive';
                    if(!isset($value['root_path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['root_path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['root_path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                    if(!isset($value['path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                }
                else if($value['type'] === 'dropbox')
                {
                    $remote_type = 'Dropbox';
                    if(!isset($value['path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                }
                else if($value['type'] === 'pCloud')
                {
                    $remote_type = 'pCloud';
                    if(!isset($value['root_path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['root_path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['root_path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                    if(!isset($value['path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                }
                else if($value['type'] === 'onedrive')
                {
                    $remote_type = 'OneDrive';

                    if(!isset($value['root_path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['root_path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['root_path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                    if(!isset($value['path']))
                    {
                        $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                        echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                    }
                    else
                    {
                        if($value['path'] == '')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be empty. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                        if($value['path'] == '/')
                        {
                            $notice_message = sprintf(__('WPvivid backup folder name in %s cannot be \'/\'. Please edit the storage and update the folder name.', 'wpvivid-backup-pro'), $remote_type);
                            echo '<div class="notice notice-warning">
                              <p>' . $notice_message . '</p>
                           </div>';
                        }
                    }
                }
            }
        }
    }

    public function add_remote_folder_warning( $warnings, $remote_type, $text ) {
        $notice_message = __( 'WPvivid backup folder name in ' . $remote_type . ' ' . $text );
        $warnings[]     = array(
            'type'       => 'warning',
            'code'       => 'remote_folder_invalid_' . sanitize_key( strtolower( $remote_type ) ),
            'message'    => $notice_message,
            'allow_html' => false,
        );

        return $warnings;
    }

    public function check_remote_storage_values_ex($warnings)
    {
        if(is_multisite())
        {
            if(!is_main_site())
            {
                return $warnings;
            }
        }
        $remoteslist=get_option('wpvivid_upload_setting');
        if(!empty($remoteslist))
        {
            foreach ($remoteslist as $key=>$value)
            {
                if($key === 'remote_selected')
                {
                    continue;
                }
                $remote_type = '';
                if($value['type'] === 'googledrive')
                {
                    $remote_type = 'Google Drive';

                    // root_path
                    if ( ! isset( $value['root_path'] ) || $value['root_path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['root_path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }

                    // path
                    if ( ! isset( $value['path'] ) || $value['path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }
                }
                else if($value['type'] === 'dropbox')
                {
                    $remote_type = 'Dropbox';

                    if ( ! isset( $value['path'] ) || $value['path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }
                }
                else if($value['type'] === 'pCloud')
                {
                    $remote_type = 'pCloud';

                    if ( ! isset( $value['root_path'] ) || $value['root_path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['root_path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }

                    if ( ! isset( $value['path'] ) || $value['path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }
                }
                else if($value['type'] === 'onedrive')
                {
                    $remote_type = 'OneDrive';

                    if ( ! isset( $value['root_path'] ) || $value['root_path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['root_path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }

                    if ( ! isset( $value['path'] ) || $value['path'] === '' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be empty. Please edit the storage and update the folder name.' );
                    } elseif ( $value['path'] === '/' ) {
                        $warnings = $this->add_remote_folder_warning( $warnings, $remote_type, 'cannot be \'/\'. Please edit the storage and update the folder name.' );
                    }
                }
            }
        }

        return $warnings;
    }

    public function check_backup_free_install_active()
    {
        $plugin_slug = 'wpvivid-backuprestore/wpvivid-backuprestore.php';
        $is_plugin_enabled = apply_filters('wpvivid_is_plugin_enabled', true, $plugin_slug);
        if ( $is_plugin_enabled )
        {
            return;
        }

        if ( !file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) )
        {
            $install_url = is_multisite()
                ? wp_nonce_url(
                    network_admin_url('update.php?action=install-plugin&plugin=wpvivid-backuprestore'),
                    'install-plugin_wpvivid-backuprestore'
                )
                : wp_nonce_url(
                    self_admin_url('update.php?action=install-plugin&plugin=wpvivid-backuprestore'),
                    'install-plugin_wpvivid-backuprestore'
                );

            echo '<div class="notice notice-error">
                              <p>We detect that <a><strong> WPvivid free plugin </strong></a> is not installed or active on your website . WPvivid pro requires it to function. <a href="'.esc_url( $install_url ).'"><strong> Click here </strong></a> to install and activate it to continue</p>
                           </div>';
        }
        else
        {
            if ( is_multisite() )
            {
                $activate_url = wp_nonce_url(
                    network_admin_url(
                        'plugins.php?action=activate&plugin=' . urlencode( $plugin_slug ) . '&networkwide=1'
                    ),
                    'activate-plugin_' . $plugin_slug
                );
            }
            else
            {
                $activate_url = wp_nonce_url(
                    admin_url(
                        'plugins.php?action=activate&plugin=' . urlencode( $plugin_slug )
                    ),
                    'activate-plugin_' . $plugin_slug
                );
            }

            echo '<div class="notice notice-error">
                              <p>We detect that <a><strong> WPvivid free plugin </strong></a> is not installed or active on your website . WPvivid pro requires it to function. <a href="'.esc_url( $activate_url ).'"><strong> Click here </strong></a> to install and activate it to continue</p>
                           </div>';
        }
    }

    public function check_backup_free_install_active_ex($warnings)
    {
        $plugin_slug = 'wpvivid-backuprestore/wpvivid-backuprestore.php';
        $is_plugin_enabled = apply_filters('wpvivid_is_plugin_enabled', true, $plugin_slug);
        if ( $is_plugin_enabled )
        {
            return $warnings;
        }

        if ( !file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) )
        {
            $install_url = is_multisite()
                ? wp_nonce_url(
                    network_admin_url('update.php?action=install-plugin&plugin=wpvivid-backuprestore'),
                    'install-plugin_wpvivid-backuprestore'
                )
                : wp_nonce_url(
                    self_admin_url('update.php?action=install-plugin&plugin=wpvivid-backuprestore'),
                    'install-plugin_wpvivid-backuprestore'
                );

            $warnings[] = array(
                'type'       => 'error',
                'code'       => 'low_disk_space',
                'message'    => __( 'We detect that <a><strong> WPvivid free plugin </strong></a> is not installed or active on your website . WPvivid pro requires it to function. <a href="'.esc_url( $install_url ).'"><strong> Click here </strong></a> to install and activate it to continue', 'wpvivid-backup-pro' ),
                'allow_html' => true,
            );
        }
        else
        {
            if ( is_multisite() )
            {
                $activate_url = wp_nonce_url(
                    network_admin_url(
                        'plugins.php?action=activate&plugin=' . urlencode( $plugin_slug ) . '&networkwide=1'
                    ),
                    'activate-plugin_' . $plugin_slug
                );
            }
            else
            {
                $activate_url = wp_nonce_url(
                    admin_url(
                        'plugins.php?action=activate&plugin=' . urlencode( $plugin_slug )
                    ),
                    'activate-plugin_' . $plugin_slug
                );
            }

            $warnings[] = array(
                'type'       => 'error',
                'code'       => 'low_disk_space',
                'message'    => __( 'We detect that <a><strong> WPvivid free plugin </strong></a> is not installed or active on your website . WPvivid pro requires it to function. <a href="'.esc_url( $activate_url ).'"><strong> Click here </strong></a> to install and activate it to continue', 'wpvivid-backup-pro' ),
                'allow_html' => true,
            );
        }

        return $warnings;
    }

    public function remote_password_crypt_import_notice()
    {
        $notice = get_option('wpvivid_remote_password_crypt_import_notice', array());

        if (empty($notice) || empty($notice['show'])) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible wpvivid-remote-password-crypt-import-notice">
            <p>
                <?php esc_html_e('We detected encrypted remote storage in the imported WPvivid settings. To use it, please copy the encryption key from the source site\'s wp-config.php to this site\'s wp-config.php.', 'wpvivid-backup-pro'); ?>
            </p>
        </div>
        <script>
            jQuery(document).on('click', '.wpvivid-remote-password-crypt-import-notice .notice-dismiss', function()
            {
                var ajax_data = {
                    'action': 'wpvivid_dismiss_remote_password_crypt_import_notice'
                };
                wpvivid_post_request_addon(ajax_data, function (data)
                {
                }, function (XMLHttpRequest, textStatus, errorThrown)
                {
                });
            });
        </script>
        <?php
    }

    public function remote_password_crypt_import_notice_ex($warnings)
    {
        $notice = get_option('wpvivid_remote_password_crypt_import_notice', array());

        if (empty($notice) || empty($notice['show'])) {
            return $warnings;
        }

        $message = __('We detected encrypted remote storage in the imported WPvivid settings. To use it, please copy the encryption key from the source site\'s wp-config.php to this site\'s wp-config.php.', 'wpvivid-backup-pro');

        $warnings[] = array(
            'type'        => 'warning',
            'code'        => 'remote_password_crypt_import_notice',
            'message'     => $message,
            'allow_html'  => true,
            'dismissible' => true,
        );

        return $warnings;
    }

    public function hide_notices()
    {
        $screen_ids=array();
        $screen_ids=apply_filters('wpvivid_get_screen_ids',$screen_ids);
        if(in_array(get_current_screen()->id,$screen_ids))
        {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public function wpvivid_v2_render_notices($context = '')
    {
        $warnings = apply_filters( 'wpvivid_v2_collect_warnings', array() );
        if ( empty( $warnings ) ) {
            return;
        }

        $need_wrapper = $context !== 'no-padding';

        if ( $need_wrapper )
        {
            ?>
            <!-- Notice Sections -->
            <div class="wpvivid-v2-padding" style="padding-bottom: 0;">
            <?php
        }

        foreach ( $warnings as $warning ) {
            if ( empty( $warning['message'] ) ) {
                continue;
            }

            $type        = isset( $warning['type'] ) ? $warning['type'] : 'warning';
            $class       = 'wpvivid-v2-notice wpvivid-v2-notice-' . $type;
            $allow_html  = ! empty( $warning['allow_html'] );
            $dismissible = ! empty( $warning['dismissible'] );
            $code        = isset( $warning['code'] ) ? sanitize_key( $warning['code'] ) : '';

            ?>
                <div class="<?php echo esc_attr( $class ); ?>" data-wpvivid-warning-code="<?php echo esc_attr( $code ); ?>">
                    <span class="dashicons dashicons-warning"></span>
                    <p>
                        <?php
                        if ( $allow_html ) {
                            echo wp_kses_post( $warning['message'] );
                        } else {
                            echo esc_html( $warning['message'] );
                        }
                        ?>
                    </p>
                    <?php
                    if ( $dismissible ) {
                        ?>
                        <button type="button" class="notice-dismiss wpvivid-v2-notice-dismiss">
                            <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wpvivid-backup-pro' ); ?></span>
                        </button>
                        <?php
                    }
                    ?>
                </div>
            <?php
        }

        if ( $need_wrapper )
        {
            ?>
            </div>
            <?php
        }
        ?>
        <script>
            if (typeof window.wpvivid_v2_notice_dismiss_loaded === 'undefined') {
                window.wpvivid_v2_notice_dismiss_loaded = true;

                jQuery(document).on('click', '.wpvivid-v2-notice-dismiss', function()
                {
                    var notice = jQuery(this).closest('.wpvivid-v2-notice');
                    var code = notice.data('wpvivid-warning-code');

                    notice.slideUp(200, function()
                    {
                        notice.remove();
                    });

                    if (code === 'remote_password_crypt_import_notice') {
                        var ajax_data = {
                            'action': 'wpvivid_dismiss_remote_password_crypt_import_notice'
                        };
                        wpvivid_post_request_addon(ajax_data, function (data)
                        {
                        }, function (XMLHttpRequest, textStatus, errorThrown)
                        {
                        });
                    }
                });
            }
        </script>
        <?php
    }

    public function check_wpvivid_plugin_active()
    {
        if (is_multisite())
        {
            if(!is_network_admin())
            {
                return ;
            }
        }

        if(!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $free_wpvivid_slug='wpvivid-backuprestore/wpvivid-backuprestore.php';
        if (is_multisite())
        {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if(!empty($mu_active_plugins)){
                foreach ($mu_active_plugins as $plugin_name => $data){
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins=get_mu_plugins();
            if(count($plugins) == 0 || !isset($plugins[$free_wpvivid_slug])){
                $plugins=get_plugins();
            }
        }
        else
        {
            $active_plugins = get_option('active_plugins');
            $plugins=get_plugins();
        }

        if(!empty($plugins))
        {
            if(isset($plugins[$free_wpvivid_slug]))
            {
                if(version_compare('0.9.29',$plugins[$free_wpvivid_slug]['Version'],'>'))
                {
                    ?>
                    <div class="notice notice-warning" style="padding: 11px 15px;">
                        <?php echo sprintf(__('The free version of %s is required higher version to use %s pro. Please update the free version first.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup Plugin'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup')); ?>
                    </div>
                    <?php
                }
                ?>
                <?php
                if(!in_array($free_wpvivid_slug, $active_plugins))
                {
                    ?>
                    <div class="notice notice-warning" style="padding: 11px 15px;">
                        <?php echo sprintf(__('The free version of %s is required to use %s Pro. Please activate the free version first.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup Plugin'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup')); ?>
                    </div>
                    <?php
                }
            }
            else
            {
                ?>
                <div class="notice notice-warning" style="padding: 11px 15px;">
                    <?php echo sprintf(__('The free version of %s is required to use %s Pro. Please install and activate the free version first.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup Plugin'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup')); ?> Click <a href=\"<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'plugin-install.php?s=WPvivid&tab=search&type=term'); ?>\">here</a> to install.
                </div>
                <?php
            }
        }
    }

    public function display_ex()
    {
        global $wpvivid_plugin;
        remove_action('wpvivid_display_page',array($wpvivid_plugin->admin,'display'));

        if(isset($_REQUEST['auto_backup'])&&$_REQUEST['auto_backup']==1)
        {
            return;
        }
        $slug = apply_filters('wpvivid_access_white_label_slug', 'wpvivid_white_label');
        if(isset($_REQUEST[$slug])&&$_REQUEST[$slug]==1)
        {
            return;
        }
        do_action('show_notice');
        ?>
        <!--<div class="wrap">-->
            <?php
            //$this->display_tabs();
            ?>
        <!--</div>-->
        <?php
    }

    public function display_tabs()
    {
        if(!class_exists('WPvivid_Tab_Page_Container_Ex'))
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/class-wpvivid-tab-page-container-ex.php';
        $this->main_tab=new WPvivid_Tab_Page_Container_Ex();

        $tabs = apply_filters('wpvivid_add_tab_page_ex', array());
        foreach ($tabs as $tab)
        {
            if(current_user_can('administrator'))
            {
                $this->main_tab->add_tab($tab['title'],$tab['slug'],$tab['func']);
            }
            else
            {
                foreach ($tab['caps'] as $cap)
                {
                    if(current_user_can($cap))
                    {
                        $this->main_tab->add_tab($tab['title'],$tab['slug'],$tab['func']);
                        break;
                    }
                }
            }
        }
        $this->main_tab->display();
        ?>
        <script>
            function switch_main_tab(id)
            {
                jQuery( document ).trigger( '<?php echo $this->main_tab->container_id ?>-show',id);
            }
            jQuery(document).ready(function($)
            {
                <?php
                if(isset($_REQUEST['tabs']))
                {
                ?>
                switch_main_tab('<?php echo $_REQUEST['tabs'];?>');
                <?php
                }
                $request_page = apply_filters('wpvivid_white_label_plugin_name', 'wpvivid-remote-page-mainwp');
                if(isset($_REQUEST[$request_page]))
                {
                ?>
                switch_main_tab('remote_storage');
                <?php
                }
                $request_page = apply_filters('wpvivid_white_label_plugin_name', 'wpvivid-restore-page-mainwp');
                if(isset($_REQUEST[$request_page]))
                {
                ?>
                switch_main_tab('backuplist');
                <?php
                }
                ?>
            });
        </script>
        <?php
    }

    public function add_sidebar()
    {
        if(class_exists( 'WPvivid_Staging' )){
            $staging_class = 'wpvivid-dashicons-blue';
            $staging_url = esc_url(apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-staging');
        }
        else{
            $staging_class = 'wpvivid-dashicons-grey';
            $staging_url = '#';
        }

        ?>
        <div id="postbox-container-1" class="postbox-container">

            <div class="meta-box-sortables ui-sortable">

                <div class="postbox  wpvivid-sidebar">

                    <h2 style="margin-top:0.5em;"><span class="dashicons dashicons-sticky wpvivid-dashicons-orange"></span>
                        <span><?php esc_html_e('Troubleshooting', 'wpvivid-backup-pro'); ?></span></h2>
                    <div class="inside" style="padding-top:0;">
                        <ul class="" >
                            <li style="border-top:1px solid #f1f1f1;"><span class="dashicons dashicons-editor-help wpvivid-dashicons-orange" ></span>
                                <a href="https://docs.wpvivid.com/troubleshooting"><b><?php esc_html_e('Troubleshooting', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li style="border-top:1px solid #f1f1f1;"><span class="dashicons dashicons-admin-generic wpvivid-dashicons-orange" ></span>
                                <a href="https://docs.wpvivid.com/wpvivid-backup-pro-advanced-settings.html"><b><?php esc_html_e('Adjust Advanced Settings', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>

                        </ul>
                    </div>

                    <h2><span class="dashicons dashicons-book-alt wpvivid-dashicons-orange" ></span>
                        <span><?php esc_html_e('Documentation', 'wpvivid-backup-pro'); ?></span></h2>
                    <div class="inside" style="padding-top:0;">
                        <ul class="">
                            <li style="border-top:1px solid #f1f1f1;"><span class="dashicons dashicons-backup  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/manual-backup-overview.html"><b><?php esc_html_e('Backup', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-backup', 'wpvivid-backup')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-migrate  wpvivid-dashicons-blue"></span>
                                <a href="https://docs.wpvivid.com/custom-migration-overview.html"><b><?php esc_html_e('Auto-Migration', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-export-site', 'wpvivid-export-site')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-editor-ul  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/wpvivid-backup-pro-backups-restore-overview.html"><b><?php esc_html_e('Backup Manager', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-calendar-alt  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/wpvivid-backup-pro-schedule-overview.html"><b><?php esc_html_e('Schedule', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-schedule', 'wpvivid-schedule')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-admin-site-alt3  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/wpvivid-backup-pro-cloud-storage-overview.html"><b><?php esc_html_e('Cloud Storage', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-remote', 'wpvivid-remote')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-randomize  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/export-content.html"><b><?php esc_html_e('Export/Import', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-export-import', 'wpvivid-export-import')); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li style="display:none;"><span class="dashicons dashicons-format-gallery  wpvivid-dashicons-red"></span>
                                <a href="https://meowapps.com/plugin/meow-analytics/"><b>Image Bulk Optimization</b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li style="display:none;"><span class="dashicons dashicons-update  wpvivid-dashicons-green"></span>
                                <a href="https://meowapps.com/plugin/wplr-sync/"><b>Lazyload</b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-code-standards  wpvivid-dashicons-green"></span>
                                <a href="https://docs.wpvivid.com/unused-images-cleaner.html"><b><?php esc_html_e('Unused Image Cleaner (beta)', 'wpvivid-backup-pro'); ?></b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'admin.php?page=wpvivid-image-cleaner'); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li style="display:none;"><span class="dashicons dashicons-cloud  wpvivid-dashicons-orange"></span>
                                <a href="https://meowapps.com/plugin/meow-analytics/"><b>CDN Integration (coming soon)</b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li style="color:#eee; display:none;"><span class="dashicons dashicons-admin-site" ></span>
                                <a href="https://meowapps.com/plugin/wp-retina-2x/"><b>Cache (coming soon)</b></a>
                                <small><span style="float: right;"><a href="#" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                            <li><span class="dashicons dashicons-welcome-view-site <?php echo esc_attr($staging_class); ?>"></span>
                                <a href="https://wpvivid.com/wpvivid-backup-pro-create-staging-site"><b>WPvivid Staging</b></a>
                                <small><span style="float: right;"><a href="<?php echo esc_url($staging_url); ?>" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                            </li>
                        </ul>
                    </div>

                    <?php
                    if(apply_filters('wpvivid_show_submit_ticket',true))
                    {
                        ?>
                        <h2>
                            <span class="dashicons dashicons-businesswoman wpvivid-dashicons-green"></span>
                            <span><?php esc_html_e('Support', 'wpvivid-backup-pro'); ?></span>
                        </h2>
                        <div class="inside">
                            <ul class="">
                                <li><span class="dashicons dashicons-admin-comments wpvivid-dashicons-green"></span>
                                    <a href="https://wpvivid.com/submit-ticket"><b><?php esc_html_e('Submit A Ticket', 'wpvivid-backup-pro'); ?></b></a>
                                    <br>
                                    <?php printf(esc_html__('The ticket system is for %s Pro users only. If you need any help with our plugin, submit a ticket and we will respond shortly.', 'wpvivid-backup-pro'), esc_html(apply_filters('wpvivid_white_label_display', 'WPvivid'))); ?>
                                </li>
                            </ul>
                        </div>
                        <!-- .inside -->
                        <?php
                    }
                    ?>

                </div>
                <!-- .postbox -->

            </div>
            <!-- .meta-box-sortables -->

        </div>
        <?php
    }

    public function wpvivid_hide_need_update_pro_notice()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );

        try {
            update_option('wpvivid_need_update_pro_notice', time() + 604800, 'no');
            $ret['result'] = 'success';
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function wpvivid_review_addon($json)
    {
        $default = false;
        $review = get_option('wpvivid_need_update_pro_notice', $default);
        $json['data']['wpvivid_need_update_pro_notice'] = $review;
        return $json;
    }

    public function migrate_notice()
    {
        global $wpvivid_plugin;
        remove_action('wpvivid_before_setup_page', array($wpvivid_plugin->admin, 'migrate_notice'));
        $migrate_notice=false;
        $migrate_status=get_option('wpvivid_migrate_status');
        if(!empty($migrate_status) && $migrate_status == 'completed')
        {
            $migrate_notice=true;
            echo '<div class="notice notice-warning is-dismissible"><p>'.__('Migration is complete and htaccess file is replaced. In order to successfully complete the migration, you\'d better reinstall 301 redirect plugin, firewall and security plugin, and caching plugin if they exist.').'</p></div>';
            delete_option('wpvivid_migrate_status');
        }
        $restore = new WPvivid_restore_data();
        if ($restore->has_restore())
        {
            $restore_status = $restore->get_restore_status();
            if ($restore_status === WPVIVID_PRO_RESTORE_COMPLETED)
            {
                $restore->clean_restore_data();
                do_action('wpvivid_rebuild_backup_list');
                $need_review=get_option('wpvivid_need_review');
                if($need_review=='not')
                {
                    update_option('wpvivid_need_review','show','no');
                    $msg = sprintf(__('Cheers! %s has successfully restored your website. If you found the plugin helpful, we would really appreciate a 5-star rating, which would motivate us to keep providing great features.', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid Backup plugin'));
                    update_option('wpvivid_review_msg',$msg,'no');
                }
                else{
                    if(!$migrate_notice)
                    {
                        echo '<div class="notice notice-success is-dismissible"><p>'.__('Restore completed successfully.').'</p></div>';
                    }
                }
            }
        }
    }

    public function check_schedule_last_running()
    {
        if(defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON) {
            $default = array();
            $schedules = get_option('wpvivid_schedule_addon_setting', $default);
            foreach ($schedules as $schedule_id => $schedule_value) {
                if ($schedule_value['status'] == 'Active') {
                    $next_timestamp = wp_next_scheduled($schedule_value['id'], array($schedule_value['id']));
                    if ($next_timestamp === false) {
                        if (isset($schedule_value['week'])) {
                            $time['start_time']['week'] = $schedule_value['week'];
                        } else
                            $time['start_time']['week'] = 'mon';

                        if (isset($schedule_value['day'])) {
                            $schedule_data['day'] = $schedule_value['day'];
                        } else
                            $time['start_time']['day'] = '01';

                        if (isset($schedule_value['current_day'])) {
                            $schedule_data['current_day'] = $schedule_value['current_day'];
                        } else
                            $time['start_time']['current_day'] = "00:00";

                        $time['type']=$schedule_value['type'];
                        $next_timestamp = WPvivid_Schedule_addon::get_start_time($time);
                    }
                    $current_timestamp = time();
                    if ($current_timestamp - $next_timestamp >= 86400) {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('We have detected that a backup was not triggered as scheduled. Please check whether your server-level cron is working properly.', 'wpvivid-backup-pro') . '</p></div>';
                        break;
                    }
                }
            }
        }
    }

    public function check_extensions()
    {
        $common_setting = get_option('wpvivid_common_setting');
        $db_connect_method = isset($common_setting['options']['wpvivid_common_setting']['db_connect_method']) ? $common_setting['options']['wpvivid_common_setting']['db_connect_method'] : 'wpdb';
        $need_php_extensions = array();
        $need_extensions_count = 0;
        $extensions=get_loaded_extensions();
        if(!function_exists("curl_init")){
            $need_php_extensions[$need_extensions_count] = 'curl';
            $need_extensions_count++;
        }
        if(!class_exists('PDO')){
            $need_php_extensions[$need_extensions_count] = 'PDO';
            $need_extensions_count++;
        }
        if(!function_exists("gzopen"))
        {
            $need_php_extensions[$need_extensions_count] = 'zlib';
            $need_extensions_count++;
        }
        if(!array_search('pdo_mysql',$extensions) && $db_connect_method === 'pdo')
        {
            $need_php_extensions[$need_extensions_count] = 'pdo_mysql';
            $need_extensions_count++;
        }
        if(!array_search('mbstring',$extensions))
        {
            $need_php_extensions[$need_extensions_count] = 'mbstring';
            $need_extensions_count++;
        }
        if(!empty($need_php_extensions)){
            $msg = '';
            $figure = 0;
            foreach ($need_php_extensions as $extension){
                $figure++;
                if($figure == 1){
                    $msg .= $extension;
                }
                else if($figure < $need_extensions_count) {
                    $msg .= ', '.$extension;
                }
                else if($figure == $need_extensions_count){
                    $msg .= ' and '.$extension;
                }
            }
            if($figure == 1){
                echo '<div class="notice notice-error"><p>'.sprintf(__('The %s extension is not detected. Please install the extension first.', 'wpvivid-backup-pro'), $msg).'</p></div>';
            }
            else{
                echo '<div class="notice notice-error"><p>'.sprintf(__('The %s extensions are not detected. Please install the extensions first.', 'wpvivid-backup-pro'), $msg).'</p></div>';
            }
        }

        if (!class_exists('PclZip')) include_once(ABSPATH.'/wp-admin/includes/class-pclzip.php');
        if (!class_exists('PclZip')) {
            echo '<div class="notice notice-error"><p>'.__('Class PclZip is not detected. Please update or reinstall your WordPress.', 'wpvivid-backup-pro').'</p></div>';
        }

        $hide_notice = get_option('wpvivid_hide_wp_cron_notice', false);
        if(defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && $hide_notice === false){
            echo '<div class="notice notice-error notice-wp-cron is-dismissible"><p>'.__('In order to execute the scheduled backups properly, please set the DISABLE_WP_CRON constant to false.', 'wpvivid-backup-pro').'</p></div>';
        }
    }

    public function check_custom_backup_default_exclude()
    {
        $custom_backup_history = get_option('wpvivid_custom_backup_history');

        $default_exclude = array();
        $uploads_exclude = get_option('wpvivid_custom_backup_default_exclude_uploads', $default_exclude);
        $upload_dir = wp_upload_dir();
        $upload_path = str_replace('\\','/', $upload_dir['basedir']);
        $upload_path = explode('/', $upload_path);
        $upload_path = implode(DIRECTORY_SEPARATOR, $upload_path);

        $check_upload_array = array('backwpup', 'ShortpixelBackups', 'backup', 'backup-guard');
        foreach ($check_upload_array as $upload_folder){
            if(file_exists($upload_path.DIRECTORY_SEPARATOR.$upload_folder)){
                if(!in_array($upload_folder, $uploads_exclude)){
                    $uploads_exclude[] = $upload_folder;
                    $need_push_array = true;
                    if(!empty($custom_backup_history['uploads_option']['exclude_uploads_list'])){
                        foreach ($custom_backup_history['uploads_option']['exclude_uploads_list'] as $key => $value){
                            if($key === $upload_folder){
                                $need_push_array = false;
                            }
                        }
                    }
                    if($need_push_array){
                        $temp_array = array();
                        $temp_array['name'] = $upload_folder;
                        $temp_array['type'] = 'wpvivid-custom-li-folder-icon';
                        $custom_backup_history['uploads_option']['exclude_uploads_list'][$upload_folder] = $temp_array;
                    }
                }
            }
        }

        update_option('wpvivid_custom_backup_history', $custom_backup_history, 'no');
        update_option('wpvivid_custom_backup_default_exclude_uploads', $uploads_exclude, 'no');
    }

    public function ajax_check_security($role='administrator')
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );

        if(is_admin()&&current_user_can('administrator'))
        {
            return;
        }
        else
        {
            $check=is_admin()&&current_user_can($role);
            $check=apply_filters('wpvivid_ajax_check_security',$check);
            if(!$check)
            {
                die();
            }
        }
    }

    public function is_plugin_enabled($is_enabled, $plugin_slug)
    {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_installed=false;
        $is_actived=false;

        $all_plugins = get_plugins();
        if ( isset( $all_plugins[ $plugin_slug ] ) ) {
            $is_installed = true;
        }

        if ( is_plugin_active( $plugin_slug ) ) {
            $is_actived = true;
        }

        if($is_installed && $is_actived)
        {
            $is_enabled=true;
        }
        else
        {
            $is_enabled=false;
        }

        return $is_enabled;
    }

    public function dismiss_remote_password_crypt_import_notice()
    {
        global $wpvivid_backup_pro;
        $wpvivid_backup_pro->ajax_check_security('manage_options');

        delete_option('wpvivid_remote_password_crypt_import_notice');

        echo json_encode(array('result' => 'success'));
        die();
    }
}