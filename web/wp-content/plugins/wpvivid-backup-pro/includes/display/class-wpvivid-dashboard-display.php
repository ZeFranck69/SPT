<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Dashboard
{
    public $installation;
    public $license;

    public function __construct()
    {
        add_filter('wpvivid_get_dashboard_menu', array($this, 'get_dashboard_menu'), 10, 2);

        add_action('wpvivid_dashboard_menus_sidebar', array($this, 'license_sidebar'), 14);
        add_action('wpvivid_dashboard_menus_sidebar', array($this, 'ticket_sidebar'), 10);

        add_action('wpvivid_add_sidebar_dashboard', array($this, 'add_sidebar'));
        add_action('wpvivid_dashboard_menus_box', array($this, 'backup_pro_menu_box'), 10);
        add_action('wpvivid_dashboard_addon_box', array($this, 'addon_box'), 10);

        add_filter('wpvivid_addon_page_url', array($this, 'addon_page_url'), 10, 2);
        add_filter('wpvivid_addon_page_title', array($this, 'addon_page_title'), 10, 2);

        add_action('wp_ajax_wpvivid_init_plugin_install_ex', array($this, 'init_plugin_install'));
        add_action('wp_ajax_wpvivid_activate_plugin', array($this, 'activate_plugin'));

        add_filter('wpvivid_check_install_addon', array($this, 'check_install_addon'), 10, 2);
    }

    public function check_install_addon($is_install, $check_slug)
    {
        global $wpvivid_backup_pro;

        if (is_multisite()) {
            if (is_main_site()) {
                $dashboard_info = get_option('wpvivid_dashboard_info', array());
            } else {
                switch_to_blog(get_main_site_id());
                $dashboard_info = get_option('wpvivid_dashboard_info', array());
                restore_current_blog();
            }
        } else {
            $dashboard_info = get_option('wpvivid_dashboard_info', array());
        }

        if (isset($dashboard_info['plugins']) && !empty($dashboard_info['plugins'])) {
            foreach ($dashboard_info['plugins'] as $slug => $info) {
                if ($slug === $check_slug) {
                    $status = $wpvivid_backup_pro->addons_loader->get_plugin_status($info);
                    if ($status['status'] !== 'Un-installed') {
                        $is_install = true;
                    } else {
                        $is_install = false;
                    }
                }
            }
        }

        return $is_install;
    }

    public function get_dashboard_menu($submenus, $parent_slug)
    {
        $display = apply_filters('wpvivid_get_menu_capability_addon', 'dashboard');
        if ($display) {
            $submenu['parent_slug'] = $parent_slug;
            $submenu['page_title'] = apply_filters('wpvivid_white_label_display', __('Dashboard', 'wpvivid-backup-pro'));
            $submenu['menu_title'] = __('Dashboard', 'wpvivid-backup-pro');

            $submenu['capability'] = apply_filters("wpvivid_menu_capability", "administrator", "wpvivid-dashboard");
            $submenu['menu_slug'] = strtolower(sprintf('%s-dashboard', apply_filters('wpvivid_white_label_slug', 'wpvivid')));
            $submenu['index'] = 1;
            $submenu['function'] = array($this, 'init_page');
            $submenus[$submenu['menu_slug']] = $submenu;
        }
        return $submenus;
    }

    public function init_plugin_install()
    {
        if (!isset($_POST['plugins'])) {
            die();
        }
        global $wpvivid_backup_pro;
        $wpvivid_backup_pro->ajax_check_security('wpvivid-can-install-plugins');

        if (is_multisite()) {
            if (is_main_site()) {
                $info = get_option('wpvivid_dashboard_info', array());
            } else {
                switch_to_blog(get_main_site_id());
                $info = get_option('wpvivid_dashboard_info', array());
                restore_current_blog();
            }
        } else {
            $info = get_option('wpvivid_dashboard_info', array());
        }

        if (empty($info)) {
            $ret['result'] = 'failed';
            $ret['error'] = 'not found dashboard info';
            echo json_encode($ret);

            die();
        }

        $plugin_install_cache['plugins'] = array();
        $plugin_install_cache['complete'] = array();

        $plugins = $_POST['plugins'];

        if (empty($plugins)) {
            $ret['result'] = 'failed';
            $ret['error'] = 'No selected plugin.';

            echo json_encode($ret);

            die();
        }

        foreach ($info['plugins'] as $slug => $plugin) {
            if (in_array($slug, $plugins)) {
                if ($wpvivid_backup_pro->addons_loader->is_plugin_install_available($plugin)) {
                    $plugin_install_cache['plugins'] = array_merge($wpvivid_backup_pro->addons_loader->get_requires_plugins($plugin), $plugin_install_cache['plugins']);
                    $plugin_install_cache['plugins'][] = $plugin;
                }
            }
        }

        if (empty($plugin_install_cache['plugins'])) {
            $ret['result'] = 'success';
            $ret['href'] = apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-dashboard', 'wpvivid-dashboard');
            $wpvivid_backup_pro->updater->update_site_transient_update_plugins();

        } else {
            update_option('wpvivid_plugin_install_cache', $plugin_install_cache, 'no');
            $ret['result'] = 'success';
            $ret['cache'] = $plugin_install_cache;
            $ret['href'] = apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-dashboard', 'wpvivid-dashboard') . '&install=1';
        }

        echo json_encode($ret);

        die();
    }

    public function activate_plugin()
    {
        if (!isset($_POST['plugins'])) {
            die();
        }
        global $wpvivid_backup_pro;
        $wpvivid_backup_pro->ajax_check_security('activate_plugin');

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        if (is_multisite()) {
            if (is_main_site()) {
                $info = get_option('wpvivid_dashboard_info', array());
            } else {
                switch_to_blog(get_main_site_id());
                $info = get_option('wpvivid_dashboard_info', array());
                restore_current_blog();
            }
        } else {
            $info = get_option('wpvivid_dashboard_info', array());
        }

        if (empty($info)) {
            $ret['result'] = 'failed';
            $ret['error'] = 'not found dashboard info';
            echo json_encode($ret);

            die();
        }

        $plugins = $_POST['plugins'];

        if (empty($plugins)) {
            $ret['result'] = 'failed';
            $ret['error'] = 'No selected plugin.';

            echo json_encode($ret);

            die();
        }

        foreach ($plugins as $slug) {
            if (isset($info['plugins'][$slug])) {
                $plugin = $info['plugins'][$slug];
                if ($plugin['install']['is_plugin'] == true) {
                    activate_plugin($plugin['install']['plugin_slug']);
                }

                if (isset($plugin['requires_plugins'])) {
                    foreach ($plugin['requires_plugins'] as $requires_plugin) {
                        activate_plugin($requires_plugin['install']['plugin_slug']);
                    }
                }
            }
        }

        $ret['result'] = 'success';
        $ret['href'] = apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-dashboard', 'wpvivid-dashboard') . '&finish=1';
        echo json_encode($ret);

        die();
    }

    public function init_page()
    {
        $white_label_setting = apply_filters('wpvivid_get_white_label_option', 'white_label_setting', array());
        if(!empty($white_label_setting))
        {
            $license_slug = apply_filters('wpvivid_license_access_slug', 'license');
            if(isset($_REQUEST[$license_slug]) && $_REQUEST[$license_slug] == 1)
            {
                do_action('wpvivid_output_license_page');
                return;
            }
        }

        $slug = apply_filters('wpvivid_access_white_label_slug', 'wpvivid_white_label');
        if (isset($_REQUEST[$slug]) && $_REQUEST[$slug] == 1) {
            do_action('wpvivid_output_white_label_page');
            return;
        }

        $first_install = get_option('wpvivid_plugins_first_install', false);
        if ($first_install === false) {
            if (is_multisite()) {
                if (is_main_site()) {
                    $user_info = get_option('wpvivid_pro_user', false);
                } else {
                    switch_to_blog(get_main_site_id());
                    $user_info = get_option('wpvivid_pro_user', false);
                    restore_current_blog();
                }
            } else {
                $user_info = get_option('wpvivid_pro_user', false);
            }
            if ($user_info === false) {
                $url = apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-license', 'wpvivid-license');
                update_option('wpvivid_plugins_first_install', 'step1', 'no');
            } else {
                $url = apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-dashboard', 'wpvivid-dashboard');
                $url .= '&first=1';
                update_option('wpvivid_plugins_first_install', 'step2', 'no');
            }

            if (is_multisite()) {
                $url = network_admin_url() . $url;
            } else {
                $url = admin_url() . $url;
            }

            ?>
            <script>
                location.href = '<?php echo $url; ?>';
            </script>
            <?php
        }

        $this->dashboard_page();

        return;
    }

    public function dashboard_page()
    {
        $plugin_slug='wpvivid-backuprestore/wpvivid-backuprestore.php';
        $is_plugin_enabled = apply_filters('wpvivid_is_plugin_enabled', true, $plugin_slug);

        ?>
        <div class="wrap wpvivid-canvas">
            <div class="wpvivid-v2-dashboard-container">

                <?php $this->dashboard_welcome_panel(); ?>

                <?php $this->dashboard_notice_panel(); ?>

                <!-- 3 Columns Layout -->
                <div class="wpvivid-v2-dashboard-columns">

                    <!-- Left Column: Site Status -->
                    <div class="wpvivid-v2-dashboard-column">
                        <?php
                        $this->dashboard_site_size_overview($is_plugin_enabled);
                        $this->dashbaord_disk_usage_card($is_plugin_enabled);
                        $this->dashboard_cloud_storage_card($is_plugin_enabled);
                        if(apply_filters('wpvivid_show_dashboard_addons',true))
                        {
                            $this->dashboard_tips_news_card();
                        }
                        ?>
                    </div>

                    <!-- Center Column: Quick Actions & Tools -->
                    <div class="wpvivid-v2-dashboard-column">
                        <?php
                        $this->dashboard_quick_action_card($is_plugin_enabled);
                        $this->dashboard_staging_card();
                        $this->dashboard_manual_backup_card($is_plugin_enabled);
                        $this->dashboard_backup_retention_card($is_plugin_enabled);
                        $this->dashboard_recent_backup_card($is_plugin_enabled);
                        ?>
                    </div>

                    <!-- Right Column: Support & Analytics -->
                    <div class="wpvivid-v2-dashboard-column">
                        <?php
                        $this->dashboard_general_schedule_card($is_plugin_enabled);
                        $this->dashboard_incremental_schedule_card($is_plugin_enabled);
                        if(apply_filters('wpvivid_show_dashboard_addons',true))
                        {
                            $this->dashboard_addons_tools_card();
                        }
                        ?>
                    </div>

                </div>

                <!-- Footer -->
                <div class="wpvivid-v2-dashboard-footer">
                    <p>© 2025 WPvivid Plugins — 900K + sites protected | <a href="https://docs.wpvivid.com/">Docs</a> | <a href="https://wpvivid.com/submit-ticket">Support</a> | <a href="https://wpvivid.com/wpvivid-backup-pro-changelog">Changelog</a></p>
                </div>
            </div>
        </div>

        <?php

        if(isset($_REQUEST['install'])&&$_REQUEST['install']||isset($_REQUEST['finish'])&&$_REQUEST['finish']||isset($_REQUEST['first'])&&$_REQUEST['first'])
        {
            ?>
            <script>
                jQuery(function(jQuery) {
                    var $target = jQuery('.wpvivid-v2-global-progress');
                    if ($target.length) {
                        jQuery('html, body').animate({
                            scrollTop: $target.offset().top
                        }, 500);
                    }
                });
            </script>
            <?php
        }
        if(isset($_REQUEST['install'])&&$_REQUEST['install'])
        {
            $plugin_install_cache=get_option('wpvivid_plugin_install_cache',array());
            if(empty($plugin_install_cache)||empty($plugin_install_cache['plugins']))
            {
                return;
            }

            ?>
            <script>
                jQuery(function(jQuery) {
                    var $target = jQuery('.wpvivid-v2-global-progress');
                    if ($target.length) {
                        jQuery('html, body').animate({
                            scrollTop: $target.offset().top
                        }, 500);
                    }
                });
            </script>
            <?php

            if(!class_exists('WPvivid_Plugin_Installer'))
            {
                include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/installer/class-wpvivid-installer.php';
            }
            $installer=new WPvivid_Plugin_Installer();
            $installer->run_installation();
        }
        ?>

        <script>
            function wpvivid_dashboard_calculate_diskspaceused()
            {
                document.getElementById("wpvivid_dashboard_calc_disk_usage").classList.add("loading");
                var ajax_data={
                    'action': 'wpvivid_junk_files_info_ex'
                };
                var current_size = jQuery('.wpvivid-dashboard-backup-size').html();
                jQuery('#wpvivid_dashboard_calc_disk_usage').css({'pointer-events': 'none', 'opacity': '0.4'});
                wpvivid_post_request_addon(ajax_data, function(data){
                    jQuery('#wpvivid_dashboard_calc_disk_usage').css({'pointer-events': 'auto', 'opacity': '1'});
                    document.getElementById("wpvivid_dashboard_calc_disk_usage").classList.remove("loading");
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success") {
                            jQuery('.wpvivid-dashboard-log-size').html(jsonarray.data.log_dir_size);
                            jQuery('.wpvivid-dashboard-backup-cache-size').html(jsonarray.data.backup_cache_size);
                            jQuery('.wpvivid-dashboard-junk-size').html(jsonarray.data.junk_size);
                            jQuery('.wpvivid-dashboard-backup-size').html(jsonarray.data.backup_size);
                        }
                    }
                    catch(err){
                        jQuery('#wpvivid_dashboard_calc_disk_usage').css({'pointer-events': 'auto', 'opacity': '1'});
                        jQuery('.wpvivid-dashboard-backup-size').html(current_size);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown) {
                    jQuery('#wpvivid_dashboard_calc_disk_usage').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('.wpvivid-dashboard-backup-size').html(current_size);
                    document.getElementById("wpvivid_dashboard_calc_disk_usage").classList.remove("loading");
                });
            }

            jQuery(document).ready(function ()
            {
                wpvivid_dashboard_calculate_diskspaceused();
            });
        </script>
        <?php
    }

    public function dashboard_welcome_panel()
    {
        ?>
        <!-- Header -->
        <div class="wpvivid-v2-dashboard-header">
            <div class="wpvivid-v2-dashboard-header-left">
                <h1><?php esc_html_e('Backup Dashboard', 'wpvivid-backup-pro'); ?></h1>
                <p><?php esc_html_e('Welcome back! Here\'s your site overview.', 'wpvivid-backup-pro'); ?></p>
            </div>

            <div class="wpvivid-v2-license-support-content">
                <!-- Column: version -->
                <div class="wpvivid-v2-version-column">

                    <h4><?php esc_html_e('Version:', 'wpvivid-backup-pro'); ?> </h4>
                    <ul class="wpvivid-v2-support-list">
                        <li><span><?php esc_html_e('Pro:', 'wpvivid-backup-pro'); ?> </span><?php echo esc_html(WPVIVID_BACKUP_PRO_VERSION); ?></li>
                        <li><a><span><?php esc_html_e('Local time:', 'wpvivid-backup-pro'); ?> </span><span><?php
                                    echo WPvivid_Time::format_local("H:i - M d, Y", time());
                                    ?></span></a></li>
                    </ul>
                </div>

                <?php
                if(apply_filters('wpvivid_show_dashboard_addons',true))
                {
                    if(is_multisite())
                    {
                        if(is_main_site())
                        {
                            $dashboard_info=get_option('wpvivid_dashboard_info',array());
                        }
                        else
                        {
                            switch_to_blog(get_main_site_id());
                            $dashboard_info=get_option('wpvivid_dashboard_info',array());
                            restore_current_blog();
                        }
                    }
                    else
                    {
                        $dashboard_info=get_option('wpvivid_dashboard_info',array());
                    }

                    if(empty($dashboard_info))
                    {
                        $active_status=__('Inactive', 'wpvivid-backup-pro');
                        $class_status='wpvivid-v2-status-error';
                    }
                    else
                    {
                        if(isset($dashboard_info['check_active'])&&$dashboard_info['check_active'])
                        {
                            $active_status='✔ '.__('Activated', 'wpvivid-backup-pro');
                            $class_status='wpvivid-v2-status-success';
                        }
                        else
                        {
                            $active_status=__('Inactive', 'wpvivid-backup-pro');
                            $class_status='wpvivid-v2-status-error';
                        }
                    }
                    ?>
                    <!-- Column: Support -->
                    <div class="wpvivid-v2-support-column">
                        <h4><?php esc_html_e('Support Center', 'wpvivid-backup-pro'); ?></h4>
                        <ul class="wpvivid-v2-support-list">
                            <li><a href="https://wpvivid.com/submit-ticket"><?php esc_html_e('Submit a Ticket', 'wpvivid-backup-pro'); ?></a></li>
                            <li><a href="https://wpvivid.com/wpvivid-backup-pro-changelog"><?php esc_html_e('View Logs', 'wpvivid-backup-pro'); ?></a></li>
                            <li><a href="https://docs.wpvivid.com"><?php esc_html_e('Documentation', 'wpvivid-backup-pro'); ?></a></li>
                        </ul>
                    </div>
                    <!-- Column: License -->
                    <div class="wpvivid-v2-license-column">
                        <h4><?php esc_html_e('License & Account', 'wpvivid-backup-pro'); ?></h4>
                        <p><?php esc_html_e('Status:', 'wpvivid-backup-pro'); ?> <span class="wpvivid-v2-license-status <?php echo esc_attr($class_status); ?>"><?php echo esc_html($active_status); ?></span></p>
                        <a href="<?php echo esc_url(apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-license', 'wpvivid-license')); ?>" class="wpvivid-v2-license-link"><?php esc_html_e('Manage License', 'wpvivid-backup-pro'); ?> →</a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function dashboard_notice_panel()
    {
        do_action('wpvivid_v2_notice', 'no-padding');
    }

    public function dashboard_site_size_overview($is_backup_free_enabled)
    {
        $type='general';
        $website_size = get_option('wpvivid_custom_select_website_size_ex', array());

        $last_calc_time=isset($website_size[$type]['calctime'])?$website_size[$type]['calctime']:0;
        $database_size=isset($website_size[$type]['database_size'])?$website_size[$type]['database_size']:0;
        $core_size=isset($website_size[$type]['core_size'])?$website_size[$type]['core_size']:0;
        $content_size=isset($website_size[$type]['content_size'])?$website_size[$type]['content_size']:0;
        $themes_size=isset($website_size[$type]['themes_size'])?$website_size[$type]['themes_size']:0;
        $plugins_size=isset($website_size[$type]['plugins_size'])?$website_size[$type]['plugins_size']:0;
        $uploads_size=isset($website_size[$type]['uploads_size'])?$website_size[$type]['uploads_size']:0;

        $total_size = size_format($database_size+$core_size+$themes_size+$plugins_size+$uploads_size+$content_size, 2);
        $database_size = size_format($database_size, 2);
        $core_size = size_format($core_size, 2);
        $themes_size = size_format($themes_size, 2);
        $plugins_size = size_format($plugins_size, 2);
        $uploads_size = size_format($uploads_size, 2);
        $content_size = size_format($content_size, 2);

        if($total_size === 0)
        {
            $total_size_display = 'No data yet';
        }
        else
        {
            $total_size_display = $total_size;
        }

        if($last_calc_time === 0)
        {
            $last_calc_time_display = 'No data yet';
        }
        else
        {
            $last_calc_time_display = WPvivid_Time::format_local('M d, Y — H:i', $last_calc_time);
        }

        if($database_size === 0)
        {
            $database_size_display = 'No data yet';
        }
        else
        {
            $database_size_display = $database_size;
        }

        if($core_size === 0)
        {
            $core_size_display = 'No data yet';
        }
        else
        {
            $core_size_display = $core_size;
        }

        if($content_size === 0)
        {
            $content_size_display = 'No data yet';
        }
        else
        {
            $content_size_display = $content_size;
        }

        if($themes_size === 0)
        {
            $themes_size_display = 'No data yet';
        }
        else
        {
            $themes_size_display = $themes_size;
        }

        if($plugins_size === 0)
        {
            $plugins_size_display = 'No data yet';
        }
        else
        {
            $plugins_size_display = $plugins_size;
        }

        if($uploads_size === 0)
        {
            $uploads_size_display = 'No data yet';
        }
        else
        {
            $uploads_size_display = $uploads_size;
        }

        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }

        ?>
        <!-- ======================================
             WPvivid v2 - Site Size Overview (Summary Style)
             ====================================== -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-site-size">
            <div class="wpvivid-v2-size-header">
                <h3><?php esc_html_e('Site Size Overview', 'wpvivid-backup-pro'); ?></h3>
                <button class="wpvivid-v2-dashboard-button-secondary wpvivid-v2-loading-btn" id="wpvivid_dashboard_calc_website_size" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Calculate Now', 'wpvivid-backup-pro'); ?></button>
            </div>

            <div class="wpvivid-v2-size-summary-info">
                <p><?php esc_html_e('Total Site Size:', 'wpvivid-backup-pro'); ?> <strong class="wpvivid-total-content-size"><?php echo esc_html($total_size_display); ?></strong></p>
                <p class="wpvivid-v2-size-last-calc"><?php esc_html_e('Last calculated on', 'wpvivid-backup-pro'); ?> <strong class="wpvivid-last-calc-time"><?php echo esc_html($last_calc_time_display); ?></strong></p>
            </div>

            <div class="wpvivid-v2-size-list">
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-database"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('Database', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-database-size"><?php echo esc_html($database_size_display); ?></span>
                </div>
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-wordpress"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('WordPress Core', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-core-size"><?php echo esc_html($core_size_display); ?></span>
                </div>
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-open-folder"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('wp-content (Uploads excluded)', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-content-size"><?php echo esc_html($content_size_display); ?></span>
                </div>
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('Plugins', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-plugins-size"><?php echo esc_html($plugins_size_display); ?></span>
                </div>
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-format-gallery"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('Uploads', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-uploads-size"><?php echo esc_html($uploads_size_display); ?></span>
                </div>
                <div class="wpvivid-v2-size-item">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <span class="wpvivid-v2-size-label"><?php esc_html_e('Themes', 'wpvivid-backup-pro'); ?></span>
                    <span class="wpvivid-v2-size-value wpvivid-themes-size"><?php echo esc_html($themes_size_display); ?></span>
                </div>

            </div>

            <p class="wpvivid-v2-size-footnote">
                <?php esc_html_e('These values are estimated from your last backup. Click "Calculate Now" to get the latest site size data.', 'wpvivid-backup-pro'); ?>
            </p>
        </div>
        <script>
            document.getElementById("wpvivid_dashboard_calc_website_size").addEventListener("click", function () {
                this.classList.add("loading");
            });

            jQuery('#wpvivid_dashboard_calc_website_size').on('click', function()
            {
                jQuery('#wpvivid_dashboard_calc_website_size').css({'pointer-events': 'none', 'opacity': '0.4'});
                var website_item_arr = new Array('database', 'core', 'content', 'themes', 'plugins', 'uploads');
                var total_file_size = 0;
                var last_calculated_time = 'N/A';
                wpvivid_dashboard_recalc_backup_size(website_item_arr, total_file_size, last_calculated_time);
            });

            function wpvivid_dashboard_recalc_backup_size(website_item_arr, total_file_size, last_calculated_time)
            {
                if(website_item_arr.length > 0)
                {
                    var website_item = website_item_arr.shift();
                    var ajax_data = {
                        'action': 'wpvivid_recalc_backup_size_ex',
                        'website_item': website_item
                    };

                    wpvivid_post_request_addon(ajax_data, function (data) {
                        try {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'success') {
                                if(website_item === 'database')
                                {
                                    jQuery('.wpvivid-database-size').html(jsonarray.database_size);
                                }
                                if(website_item === 'core')
                                {
                                    jQuery('.wpvivid-core-size').html(jsonarray.core_size);
                                }
                                if(website_item === 'content')
                                {
                                    jQuery('.wpvivid-content-size').html(jsonarray.content_size);
                                }
                                if(website_item === 'themes')
                                {
                                    jQuery('.wpvivid-themes-size').html(jsonarray.themes_size);
                                }
                                if(website_item === 'plugins')
                                {
                                    jQuery('.wpvivid-plugins-size').html(jsonarray.plugins_size);
                                }
                                if(website_item === 'uploads')
                                {
                                    jQuery('.wpvivid-uploads-size').html(jsonarray.uploads_size);
                                }

                                if(typeof jsonarray.last_calculated_time !== 'undefined')
                                {
                                    last_calculated_time = jsonarray.last_calculated_time;
                                }
                                wpvivid_dashboard_recalc_backup_size(website_item_arr, jsonarray.total_file_size, last_calculated_time);
                            }
                            else
                            {
                                wpvivid_dashboard_recalc_backup_size(website_item_arr, total_file_size, last_calculated_time);
                            }
                        }
                        catch (err) {
                            wpvivid_dashboard_recalc_backup_size(website_item_arr, total_file_size, last_calculated_time);
                        }
                    }, function (XMLHttpRequest, textStatus, errorThrown) {
                        wpvivid_dashboard_recalc_backup_size(website_item_arr, total_file_size, last_calculated_time);
                    });
                }
                else
                {
                    jQuery('.wpvivid-total-content-size').html(total_file_size);
                    jQuery('.wpvivid-last-calc-time').html(last_calculated_time);
                    jQuery('#wpvivid_dashboard_calc_website_size').css({'pointer-events': 'auto', 'opacity': '1'});
                    document.getElementById("wpvivid_dashboard_calc_website_size").classList.remove("loading");
                }
            }
        </script>
        <?php
    }

    public function dashbaord_disk_usage_card($is_backup_free_enabled)
    {
        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }
        ?>
        <!-- ======================================
			WPvivid v2 - Disk Usage Card
			====================================== -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-disk">
            <div class="wpvivid-v2-disk-header">
                <h3><?php esc_html_e('Backup Disk Usage', 'wpvivid-backup-pro'); ?></h3>
                <div class="wpvivid-v2-storage-actions" style="<?php echo esc_attr($button_style); ?>">
                    <button class="wpvivid-v2-dashboard-button-secondary wpvivid-v2-loading-btn" id="wpvivid_dashboard_calc_disk_usage"><?php esc_html_e('Calculate', 'wpvivid-backup-pro'); ?></button> <span> | </span><a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-setting', 'wpvivid-setting'); ?>"><?php esc_html_e('Manage Usage', 'wpvivid-backup-pro'); ?> →</a>
                </div>
            </div>

            <div class="wpvivid-v2-disk-summary">
                <div><strong><?php esc_html_e('Backup Size:', 'wpvivid-backup-pro'); ?></strong></div>
                <div><strong class="wpvivid-dashboard-backup-size"><?php esc_html_e('No data yet', 'wpvivid-backup-pro'); ?></strong></div>
            </div>

            <div class="wpvivid-v2-disk-list">
                <div class="wpvivid-v2-disk-item">
                    <label for="wpvivid-log"><?php esc_html_e('Logs', 'wpvivid-backup-pro'); ?></label>
                    <span class="wpvivid-v2-disk-size wpvivid-dashboard-log-size"><?php esc_html_e('No data yet', 'wpvivid-backup-pro'); ?></span>
                </div>

                <div class="wpvivid-v2-disk-item">
                    <label for="wpvivid-cache"><?php esc_html_e('Backup Cache', 'wpvivid-backup-pro'); ?></label>
                    <span class="wpvivid-v2-disk-size wpvivid-dashboard-backup-cache-size"><?php esc_html_e('No data yet', 'wpvivid-backup-pro'); ?></span>
                </div>

                <div class="wpvivid-v2-disk-item">
                    <label for="wpvivid-junk"><?php esc_html_e('Junk Files', 'wpvivid-backup-pro'); ?></label>
                    <span class="wpvivid-v2-disk-size wpvivid-dashboard-junk-size"><?php esc_html_e('No data yet', 'wpvivid-backup-pro'); ?></span>
                </div>
            </div>
        </div>
        <script>
            jQuery('#wpvivid_dashboard_calc_disk_usage').on('click', function()
            {
                wpvivid_dashboard_calculate_diskspaceused();
            });
        </script>
        <?php
    }

    public function get_cloud_storage_info($storage_type)
    {
        $assets_url=WPVIVID_BACKUP_PRO_PLUGIN_URL . 'includes/display/images';

        $src_url='';
        $storage_display='';
        if($storage_type=='amazons3')
        {
            $src_url=$assets_url.'/amazon-icon.png';
            $storage_display='Amazon S3';
        }
        else if($storage_type=='b2')
        {
            $src_url=$assets_url.'/backblaze-icon.png';
            $storage_display='Backblaze';
        }
        else if($storage_type=='dropbox')
        {
            $src_url=$assets_url.'/dropbox-icon.png';
            $storage_display='Dropbox';
        }
        else if($storage_type=='ftp')
        {
            $src_url=$assets_url.'/ftp-icon.png';
            $storage_display='FTP';
        }
        else if($storage_type=='ftp2')
        {
            $src_url=$assets_url.'/ftp-icon.png';
            $storage_display='FTP';
        }
        else if($storage_type=='googledrive')
        {
            $src_url=$assets_url.'/google-drive-icon.png';
            $storage_display='Google Drive';
        }
        else if($storage_type=='nextcloud')
        {
            $src_url=$assets_url.'/nextcloud.png';
            $storage_display='Nextcloud';
        }
        else if($storage_type=='onedrive')
        {
            $src_url=$assets_url.'/onedrive-icon.png';
            $storage_display='Microsoft OneDrive';
        }
        else if($storage_type=='onedrive_shared')
        {
            $src_url=$assets_url.'/onedrive-icon.png';
            $storage_display='OneDrive Shared Drives';
        }
        else if($storage_type=='pCloud')
        {
            $src_url=$assets_url.'/pcloud-icon.png';
            $storage_display='pCloud';
        }
        else if($storage_type=='s3compat')
        {
            $src_url=$assets_url.'/amazon-icon.png';
            $storage_display='S3 Compatible Storage';
        }
        else if($storage_type=='sftp')
        {
            $src_url=$assets_url.'/sftp-icon.png';
            $storage_display='SFTP';
        }
        else if($storage_type=='wasabi')
        {
            $src_url=$assets_url.'/wasabi-cloud-icon.png';
            $storage_display='Wasabi';
        }
        else if($storage_type=='webdav')
        {
            $src_url=$assets_url.'/webdav-icon.png';
            $storage_display='Webdav';
        }

        $cloud_storage['src']=$src_url;
        $cloud_storage['type']=$storage_display;
        return $cloud_storage;
    }

    public function dashboard_cloud_storage_card($is_backup_free_enabled)
    {
        $remoteslist=get_option('wpvivid_upload_setting', array());
        $has_remote=false;
        if(isset($remoteslist) && !empty($remoteslist)) {
            foreach ($remoteslist as $key => $value) {
                if ($key === 'remote_selected') {
                    continue;
                }
                else {
                    $has_remote=true;
                }
            }
        }
        $wpvivid_user_history=get_option('wpvivid_user_history', array());
        $remote_select=array();
        if(array_key_exists('remote_selected', $wpvivid_user_history))
        {
            $remote_select=$wpvivid_user_history['remote_selected'];
        }

        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }

        ?>
        <!-- =============================
             WPvivid v2 - Cloud Storage Card (Updated)
             ============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-storage">
            <div class="wpvivid-v2-storage-header">
                <h3><?php esc_html_e('Cloud Storage', 'wpvivid-backup-pro'); ?></h3>
                <div class="wpvivid-v2-storage-actions">
                    <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-remote', 'wpvivid-remote'); ?>" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Manage Storage', 'wpvivid-backup-pro'); ?> →</a>
                </div>
            </div>

            <div class="wpvivid-v2-storage-content">

                <?php
                $display_count=2;
                $current_count=0;
                $wpvivid_new_remote_list=get_option('wpvivid_new_remote_list',array());
                if($has_remote)
                {
                    if (isset($remoteslist) && !empty($remoteslist))
                    {
                        foreach ($remoteslist as $key => $value) {
                            if ($key === 'remote_selected') {
                                continue;
                            } else {
                                if (in_array($key, $remote_select)) {
                                    $is_default = true;
                                } else {
                                    $is_default = false;
                                }

                                $storage_type = $value['type'];
                                $current_count++;
                                if ($current_count <= $display_count) {
                                    $cloud_storage = $this->get_cloud_storage_info($storage_type);
                                    $wpvivid_remote_size = 0;
                                    if (isset($wpvivid_new_remote_list[$key]) && !empty($wpvivid_new_remote_list[$key])) {
                                        $wpvivid_remote_backups = $wpvivid_new_remote_list[$key];
                                        foreach ($wpvivid_remote_backups as $backup_id => $backup) {
                                            if (isset($backup['backup']['files']) && !empty($backup['backup']['files'])) {
                                                foreach ($backup['backup']['files'] as $file_info) {
                                                    $wpvivid_remote_size += $file_info['size'];
                                                }
                                            }
                                        }
                                    }
                                    $wpvivid_remote_size = size_format($wpvivid_remote_size, 2);
                                    ?>
                                    <div class="wpvivid-v2-storage-item">
                                        <img src="<?php echo $cloud_storage['src']; ?>"
                                             alt="Google Drive">
                                        <div class="wpvivid-v2-storage-info">
                                            <div class="wpvivid-v2-storage-top">
                                                <strong><?php echo $cloud_storage['type']; ?></strong>
                                                <?php
                                                if ($is_default) {
                                                    ?>
                                                    <span class="wpvivid-v2-storage-default">Default</span>
                                                    <?php
                                                }
                                                ?>
                                            </div>

                                            <div class="wpvivid-v2-storage-meta">
                                                Used: <span><?php echo $wpvivid_remote_size; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        }
                    }
                }
                else
                {
                    ?>
                    <div class="wpvivid-v2-storage-item wpvivid-v2-storage-empty">

                        <div class="wpvivid-v2-storage-empty-content">
                            <strong><?php esc_html_e('No Cloud Storage Connected', 'wpvivid-backup-pro'); ?></strong>
                            <p><?php esc_html_e('You haven\'t connected any remote storage yet.', 'wpvivid-backup-pro'); ?></p>
                            <button class="wpvivid-v2-storage-connect-button" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Connect Storage', 'wpvivid-backup-pro'); ?></button>
                        </div>
                    </div>
                    <?php
                }

                ?>
            </div>
        </div>
        <script>
            jQuery('.wpvivid-v2-storage-connect-button').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-remote', 'wpvivid-remote'); ?>';
            });
        </script>
        <?php
    }

    public function dashboard_tips_news_card()
    {
        ?>
        <!-- ======================================
			WPvivid v2 - Tips & News Card
			====================================== -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-tips-news">
            <div class="wpvivid-v2-tips-header">
                <h3><?php esc_html_e('Documentation', 'wpvivid-backup-pro'); ?></h3>
                <a href="https://docs.wpvivid.com/" class="wpvivid-v2-tips-view-all"><?php esc_html_e('View All', 'wpvivid-backup-pro'); ?> →</a>
            </div>

            <div class="wpvivid-v2-tips-list">

                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-overview.html" class="wpvivid-v2-tips-title"><?php esc_html_e('Overview: Backup & Migration Pro', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-tip"><?php esc_html_e('Backup & Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>

                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-pro-dashboard.html" class="wpvivid-v2-tips-title"><?php esc_html_e('Dashboard', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-tip"><?php esc_html_e('Backup & Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>

                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/manual-backup-overview.html" class="wpvivid-v2-tips-title"><?php esc_html_e('Manual Backup', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-tip"><?php esc_html_e('Backup', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-schedule-overview.html" class="wpvivid-v2-tips-title"><?php esc_html_e('Backup Schedule', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-tip"><?php esc_html_e('Backup', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-multisites.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Back up WordPress Multisites', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-tip"><?php esc_html_e('Backup', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-rollback-overview.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Rollback (Auto Backup before Update)', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-import-site.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Import Site', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-export-site.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Export Site', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-migrate-wordpress-site-via-remote-storage.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Migrate A WordPress Site via Remote Storage', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-migrate-multisite-childsite-to-single-wordpress-install.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Migrate A Multisite Childsite to A Single WordPress', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>
                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-backup-pro-migrate-wordpress-multisite.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Migrate WordPress Multisite', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-news"><?php esc_html_e('Migration', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>


                <div class="wpvivid-v2-tips-item">
                    <span class="wpvivid-v2-tips-icon">→</span>
                    <div class="wpvivid-v2-tips-content">
                        <a href="https://docs.wpvivid.com/wpvivid-staging-pro-overview.html"
                           class="wpvivid-v2-tips-title"><?php esc_html_e('Overview: Staging', 'wpvivid-backup-pro'); ?></a>
                        <span class="wpvivid-v2-tips-tag wpvivid-v2-tag-new"><?php esc_html_e('Staging', 'wpvivid-backup-pro'); ?></span>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    public function dashboard_quick_action_card($is_backup_free_enabled)
    {
        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }
        ?>
        <!-- =============================
			WPvivid v2 - Quick Actions Card
			============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-card-action">
            <div class="wpvivid-v2-action-header">
                <h3><?php esc_html_e('Quick Actions', 'wpvivid-backup-pro'); ?></h3>
            </div>

            <div class="wpvivid-v2-dashboard-actions" style="<?php echo esc_attr($button_style); ?>">
                <button class="wpvivid-v2-dashboard-button-primary wpvivid-dashboard-manual-backup-action"><?php esc_html_e('Manual Backup', 'wpvivid-backup-pro'); ?></button>

                <button class="wpvivid-v2-dashboard-button-secondary wpvivid-dashboard-export-site-action"><?php esc_html_e('Export Site', 'wpvivid-backup-pro'); ?></button>

                <button class="wpvivid-v2-dashboard-button-secondary wpvivid-dashboard-import-site-action"><?php esc_html_e('Import Site', 'wpvivid-backup-pro'); ?></button>
            </div>
        </div>
        <script>
            jQuery('.wpvivid-dashboard-manual-backup-action').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup', 'wpvivid-backup'); ?>';
            });

            jQuery('.wpvivid-dashboard-export-site-action').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-site', 'wpvivid-export-site'); ?>';
            });

            jQuery('.wpvivid-dashboard-import-site-action').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-import-site', 'wpvivid-import-site'); ?>';
            });
        </script>
        <?php
    }

    public function dashboard_staging_card()
    {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_installed=false;
        $is_actived=false;
        $plugin_slug = 'wpvivid-staging/wpvivid-staging.php';
        $all_plugins = get_plugins();
        if ( isset( $all_plugins[ $plugin_slug ] ) ) {
            $is_installed = true;
        }

        if ( is_plugin_active( $plugin_slug ) ) {
            $is_actived = true;
        }

        global $wpdb;
        $table_name = $wpdb->base_prefix."wpvivid_options";
        $option_name = 'staging_site_data';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );

        if ($table_exists !== $table_name)
        {
            $staging_list = array();
        }
        else
        {
            $query =$wpdb->prepare('select option_value from '.$table_name.' where option_name = %s', $option_name);
            $result =$wpdb->get_var($query);
            if(empty($result))
            {
                $staging_list=array();
            }
            else
            {
                $staging_list=maybe_unserialize($result);
            }
        }

        ?>
        <!-- ======================================
			WPvivid v2 - Staging Card (full version)
			====================================== -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-staging">
            <!-- Header -->
            <div class="wpvivid-v2-staging-header">
                <h3><?php esc_html_e('Staging', 'wpvivid-backup-pro'); ?></h3>
                <?php
                if($is_installed && $is_actived && !empty($staging_list))
                {
                    ?>
                    <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-staging', 'wpvivid-staging'); ?>" class="wpvivid-v2-backup-view-all"><?php esc_html_e('View All', 'wpvivid-backup-pro'); ?> →</a>
                    <?php
                }
                ?>
            </div>

            <div class="wpvivid-v2-staging-content">

                <?php
                if($is_installed && $is_actived && !empty($staging_list))
                {
                    $display_count=3;
                    $current_count=0;
                    ?>
                    <!-- ====== ① Non-empty: Staging List ====== -->
                    <div class="wpvivid-v2-staging-list">
                        <?php
                        foreach ($staging_list as $staging_site)
                        {
                            $site_url=$staging_site['site_url'];
                            if(isset($staging_site['create_time']))
                            {
                                $create_time=$staging_site['create_time'];
                                $create_time = WPvivid_Time::format_local('M d, Y', $create_time);
                            }
                            else
                            {
                                $create_time='N/A';
                            }

                            $current_count++;
                            if($current_count <= $display_count)
                            {
                                ?>
                                <div class="wpvivid-v2-staging-item">
                                    <div class="wpvivid-v2-staging-info">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <div>
                                            <strong><a href="<?php echo esc_attr($site_url); ?>"><?php echo esc_html($site_url); ?></a></strong>
                                            <div class="wpvivid-v2-staging-meta">
                                                Created: <?php echo $create_time; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="wpvivid-v2-staging-actions">
                                        <a href="<?php echo esc_attr($site_url); ?>" class="wpvivid-v2-staging-link"><?php esc_html_e('Details', 'wpvivid-backup-pro'); ?> →</a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <p class="wpvivid-v2-staging-tip">
                            <?php esc_html_e('Safely test updates or new features in a staging site before going live.', 'wpvivid-backup-pro'); ?>
                        </p>
                    </div>
                    <?php
                }
                else
                {
                    ?>
                    <!-- ====== Empty State ====== -->
                    <div class="wpvivid-v2-staging-empty">
                        <div class="wpvivid-v2-staging-empty-icon">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                        </div>
                        <p class="wpvivid-v2-staging-empty-text">
                            <?php esc_html_e('No staging sites have been created yet.', 'wpvivid-backup-pro'); ?><br>
                            <?php esc_html_e('To use the staging feature, please install and activate the', 'wpvivid-backup-pro'); ?> <strong><?php esc_html_e('Staging Addon', 'wpvivid-backup-pro'); ?></strong>,
                            <?php esc_html_e('then create your first staging site.', 'wpvivid-backup-pro'); ?>
                        </p>
                        <?php
                        if($is_installed && $is_actived)
                        {
                            ?>
                            <button class="wpvivid-v2-dashboard-button-primary wpvivid-dashboard-staging-action"><?php esc_html_e('Create New Staging Site', 'wpvivid-backup-pro'); ?></button>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
                ?>
            </div>

        </div>
        <script>
            jQuery('.wpvivid-dashboard-staging-action').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-staging', 'wpvivid-staging'); ?>';
            });
        </script>
        <?php
    }

    function wpvivid_get_addon_meta( $items, $need_login ) {
        $status   = '';
        $can_href = false;

        switch ( $items['status'] ) {
            case 'Not available':
                $status   = 'Unavailable';
                $can_href = false;
                break;

            case 'Inactive':
                $status   = __('Activate', 'wpvivid-backup-pro');
                $can_href = false;
                break;

            case 'Installed':
            case 'Up to date':
                if ( ! empty( $items['requires_plugins'] ) ) {
                    $status   = '';
                    $can_href = true;
                    foreach ( $items['requires_plugins'] as $plugin ) {
                        if ( $plugin['status'] !== 'Installed' && $plugin['status'] !== 'Up to date' ) {
                            $status   = 'Install';
                            $can_href = false;
                            break;
                        }
                    }
                } else {
                    $status   = '';
                    $can_href = true;
                }
                break;

            case 'Un-installed':
                $status   = 'Install';
                $can_href = false;
                break;

            case 'Update now':
                $status   = 'Update';
                $can_href = true;
                break;

            default:
                $status   = '';
                $can_href = true;
                break;
        }

        $is_install = ! empty( $_REQUEST['install'] );

        if ( $is_install ) {
            $install_class = '';
        } elseif ( ! empty( $items['is_free'] ) ) {
            $install_class = 'wpvivid-addons';
        } elseif ( $need_login ) {
            $install_class = 'wpvivid-need-login';
        } else {
            $install_class = 'wpvivid-addons';
        }

        $installed_status = array( 'Installed', 'Up to date', 'Update now', 'Inactive' );
        $installed        = in_array( $items['status'], $installed_status, true );

        return compact( 'status', 'can_href', 'install_class', 'installed' );
    }

    public function dashboard_addons_tools_card()
    {
        $dashboard_addons_display = get_option('wpvivid_dashboard_addons_display', array());

        $dashboard_addons_display = wp_parse_args($dashboard_addons_display, array(
            'staging'                 => 1,
            'database_snapshots'      => 1,
            'unused_image_cleaner'    => 1,
            'url_replacement'         => 1,
            'rollback'                => 1,
            'roles_capabilities'      => 1,
            'export_import_post_page' => 1,
            'white_label'             => 1,
            'image_optimization_pro'  => 1,
        ));

        $show_dashboard_addons = false;

        foreach ($dashboard_addons_display as $display)
        {
            if (intval($display) === 1)
            {
                $show_dashboard_addons = true;
                break;
            }
        }

        if(is_multisite())
        {
            if(is_main_site())
            {
                $user_info= get_option('wpvivid_pro_user',false);
                $dashboard_info=get_option('wpvivid_dashboard_info',array());
                $last_login_time=get_option('wpvivid_last_login_time',0);
            }
            else
            {
                switch_to_blog(get_main_site_id());
                $user_info= get_option('wpvivid_pro_user',false);
                $dashboard_info=get_option('wpvivid_dashboard_info',array());
                $last_login_time=get_option('wpvivid_last_login_time',0);
                restore_current_blog();
            }
        }
        else
        {
            $user_info= get_option('wpvivid_pro_user',false);
            $dashboard_info=get_option('wpvivid_dashboard_info',array());
            $last_login_time=get_option('wpvivid_last_login_time',0);
        }

        $all_installed=true;
        $plugins=array();
        if(isset($dashboard_info) && !empty($dashboard_info))
        {
            $plugins=$this->get_plugins_status($dashboard_info);
            foreach ($plugins as $item)
            {
                if($item['status']=='Installed'||$item['status']=='Up to date'||$item['status']=='Update now')
                {
                }
                else
                {
                    $all_installed=false;
                }
            }
        }

        $need_login=false;
        if($user_info === false)
        {
            $need_login=true;
        }
        else
        {
            if($last_login_time+60*60*24>time())
            {
                $need_login=false;
            }
            else
            {
                if($all_installed)
                {
                    $need_login=false;
                }
                else
                {
                    $need_login=true;
                }
            }
        }

        $error = __('Please verify your Pro license again to avoid abuse of addons.', 'wpvivid-backup-pro');

        if($show_dashboard_addons)
        {
            ?>
            <!-- =============================
                WPvivid v2 - Addons & Tools (2 Column Layout)
                ============================= -->
            <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-addons">
                <div class="wpvivid-v2-addons-header">
                    <h3><?php esc_html_e('Addons & Tools', 'wpvivid-backup-pro'); ?></h3>
                    <?php
                    if($user_info !== false)
                    {
                        ?>
                        <a href="<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'plugins.php?s=wpvivid&plugin_status=all'); ?>" class="wpvivid-v2-addons-update-all"><?php esc_html_e('Update All', 'wpvivid-backup-pro'); ?></a>
                        <?php
                    }
                    ?>
                </div>

                <!-- License + Global Progress -->
                <div class="wpvivid-v2-license-section" style="">
                    <?php
                    if(isset($_REQUEST['install'])&&$_REQUEST['install'])
                    {
                        ?>
                        <div class="wpvivid-v2-global-progress">
                            <div class="wpvivid-v2-global-progress-bar" style="width: 45%;"></div>
                        </div>
                        <?php
                    }
                    else
                    {
                        if($need_login)
                        {
                            ?>
                            <div class="wpvivid-v2-license-input-row">
                                <input type="text" id="wpvivid_account_license" placeholder="<?php esc_attr_e('Enter your license key', 'wpvivid-backup-pro'); ?>" />
                                <button class="wpvivid-v2-license-auth"><?php esc_html_e('Authenticate', 'wpvivid-backup-pro'); ?></button>
                            </div>

                            <div class="wpvivid-v2-global-progress" style="display: none;">
                                <div class="wpvivid-v2-global-progress-bar" style="width: 45%;"></div>
                            </div>

                            <p class="wpvivid-v2-license-tip">
                                <?php
                                if(empty($dashboard_info))
                                {
                                    $text=sprintf(
                                        __('You can use either a father license or a child license to activate %s plugins', 'wpvivid-backup-pro'),
                                        apply_filters('wpvivid_white_label_display', 'WPvivid')
                                    );
                                }
                                else
                                {
                                    $text=__('Please verify your Pro license again to avoid abuse of addons.', 'wpvivid-backup-pro');
                                }
                                echo esc_html($text);
                                ?>
                            </p>
                            <?php
                        }
                    }
                    ?>
                </div>

                <?php
                if($user_info !== false)
                {
                    if(intval($dashboard_addons_display['staging']) === 1)
                    {
                        if(isset($plugins['staging_pro']))
                        {
                            $items = $plugins['staging_pro'];

                            $meta = $this->wpvivid_get_addon_meta( $items, $need_login );
                            $status        = $meta['status'];
                            $can_href      = $meta['can_href'];
                            $install_class = $meta['install_class'];
                            $installed     = $meta['installed'];

                            $page_url = apply_filters( 'wpvivid_addon_page_url', '', $items['slug'] );
                            $title    = apply_filters( 'wpvivid_addon_page_title', $items['name'], $items['slug'] );

                            ?>
                            <div class="wpvivid-v2-addon-card wpvivid-v2-addon-staging wpvivid-v2-addon-installed" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                <div class="wpvivid-v2-addon-top">
                                    <div class="wpvivid-v2-addon-title">
                                        <span class="dashicons dashicons-admin-site wpvivid-v2-addon-icon-staging"></span>
                                        <strong>
                                            <?php if ( $can_href && ! empty( $page_url ) ) : ?>
                                                <a href="<?php echo $page_url; ?>"><?php echo $title; ?></a>
                                            <?php else : ?>
                                                <a><?php echo $title; ?></a>
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                    <a class="wpvivid-v2-addon-update <?php echo $install_class; ?> <?php echo $status; ?>">
                                        <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                    </a>
                                </div>

                                <p class="wpvivid-v2-addon-desc">
                                    <?php echo esc_html($this->wpvivid_get_addon_desc($items)); ?>
                                </p>

                                <div class="wpvivid-v2-addon-footer">
                                    <?php if ( $installed ) : ?>
                                        <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                    <?php else : ?>
                                        <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                    <?php endif; ?>

                                    <?php if ( $can_href ) : ?>
                                        <a href="<?php echo apply_filters( 'wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-staging', 'wpvivid-staging' ); ?>"
                                           class="wpvivid-v2-addon-button"><?php esc_html_e('Create Staging', 'wpvivid-backup-pro'); ?> →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }

                    ?>
                    <!-- 🔸 Other Addons (Two Columns Grid) -->
                    <div class="wpvivid-v2-addons-grid-2col">
                        <?php
                        if($this->has_backup_pro($plugins))
                        {
                            $has_backup_pro=true;
                        }
                        else
                        {
                            $has_backup_pro=false;
                        }

                        if(intval($dashboard_addons_display['database_snapshots']) === 1)
                        {
                            if(isset($plugins['snapshot_database']))
                            {
                                $items = $plugins['snapshot_database'];

                                $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                                $status        = $meta['status'];
                                $can_href      = $meta['can_href'];
                                $install_class = $meta['install_class'];
                                $installed     = $meta['installed'];

                                $page_url = apply_filters( 'wpvivid_addon_page_url', '', $items['slug'] );
                                $title    = apply_filters( 'wpvivid_addon_page_title', $items['name'], $items['slug'] );
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed wpvivid-v2-addon-has-update" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons dashicons-database wpvivid-v2-addon-icon-database"></span>
                                            <strong>
                                                <?php if ( $can_href && ! empty( $page_url ) ) : ?>
                                                    <a href="<?php echo $page_url; ?>"><?php echo $title; ?></a>
                                                <?php else : ?>
                                                    <a><?php echo $title; ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <p class="wpvivid-v2-addon-desc">
                                        <?php echo esc_html($this->wpvivid_get_addon_desc($items)); ?>
                                    </p>

                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }

                        if(isset($plugins['backup_pro']))
                        {
                            $items = $plugins['backup_pro'];

                            $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                            $status        = $meta['status'];
                            $can_href      = $meta['can_href'];
                            $install_class = $meta['install_class'];
                            $installed     = $meta['installed'];

                            $backup_addons = array(
                                'unused_image_cleaner' => array(
                                    'icon'     => 'dashicons-format-gallery wpvivid-v2-addon-icon-image',
                                    'title'    => __('Unused Image Cleaner', 'wpvivid-backup-pro'),
                                    'desc'     => __('Analyze and find unused images in your media folder and delete them safely.', 'wpvivid-backup-pro'),
                                    'page_url' => apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-image-cleaner', 'wpvivid-image-cleaner'),
                                ),
                                'url_replacement'      => array(
                                    'icon'     => 'dashicons-admin-links wpvivid-v2-addon-icon-url',
                                    'title'    => __('URL Replacement', 'wpvivid-backup-pro'),
                                    'desc'     => __('Perform quick domain/URL replacement in the database safely.', 'wpvivid-backup-pro'),
                                    'page_url' => apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-import', 'wpvivid-export-import').'&url_replace',
                                ),
                                'rollback'             => array(
                                    'icon'     => 'dashicons-image-rotate wpvivid-v2-addon-icon-rollback',
                                    'title'    => __('Rollback', 'wpvivid-backup-pro'),
                                    'desc'     => __('Rollback your site to a prior state of plugins, themes, or WordPress core.', 'wpvivid-backup-pro'),
                                    'page_url' => apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-rollback', 'wpvivid-rollback'),
                                ),
                            );

                            if(intval($dashboard_addons_display['unused_image_cleaner']) !== 1)
                            {
                                unset($backup_addons['unused_image_cleaner']);
                            }
                            if(intval($dashboard_addons_display['url_replacement']) !== 1)
                            {
                                unset($backup_addons['url_replacement']);
                            }
                            if(intval($dashboard_addons_display['rollback']) !== 1)
                            {
                                unset($backup_addons['rollback']);
                            }

                            foreach ( $backup_addons as $addon_key => $addon )
                            {
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons <?php echo esc_attr($addon['icon']); ?>"></span>
                                            <strong>
                                                <?php if ( $can_href ) : ?>
                                                    <a href="<?php echo $addon['page_url']; ?>"><?php echo $addon['title']; ?></a>
                                                <?php else : ?>
                                                    <a><?php echo $addon['title']; ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <p class="wpvivid-v2-addon-desc">
                                        <?php echo esc_html($addon['desc']); ?>
                                    </p>
                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }

                        if(intval($dashboard_addons_display['roles_capabilities']) === 1)
                        {
                            if(isset($plugins['role_cap']))
                            {
                                $items = $plugins['role_cap'];

                                $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                                $status        = $meta['status'];
                                $can_href      = $meta['can_href'];
                                $install_class = $meta['install_class'];
                                $installed     = $meta['installed'];

                                $page_url = apply_filters( 'wpvivid_addon_page_url', '', $items['slug'] );
                                $title    = apply_filters( 'wpvivid_addon_page_title', $items['name'], $items['slug'] );
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed wpvivid-v2-addon-has-update" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons dashicons-admin-users wpvivid-v2-addon-icon-roles"></span>
                                            <strong>
                                                <?php if ( $can_href && ! empty( $page_url ) ) : ?>
                                                    <a href="<?php echo $page_url; ?>"><?php echo $title; ?></a>
                                                <?php else : ?>
                                                    <a><?php echo $title; ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <p class="wpvivid-v2-addon-desc">
                                        <?php echo esc_html($this->wpvivid_get_addon_desc($items)); ?>
                                    </p>

                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }

                        if(intval($dashboard_addons_display['export_import_post_page']) === 1)
                        {
                            if(isset($plugins['backup_pro']))
                            {
                                $items = $plugins['backup_pro'];

                                $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                                $status        = $meta['status'];
                                $can_href      = $meta['can_href'];
                                $install_class = $meta['install_class'];
                                $installed     = $meta['installed'];
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed wpvivid-v2-addon-has-update" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons dashicons-admin-page wpvivid-v2-addon-icon-export"></span>
                                            <strong>
                                                <?php if ( $can_href ) : ?>
                                                    <a href="<?php echo apply_filters( 'wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-import', 'wpvivid-export-import' ); ?>">
                                                        <?php esc_html_e('Export/Import Post or Page', 'wpvivid-backup-pro'); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <a><?php esc_html_e('Export/Import Post or Page', 'wpvivid-backup-pro'); ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <p class="wpvivid-v2-addon-desc">
                                        <?php esc_html_e('Export or import website content including pages, posts, terms, and images.', 'wpvivid-backup-pro'); ?>
                                    </p>

                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }

                        if(intval($dashboard_addons_display['white_label']) === 1)
                        {
                            if(isset($plugins['white_label']))
                            {
                                $items = $plugins['white_label'];

                                $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                                $status        = $meta['status'];
                                $can_href      = $meta['can_href'];
                                $install_class = $meta['install_class'];
                                $installed     = $meta['installed'];

                                $page_url = apply_filters( 'wpvivid_addon_page_url', '', $items['slug'] );
                                $title    = apply_filters( 'wpvivid_addon_page_title', $items['name'], $items['slug'] );
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed wpvivid-v2-addon-has-update" addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons dashicons-awards wpvivid-v2-addon-icon-rollback"></span>
                                            <strong>
                                                <?php if ( $can_href && ! empty( $page_url ) ) : ?>
                                                    <a href="<?php echo $page_url; ?>"><?php echo $title; ?></a>
                                                <?php else : ?>
                                                    <a><?php echo $title; ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <p class="wpvivid-v2-addon-desc">
                                        <?php echo esc_html($this->wpvivid_get_addon_desc($items)); ?>
                                    </p>

                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }

                        if(intval($dashboard_addons_display['image_optimization_pro']) === 1)
                        {
                            if(isset($plugins['imgoptim_pro']))
                            {
                                $items = $plugins['imgoptim_pro'];

                                $meta  = $this->wpvivid_get_addon_meta( $items, $need_login );
                                $status        = $meta['status'];
                                $can_href      = $meta['can_href'];
                                $install_class = $meta['install_class'];
                                $installed     = $meta['installed'];

                                $page_url = apply_filters( 'wpvivid_addon_page_url', '', $items['slug'] );
                                $title    = apply_filters( 'wpvivid_addon_page_title', $items['name'], $items['slug'] );
                                ?>
                                <div class="wpvivid-v2-addon-card wpvivid-v2-addon-installed wpvivid-v2-addon-has-update"
                                     addon-type="<?php echo esc_attr($items['slug']); ?>">
                                    <div class="wpvivid-v2-addon-top">
                                        <div class="wpvivid-v2-addon-title">
                                            <span class="dashicons dashicons-admin-page wpvivid-v2-addon-icon-image"></span>
                                            <strong>
                                                <?php if ( $can_href && ! empty( $page_url ) ) : ?>
                                                    <a href="<?php echo $page_url; ?>"><?php echo $title; ?></a>
                                                <?php else : ?>
                                                    <a><?php echo $title; ?></a>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <p class="wpvivid-v2-addon-desc">
                                        <?php echo esc_html($this->wpvivid_get_addon_desc($items)); ?>
                                    </p>

                                    <div class="wpvivid-v2-addon-footer">
                                        <?php if ( $installed ) : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-success"><?php esc_html_e('Installed', 'wpvivid-backup-pro'); ?></span>
                                        <?php else : ?>
                                            <span class="wpvivid-v2-addon-status wpvivid-v2-status-inactive"><?php esc_html_e('Uninstalled', 'wpvivid-backup-pro'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpvivid-v2-addon-button <?php echo $install_class; ?> <?php echo $status; ?>">
                                            <?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <?php
                }
                ?>

            </div>

            <script>
                var retry_times = 0;
                var max_retry_times = 3;

                jQuery('.wpvivid-v2-license-auth').click(function()
                {
                    wpvivid_dashboard_login();
                });

                function wpvivid_dashboard_login()
                {
                    var license = jQuery('#wpvivid_account_license').val();
                    var ajax_data={
                        'action':'wpvivid_dashboard_login',
                        'license':license,
                    };

                    var login_msg = '<?php echo sprintf(__('Logging in to your %s account', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid')); ?>';
                    wpvivid_lock_login(true);
                    wpvivid_login_progress(login_msg);
                    wpvivid_post_request_addon(ajax_data, function(data)
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            //need_active
                            if(jsonarray.need_active)
                            {
                                retry_times=0;
                                wpvivid_login_progress('You have successfully logged in');
                                wpvivid_active_site();
                            }
                            else
                            {
                                retry_times=0;
                                wpvivid_login_progress('You have successfully logged in');
                                location.reload();
                            }
                        }
                        else
                        {
                            retry_times++;
                            if(retry_times<max_retry_times)
                            {
                                wpvivid_dashboard_login();
                            }
                            else
                            {
                                if (/cURL error 28/i.test(jsonarray.error))
                                {
                                    wpvivid_dashboard_login_direct();
                                }
                                else
                                {
                                    wpvivid_lock_login(false,jsonarray.error);
                                }
                            }
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown)
                    {
                        retry_times++;
                        if(retry_times<max_retry_times)
                        {
                            wpvivid_dashboard_login();
                        }
                        else
                        {
                            var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                            wpvivid_lock_login(false,error_message);
                        }
                    });
                }

                function wpvivid_dashboard_login_direct()
                {
                    var license = jQuery('#wpvivid_account_license').val();
                    var ajax_data={
                        'action':'wpvivid_dashboard_login_direct',
                        'license':license,
                    };

                    var login_msg = '<?php echo sprintf(__('Logging in to your %s account', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid')); ?>';
                    wpvivid_lock_login(true);
                    wpvivid_login_progress(login_msg);
                    wpvivid_post_request_addon(ajax_data, function(data)
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            //need_active
                            if(jsonarray.need_active)
                            {
                                retry_times=0;
                                wpvivid_login_progress('You have successfully logged in');
                                wpvivid_active_site();
                            }
                            else
                            {
                                retry_times=0;
                                wpvivid_login_progress('You have successfully logged in');
                                location.reload();
                            }
                        }
                        else
                        {
                            retry_times++;
                            if(retry_times<max_retry_times)
                            {
                                wpvivid_dashboard_login_direct();
                            }
                            else
                            {
                                wpvivid_lock_login(false,jsonarray.error);
                            }
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown)
                    {
                        retry_times++;
                        if(retry_times<max_retry_times)
                        {
                            wpvivid_dashboard_login_direct();
                        }
                        else
                        {
                            var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                            wpvivid_lock_login(false,error_message);
                        }
                    });
                }

                function wpvivid_active_site()
                {
                    var license = jQuery('#wpvivid_account_license').val();
                    var ajax_data={
                        'action':'wpvivid_dashboard_active',
                        'license':license,
                    };

                    wpvivid_lock_login(true);
                    wpvivid_login_progress('Activating your license on the current site');
                    wpvivid_post_request_addon(ajax_data, function(data)
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            wpvivid_login_progress('Your license has been activated successfully');
                            location.reload();
                        }
                        else
                        {
                            wpvivid_lock_login(false,jsonarray.error);
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown)
                    {
                        var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                        wpvivid_lock_login(false,error_message);
                    });
                }

                function wpvivid_lock_login(lock,error='')
                {
                    if(lock)
                    {
                        jQuery('.wpvivid-v2-license-auth').css({'pointer-events': 'none', 'opacity': '0.4'});
                        jQuery('.wpvivid-v2-global-progress').show();
                    }
                    else
                    {
                        jQuery('.wpvivid-v2-license-tip').html('');
                        jQuery('.wpvivid-v2-global-progress').hide();
                        jQuery('.wpvivid-v2-license-auth').css({'pointer-events': 'auto', 'opacity': '1'});

                        if(error!=='')
                        {
                            jQuery('.wpvivid-v2-license-tip').html(error);
                        }
                    }
                }

                function wpvivid_login_progress(log)
                {
                    jQuery('.wpvivid-v2-license-tip').html(log);
                }

                jQuery('.wpvivid-addons').on('click', function()
                {
                    if(jQuery(this).hasClass('Activate'))
                    {
                        var json = {};
                        json['plugins_list'] = Array();
                        var addon_type = jQuery(this).closest('.wpvivid-v2-addon-card').attr('addon-type');
                        json['plugins_list'].push(addon_type);

                        var ajax_data={
                            'action':'wpvivid_activate_plugin',
                            'plugins':json['plugins_list'],
                        };

                        wpvivid_post_request_addon(ajax_data, function(data)
                        {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'success')
                            {
                                location.href=jsonarray.href;
                            }
                        }, function(XMLHttpRequest, textStatus, errorThrown)
                        {
                        });
                    }
                    if(jQuery(this).hasClass('Install') || jQuery(this).hasClass('Update'))
                    {
                        var json = {};
                        json['plugins_list'] = Array();

                        var addon_type = jQuery(this).closest('.wpvivid-v2-addon-card').attr('addon-type');
                        json['plugins_list'].push(addon_type);

                        var ajax_data={
                            'action':'wpvivid_init_plugin_install_ex',
                            'plugins':json['plugins_list'],
                        };

                        wpvivid_post_request_addon(ajax_data, function(data)
                        {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'success')
                            {
                                location.href=jsonarray.href;
                            }
                            else
                            {
                                location.reload();
                            }
                        }, function(XMLHttpRequest, textStatus, errorThrown)
                        {
                            location.reload();
                        });
                    }
                });

                jQuery('.wpvivid-need-login').on('click', function()
                {
                    alert("<?php echo $error; ?>");
                });
            </script>

            <?php
        }
    }

    public function get_backup_content($backup)
    {
        $content['db']=false;
        $content['files']=false;
        $content['custom']=false;

        if(isset($backup['backup_info'])&&!empty($backup['backup_info']))
        {
            foreach ($backup['backup_info']['types'] as $type=>$data)
            {
                if($type==='Database')
                {
                    $content['db']=true;
                }
                else if($type==='Additional Databases')
                {
                    $content['db']=true;
                }
                else if($type==='Others')
                {
                    $content['custom']=true;
                }
                else if($type==='themes' || $type==='plugins' || $type==='uploads' || $type==='wp-content' || $type==='Wordpress Core')
                {
                    $content['files']=true;
                }
            }


            return $content;
        }

        $has_db = false;
        $has_file = false;
        $has_custom = false;
        $type_list = array();
        $ismerge = false;

        if(isset($backup['backup']['files']))
        {
            foreach ($backup['backup']['files'] as $key => $value)
            {
                $file_name = $value['file_name'];
                if(WPvivid_backup_pro_function::is_wpvivid_db_backup($file_name))
                {
                    $has_db = true;
                    if(!in_array('Database', $type_list))
                    {
                        $type_list[] = 'Database';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_themes_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('Themes', $type_list)) {
                        $type_list[] = 'Themes';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_plugin_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('Plugins', $type_list)) {
                        $type_list[] = 'Plugins';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_uploads_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('wp-content/uploads', $type_list)) {
                        $type_list[] = 'wp-content/uploads';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_content_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('wp-content', $type_list)) {
                        $type_list[] = 'wp-content';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_core_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('Wordpress Core', $type_list)) {
                        $type_list[] = 'Wordpress Core';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_other_backup($file_name))
                {
                    $has_custom = true;
                    if(!in_array('Additional Folder', $type_list)) {
                        $type_list[] = 'Additional Folder';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_additional_db_backup($file_name))
                {
                    $has_file = true;
                    if(!in_array('Additional Databases', $type_list)) {
                        $type_list[] = 'Additional Databases';
                    }
                }
                else if(WPvivid_backup_pro_function::is_wpvivid_all_backup($file_name))
                {
                    $ismerge = true;
                }
            }
        }
        //all
        if($ismerge)
        {
            $backup_item = new WPvivid_New_Backup_Item($backup);
            $files_info=array();
            foreach ($backup['backup']['files'] as $file)
            {
                $file_name = $file['file_name'];
                $files_info[$file_name]=$backup_item->get_file_info($file_name);
            }
            $info=array();
            foreach ($files_info as $file_name=>$file_info)
            {
                if(isset($file_info['has_child']))
                {
                    if(isset($file_info['child_file']))
                    {
                        foreach ($file_info['child_file'] as $child_file_name=>$child_file_info)
                        {
                            if(isset($child_file_info['file_type']))
                            {
                                $info['type'][] = $child_file_info['file_type'];
                            }
                        }
                    }
                }
                else {
                    if(isset($file_info['file_type']))
                    {
                        $info['type'][] = $file_info['file_type'];
                    }
                }
            }

            if(isset($info['type']))
            {
                foreach ($info['type'] as $backup_content)
                {
                    if ($backup_content === 'databases')
                    {
                        $has_db = true;
                        if(!in_array('Database', $type_list))
                        {
                            $type_list[] = 'Database';
                        }
                    }
                    if($backup_content === 'themes')
                    {
                        $has_file = true;
                        if(!in_array('Themes', $type_list))
                        {
                            $type_list[] = 'Themes';
                        }
                    }
                    if($backup_content === 'plugin')
                    {
                        $has_file = true;
                        if(!in_array('Plugins', $type_list))
                        {
                            $type_list[] = 'Plugins';
                        }
                    }
                    if($backup_content === 'upload')
                    {
                        $has_file = true;
                        if(!in_array('wp-content/uploads', $type_list))
                        {
                            $type_list[] = 'wp-content/uploads';
                        }
                    }
                    if($backup_content === 'wp-content')
                    {
                        $has_file = true;
                        if(!in_array('wp-content', $type_list))
                        {
                            $type_list[] = 'wp-content';
                        }
                    }
                    if($backup_content === 'wp-core')
                    {
                        $has_file = true;
                        if(!in_array('Wordpress Core', $type_list))
                        {
                            $type_list[] = 'Wordpress Core';
                        }
                    }
                    if($backup_content === 'custom')
                    {
                        $has_custom = true;
                        if(!in_array('Additional Folder', $type_list))
                        {
                            $type_list[] = 'Additional Folder';
                        }
                    }
                    if($backup_content === 'additional_databases')
                    {
                        $has_file = true;
                        if(!in_array('Additional Databases', $type_list))
                        {
                            $type_list[] = 'Additional Databases';
                        }
                    }
                }
            }
        }

        $content['db']=$has_db;
        $content['files']=$has_file;
        $content['custom']=$has_custom;

        return $content;
    }

    public function dashboard_manual_backup_card($is_backup_free_enabled)
    {
        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }

        $has_manual_data=false;
        $manual_backup=array();
        ?>
        <!-- =============================
			WPvivid v2 - Manual Backup
			============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-last-backup">
            <div class="wpvivid-v2-last-backup-header">
                <h3><?php esc_html_e('Last Manual Backup', 'wpvivid-backup-pro'); ?></h3>
            </div>

            <?php
            if ( class_exists( 'WPvivid_New_BackupList' ) )
            {
                $backup_list = new WPvivid_New_BackupList();
                $all_backups = $backup_list->get_all_backup();

                if ( is_array( $all_backups ) && !empty( $all_backups ) )
                {
                    foreach ($all_backups as $backup)
                    {
                        if($backup['type'] === 'Manual')
                        {
                            $has_manual_data=true;
                            $manual_backup=$backup;
                            break;
                        }
                    }
                }
            }
            else
            {
                $has_manual_data=false;
            }

            if($has_manual_data)
            {
                $backup_id=$manual_backup['id'];
                $backup_create_time=$manual_backup['create_time'];
                $backup_type=$manual_backup['type'];
                if(!empty($manual_backup['remote']))
                {
                    $remote=array_shift($manual_backup['remote']);
                    $cloud_storage=$this->get_cloud_storage_info($remote['type']);
                    $backup_destination=$cloud_storage['type'];
                }
                else
                {
                    $backup_destination='Localhost';
                }

                $backup_size=0;
                foreach ($manual_backup['backup']['files'] as $file)
                {
                    $backup_size+=$file['size'];
                }
                $backup_size=size_format($backup_size,2);

                $get_backup_content=$this->get_backup_content($manual_backup);
                if( $get_backup_content['files'] )
                {
                    $has_file=true;
                }
                else if( $get_backup_content['custom'] )
                {
                    $has_file=true;
                }
                else
                {
                    $has_file=false;
                }

                if( $get_backup_content['db'] )
                {
                    $has_db=true;
                }
                else
                {
                    $has_db=false;
                }

                $backup_content='File + Database';
                if($has_file && !$has_db)
                {
                    $backup_content='File';
                }
                else if(!$has_file && $has_db)
                {
                    $backup_content='Database';
                }
                else if($has_file && $has_db)
                {
                    $backup_content='File + Database';
                }

                $backup_log=$backup_id.'_backup_log.txt';
                ?>
                <div class="wpvivid-v2-last-backup-content">
                    <div class="wpvivid-v2-last-backup-time">
                        <span class="dashicons dashicons-backup"></span>
                        <strong>
                            <?php
                            echo WPvivid_Time::format_local("M d, Y — H:i", $backup_create_time);
                            ?>
                        </strong>
                    </div>

                    <div class="wpvivid-v2-last-backup-meta">
                        <div><span><?php esc_html_e('Size', 'wpvivid-backup-pro'); ?>: <strong><?php echo $backup_size; ?></strong></span><span> | </span>
                            <span><span><?php esc_html_e('Type', 'wpvivid-backup-pro'); ?>: </span><span><?php echo $backup_type; ?></span><span> | </span><span><?php echo $backup_content; ?></span></span></div>
                        <div><?php esc_html_e('Destination', 'wpvivid-backup-pro'); ?>: <?php echo $backup_destination; ?></div>
                        <div><?php esc_html_e('Backup ID', 'wpvivid-backup-pro'); ?>: <?php echo $backup_id; ?></div>
                    </div>

                    <div class="wpvivid-v2-last-backup-actions" style="<?php echo esc_attr($button_style); ?>">
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore').'&log='.$backup_log; ?>"><?php esc_html_e('View Log', 'wpvivid-backup-pro'); ?> →</a>
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore').'&download=1&backup_id='.$backup_id; ?>"><?php esc_html_e('Download', 'wpvivid-backup-pro'); ?> →</a>
                    </div>
                </div>
                <?php
            }
            else
            {
                ?>
                <div class="wpvivid-v2-last-backup-empty">
                    <div class="wpvivid-v2-last-backup-empty-icon">
                        <span class="dashicons dashicons-backup"></span>
                    </div>
                    <div class="wpvivid-v2-last-backup-empty-content">
                        <strong><?php esc_html_e('No Manual Backups Found', 'wpvivid-backup-pro'); ?></strong>
                        <p><?php esc_html_e('Your site hasn\'t been backed up manually yet.', 'wpvivid-backup-pro'); ?></p>
                        <button class="wpvivid-v2-last-backup-start" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Create Your First Backup', 'wpvivid-backup-pro'); ?></button>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <script>
            jQuery('.wpvivid-v2-last-backup-start').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup', 'wpvivid-backup'); ?>';
            });
        </script>
        <?php
    }

    public function dashboard_backup_retention_card($is_backup_free_enabled)
    {
        $options=get_option('wpvivid_common_setting');
        if(isset($options['manual_max_backup_count']))
            $manual_max_backup_count = $options['manual_max_backup_count'];
        else
            $manual_max_backup_count = WPVIVID_DEFAULT_LOCAL_BACKUP_COUNT;
        $manual_max_backup_count=intval($manual_max_backup_count);

        if(isset($options['manual_max_backup_db_count']))
            $manual_max_backup_db_count = $options['manual_max_backup_db_count'];
        else
            $manual_max_backup_db_count = WPVIVID_DEFAULT_LOCAL_BACKUP_COUNT;
        $manual_max_backup_db_count=intval($manual_max_backup_db_count);

        if(isset($options['manual_max_remote_backup_count']))
            $manual_max_remote_backup_count = $options['manual_max_remote_backup_count'];
        else if(isset($options['max_remote_backup_count']))
            $manual_max_remote_backup_count =$options['max_remote_backup_count'];
        else
            $manual_max_remote_backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        $manual_max_remote_backup_count=intval($manual_max_remote_backup_count);
        if($manual_max_remote_backup_count==0)
        {
            $manual_max_remote_backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        }

        if(isset($options['manual_max_remote_backup_db_count']))
            $manual_max_remote_backup_db_count = $options['manual_max_remote_backup_db_count'];
        else if(isset($options['max_remote_backup_db_count']))
            $manual_max_remote_backup_db_count = $options['max_remote_backup_db_count'];
        else
            $manual_max_remote_backup_db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        $manual_max_remote_backup_db_count=intval($manual_max_remote_backup_db_count);
        if($manual_max_remote_backup_db_count==0)
        {
            $manual_max_remote_backup_db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        }

        if(isset($options['schedule_max_backup_count']))
            $schedule_max_backup_count = $options['schedule_max_backup_count'];
        else
            $schedule_max_backup_count = WPVIVID_DEFAULT_LOCAL_BACKUP_COUNT;
        $schedule_max_backup_count=intval($schedule_max_backup_count);

        if(isset($options['schedule_max_backup_db_count']))
            $schedule_max_backup_db_count = $options['schedule_max_backup_db_count'];
        else
            $schedule_max_backup_db_count = WPVIVID_DEFAULT_LOCAL_BACKUP_COUNT;
        $schedule_max_backup_db_count=intval($schedule_max_backup_db_count);

        if(isset($options['schedule_max_remote_backup_count']))
            $schedule_max_remote_backup_count = $options['schedule_max_remote_backup_count'];
        else if(isset($options['max_remote_backup_count']))
            $schedule_max_remote_backup_count = $options['max_remote_backup_count'];
        else
            $schedule_max_remote_backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        $schedule_max_remote_backup_count=intval($schedule_max_remote_backup_count);
        if($schedule_max_remote_backup_count==0)
        {
            $schedule_max_remote_backup_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        }
        if(isset($options['schedule_max_remote_backup_db_count']))
            $schedule_max_remote_backup_db_count = $options['schedule_max_remote_backup_db_count'];
        else if(isset($options['max_remote_backup_db_count']))
            $schedule_max_remote_backup_db_count = $options['max_remote_backup_db_count'];
        else
            $schedule_max_remote_backup_db_count = WPVIVID_DEFAULT_REMOTE_BACKUP_COUNT;
        $schedule_max_remote_backup_db_count=intval($schedule_max_remote_backup_db_count);


        if(isset($options['incremental_max_db_count']))
            $incremental_max_db_count = $options['incremental_max_db_count'];
        else
            $incremental_max_db_count = WPVIVID_DEFAULT_INCREMENTAL_REMOTE_BACKUP_COUNT;
        $incremental_max_db_count=intval($incremental_max_db_count);

        if(isset($options['incremental_max_backup_count']))
            $incremental_max_backup_count = $options['incremental_max_backup_count'];
        else
            $incremental_max_backup_count = WPVIVID_DEFAULT_INCREMENTAL_REMOTE_BACKUP_COUNT;
        $incremental_max_backup_count=intval($incremental_max_backup_count);

        if(isset($options['incremental_max_remote_backup_count']))
            $incremental_max_remote_backup_count = $options['incremental_max_remote_backup_count'];
        else
            $incremental_max_remote_backup_count = WPVIVID_DEFAULT_INCREMENTAL_REMOTE_BACKUP_COUNT;
        $incremental_max_remote_backup_count=intval($incremental_max_remote_backup_count);

        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }
        ?>
        <!-- =============================
            WPvivid v2 - Backup Retention
            ============================= -->
        <div class="wpvivid-v2-backup-retention-container">
            <div class="wpvivid-v2-backup-retention-header">
                <h3 class="wpvivid-v2-backup-retention-title"><?php esc_html_e('Backup Retention', 'wpvivid-backup-pro'); ?></h3>
                <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-setting', 'wpvivid-setting'); ?>" class="wpvivid-v2-backup-retention-edit" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Edit', 'wpvivid-backup-pro'); ?> →</a>
            </div>

            <div class="wpvivid-v2-backup-retention-tabs" style="<?php echo esc_attr($button_style); ?>">
                <button class="wpvivid-v2-backup-retention-tab wpvivid-v2-manual-backup-retention-tab active"><?php esc_html_e('Manual Backup', 'wpvivid-backup-pro'); ?></button>
                <button class="wpvivid-v2-backup-retention-tab wpvivid-v2-general-backup-retention-tab"><?php esc_html_e('Schedule (General)', 'wpvivid-backup-pro'); ?></button>
                <button class="wpvivid-v2-backup-retention-tab wpvivid-v2-incremental-backup-retention-tab"><?php esc_html_e('Schedule (Incremental)', 'wpvivid-backup-pro'); ?></button>
            </div>

            <div class="wpvivid-v2-backup-retention-content-manual">
                <div class="wpvivid-v2-backup-retention-section active">
                    <div class="wpvivid-v2-backup-retention-row">
                        <span><?php esc_html_e('File backups retained', 'wpvivid-backup-pro'); ?></span>
                        <span><?php echo $manual_max_backup_count; ?></span>
                    </div>
                    <div class="wpvivid-v2-backup-retention-row">
                        <span><?php esc_html_e('Database backups retained', 'wpvivid-backup-pro'); ?></span>
                        <span><?php echo $manual_max_backup_db_count; ?></span>
                    </div>
                    <div class="wpvivid-v2-backup-retention-row">
                        <span><?php esc_html_e('(remote storage) File backups retained', 'wpvivid-backup-pro'); ?></span>
                        <span><?php echo $manual_max_remote_backup_count; ?></span>
                    </div>
                    <div class="wpvivid-v2-backup-retention-row">
                        <span><?php esc_html_e('(remote storage) Database backups retained', 'wpvivid-backup-pro'); ?></span>
                        <span><?php echo $manual_max_remote_backup_db_count; ?></span>
                    </div>
                </div>
            </div>
            <!-- Schedule (General) -->
            <div class="wpvivid-v2-backup-retention-content-schedule-gen" style="display: none;">
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(localhost) File backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $schedule_max_backup_count; ?></span>
                </div>
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(localhost) Database backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $schedule_max_backup_db_count; ?></span>
                </div>
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(remote storage) File backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $schedule_max_remote_backup_count; ?></span>
                </div>
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(remote storage) Database backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $schedule_max_remote_backup_db_count; ?></span>
                </div>
            </div>

            <!-- Schedule (Incremental) -->
            <div class="wpvivid-v2-backup-retention-content-schedule-icre" style="display: none;">
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(localhost) Incremental database backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $incremental_max_db_count; ?></span>
                </div>
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(localhost) Cycles of incremental backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $incremental_max_backup_count; ?></span>
                </div>
                <div class="wpvivid-v2-backup-retention-row">
                    <span><?php esc_html_e('(remote storage) Cycles of incremental backups retained', 'wpvivid-backup-pro'); ?></span>
                    <span><?php echo $incremental_max_remote_backup_count; ?></span>
                </div>
            </div>
        </div>
        <script>
            function wpvivid_remove_retention_class()
            {
                jQuery('.wpvivid-v2-backup-retention-tab').removeClass('active');
            }

            jQuery('.wpvivid-v2-manual-backup-retention-tab').on('click', function()
            {
                wpvivid_remove_retention_class();
                jQuery('.wpvivid-v2-manual-backup-retention-tab').addClass('active');
                jQuery('.wpvivid-v2-backup-retention-content-manual').show();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-gen').hide();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-icre').hide();
            });

            jQuery('.wpvivid-v2-general-backup-retention-tab').on('click', function()
            {
                wpvivid_remove_retention_class();
                jQuery('.wpvivid-v2-general-backup-retention-tab').addClass('active');
                jQuery('.wpvivid-v2-backup-retention-content-manual').hide();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-gen').show();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-icre').hide();
            });

            jQuery('.wpvivid-v2-incremental-backup-retention-tab').on('click', function()
            {
                wpvivid_remove_retention_class();
                jQuery('.wpvivid-v2-incremental-backup-retention-tab').addClass('active');
                jQuery('.wpvivid-v2-backup-retention-content-manual').hide();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-gen').hide();
                jQuery('.wpvivid-v2-backup-retention-content-schedule-icre').show();
            });
        </script>
        <?php
    }

    public function dashboard_general_schedule_card($is_backup_free_enabled)
    {
        $offset=get_option('gmt_offset');
        $enable_schedules_backups=apply_filters('wpvivid_get_general_schedule_status',false);
        $data=$this->get_general_schedules_data();

        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }
        ?>
        <!-- =============================
				WPvivid v2 - General Schedule
				============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-schedule-general">
            <div class="wpvivid-v2-schedule-header">
                <h3><?php esc_html_e('General Schedule', 'wpvivid-backup-pro'); ?></h3>
                <div>
                    <?php
                    if($enable_schedules_backups)
                    {
                        ?>
                        <span class="wpvivid-v2-schedule-status wpvivid-v2-status-success"><?php esc_html_e('Enabled', 'wpvivid-backup-pro'); ?></span>
                        <?php
                    }
                    else
                    {
                        ?>
                        <span class="wpvivid-v2-schedule-status wpvivid-v2-status-error"><?php esc_html_e('Disabled', 'wpvivid-backup-pro'); ?></span>
                        <?php
                    }
                    ?>
                </div>

            </div>

            <?php
            if($enable_schedules_backups)
            {
                ?>
                <div class="wpvivid-v2-schedule-content">
                    <div class="wpvivid-v2-schedule-last">
                        <span class="dashicons dashicons-yes"></span>
                        <?php
                        if($data['last_backup_time'] !== 'N/A')
                        {
                            ?>
                            <strong><?php esc_html_e('Last Run', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['last_backup_time']; ?> — <span class="wpvivid-v2-status-success  wpvivid-v2-status-span"><?php echo $data['last_backup_status']; ?></span>
                            <?php
                        }
                        else
                        {
                            ?>
                            <strong><?php esc_html_e('Last Run', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['last_backup_time']; ?>
                            <?php
                        }
                        ?>
                    </div>
                    <div class="wpvivid-v2-schedule-time">
                        <span class="dashicons dashicons-backup"></span>
                        <strong><?php esc_html_e('Next Run', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['next_backup_time']; ?>
                    </div>

                    <div class="wpvivid-v2-schedule-meta">
                        <div><strong><?php esc_html_e('Type', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['next_backup_type']; ?> | <strong><?php esc_html_e('Frequency', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['next_backup_cycle']; ?></div>
                        <div><strong><?php esc_html_e('Destination', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['next_backup_destination']; ?></div>
                        <div><strong><?php esc_html_e('Estimated Size', 'wpvivid-backup-pro'); ?>:</strong> <?php echo $data['next_backup_estimate_size']; ?></div>
                    </div>



                    <div class="wpvivid-v2-schedule-actions" style="<?php echo esc_attr($button_style); ?>">
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule').'&full_backup'; ?>"><?php esc_html_e('View Details', 'wpvivid-backup-pro'); ?> →</a>
                    </div>
                </div>
                <?php
            }
            else
            {
                ?>
                <div class="wpvivid-v2-schedule-empty">
                    <div class="wpvivid-v2-schedule-empty-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>

                    <div class="wpvivid-v2-schedule-empty-content">
                        <strong><?php esc_html_e('No Backup Schedules Set', 'wpvivid-backup-pro'); ?></strong>
                        <p><?php esc_html_e('You haven\'t configured any scheduled backups yet.', 'wpvivid-backup-pro'); ?></p>
                        <button class="wpvivid-v2-schedule-create wpvivid-dashboard-create-general-schedule" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Create or Enable Schedule', 'wpvivid-backup-pro'); ?></button>
                    </div>
                </div>
                <?php
            }
            ?>


        </div>
        <script>
            jQuery('.wpvivid-dashboard-create-general-schedule').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule').'&full_backup'; ?>';
            });
        </script>
        <?php
    }

    public function dashboard_incremental_schedule_card($is_backup_free_enabled)
    {
        $offset=get_option('gmt_offset');
        $enable_incremental_schedules=get_option('wpvivid_enable_incremental_schedules', false);
        $data=$this->get_incremental_schedules_data();

        $backup_id='';
        if(isset($data['incremental_backup_id']))
        {
            $backup_id=$data['incremental_backup_id'];
        }
        $backup_log=$backup_id.'_backup_log.txt';

        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }
        ?>
        <!-- =============================
             WPvivid v2 - Incremental Schedule
             ============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-schedule-incremental">
            <div class="wpvivid-v2-schedule-header">
                <h3><?php esc_html_e('Incremental Schedule', 'wpvivid-backup-pro'); ?></h3>
                <div>
                    <?php
                    if($enable_incremental_schedules)
                    {
                        ?>
                        <span class="wpvivid-v2-schedule-status wpvivid-v2-status-success"><?php esc_html_e('Enabled', 'wpvivid-backup-pro'); ?></span>
                        <?php
                    }
                    else
                    {
                        ?>
                        <span class="wpvivid-v2-schedule-status wpvivid-v2-status-error"><?php esc_html_e('Disabled', 'wpvivid-backup-pro'); ?></span>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <?php
            if($enable_incremental_schedules)
            {
                $incremental_backup_schedule='N/A';
                $database_backup_schedule='N/A';
                $incremental_schedules=get_option('wpvivid_incremental_schedules');
                if(!empty($incremental_schedules))
                {
                    $schedule=array_shift($incremental_schedules);
                    $incremental_backup_schedule=$schedule['incremental_files_recurrence'];
                    $database_backup_schedule=$schedule['incremental_db_recurrence'];
                    $recurrence = wp_get_schedules();
                    if (isset($recurrence[$incremental_backup_schedule]))
                    {
                        $incremental_backup_schedule = $recurrence[$incremental_backup_schedule]['display'];
                    }
                    if (isset($recurrence[$database_backup_schedule]))
                    {
                        $database_backup_schedule = $recurrence[$database_backup_schedule]['display'];
                    }
                }
                ?>
                <div class="wpvivid-v2-schedule-content">
                    <div class="wpvivid-v2-schedule-meta">
                        <div><strong><?php esc_html_e('Linked Full Backup Cycle', 'wpvivid-backup-pro'); ?>: </strong><a><?php echo $data['last_full_backup_time']; ?></a></div>
                    </div>
                    <div class="wpvivid-v2-schedule-last">
                        <div><span class="dashicons dashicons-clock"></span><strong><?php esc_html_e('Last Run', 'wpvivid-backup-pro'); ?>: </strong></div>

                        <div class="wpvivid-v2-schedule-time-last-run">
                            <div><span><span><?php esc_html_e('Database', 'wpvivid-backup-pro'); ?>: </span><span><?php echo $data['last_db_backup_time']; ?></span></span>
                                <?php
                                if($data['last_db_backup_status'] !== 'N/A')
                                {
                                    ?>
                                    -<span class="wpvivid-v2-status-success wpvivid-v2-status-span"><?php echo $data['last_db_backup_status']; ?></span>
                                    <?php
                                }
                                ?>
                            </div>
                            <p><span><span><?php esc_html_e('Files', 'wpvivid-backup-pro'); ?>: </span><span><?php echo $data['last_files_backup_time']; ?></span></span>
                                <?php
                                if($data['last_files_backup_status'] !== 'N/A')
                                {
                                    ?>
                                    -<span class="wpvivid-v2-status-success wpvivid-v2-status-span"><?php echo $data['last_files_backup_status']; ?></span>
                                    <?php
                                }
                                ?>
                            </p>
                        </div>
                    </div>


                    <div class="wpvivid-v2-schedule-time">

                        <div><span class="dashicons dashicons-update-alt"></span><strong><?php esc_html_e('Next Run', 'wpvivid-backup-pro'); ?>: </strong></div>

                        <div class="wpvivid-v2-schedule-time-next-run">
                            <div><span><span><?php esc_html_e('Database', 'wpvivid-backup-pro'); ?>: </span><span><?php echo $data['next_db_backup']; ?></span></span><span>(<?php echo $database_backup_schedule; ?>)</span></div>
                            <p><span><span><?php esc_html_e('Files', 'wpvivid-backup-pro'); ?>: </span><span><?php echo $data['next_files_backup']; ?></span></span><span>(<?php echo $incremental_backup_schedule; ?>)</span></p>
                        </div>
                    </div>

                    <div class="wpvivid-v2-schedule-actions">
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore').'&log='.$backup_log; ?>" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('View Logs', 'wpvivid-backup-pro'); ?> →</a>
                    </div>
                </div>
                <?php
            }
            else
            {
                ?>
                <div class="wpvivid-v2-schedule-empty">
                    <div class="wpvivid-v2-schedule-empty-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>

                    <div class="wpvivid-v2-schedule-empty-content">
                        <strong><?php esc_html_e('No Incremental Schedules Set', 'wpvivid-backup-pro'); ?></strong>
                        <p><?php esc_html_e('You haven\'t enable the incremental schedule yet.', 'wpvivid-backup-pro'); ?></p>
                        <button class="wpvivid-v2-schedule-create wpvivid-dashboard-create-incremental-schedule" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Enable Incremental Schedule', 'wpvivid-backup-pro'); ?></button>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <script>
            jQuery('.wpvivid-dashboard-create-incremental-schedule').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule').'&incremental_backup_schedules'; ?>';
            });
        </script>
        <?php
    }

    public function dashboard_recent_backup_card($is_backup_free_enabled)
    {
        if($is_backup_free_enabled)
        {
            $button_style='pointer-events: auto; opacity: 1';
        }
        else
        {
            $button_style='pointer-events: none; opacity: 0.4';
        }

        $has_recent_data=false;
        $recent_backups=array();
        ?>
        <!-- =============================
			WPvivid v2 - Recent Backups Card
			============================= -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-recent-backups">
            <div class="wpvivid-v2-backup-header">
                <h3><?php esc_html_e('Recent Backups', 'wpvivid-backup-pro'); ?></h3>
                <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore'); ?>" class="wpvivid-v2-backup-view-all" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('View All', 'wpvivid-backup-pro'); ?> →</a>
            </div>

            <?php
            if ( class_exists( 'WPvivid_New_BackupList' ) )
            {
                $backup_list = new WPvivid_New_BackupList();
                $all_backups = $backup_list->get_all_backup();

                if ( is_array( $all_backups ) ) {
                    $recent_backups = array_slice( $all_backups, 0, 9);
                }

                if(!empty($recent_backups))
                {
                    $has_recent_data=true;
                }
                else
                {
                    $has_recent_data=false;
                }

            }
            else
            {
                $has_recent_data=false;
            }

            if($has_recent_data)
            {
                ?>
                <div class="wpvivid-v2-backup-list">
                    <?php
                    foreach ($recent_backups as $backup)
                    {
                        $backup_id=$backup['id'];
                        $backup_create_time=$backup['create_time'];
                        $backup_type=$backup['type'];
                        if($backup_type === 'Manual')
                        {
                            $backup_type = __('Manual Backup', 'wpvivid-backup-pro');
                        }
                        else if($backup_type === 'Cron')
                        {
                            $backup_type = 'General Schedule';
                        }
                        else if($backup_type === 'Incremental')
                        {
                            $backup_type = 'Incremental Schedule Cycle';
                        }

                        if(!empty($backup['remote']))
                        {
                            $remote=array_shift($backup['remote']);
                            $cloud_storage=$this->get_cloud_storage_info($remote['type']);
                            $backup_destination=$cloud_storage['type'];
                        }
                        else
                        {
                            $backup_destination='Localhost';
                        }

                        ?>
                        <div class="wpvivid-v2-backup-row">
                            <div class="wpvivid-v2-backup-info">
                                <span class="dashicons dashicons-backup"></span>
                                <div>
                                    <strong>
                                        <?php
                                        echo WPvivid_Time::format_local("M d, Y — H:i", $backup_create_time);
                                        ?>
                                    </strong>
                                    <div class="wpvivid-v2-backup-meta"><?php echo $backup_type; ?> · <?php echo $backup_destination; ?></div>
                                </div>
                            </div>
                            <div class="wpvivid-v2-backup-actions" style="<?php echo esc_attr($button_style); ?>">
                                <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore').'&download=1&backup_id='.$backup_id; ?>" class="wpvivid-v2-backup-action-link"><?php esc_html_e('Download', 'wpvivid-backup-pro'); ?></a>
                                <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore').'&restore=1&backup_id='.$backup_id; ?>" class="wpvivid-v2-backup-action-link"><?php esc_html_e('Restore', 'wpvivid-backup-pro'); ?></a>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
            else
            {
                ?>
                <div class="wpvivid-v2-backup-list-empty">
                    <div class="wpvivid-v2-backup-empty-icon">
                        <span class="dashicons dashicons-archive"></span>
                    </div>

                    <div class="wpvivid-v2-backup-empty-content">
                        <strong><?php esc_html_e('No Backups Found', 'wpvivid-backup-pro'); ?></strong>
                        <p><?php esc_html_e('Your site has not been backed up yet.', 'wpvivid-backup-pro'); ?></p>
                        <button class="wpvivid-v2-backup-start" style="<?php echo esc_attr($button_style); ?>"><?php esc_html_e('Run Manual Backup', 'wpvivid-backup-pro'); ?></button>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <script>
            jQuery('.wpvivid-v2-backup-start').on('click', function()
            {
                location.href='<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup', 'wpvivid-backup'); ?>';
            });
        </script>
        <?php
    }

    public function dashboard_license_support_card()
    {
        ?>
        <!-- ======================================
             WPvivid v2 - License & Support Card
             ====================================== -->
        <div class="wpvivid-v2-dashboard-card wpvivid-v2-dashboard-license-support">
            <div class="wpvivid-v2-license-support-header">
                <h3>License & Support</h3>
            </div>

            <div class="wpvivid-v2-license-support-content">
                <!-- Left Column: License -->
                <div class="wpvivid-v2-license-column">
                    <h4>License & Account</h4>
                    <p>Status: <span class="wpvivid-v2-license-status wpvivid-v2-status-success">✔ Activated</span></p>
                    <a href="#" class="wpvivid-v2-license-link">Manage License →</a>
                </div>

                <!-- Right Column: Support -->
                <div class="wpvivid-v2-support-column">
                    <h4>Support Center</h4>
                    <ul class="wpvivid-v2-support-list">
                        <li><a href="https://wpvivid.com/submit-ticket">Submit a Ticket</a></li>
                        <li><a href="https://wpvivid.com/wpvivid-backup-pro-changelog">View Logs</a></li>
                        <li><a href="https://docs.wpvivid.com">Documentation</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    public function license_sidebar()
    {
        if(apply_filters('wpvivid_show_dashboard_addons',true))
        {
            if(current_user_can('administrator'))
            {
                $url='admin.php?page='.strtolower(sprintf('%s-license', apply_filters('wpvivid_white_label_slug', 'wpvivid')));
                ?>
                <div class="wpvivid-four-cols">
                    <ul>
                        <li><span class="dashicons dashicons-admin-network wpvivid-dashicons-middle wpvivid-dashicons-green"></span>
                            <a href="<?php echo $url; ?>"><b>License</b></a>
                            <?php
                            if(is_multisite())
                            {
                                if(is_main_site())
                                {
                                    $user_info= get_option('wpvivid_pro_user',false);
                                }
                                else
                                {
                                    switch_to_blog(get_main_site_id());
                                    $user_info= get_option('wpvivid_pro_user',false);
                                    restore_current_blog();
                                }
                            }
                            else
                            {
                                $user_info= get_option('wpvivid_pro_user',false);
                            }
                            if($user_info===false)
                            {
                                ?>
                                <span class="wpvivid-rectangle-small wpvivid-red">un-authorized</span>
                                <?php
                            }
                            else
                            {
                                ?>
                                <span class="wpvivid-rectangle-small wpvivid-green">Authorized</span>
                                <?php
                            }
                            ?>
                            <br>
                            Activate <?php echo apply_filters('wpvivid_white_label_display', 'WPvivid'); ?> Pro license on the website, check update and enable automatic update.</li>
                    </ul>
                </div>
                <?php
            }
        }
    }

    public function ticket_sidebar()
    {
        if(apply_filters('wpvivid_show_submit_ticket',true))
        {
            ?>
            <div class="wpvivid-four-cols">
                <ul>
                    <li><span class="dashicons dashicons-admin-comments wpvivid-dashicons-middle wpvivid-dashicons-green"></span>
                        <a href="https://wpvivid.com/submit-ticket"><b>Submit a Ticket</b></a>
                        <br>
                        If you find a php error or a vulnerability in plugin, you can create ticket in hot support that we responded instantly</li>
                </ul>
            </div>
            <?php
        }
    }

    public function add_sidebar()
    {
        if(apply_filters('wpvivid_show_sidebar',true))
        {
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
    }

    public function backup_pro_menu_box()
    {
        $show=false;

        if(class_exists('WPvivid_Backup_Restore_Page_addon'))
        {
            $show=true;
            $backup=true;
        }
        else
        {
            $backup=false;
        }

        if(class_exists('WPvivid_Migration_Page_addon'))
        {
            $show=true;
            $migration=true;
        }
        else
        {
            $migration=false;
        }

        if(class_exists('WPvivid_BackupList_addon'))
        {
            $show=true;
            $backup_list=true;
        }
        else
        {
            $backup_list=false;
        }

        if(class_exists('WPvivid_Schedule_addon'))
        {
            $show=true;
            $schedule=true;
        }
        else
        {
            $schedule=false;
        }

        //WPvivid_Multi_Remote_addon WPvivid_Export_Import_addon

        if(class_exists('WPvivid_Multi_Remote_addon'))
        {
            $show=true;
            $remote=true;
        }
        else
        {
            $remote=false;
        }

        if(class_exists('WPvivid_Export_Import_addon'))
        {
            $show=true;
            $export=true;
        }
        else
        {
            $export=false;
        }

        if(class_exists('WPvivid_Uploads_Cleaner_addon'))
        {
            $show=true;
            $upload_cleaner=true;
        }
        else
        {
            $upload_cleaner=false;
        }

        if($show)
        {
            ?>
            <div class="wpvivid-dashboard" style="margin-bottom:1em;">
            <?php
            if($schedule)
            {
                $offset=get_option('gmt_offset');
                $enable_incremental_schedules=get_option('wpvivid_enable_incremental_schedules', false);
                $enable_schedules_backups=apply_filters('wpvivid_get_general_schedule_status',false);
                if($enable_schedules_backups||$enable_incremental_schedules)
                {
                    $dashicon="wpvivid-green";
                    $enable_status = 'Enabled';
                }
                else{
                    $dashicon="wpvivid-grey";
                    $enable_status = 'Disabled';
                }
                if($enable_schedules_backups)
                {
                    $type="General";
                }
                else if($enable_incremental_schedules)
                {
                    $type="Incremental";
                }
                else
                {
                    $type="";
                }
                //
                ?>
                <div class="wpvivid-one-coloum" style="border-bottom:1px solid #eee;background:#eaf1fe;">
                    <div style="padding-left:1em;">
                        <p><span class="dashicons dashicons-calendar-alt wpvivid-dashicons-green"></span>
                            <span><strong>Backup Schedule:</strong></span>
                            <span class="wpvivid-rectangle <?php echo $dashicon?>"><?php echo esc_html($enable_status)?></span>
                            <?php
                            if(!empty($type))
                            {
                                ?>
                                <span><strong>Type:</strong></span>
                                <span class="wpvivid-rectangle wpvivid-green"><?php echo esc_html($type)?></span>
                                <?php
                            }
                            ?>
                        <p>
                    </div>
                    <?php
                    if($enable_incremental_schedules)
                    {
                        $data=$this->get_incremental_schedules_data();
                        ?>
                        <div class="wpvivid-two-col">
                            <div style="padding:0 1em;">
                                <p><span class="dashicons dashicons-category wpvivid-dashicons-orange"></span>
                                    <span>Last Backup (Files): </span><span style="padding-right:0.2em"><?php echo $data['last_files_backup_time'] ?></span>
                                    <span style="padding-right:0.2em"><?php echo $data['last_files_backup_status']?></span></p>
                                <p><span class="dashicons dashicons-admin-site-alt3 wpvivid-dashicons-blue"></span>
                                    <span>Last Backup (Database): </span><span><?php echo $data['last_db_backup_time'] ?></span></p>
                            </div>
                        </div>
                        <div class="wpvivid-two-col">
                            <div style="padding:0 1em;">
                                <p><span class="dashicons dashicons-category wpvivid-dashicons-grey"></span>
                                    <span>Next Backup (Files): </span><span><?php echo $data['next_files_backup'] ?></span></p>
                                <p><span class="dashicons dashicons-admin-site-alt3 wpvivid-dashicons-grey"></span>
                                    <span>Next Backup (Database): </span><span><?php echo $data['next_db_backup'] ?></span></p>
                            </div>
                        </div>
                        <?php
                    }
                    else if($enable_schedules_backups)
                    {
                        $data=$this->get_general_schedules_data();
                        ?>
                        <div class="wpvivid-two-col">
                            <div style="padding:0 1em;">
                                <p><span class="dashicons dashicons-category wpvivid-dashicons-orange"></span>
                                    <span>Last Backup : </span><span style="padding-right:0.2em"><?php echo $data['last_backup_time']; ?></span>
                                    <span style="padding-right:0.2em"><?php echo $data['last_backup_status']; ?></span></p>
                            </div>
                        </div>
                        <div class="wpvivid-two-col">
                            <div style="padding:0 1em;">
                                <p><span class="dashicons dashicons-category wpvivid-dashicons-grey"></span>
                                    <span>Next Backup : </span><span><?php echo $data['next_backup_time']; ?></span></p>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div style="clear:both;"></div>
                <?php
            }
            ?>
                <div class="wpvivid-clear-float">
                    <div class="wpvivid-one-coloum">
                        <span>
                            <h1>
                                <span class="dashicons dashicons-list-view wpvivid-dashicons-blue" style="margin-top:0.3em;"></span>Backup & Migration
                                <span style="margin-left:2em; font-size:13px;float:right;"><span class="dashicons dashicons-update wpvivid-dashicons-green"></span><strong><a href="<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'plugins.php?s=wpvivid&plugin_status=all'); ?>">Update all</a></strong></span>
                            </h1>
                        </span>
                    </div>
                    <div>
                        <?php
                        if(apply_filters('wpvivid_show_dashboard_addons',true))
                        {
                            $show_learn_more_link = true;
                        }
                        else
                        {
                            $show_learn_more_link = false;
                        }
                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-backup'))
                        {
                            if($backup)
                            {
                                $help_url='https://docs.wpvivid.com/manual-backup-overview.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup', 'wpvivid-backup');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons-backup wpvivid-dashicons-large wpvivid-dashicons-green"></span>
                                                <a href="'.$url.'"><b>'.esc_html__('Manual Backup', 'wpvivid-backup-pro').'</b></a>
                                                '.$learn_more.'
                                                <br>
                                                Create an on-demand backup of your website for restoration or migration.
                                            </div>';
                            }
                        }

                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-use-schedule'))
                        {
                            if($schedule)
                            {
                                $help_url='https://docs.wpvivid.com/wpvivid-backup-pro-schedule-overview.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-schedule', 'wpvivid-schedule');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons-calendar-alt wpvivid-dashicons-large wpvivid-dashicons-green"></span>
                                                <a href="'.$url.'"><b>'.esc_html__('Schedule', 'wpvivid-backup-pro').'</b></a>
                                                '.$learn_more.'
                                                <br>
                                                '.esc_html__('Set up schedules to back up the site automatically: general or incremental backup schedules.', 'wpvivid-backup-pro').'
                                            </div>';
                            }
                        }

                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-mange-backup'))
                        {
                            if($backup_list)
                            {
                                $help_url='https://docs.wpvivid.com/wpvivid-backup-pro-backups-restore-overview.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-backup-and-restore', 'wpvivid-backup-and-restore');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons dashicons-database wpvivid-dashicons-large wpvivid-dashicons-green"></span>
                                                <a href="'.$url.'"><b>'.esc_html__('Backup Manager', 'wpvivid-backup-pro').'</b></a>
                                                '.$learn_more.'
                                                <br>
                                                '.esc_html__('A centralized place for managing all your backups, uploading backups and restoring the backups.', 'wpvivid-backup-pro').'
                                            </div>';
                            }
                        }

                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-mange-remote'))
                        {
                            if($remote)
                            {
                                $help_url='https://docs.wpvivid.com/wpvivid-backup-pro-cloud-storage-overview.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-remote', 'wpvivid-remote');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons-cloud wpvivid-dashicons-large wpvivid-dashicons-green"></span>
                                                <a href="'.$url.'"><b>'.esc_html__('Cloud Storage', 'wpvivid-backup-pro').'</b></a>
                                                '.$learn_more.'
                                                <br>
                                                '.sprintf(esc_html__('Connect %s to the leading cloud storage to store your website backups off-site.', 'wpvivid-backup-pro'), esc_html(apply_filters('wpvivid_white_label_display', 'WPvivid'))).'
                                            </div>';
                            }
                        }

                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-export-site'))
                        {
                            if($backup)
                            {
                                $help_url='https://docs.wpvivid.com/wpvivid-backup-pro-export-site.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-site', 'wpvivid-export-site');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons-migrate wpvivid-dashicons-large wpvivid-dashicons-blue"></span>
                                                <a href="'.$url.'"><b>Export Site</b></a>
                                                '.$learn_more.'
                                                <br>
                                                Export the site to localhost(web server), remote storage or target site (auto-migration) for migration purpose.
                                            </div>';
                            }
                        }

                        if( apply_filters('wpvivid_current_user_can',true,'wpvivid-can-import-site'))
                        {
                            if($backup_list)
                            {
                                $help_url='https://docs.wpvivid.com/wpvivid-backup-pro-import-site.html';
                                $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-import-site', 'wpvivid-import-site');
                                if($show_learn_more_link)
                                {
                                    $learn_more='<span style="float: right;"><a href="'.$help_url.'">'.esc_html__('Learn more...', 'wpvivid-backup-pro').'</a></span>';
                                }
                                else
                                {
                                    $learn_more='';
                                }
                                echo '<div class="wpvivid-two-col wpvivid-dashboard-list">
                                                <span class="dashicons dashicons-download wpvivid-dashicons-large wpvivid-dashicons-blue"></span>
                                                <a href="'.$url.'"><b>Import Site</b></a>
                                                '.$learn_more.'
                                                <br>
                                                Import a site from localhost(web server), remote storage or source site (auto-migration).
                                            </div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public function login_form()
    {
        if(is_multisite())
        {
            if(is_main_site())
            {
                $info=get_option('wpvivid_dashboard_info',array());
            }
            else
            {
                switch_to_blog(get_main_site_id());
                $info=get_option('wpvivid_dashboard_info',array());
                restore_current_blog();
            }
        }
        else
        {
            $info=get_option('wpvivid_dashboard_info',array());
        }

        if(empty($info))
        {
            $text=sprintf(
                __('Note: You can use either a father license or a child license to activate %s plugins', 'wpvivid-backup-pro'),
                apply_filters('wpvivid_white_label_display', 'WPvivid')
            );
        }
        else
        {
            $text=__('Note: Please verify your Pro license again to avoid abuse of addons.', 'wpvivid-backup-pro');
        }
        ?>
        <div id="wpvivid_dashboard_form" style="padding:0 1em 0em 1em;">
            <div>
                <span><input type="text" id="wpvivid_account_license" placeholder="<?php esc_attr_e('Enter a license key', 'wpvivid-backup-pro'); ?>"><input type="submit" id="wpvivid_active_btn" class="button" value="<?php esc_attr_e('Authenticate', 'wpvivid-backup-pro'); ?>"></span></br>
                <div id="wpvivid_login_box_progress" style="display: none;">
                    <p>
                        <span class="dashicons dashicons-admin-network wpvivid-dashicons-green"></span>
                        <span id="wpvivid_log_progress_text"></span>
                    </p>
                </div>
                <div id="wpvivid_login_error_msg_box" style=";">
                    <p>
                        <span class="dashicons dashicons-info wpvivid-dashicons-grey"></span>
                        <span id="wpvivid_login_error_msg"><?php echo $text;?></span>
                    </p>
                </div>
                <div style="clear: both;"></div>
            </div>
        </div>
        <script>
            var retry_times = 0;
            var max_retry_times = 3;

            jQuery('#wpvivid_active_btn').click(function()
            {
                wpvivid_dashboard_login();
            });

            function wpvivid_dashboard_login()
            {
                var license = jQuery('#wpvivid_account_license').val();
                var ajax_data={
                    'action':'wpvivid_dashboard_login',
                    'license':license,
                };

                var login_msg = '<?php echo sprintf(__('Logging in to your %s account', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid')); ?>';
                wpvivid_lock_login(true);
                wpvivid_login_progress(login_msg);
                jQuery('#wpvivid_pro_notice').hide();
                wpvivid_post_request_addon(ajax_data, function(data)
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        //need_active
                        if(jsonarray.need_active)
                        {
                            retry_times=0;
                            wpvivid_login_progress('You have successfully logged in');
                            wpvivid_active_site();
                        }
                        else
                        {
                            retry_times=0;
                            wpvivid_login_progress('You have successfully logged in');
                            location.reload();
                        }
                    }
                    else
                    {
                        retry_times++;
                        if(retry_times<max_retry_times)
                        {
                            wpvivid_dashboard_login();
                        }
                        else
                        {
                            if (/cURL error 28/i.test(jsonarray.error))
                            {
                                wpvivid_dashboard_login_direct();
                            }
                            else
                            {
                                wpvivid_lock_login(false,jsonarray.error);
                            }
                        }
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    retry_times++;
                    if(retry_times<max_retry_times)
                    {
                        wpvivid_dashboard_login();
                    }
                    else
                    {
                        var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                        wpvivid_lock_login(false,error_message);
                    }
                });
            }

            function wpvivid_dashboard_login_direct()
            {
                var license = jQuery('#wpvivid_account_license').val();
                var ajax_data={
                    'action':'wpvivid_dashboard_login_direct',
                    'license':license,
                };

                var login_msg = '<?php echo sprintf(__('Logging in to your %s account', 'wpvivid-backup-pro'), apply_filters('wpvivid_white_label_display', 'WPvivid')); ?>';
                wpvivid_lock_login(true);
                wpvivid_login_progress(login_msg);
                jQuery('#wpvivid_pro_notice').hide();
                wpvivid_post_request_addon(ajax_data, function(data)
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        //need_active
                        if(jsonarray.need_active)
                        {
                            retry_times=0;
                            wpvivid_login_progress('You have successfully logged in');
                            wpvivid_active_site();
                        }
                        else
                        {
                            retry_times=0;
                            wpvivid_login_progress('You have successfully logged in');
                            location.reload();
                        }
                    }
                    else
                    {
                        retry_times++;
                        if(retry_times<max_retry_times)
                        {
                            wpvivid_dashboard_login_direct();
                        }
                        else
                        {
                            wpvivid_lock_login(false,jsonarray.error);
                        }
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    retry_times++;
                    if(retry_times<max_retry_times)
                    {
                        wpvivid_dashboard_login_direct();
                    }
                    else
                    {
                        var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                        wpvivid_lock_login(false,error_message);
                    }
                });
            }

            function wpvivid_active_site()
            {
                var license = jQuery('#wpvivid_account_license').val();
                var ajax_data={
                    'action':'wpvivid_dashboard_active',
                    'license':license,
                };

                wpvivid_lock_login(true);
                wpvivid_login_progress('Activating your license on the current site');
                jQuery('#wpvivid_pro_notice').hide();
                wpvivid_post_request_addon(ajax_data, function(data)
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        wpvivid_login_progress('Your license has been activated successfully');
                        location.reload();
                    }
                    else
                    {
                        wpvivid_lock_login(false,jsonarray.error);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_dashboard_output_ajaxerror('check update', textStatus, errorThrown);
                    wpvivid_lock_login(false,error_message);
                });
            }

            function wpvivid_lock_login(lock,error='')
            {
                if(lock)
                {
                    jQuery('#wpvivid_active_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                    jQuery('#wpvivid_login_box_progress').show();
                    jQuery('#wpvivid_login_error_msg_box').hide();
                }
                else
                {
                    jQuery('#wpvivid_log_progress_text').html('');
                    jQuery('#wpvivid_login_box_progress').hide();
                    jQuery('#wpvivid_active_btn').css({'pointer-events': 'auto', 'opacity': '1'});

                    if(error!=='')
                    {
                        //wpvivid_display_pro_notice('Error', error);
                        jQuery('#wpvivid_login_error_msg_box').show();
                        jQuery('#wpvivid_login_error_msg').html(error);
                    }
                }
            }

            function wpvivid_login_progress(log)
            {
                jQuery('#wpvivid_log_progress_text').html(log);
            }
        </script>
        <?php
    }

    public function get_plugins_status($dashboard_info)
    {
        global $wpvivid_backup_pro;
        $plugins=array();

        foreach ($dashboard_info['plugins'] as $slug=>$info)
        {
            $plugin['name']=$info['name'];
            $plugin['slug']=$slug;
            $status=$wpvivid_backup_pro->addons_loader->get_plugin_status($info);

            if($status['status']=='Installed'&&$status['action']=='Update')
            {
                $plugin['status']='Update now';
            }
            else
            {
                $plugin['status']=$status['status'];
            }

            $plugin['info']=$info['description'];
            $plugin['requires_plugins']=$wpvivid_backup_pro->addons_loader->get_plugin_requires($info);
            $plugin['is_free']=$wpvivid_backup_pro->addons_loader->is_plugin_free($info);
            $plugins[$slug]=$plugin;
        }
        return $plugins;
    }

    public function progress_bar()
    {
        if(isset($_REQUEST['install'])&&$_REQUEST['install'])
        {
            ?>
            <div style="padding:0 1em;">
                <div>
                    <span>
                        <strong>Installing addon: </strong>
                    </span>
                    <span id="wpvivid_plugin_title"></span>
                    <br>
                    <span class="wpvivid-span-progress" >
                        <span id="wpvivid_plugin_progress_text" class="wpvivid-span-processed-progress wpvivid-span-processed-percent-progress"></span>
                    </span>
                </div>
            </div>
            <?php
        }
        else
        {
            ?>
            <div class="wpvivid-install-addon-init-data" style="padding:0 1em; display: none;">
                <div>
                    <span>
                        <strong>Initializing data</strong>
                    </span>
                    <span id="wpvivid_plugin_title"></span>
                    <br>
                    <span class="wpvivid-span-progress" >
                        <span id="wpvivid_plugin_progress_text" class="wpvivid-span-processed-progress wpvivid-span-processed-percent-progress"></span>
                    </span>
                </div>
            </div>
            <?php
        }
    }

    public function addon_bar()
    {
        if(is_multisite())
        {
            if(is_main_site())
            {
                $last_login_time=get_option('wpvivid_last_login_time',0);
                $dashboard_info=get_option('wpvivid_dashboard_info',array());
            }
            else
            {
                switch_to_blog(get_main_site_id());
                $last_login_time=get_option('wpvivid_last_login_time',0);
                $dashboard_info=get_option('wpvivid_dashboard_info',array());
                restore_current_blog();
            }
        }
        else
        {
            $last_login_time=get_option('wpvivid_last_login_time',0);
            $dashboard_info=get_option('wpvivid_dashboard_info',array());
        }

        $plugins=$this->get_plugins_status($dashboard_info);
        $all_installed=true;
        foreach ($plugins as $item)
        {
            if($item['status']=='Installed'||$item['status']=='Up to date'||$item['status']=='Update now')
            {
            }
            else
            {
                $all_installed=false;
            }
        }

        $need_login=false;

        if($last_login_time+60*60*24>time())
        {
            $need_login=false;
        }
        else
        {
            if($all_installed)
            {
                $need_login=false;
            }
            else
            {
                $need_login=true;
            }
        }

        if(isset($_REQUEST['install'])&&$_REQUEST['install'])
        {

        }
        else
        {
            if($need_login)
            {
                $this->login_form();
            }
        }

        ?>
        <div id="wpvivid_addons_list" style="padding: 0 1em 1em 1em;">
            <ul class="wpvivid-three-cols">
                <?php
                foreach ($plugins as $item)
                {
                    if($item['slug'] === 'backup_pro')
                        continue;
                    $this->output_addons($item,$need_login);
                }

                if($this->has_backup_pro($plugins))
                {
                    $this->output_backup_addons($plugins,$need_login);
                }
                else
                {
                    foreach ($plugins as $item)
                    {
                        if($item['slug'] === 'backup_pro')
                        {
                            $this->output_addons($item,$need_login);
                        }
                    }

                }
                ?>
            </ul>
        </div>
        <div style="clear:both;"></div>
        <div style="padding:1em;">
            <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-small wpvivid-dashicons-blue"></span><span>= Installed and Activated</span>
            <span style="padding:0 0.5em"></span>
            <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-small wpvivid-dashicons-grey"></span><span>= Not Installed</span>
        </div>
        <?php
        if(isset($_REQUEST['install'])&&$_REQUEST['install']||isset($_REQUEST['finish'])&&$_REQUEST['finish']||isset($_REQUEST['first'])&&$_REQUEST['first'])
        {
            ?>
            <script>
                jQuery(document).scrollTop(jQuery(document).height());
            </script>
            <?php
        }
        if(isset($_REQUEST['install'])&&$_REQUEST['install'])
        {
            $plugin_install_cache=get_option('wpvivid_plugin_install_cache',array());
            if(empty($plugin_install_cache)||empty($plugin_install_cache['plugins']))
            {
                return;
            }

            ?>
            <!--<div style="padding:1em;"></div>-->
            <script>
                jQuery(document).scrollTop(jQuery(document).height());
            </script>
            <?php

            if(!class_exists('WPvivid_Plugin_Installer'))
            {
                include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'includes/installer/class-wpvivid-installer.php';
            }
            $installer=new WPvivid_Plugin_Installer();
            $installer->run_installation();
        }
    }

    public function output_addons($item,$need_login)
    {
        $status='';
        $class='';
        $span='';
        $background_class='';
        $can_href=false;
        $is_free_plugin=$item['is_free'];

        if($item['status']=='Not available')
        {
            $status='Unavailable';
            $class='wpvivid-dashicons-grey';
            $span='';
            $background_class='';
            $can_href=false;
        }
        else if ($item['status'] == 'Inactive')
        {
            $status='Activate';
            $class='wpvivid-dashicons-grey';
            $span='';
            $background_class='';
            $can_href=false;
        }
        else if($item['status']=='Installed'||$item['status']=='Up to date')
        {
            if($item['requires_plugins']!==false)
            {
                foreach ($item['requires_plugins'] as $plugin)
                {
                    if($plugin['status']=='Installed'||$plugin['status']=='Up to date')
                    {
                        $status='';
                        $class='wpvivid-dashicons-blue';
                        $span='';
                        $background_class='wpvivid-three-cols-active';
                        $can_href=true;
                    }
                    else
                    {
                        $status='Install';
                        $class='wpvivid-dashicons-grey';
                        $span='';
                        $background_class='';
                        $can_href=false;
                    }
                }
            }
            else
            {
                $status='';
                $class='wpvivid-dashicons-blue';
                $span='';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
        }
        else
        {
            if($item['status']=='Un-installed')
            {
                $status='Install';

                $class='wpvivid-dashicons-grey';
                $span='';
                $background_class='';
                $can_href=false;
            }
            else if($item['status']=='Update now')
            {
                $status='Update';
                $class='wpvivid-dashicons-blue';
                $span='<span class="wpvivid-three-cols-update" title="There is a new version">1</span>';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
            else
            {
                $status='';
                $class='wpvivid-dashicons-blue';
                $span='';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
        }

        $page_url=apply_filters('wpvivid_addon_page_url', '',$item['slug']);
        $title=apply_filters('wpvivid_addon_page_title',$item['name'],$item['slug']);

        $is_install=isset($_REQUEST['install'])&&$_REQUEST['install'];

        if($is_install)
        {
            $install_class='';
        }
        else if($is_free_plugin)
        {
            $install_class='wpvivid-addons';
        }
        else if($need_login)
        {
            $install_class='wpvivid-need-login';
        }
        else
        {
            $install_class='wpvivid-addons';
        }
        ?>
        <li>
            <div class="wpvivid-three-cols-li <?php echo esc_attr($background_class); ?>" addon-type="<?php echo esc_attr($item['slug']); ?>">
                <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-middle <?php echo esc_attr($class); ?>"></span>
                <?php echo esc_html($span); ?>
                <b>
                    <?php
                    if($can_href&&!empty($page_url))
                    {
                        ?>
                        <a href="<?php echo $page_url;?>"><?php echo $title;?></a>
                        <?php
                    }
                    else
                    {
                        ?>
                        <a><?php echo $title;?></a>
                        <?php
                    }
                    ?>
                </b>
                <a>
                    <small>
                        <span class="<?php echo esc_attr($install_class); ?> <?php echo $status; ?>" style="float: right;"><?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?></span>
                    </small>
                </a>
                <br>
                <span class="wpvivid-addon-info-text" title="<?php echo $item['info'];?>"><?php echo $item['info'];?></span>
            </div>
        </li>
        <?php
    }

    public function has_backup_pro($plugins)
    {
        //$dashboard_info=get_option('wpvivid_dashboard_info',array());
        //$plugins=$this->get_plugins_status($dashboard_info);
        $has=false;
        foreach ($plugins as $item)
        {
            if($item['slug'] === 'backup_pro')
            {
                if($item['status']=='Installed'||$item['status']=='Up to date')
                {
                    $has=true;
                }
                else if($item['status']=='Update now')
                {
                    $has=true;
                }
                else if($item['status']=='Inactive')
                {
                    $has=true;
                }
            }
        }
        return $has;
    }

    public function output_backup_addons($plugins,$need_login)
    {
        //$dashboard_info = get_option('wpvivid_dashboard_info', array());
        //$plugins = $this->get_plugins_status($dashboard_info);
        $item = $plugins['backup_pro'];

        $status = '';
        $class = '';
        $span = '';
        $background_class = '';
        $can_href = false;

        if ($item['status'] == 'Not available') {
            $status = 'Unavailable';
            $class = 'wpvivid-dashicons-grey';
            $span = '';
            $background_class = '';
            $can_href = false;
        }
        else if ($item['status'] == 'Inactive')
        {
            $status='Activate';
            $class='wpvivid-dashicons-grey';
            $span='';
            $background_class='';
            $can_href=false;
        }
        else if($item['status']=='Installed'||$item['status']=='Up to date')
        {
            if($item['requires_plugins']!==false)
            {
                foreach ($item['requires_plugins'] as $plugin)
                {
                    if($plugin['status']=='Installed'||$plugin['status']=='Up to date')
                    {
                        $status='';
                        $class='wpvivid-dashicons-blue';
                        $span='';
                        $background_class='wpvivid-three-cols-active';
                        $can_href=true;
                    }
                    else
                    {
                        $status='Install';
                        $class='wpvivid-dashicons-grey';
                        $span='';
                        $background_class='';
                        $can_href=false;
                    }
                }
            }
            else
            {
                $status='';
                $class='wpvivid-dashicons-blue';
                $span='';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
        }
        else
        {
            if($item['status']=='Un-installed')
            {
                $status='Install';

                $class='wpvivid-dashicons-grey';
                $span='';
                $background_class='';
                $can_href=false;
            }
            else if($item['status']=='Update now')
            {
                $status='Update';
                $class='wpvivid-dashicons-blue';
                $span='<span class="wpvivid-three-cols-update" title="There is a new version">1</span>';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
            else
            {
                $status='';
                $class='wpvivid-dashicons-blue';
                $span='';
                $background_class='wpvivid-three-cols-active';
                $can_href=true;
            }
        }

        $is_install=isset($_REQUEST['install'])&&$_REQUEST['install'];

        if($is_install)
        {
            $install_class='';
        }
        else if($need_login)
        {
            $install_class='wpvivid-need-login';
        }
        else
        {
            $install_class='wpvivid-addons';
        }
        ?>
        <li>
            <div class="wpvivid-three-cols-li <?php echo esc_attr($background_class); ?>" addon-type="backup_pro">
                <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-middle <?php echo esc_attr($class); ?>"></span>
                <?php echo esc_html($span); ?>
                <b>
                    <?php
                    if($can_href)
                    {
                        ?>
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-image-cleaner', 'wpvivid-image-cleaner'); ?>"><?php esc_html_e('Unused Image Scanner', 'wpvivid-backup-pro'); ?></a>
                        <?php
                    }
                    else
                    {
                        ?>
                        <a><?php esc_html_e('Unused Image Scanner', 'wpvivid-backup-pro'); ?></a>
                        <?php
                    }
                    ?>
                </b>
                <a><small><span class="<?php echo esc_attr($install_class); ?> <?php echo $status; ?>" style="float: right;"><?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?></span></small></a><br>
                <span class="wpvivid-addon-info-text" title="<?php esc_attr_e('Analyze and find unused images in your media folder and delete them.', 'wpvivid-backup-pro'); ?>">
                    <?php esc_html_e('Analyze and find unused images in your media folder and delete them.', 'wpvivid-backup-pro'); ?>
                </span>
            </div>
        </li>
        <li>
            <div class="wpvivid-three-cols-li <?php echo esc_attr($background_class); ?>" addon-type="backup_pro">
                <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-middle <?php echo esc_attr($class); ?>"></span>
                <?php echo esc_html($span); ?>
                <b>
                    <?php
                    if($can_href)
                    {
                        ?>
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-import', 'wpvivid-export-import'); ?>"><?php esc_html_e('Export/Import Post or Page', 'wpvivid-backup-pro'); ?></a>
                        <?php
                    }
                    else
                    {
                        ?>
                        <a><?php esc_html_e('Export/Import Post or Page', 'wpvivid-backup-pro'); ?></a>
                        <?php
                    }
                    ?>
                </b>
                <a><small><span class="<?php echo esc_attr($install_class); ?> <?php echo $status; ?>" style="float: right;"><?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?></span></small></a><br>
                <span class="wpvivid-addon-info-text" title="Export or import website content in bulk, including pages, posts, comments, terms, images and thumbnails.">
                    Export or import website content in bulk, including pages, posts, comments, terms, images and thumbnails.
                </span>
            </div>
        </li>
        <li>
            <div class="wpvivid-three-cols-li <?php echo esc_attr($background_class); ?>" addon-type="backup_pro">
                <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-middle <?php echo esc_attr($class); ?>"></span>
                <?php echo esc_html($span); ?>
                <b>
                    <?php
                    if($can_href)
                    {
                        ?>
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-export-import', 'wpvivid-export-import').'&url_replace'; ?>">URL Replacement</a>
                        <?php
                    }
                    else
                    {
                        ?>
                        <a>URL Replacement</a>
                        <?php
                    }
                    ?>
                </b>
                <a><small><span class="<?php echo esc_attr($install_class); ?> <?php echo $status; ?>" style="float: right;"><?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?></span></small></a><br>
                <span class="wpvivid-addon-info-text" title="Do a quick domain/url replacing in the database, with no need to perform a database migration.">
                    Do a quick domain/url replacing in the database, with no need to perform a database migration.
                </span>
            </div>
        </li>
        <li>
            <div class="wpvivid-three-cols-li <?php echo esc_attr($background_class); ?>" addon-type="backup_pro">
                <span class="dashicons dashicons-admin-plugins wpvivid-dashicons-middle <?php echo esc_attr($class); ?>"></span>
                <?php echo esc_html($span); ?>
                <b>
                    <?php
                    if($can_href)
                    {
                        ?>
                        <a href="<?php echo apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-rollback', 'wpvivid-rollback'); ?>">Rollback</a>
                        <?php
                    }
                    else
                    {
                        ?>
                        <a>Rollback</a>
                        <?php
                    }
                    ?>
                </b>
                <a><small><span class="<?php echo esc_attr($install_class); ?> <?php echo $status; ?>" style="float: right;"><?php echo esc_html($this->wpvivid_get_addon_status_label($status)); ?></span></small></a><br>
                <span class="wpvivid-addon-info-text" title="Perform a return to a prior state of plugins, themes and Wordpress core.">
                   Perform a return to a prior state of plugins, themes and Wordpress core.
                </span>
            </div>
        </li>
        <?php
    }

    public function addon_form()
    {
        $this->progress_bar();
        $this->addon_bar();
        $error = __('Please verify your Pro license again to avoid abuse of addons.', 'wpvivid-backup-pro');
        ?>
        <script>
            jQuery('.wpvivid-addons').on('click', function()
            {
                if(jQuery(this).hasClass('Activate'))
                {
                    var json = {};
                    json['plugins_list'] = Array();
                    var addon_type = jQuery(this).closest('div').attr('addon-type');
                    json['plugins_list'].push(addon_type);

                    var ajax_data={
                        'action':'wpvivid_activate_plugin',
                        'plugins':json['plugins_list'],
                    };

                    wpvivid_post_request_addon(ajax_data, function(data)
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            location.href=jsonarray.href;
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown)
                    {
                    });
                }
                if(jQuery(this).hasClass('Install') || jQuery(this).hasClass('Update'))
                {
                    var json = {};
                    json['plugins_list'] = Array();

                    var addon_type = jQuery(this).closest('div').attr('addon-type');
                    json['plugins_list'].push(addon_type);

                    var ajax_data={
                        'action':'wpvivid_init_plugin_install_ex',
                        'plugins':json['plugins_list'],
                    };

                    jQuery('.wpvivid-install-addon-init-data').show();
                    jQuery('#wpvivid_dashboard_form').hide();
                    jQuery('.wpvivid-span-processed-percent-progress').css('width', '0%');

                    wpvivid_post_request_addon(ajax_data, function(data)
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            location.href=jsonarray.href;
                        }
                        else
                        {
                            location.reload();
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown)
                    {
                        location.reload();
                    });
                }
            });

            jQuery('.wpvivid-need-login').on('click', function()
            {
                alert("<?php echo $error; ?>");
            });
        </script>
        <?php
    }

    public function addon_box()
    {
        if(is_multisite())
        {
            if(is_main_site())
            {
                $user_info= get_option('wpvivid_pro_user',false);
            }
            else
            {
                switch_to_blog(get_main_site_id());
                $user_info= get_option('wpvivid_pro_user',false);
                restore_current_blog();
            }
        }
        else
        {
            $user_info= get_option('wpvivid_pro_user',false);
        }

        ?>
        <div class="wpvivid-dashboard">
            <div class="wpvivid-clear-float">
                <div class="wpvivid-one-coloum" style="padding:1em; box-sizing: border-box;">
                    <span>
                        <h1>
                            <span class="dashicons dashicons-list-view wpvivid-dashicons-blue" style="margin-top:0.3em;"></span>
                            <span>Addons/Tools</span>
                            <?php
                            if($user_info===false)
                            {

                            }
                            else
                            {
                                ?>
                                <span style="margin-left:2em; font-size:13px;float:right;">
                                    <span class="dashicons dashicons-update wpvivid-dashicons-green"></span>
                                    <strong>
                                        <a href="<?php echo esc_url(apply_filters('wpvivid_get_admin_url', '').'plugins.php?s=wpvivid&plugin_status=all'); ?>">Update all</a>
                                    </strong>
                                </span>
                                <?php
                            }
                            ?>
                        </h1>
                    </span>
                </div>
                <div style="clear:both;"></div>

                <?php
                if($user_info===false)
                {
                    $this->login_form();
                }
                else
                {
                    $this->addon_form();
                }
                ?>
            </div>
        </div>
        <?php
    }
    //
    public function get_incremental_schedules_data()
    {
        $offset=get_option('gmt_offset');
        $enable_incremental_schedules=get_option('wpvivid_enable_incremental_schedules', false);
        $data=apply_filters('wpvivid_get_incremental_data',array());
        if(empty($data))
        {
            $next_files_backup='N/A';
            $next_db_backup='N/A';
        }
        else
        {
            if($enable_incremental_schedules)
            {
                $next_db_backup = $data['database_backup']['backup_next_time'];
                if($next_db_backup==0)
                {
                    $next_db_backup='N/A';
                }
                else
                {
                    $next_db_backup = WPvivid_Time::format_local("H:i:s - F-d-Y ", $next_db_backup);
                }

                $next_incremental_backup= $data['incremental_backup']['backup_next_time'];
                $next_full_backup= $data['full_backup']['backup_next_time'];

                $next_files_backup=max($next_full_backup,$next_incremental_backup);
                if($next_files_backup==0)
                {
                    $next_files_backup='N/A';
                }
                else
                {
                    $next_files_backup = WPvivid_Time::format_local("H:i:s - F-d-Y ", $next_files_backup);
                }
            }
            else
            {
                $next_files_backup='N/A';
                $next_db_backup='N/A';
            }
        }

        $last_full_backup_time=0;
        $last_full_backup_status='';
        $last_incremental_backup_time=0;
        $last_incremental_backup_status='';
        $incremental_backup_id='';
        $last_msg=get_option('wpvivid_full_backup_last_msg',array());
        if(!empty($last_msg))
        {
            $last_full_backup_time=$last_msg['status']['start_time'] ;
            if($last_msg['status']['str'] == 'completed')
            {
                $last_full_backup_status='Succeeded';
            }
            else if($last_msg['status']['str'] == 'error')
            {
                $last_full_backup_status='Failed';
            }
            else if($last_msg['status']['str'] == 'cancel')
            {
                $last_full_backup_status='Canceled';
            }
            else
            {
                $last_full_backup_status='Succeeded';
            }

            if(isset($last_msg['id']))
            {
                $incremental_backup_id=$last_msg['id'];
            }
        }

        $last_msg=get_option('wpvivid_incremental_backup_last_msg',array());
        if(!empty($last_msg))
        {
            $last_incremental_backup_time=$last_msg['status']['start_time'] ;
            if($last_msg['status']['str'] == 'completed')
            {
                $last_incremental_backup_status='Succeeded';
            }
            else if($last_msg['status']['str'] == 'error')
            {
                $last_incremental_backup_status='Failed';
            }
            else if($last_msg['status']['str'] == 'cancel')
            {
                $last_incremental_backup_status='Canceled';
            }
            else
            {
                $last_incremental_backup_status='Succeeded';
            }
        }

        if($last_incremental_backup_time>=$last_full_backup_time)
        {
            $last_files_backup_time=$last_incremental_backup_time;
            $last_files_backup_status=$last_incremental_backup_status;
        }
        else if($last_incremental_backup_time<$last_full_backup_time)
        {
            $last_files_backup_time=$last_full_backup_time;
            $last_files_backup_status=$last_full_backup_status;
        }
        else
        {
            $last_files_backup_time=0;
            $last_files_backup_status='';
        }

        if($last_files_backup_time>0)
        {
            $last_files_backup_time=WPvivid_Time::format_local("H:i:s - F-d-Y ", $last_files_backup_time);
        }
        else
        {
            $last_files_backup_time='N/A';
        }

        $last_db_backup_time=0;
        $last_db_backup_status='';
        $last_msg=get_option('wpvivid_incremental_database_last_msg',array());
        if(!empty($last_msg))
        {
            $last_db_backup_time=$last_msg['status']['start_time'];
            if($last_msg['status']['str'] == 'completed')
            {
                $last_db_backup_status='Succeeded';
            }
            else if($last_msg['status']['str'] == 'error')
            {
                $last_db_backup_status='Failed';
            }
            else if($last_msg['status']['str'] == 'cancel')
            {
                $last_db_backup_status='Canceled';
            }
            else
            {
                $last_db_backup_status='Succeeded';
            }
        }

        if($last_db_backup_time>0)
        {
            $last_db_backup_time=WPvivid_Time::format_local("H:i:s - F-d-Y ", $last_db_backup_time);
        }
        else
        {
            $last_db_backup_time='N/A';
        }

        if($last_full_backup_time>0)
        {
            $last_full_backup_time=WPvivid_Time::format_local("H:i:s - F-d-Y ", $last_full_backup_time);
        }
        else
        {
            $last_full_backup_time='N/A';
        }

        $data['last_full_backup_time']=$last_full_backup_time;
        $data['last_files_backup_time']=$last_files_backup_time;
        $data['last_files_backup_status']=$last_files_backup_status;
        $data['last_db_backup_time']=$last_db_backup_time;
        $data['last_db_backup_status']=$last_db_backup_status;
        $data['next_files_backup']=$next_files_backup;
        $data['next_db_backup']=$next_db_backup;
        $data['incremental_backup_id']=$incremental_backup_id;
        return $data;
    }

    public function get_general_schedules_data()
    {
        $offset=get_option('gmt_offset');

        $schedules = get_option('wpvivid_schedule_addon_setting', array());
        $avtived_schedules = array();

        $last_backup_time='N/A';
        $next_backup_time='N/A';
        $last_backup_status='';
        $next_backup_type='N/A';
        $next_backup_cycle='N/A';
        $next_backup_destination='N/A';
        $duration='N/A';
        $estimated_size='N/A';

        $remoteslist=get_option('wpvivid_upload_setting');

        if(!empty($schedules)){
            foreach ($schedules as $schedule){
                if($schedule['status'] === 'Active'){
                    $avtived_schedules[] = $schedule;
                }
            }
            $avtived_schedules = $this->sort_list($avtived_schedules);
            foreach ($avtived_schedules as $schedule){
                $timestamp=wp_next_scheduled($schedule['id'], array($schedule['id']));
                if($timestamp !== false) {
                    $next_backup_time = WPvivid_Time::format_local("H:i:s - M-d-Y ", $timestamp);
                }
                else{
                    $next_backup_time = 'N/A';
                }

                if(isset($schedule['estimate_size']))
                {
                    $estimated_size=size_format($schedule['estimate_size'], 2);
                }

                if (isset($schedule['backup']['backup_files'])) {
                    $backup_type = $schedule['backup']['backup_files'];
                    if ($backup_type === 'files+db')
                    {
                        $backup_type = 'Full Backup';
                    } else if ($backup_type === 'files')
                    {
                        $backup_type = __('WordPress Files (Exclude Database)', 'wpvivid-backup-pro');
                    } else if ($backup_type === 'db')
                    {
                        $backup_type = __('Only Database', 'wpvivid-backup-pro');
                    }
                } else {
                    if (isset($schedule['backup']['backup_select'])) {
                        $has_db=false;
                        $has_file=false;
                        if($schedule['backup']['backup_select']['db'] == '1' || $schedule['backup']['backup_select']['additional_db'] == '1'){
                            $has_db = true;
                        }
                        if($schedule['backup']['backup_select']['themes'] == '1' || $schedule['backup']['backup_select']['plugin'] == '1' ||
                            $schedule['backup']['backup_select']['uploads'] == '1' || $schedule['backup']['backup_select']['content'] == '1' ||
                            $schedule['backup']['backup_select']['core'] == '1' || $schedule['backup']['backup_select']['other'] == '1'){
                            $has_file = true;
                        }
                        if($has_db && $has_file){
                            $backup_type = 'Full Backup';
                        }
                        else if($has_db){
                            $backup_type = __('Only Database', 'wpvivid-backup-pro');
                        }
                        else{
                            $backup_type = __('WordPress Files (Exclude Database)', 'wpvivid-backup-pro');
                        }
                    }
                    else{
                        $backup_type = 'N/A';
                    }
                }
                $next_backup_type=$backup_type;

                if (!isset($schedule['week']))
                {
                    $schedule['week'] = 'N/A';
                }
                $recurrence = wp_get_schedules();
                if (isset($recurrence[$schedule['type']])) {
                    $schedule_type = $recurrence[$schedule['type']]['display'];
                    if ($schedule_type === 'Weekly')
                    {
                        if (isset($schedule['week']))
                        {
                            if ($schedule['week'] === 'sun')
                            {
                                $schedule_type = $schedule_type . '-Sunday';
                            } else if ($schedule['week'] === 'mon')
                            {
                                $schedule_type = $schedule_type . '-Monday';
                            } else if ($schedule['week'] === 'tue')
                            {
                                $schedule_type = $schedule_type . '-Tuesday';
                            } else if ($schedule['week'] === 'wed') {

                                $schedule_type = $schedule_type . '-Wednesday';
                            } else if ($schedule['week'] === 'thu')
                            {
                                $schedule_type = $schedule_type . '-Thursday';
                            } else if ($schedule['week'] === 'fri')
                            {
                                $schedule_type = $schedule_type . '-Friday';
                            } else if ($schedule['week'] === 'sat')
                            {
                                $schedule_type = $schedule_type . '-Saturday';
                            }
                        }
                    }
                } else {
                    $schedule_type = 'not found';
                }
                $next_backup_cycle=$schedule_type;

                $backup_to = 'Localhost';
                if (isset($schedule['backup']['local'])) {
                    if ($schedule['backup']['local'] == '1')
                    {
                        $backup_to = 'Localhost';
                    } else {
                        if(isset($schedule['backup']['remote_id']))
                        {
                            $remote_id=$schedule['backup']['remote_id'];
                            if(!empty($remoteslist))
                            {
                                foreach ($remoteslist as $key => $value)
                                {
                                    if($key === 'remote_selected')
                                    {
                                        continue;
                                    }
                                    else{
                                        if($remote_id === $key)
                                        {
                                            $storage_type=$value['type'];
                                            $backup_to=$storage_type;
                                        }
                                    }
                                }
                            }
                        }
                        else
                        {
                            $backup_to='All activated remote storage';
                        }
                    }
                } else {
                    $backup_to = 'Localhost';
                }
                $next_backup_destination=$backup_to;
                //

                break;
            }

            $message=WPvivid_Pro_Option::get_general_schedule_data();
            if(!empty($message)){
                $duration=$message['status']['task_end_time']-$message['status']['task_start_time'];
                $last_backup_time = WPvivid_Time::format_local("H:i:s - M-d-Y ", $message['status']['start_time']);
                if($message['status']['str'] == 'completed'){
                    $last_backup_status='Succeeded';
                }
                elseif($message['status']['str'] == 'error'){
                    $last_backup_status='Failed';
                }
                elseif($message['status']['str'] == 'cancel'){
                    $last_backup_status='Failed';
                }
                else{
                    $last_backup_status='Succeeded';
                }
            }
        }

        $data['last_backup_time']=$last_backup_time;
        $data['next_backup_time']=$next_backup_time;
        $data['last_backup_status']=$last_backup_status;
        $data['last_backup_duration']=$duration;
        $data['next_backup_type']=$next_backup_type;
        $data['next_backup_cycle']=$next_backup_cycle;
        $data['next_backup_destination']=$next_backup_destination;
        $data['next_backup_estimate_size']=$estimated_size;
        return $data;
    }

    public function sort_list($schedule)
    {
        uasort($schedule, function ($a, $b) {
            $a_timestamp = wp_next_scheduled($a['id'], array($a['id']));
            $a['next_start'] = $a_timestamp;
            $b_timestamp = wp_next_scheduled($b['id'], array($b['id']));
            $b['next_start'] = $b_timestamp;
            if ($a['next_start'] > $b['next_start']) {
                return 1;
            } else if ($a['next_start'] === $b['next_start']) {
                return 0;
            } else {
                return -1;
            }
        });

        return $schedule;
    }

    public function addon_page_url($url,$slug)
    {
        if($slug=='imgoptim_pro')
        {
            $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-imgoptim', 'wpvivid-imgoptim');
        }
        else if($slug=='staging_pro')
        {
            $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-staging', 'wpvivid-staging');
        }
        else if($slug=='white_label')
        {
            $url='';
        }
        else if($slug=='role_cap')
        {
            $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-capabilities', 'wpvivid-capabilities');
        }
        else if($slug=='snapshot_database')
        {
            $url=apply_filters('wpvivid_white_label_page_redirect', 'admin.php?page=wpvivid-snapshot', 'wpvivid-snapshot');
        }
        return $url;
    }


    public function wpvivid_get_addon_status_label($status)
    {
        switch ($status)
        {
            case 'Install':
                return __('Install', 'wpvivid-backup-pro');
            case 'Update':
                return __('Update', 'wpvivid-backup-pro');
            case 'Activate':
                return __('Activate', 'wpvivid-backup-pro');
            case 'Unavailable':
                return __('Unavailable', 'wpvivid-backup-pro');
            default:
                return $status;
        }
    }

    public function wpvivid_get_addon_desc($items)
    {
        $slug = isset($items['slug']) ? $items['slug'] : '';
        switch ($slug)
        {
            case 'staging_pro':
                return __('Create a staging site to test changes securely and publish the staging site to live.', 'wpvivid-backup-pro');
            case 'snapshot_database':
                return __('Create snapshots of a WordPress database quickly.', 'wpvivid-backup-pro');
            case 'white_label':
                return __('Rebrand WPvivid with your own branding and present WPvivid features as your own service.', 'wpvivid-backup-pro');
            case 'role_cap':
                return __('Edit capabilities of user roles on your website to access WPvivid pro features.', 'wpvivid-backup-pro');
            case 'imgoptim_pro':
                return __('More advanced image optimization features developed based on the free version.', 'wpvivid-backup-pro');
            default:
                return isset($items['info']) ? $items['info'] : '';
        }
    }

    public function addon_page_title($title,$slug)
    {
        if($slug=='imgoptim_pro')
        {
            $title=__('Image Optimization Pro', 'wpvivid-backup-pro');
        }
        else if($slug=='staging_pro')
        {
            $title=__('Staging', 'wpvivid-backup-pro');
        }
        else if($slug=='white_label')
        {
            $title=__('White Label', 'wpvivid-backup-pro');
        }
        else if($slug=='role_cap')
        {
            $title=__('Roles & Capabilities', 'wpvivid-backup-pro');
        }
        return $title;
    }
}