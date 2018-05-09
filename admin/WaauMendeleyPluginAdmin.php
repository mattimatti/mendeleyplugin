<?php

/**
 * Waau Mendeley Plugin
 *
 * @package   WaauMendeleyPluginAdmin
 * @author    Matteo Monti, credits to Davide Parisi, Nicola Musicco
 * @copyright 2014 -2018
 */
class WaauMendeleyPluginAdmin
{

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    /**
     * Slug of the plugin screen.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_screen_hook_suffix = null;

    protected $options = null;

    // protected $client = null;
    protected $callback_url = '';

    /**
     * 
     * @var unknown
     */
    protected $plugin_slug = null;

    /**
     * 
     * @var WaauMendeleyPlugin
     */
    protected $plugin;

    /**
     * Initialize the plugin by loading admin scripts & styles and adding a
     * settings page and menu.
     *
     * @since     1.0.0
     */
    function __construct()
    {
        $this->init();
        
        // Load admin style sheet and JavaScript.
        add_action('admin_enqueue_scripts', array(
            $this,
            'enqueue_admin_styles'
        ));
        add_action('admin_enqueue_scripts', array(
            $this,
            'enqueue_admin_scripts'
        ));
        
        // Add the options page and menu item.
        add_action('admin_menu', array(
            $this,
            'add_plugin_admin_menu'
        ));
        
        // Add an action link pointing to the options page.
        $plugin_basename = plugin_basename(plugin_dir_path(realpath(dirname(__FILE__))) . $this->plugin_slug . '.php');
        
        add_filter('plugin_action_links_' . $plugin_basename, array(
            $this,
            'add_action_links'
        ));
        
        add_action('admin_action_request_token', array(
            $this,
            'request_access_token'
        ));
        
        add_action('admin_action_load_groups', array(
            $this,
            'load_user_groups'
        ));
        
        add_action('admin_action_import_publications', array(
            $this,
            'import_group_publications'
        ));
        
        // add contextual help
        add_filter('contextual_help', array(
            $this,
            'show_help'
        ));
        
        add_action('admin_init', array(
            $this,
            'initialize_options'
        ));
    }

