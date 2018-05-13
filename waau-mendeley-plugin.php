<?php
/*
 * @wordpress-plugin
 * Plugin Name: Waau Mendeley Wordpress Plugin
 * Plugin URI: https://github.com/mattimatti/mendeleyplugin
 * Description: This plugin allows connecting to Mendeley® and searching group documents
 * Version: 1.0.35
 * Author: Matteo Monti, credits to Davide Parisi, Nicola Musicco
 * Author URI: http://waau.it
 * License: MIT
 */
define('MENDELEY__PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MENDELEY__PLUGIN_URL', plugin_dir_url(__FILE__));



// If this file is called directly, abort.
if (! defined('WPINC')) {
    die();
}
/*----------------------------------------------------------------------------*
 * Auto Update Functionality
 *----------------------------------------------------------------------------*/

require_once MENDELEY__PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/mattimatti/waau-mendeleyplugin/',
    __FILE__,
    'waau-mendeley-plugin.php'
    );

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');


$myUpdateChecker->getVcsApi()->enableReleaseAssets();
//Optional: Set the branch that contains the stable release.
// $myUpdateChecker->setBranch('stable-branch-name');
 
 
 /*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once MENDELEY__PLUGIN_DIR . 'includes/vendor/autoload.php';
require_once MENDELEY__PLUGIN_DIR . 'public/WaauMendeleyPlugin.php';

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook(__FILE__, array(
    'WaauMendeleyPlugin',
    'activate',
));
register_deactivation_hook(__FILE__, array(
    'WaauMendeleyPlugin',
    'deactivate',
));

add_action('plugins_loaded', array(
    'WaauMendeleyPlugin',
    'get_instance',
));

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {

    require_once MENDELEY__PLUGIN_DIR . 'admin/WaauMendeleyPluginAdmin.php';
    add_action('plugins_loaded', array(
        'WaauMendeleyPluginAdmin',
        'get_instance',
    ));
}

/*----------------------------------------------------------------------------*
 * Mendeley API Functionality
 *----------------------------------------------------------------------------*/

require_once MENDELEY__PLUGIN_DIR . 'includes/MendeleyApi.php';
add_action('plugins_loaded', array(
    'MendeleyApi',
    'get_instance',
));
