<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   WaauMendeleyPlugin
 * @author    Davide Parisi <davideparisi@gmail.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 --
 */

// If uninstall not called from WordPress, then exit
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

global $wpdb;

if (is_multisite()) {
    
    $blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A);
    if ($blogs) {
        foreach ($blogs as $blog) {
            switch_to_blog($blog['blog_id']);
            
            // uninstall
            WaauMendeleyPlugin::single_uninstall();
            
            restore_current_blog();
        }
    }
} else {
    // uninstall
    WaauMendeleyPlugin::single_uninstall();
}