    /**
     * 
     */
    public function init()
    {
        $this->plugin = WaauMendeleyPlugin::get_instance();
        
        $this->plugin_slug = $this->plugin->get_plugin_slug();
        
        $this->callback_url = admin_url('options-general.php?page=' . $this->plugin_slug);
        
        $options = $this->plugin->get_options();
        
        if (isset($options['access-token'])) {
            $actoken = $options['access-token'];
            if (isset($actoken) && $actoken) {
                $this->check_access_token();
            }
        }
    }

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance()
    {
        
        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Register and enqueue admin-specific style sheet.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_styles()
    {
        if (! isset($this->plugin_screen_hook_suffix)) {
            return;
        }
        
        $screen = get_current_screen();
        
        if ($this->plugin_screen_hook_suffix == $screen->id) {
            wp_enqueue_style($this->plugin_slug . '-admin-styles', plugins_url('assets/css/admin.css', __FILE__), array(), WaauMendeleyPlugin::VERSION);
        }
    }

    /**
     * Register and enqueue admin-specific JavaScript.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_scripts()
    {
        if (! isset($this->plugin_screen_hook_suffix)) {
            return;
        }
        
//         $screen = get_current_screen();
//         if ($this->plugin_screen_hook_suffix == $screen->id) {
//             wp_enqueue_script($this->plugin_slug . '-admin-script', plugins_url('assets/js/admin.js', __FILE__), array(
//                 'jquery'
//             ), WaauMendeleyPlugin::VERSION);
//         }
    }

    /* ------------------------------------------------------------------------ *
     * Setting Registration
     * ------------------------------------------------------------------------ */
    public function default_keys_options()
    {
        $defaults = array(
            'client_id' => '',
            'client_secret' => '',
            'group_id' => '',
            'cache' => false
        );
        
        return apply_filters('default_keys_options', $defaults);
    }

    public function initialize_options()
    {
        
        // resetta gli access tokens?
        //add_option($this->plugin_slug, apply_filters('default_keys_options', $this->default_keys_options()));
        
        add_settings_section('waau_mendeley_settings_section', 'API Key Setting', array(
            $this,
            'options_callback'
        ), $this->plugin_slug);
        
        add_settings_field('client_id', 'Client ID', array(
            $this,
            'client_id_input_callback'
        ), $this->plugin_slug, 'waau_mendeley_settings_section', array(
            'Insert the client ID'
        ));
        
        add_settings_field('client_secret', 'Client Secret', array(
            $this,
            'client_secret_input_callback'
        ), $this->plugin_slug, 'waau_mendeley_settings_section', array(
            'Insert the client secret'
        ));
        
        add_settings_field('group_id', 'Group Id', array(
            $this,
            'demo_select_display'
        ), $this->plugin_slug, 'waau_mendeley_settings_section', array(
            'Insert the group id'
        ));
        
        // add_settings_field("demo-select", "Demo Select Box", "demo_select_display", "demo", "section");
        
        register_setting($this->plugin_slug, $this->plugin_slug, array(
            $this,
            'validate'
        ));
    }

    function demo_select_display()
    {
        $options = $this->plugin->get_options();
        
            
            $groups = get_option($this->plugin_slug . '-groups');
            $gid = (isset($options['group_id'])) ? $options['group_id'] : '';
            
            ?>
<select name="<?php echo $this->plugin_slug; ?>[group_id]">
	<option value="">Select a group</option>
<?php
            if ($groups && is_array($groups)) {
                foreach ($groups as $group) {
                    ?>
				<option value="<?php echo $group['id']; ?>" <?php selected($gid, $group['id']); ?>><?php echo $group['name']; ?></option>
		<?php
                } // ed foreach
            } else {
                ?>
				<option value="">Load Groups</option>
		<?php
            }
            ?>
</select>
<a href="<?php admin_url( "admin.php" ); ?>?action=load_groups">Load groups</a>

<?php
        
    }

    public function options_callback()
    {
        echo '<p class="description">Enter the <code>client ID</code> and <code>client secret</code> you have got from registering this plugin on <a href="http://dev.mendeley.com">Mendeley</a> (see contextual help tab above)</p>';
    }

    public function client_id_input_callback($args)
    {
        $options = $this->plugin->get_options();
        $html = '<input type="text" id="client_id" name="' . $this->plugin_slug . '[client_id]" value="' . $options['client_id'] . '" />';
        echo $html;
    }

    public function client_secret_input_callback($args)
    {
        $options = $this->plugin->get_options();
        $html = '<input type="password" id="client_secret" name="' . $this->plugin_slug . '[client_secret]" value="' . $options['client_secret'] . '" />';
        echo $html;
    }

    // public function group_id_input_callback($args)
    // {
    // $options = $this->plugin->get_options();
    // if (! isset($options['group_id'])) {
    // $options['group_id'] = '';
    // }
    // $html = '<input type="text" id="group_id" name="' . $this->plugin_slug . '[group_id]" value="' . $options['group_id'] . '" />';
    // echo $html;
    // }
    public function validate($input)
    {
        $output = array();
        foreach ($input as $key => $value) {
            if (isset($input[$key])) {
                if ($key == 'access_token') {
                    $output[$key] = $input[$key];
                } else {
                    $thekey = (isset($input[$key]) && ! empty($input[$key])) ? $input[$key] : '';
                    $output[$key] = strip_tags(stripslashes($thekey));
                }
            }
        }
        
        return apply_filters('validate', $output, $input);
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu()
    {
        
        /*
         * Add a settings page for this plugin to the Settings menu.
         *
         * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
         *
         *        Administration Menus: http://codex.wordpress.org/Administration_Menus
         */
        $this->plugin_screen_hook_suffix = add_options_page(__('Waau Mendeley Plugin', $this->plugin_slug), __('Mendeley Settings', $this->plugin_slug), 'manage_options', $this->plugin_slug, array(
            $this,
            'display_plugin_admin_page'
        ));
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page()
    {
        if (isset($_GET['code'])) {
            $this->store_access_token($_GET['code']);
        }
        include_once ('views/admin.php');
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links($links)
    {
        return array_merge(array(
            'settings' => '<a href="' . admin_url('options-general.php?page=' . $this->plugin_slug) . '">' . __('Settings', $this->plugin_slug) . '</a>'
        ), $links);
    }

    /**
     * NOTE:     Filters are points of execution in which WordPress modifies data
     *           before saving it or sending it to the browser.
     *
     *           Filters: http://codex.wordpress.org/Plugin_API#Filters
     *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
     *
     * @since    1.0.0
     */
    public function show_help()
    {
        $screen = get_current_screen();
        $screen->add_help_tab(array(
            'id' => 'plugin_options_help',
            'title' => 'Setup',
            'content' => "<h2>Mendeley Plugin Setup</h2>",
            'callback' => array(
                $this,
                'show_help_content'
            )
        ));
    }

    public function show_help_content()
    {
        echo file_get_contents(MENDELEY__PLUGIN_DIR . 'admin/views/help.php');
    }

    /**
     * 
     */
    public function request_access_token()
    {
        $options = $this->plugin->get_options();
        if ($options['client_id'] === '' || $options['client_secret'] === '') {
            return;
        }
        // get setted client instance
        $client = $this->set_up_client($options);
        
        // Redirect to mendeley login page
        $client->start_authorization_flow();
    }

    /**
     * 
     */
    public function load_user_groups()
    {
        $url = $_SERVER['HTTP_REFERER'];
        
        $options = $this->plugin->get_options();
        if ($options['client_id'] === '' || $options['client_secret'] === '') {
            return;
        }
        
        $client = $this->getClient(true);
        
        // Redirect to mendeley login page
        $groups = $client->load_groups();
        
        if ($groups) {
            update_option($this->plugin_slug . '-groups', $groups);
        }
        
        wp_redirect($url);
        
        exit();
    }

    /**
     * 
     * @param string $refreshToken
     * @return void|string|MendeleyApi
     */
    public function getClient($refreshToken = true)
    {
        $options = $this->plugin->get_options();
        
        if (! isset($options) || false == $options) { // if cannot get options
            return; // exit and do nothing
        }
        
        if (! isset($options['expire_time'])) {
            return $this->request_access_token();
        }
        
        if (isset($options['expire_time'])) {
            
            if (time() > $options['expire_time']) {
                $this->refresh_token();
                $options = $this->plugin->get_options();
            }
        }
        
        $token_data_array = $options['access_token']['result'];
        
        if (! isset($token_data_array)) {
            return "must authenticate";
        }
        
        $client = $this->set_up_client($options);
        $client->set_callback_url(admin_url('options-general.php?page=' . $this->plugin_slug));
        
        $token = $token_data_array['access_token'];
        $client->set_client_access_token($token);
        
        return $client;
    }

    /**
     * 
     */
    public function import_group_publications()
    {
        $url = $_SERVER['HTTP_REFERER'];
        
        $options = $this->plugin->get_options();
        
        // $author_info = $client->get_account_info();
        // update_option($this->plugin_slug . '-account-info', $author_info);
        
        $client = $this->getClient(true);
        try {
            //
            $publications = $client->index_group_publications();
            
            //
            $dt = new DateTime();
            $options['last-import'] = $dt->format('d-m-Y H:i:s');
            $options['indexed-count'] = count($publications);
            
            //
            //
        } catch (Exception $ex) {
            $options['last-import-error'] = $ex->getMessage();
        }
        $this->plugin->update_options($options);
        
        wp_redirect($url);
        
        exit();
    }

    /**
     * 
     * @param string $auth_code
     */
    public function store_access_token($auth_code)
    {
        $options = $this->plugin->get_options();
        
        $client = $this->set_up_client($options);
        
        $access_token = $client->get_access_token($auth_code);
        
        if ($access_token['code'] === 200) {
            $options['access_token'] = $access_token;
            $access_token_data = $options['access_token']['result'];
            $expire_time = (time() + $access_token_data['expires_in']);
            $expire_time_humanized = date('d-n-Y H:i:s', $expire_time);
            $options['expire_time'] = $expire_time;
            $options['et_humanized'] = $expire_time_humanized;
            $this->plugin->update_options($options);
        }
    }

    public function check_access_token()
    {
        $options = $this->plugin->get_options();
        $access_token_data = $options['access_token']['result'];
        
        if (time() > $access_token_data['expire_time']) {
            $this->refresh_token();
        }
    }

    public function refresh_token()
    {
        $options = $this->plugin->get_options();
        
        $client = $this->set_up_client($options);
        $result = $options['access_token']['result'];
        $refresh_token = $result['refresh_token'];
        
        $client->set_up($options['client_id'], $options['client_secret'], $this->callback_url);
        $new_token = $client->refresh_access_token($refresh_token);
        $options['access_token'] = $new_token;
        $access_token_data = $options['access_token']['result'];
        $expire_time = (time() + $access_token_data['expires_in']);
        $options['expire_time'] = $expire_time;
        
        $this->plugin->update_options($options);
    }

    /*------------------------------------------------------------------------------
     *
     * Private Functions/utilities
     *
     -----------------------------------------------------------------------------*/
    private function set_up_client($options)
    {
        $client = MendeleyApi::get_instance();
        $client->setOptions($options);
        $client->set_up($options['client_id'], $options['client_secret'], $this->callback_url);
        $client->init();
        
        return $client;
    }
}
