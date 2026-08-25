<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR')) {
    exit;
}

if (!class_exists('WPvivid_Pro_i18n')) {
    class WPvivid_Pro_i18n
    {
        public function load_plugin_textdomain()
        {
            load_plugin_textdomain(
                'wpvivid-backup-pro',
                false,
                dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
            );
        }
    }
}
