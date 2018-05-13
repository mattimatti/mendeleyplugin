<?php

/**
 * Waau Mendeley Plugin
 *
 * @package   WaauMendeleyPlugin
 * @author    Matteo Monti, credits to Davide Parisi, Nicola Musicco
 * @copyright 2014 -2018
 */
class WaauMendeleyPlugin
{

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @var     string
     */
    const VERSION = '1.0.32';

    /**
     *
     * Unique identifier for your plugin.
     *
     *
     * The variable name is used as the text domain when internationalizing strings
     * of text. Its value should match the Text Domain file header in the main
     * plugin file.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'waau-mendeley-plugin';

    /**
     *
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $db_version = '1.0';

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    /**
     * Initialize the plugin by setting localization and loading public scripts
     * and styles.
     *
     * @since     1.0.0
     */
    private function __construct()
    {
        
        // Load plugin text domain
        add_action('init', array(
            $this,
            'load_plugin_textdomain'
        ));
        
        // Register shortcode/s
        add_action('init', array(
            $this,
            'register_shortcode'
        ));
        
        // Activate plugin when new blog is added
        add_action('wpmu_new_blog', array(
            $this,
            'activate_new_site'
        ));
        
        // Register public-facing style sheet.
        add_action('wp_enqueue_scripts', array(
            $this,
            'enqueue_styles'
        ));
        
        // Register public-facing JavaScript.
        add_action('wp_enqueue_scripts', array(
            $this,
            'enqueue_scripts'
        ));
        
        /**
         * Search documents by given params
         */
        add_action('parse_request', array(
            $this,
            'handleSearchAction'
        ));
        
        /**
         * Download a document by given params
         */
        add_action('parse_request', array(
            $this,
            'handleDownloadAction'
        ));
        
        /**
         * Define how to view a document by given params
         */
        add_action('parse_request', array(
            $this,
            'handleViewAction'
        ));
    }

    /**
     * Return the plugin slug.
     *
     * @since    1.0.0
     *
     * @return    Plugin slug variable.
     */
    public function get_plugin_slug()
    {
        return $this->plugin_slug;
    }

    /**
     * Return the database version.
     *
     * @since    1.0.0
     *
     * @return    Database version.
     */
    public function get_db_version()
    {
        return $this->db_version;
    }

    /**
     * Return the database table name.
     *
     * @since    1.0.0
     *
     * @return    Database table name.
     */
    public function get_db_tablename()
    {
        global $wpdb;
        return $wpdb->prefix . "mendeleydocindex";
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
     * Fired when the plugin is activated.
     *
     * @since    1.0.0
     *
     * @param    boolean $network_wide True if WPMU superadmin uses
     *                                       "Network Activate" action, false if
     *                                       WPMU is disabled or plugin is
     *                                       activated on an individual blog.
     */
    public static function activate($network_wide)
    {
        if (function_exists('is_multisite') && is_multisite()) {
            
            if ($network_wide) {
                
                // Get all blog ids
                $blog_ids = self::get_blog_ids();
                
                foreach ($blog_ids as $blog_id) {
                    
                    switch_to_blog($blog_id);
                    self::single_activate();
                    
                    restore_current_blog();
                }
            } else {
                self::single_activate();
            }
        } else {
            self::single_activate();
        }
    }

    /**
     * Fired when the plugin is deactivated.
     *
     * @since    1.0.0
     *
     * @param    boolean $network_wide True if WPMU superadmin uses
     *                                       "Network Deactivate" action, false if
     *                                       WPMU is disabled or plugin is
     *                                       deactivated on an individual blog.
     */
    public static function deactivate($network_wide)
    {
        if (function_exists('is_multisite') && is_multisite()) {
            
            if ($network_wide) {
                
                // Get all blog ids
                $blog_ids = self::get_blog_ids();
                
                foreach ($blog_ids as $blog_id) {
                    
                    switch_to_blog($blog_id);
                    self::single_deactivate();
                    
                    restore_current_blog();
                }
            } else {
                self::single_deactivate();
            }
        } else {
            self::single_deactivate();
        }
    }

    /**
     * Fired when a new site is activated with a WPMU environment.
     *
     * @since    1.0.0
     *
     * @param    int $blog_id ID of the new blog.
     */
    public function activate_new_site($blog_id)
    {
        if (1 !== did_action('wpmu_new_blog')) {
            return;
        }
        
        switch_to_blog($blog_id);
        self::single_activate();
        restore_current_blog();
    }

    /**
     * Get all blog ids of blogs in the current network that are:
     * - not archived
     * - not spam
     * - not deleted
     *
     * @since    1.0.0
     *
     * @return   array|false    The blog ids, false if no matches.
     */
    private static function get_blog_ids()
    {
        global $wpdb;
        
        // get an array of blog ids
        $sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
        
        return $wpdb->get_col($sql);
    }

    /**
     * Fired for each blog when the plugin is activated.
     *
     * @since    1.0.0
     */
    private static function single_activate()
    {
        $plugin_slug = self::get_instance()->get_plugin_slug();
        $db_version = self::get_instance()->get_db_version();
        
        // check if the version has changed
        $old_version = get_option($plugin_slug . '-db_version');
        
        if ($old_version) {
            // if we have a version change.. DROP and create
            if ($db_version != $old_version) {
                self::single_deactivate();
            }
        }
        
        $table_name = self::get_instance()->get_db_tablename();
        
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(1) NOT NULL AUTO_INCREMENT,
        documentid varchar(255) NULL,
        fullsearch text NULL,
        serialized text NULL,
        PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        add_option($plugin_slug . '-db_version', $db_version);
    }

    /**
     * Fired for each blog when the plugin is deactivated.
     *
     * @since    1.0.0
     */
    private static function single_deactivate()
    {
        $table_name = self::get_instance()->get_db_tablename();
        $plugin_slug = self::get_instance()->get_plugin_slug();
        
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // remove all settings..
        self::get_instance()->update_options(array());
        
        $defaults = array(
            'client_id' => '',
            'client_secret' => '',
            'group_id' => '',
            'cache' => false
        );
        
        self::get_instance()->update_options($defaults);
        
        delete_option($plugin_slug . '-groups');
        
        $sql = "TRUNCATE TABLE $table_name;";
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        self::single_uninstall();
    }

    /**
     * Fired for each blog when the plugin is deactivated.
     *
     * @since    1.0.0
     */
    public static function single_uninstall()
    {
        $table_name = self::get_instance()->get_db_tablename();
        $plugin_slug = self::get_instance()->get_plugin_slug();
        
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "DROP TABLE IF EXISTS $table_name;";
        
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        delete_option($plugin_slug . '-db_version');
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        // $domain = $this->plugin_slug;
        // $locale = apply_filters('plugin_locale', get_locale(), $domain);
        // load_textdomain($domain, trailingslashit(WP_LANG_DIR) . $domain . '/' . $domain . '-' . $locale . '.mo');
        // load_plugin_textdomain($domain, false, basename(plugin_dir_path(dirname(__FILE__))) . '/languages/');
    }

    /**
     * Register and enqueue public-facing style sheet.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        // wp_register_style($this->plugin_slug . 'plugin_css', MENDELEY__PLUGIN_URL . 'public/assets/css/dist/app.min.css', array(), self::VERSION, 'all');
        wp_register_style($this->plugin_slug . 'plugin_css', MENDELEY__PLUGIN_URL . 'public/assets/css/dist/app.css', array(), self::VERSION, 'screen');
    }

    /**
     * Register and enqueues public-facing JavaScript files.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_register_script($this->plugin_slug . '_plugin_js', MENDELEY__PLUGIN_URL . 'public/assets/js/dist/app.js', array(
            'jquery'
        ), self::VERSION);
        
        //
        wp_register_script($this->plugin_slug . '_underscore', MENDELEY__PLUGIN_URL . 'public/assets/js/dist/underscore-min.js', array(), self::VERSION);
    }

    /*----------------------------------------------------------------------------/
     *
     * Process Shortcode
     *
     *---------------------------------------------------------------------------*/
    public function register_shortcode()
    {
        add_shortcode('mendeleysearch', array(
            $this,
            'shortcode_mendeleysearch'
        ));
    }

    /**
     *
     * @param array $atts
     */
    public function shortcode_mendeleysearch($atts = array())
    {
        // register styles and scripts only if the shortcode is in place
        wp_enqueue_style($this->plugin_slug . 'plugin_css');
        wp_enqueue_script($this->plugin_slug . '_plugin_js');
        wp_enqueue_script($this->plugin_slug . '_underscore');
        
        // accept shortcode parameters
        // [mendeleysearch id="17" extra="BAR"]
        shortcode_atts(array(
            'id' => 'id not set',
            'extra' => 'extra not set'
        ), $atts, 'mendeleysearch');
        
        // load a generic/ non localized gui
        $tpl = file_get_contents(MENDELEY__PLUGIN_DIR . 'includes/gui.html');
        
        return $tpl;
    }

    public function get_account_info()
    {
        $info = get_option($this->plugin_slug . '-account-info');
        return $info;
    }

    public function get_access_token()
    {
        $options = $this->get_options();
        return $options['access_token']['result']['access_token'];
    }

    /**
     * 
     * @param array $params
     * @return array|void|NULL|mixed
     */
    public function get_publications($params = array())
    {
        // get the stored options
        $options = $this->get_options();
        
        // if publications in cache
        if (isset($options['cache'])) {
            if ($options['cache'] == true) {
                return $this->get_cached_publications();
            }
            return $this->get_remote_publications($params);
        }
        
        return $this->get_remote_publications($params);
    }

    /**
     * Load File info  for given document
     * 
     * @param string $documentId
     */
    public function loadDocumentFile($doc_id)
    {
        $client = $this->getClient();
        
        $info = $client->get_file_info($doc_id);
        
        return $info;
    }

    /**
     * 
     * @param bool $refreshToken
     */
    public function getClient()
    {
        // get the stored options
        $options = $this->get_options();
        
        // if cannot get options we are not setup yet..
        if (false == $options) {
            throw new Exception("Missing Options");
        }
        
        $client = MendeleyApi::get_instance();
        
        $client->setOptions($options)->set_up($options['client_id'], $options['client_secret'], admin_url('options-general.php?page=' . $this->plugin_slug));
        
        $tokendata = $client->checkAccessToken();
        
        if ($tokendata) {
            $options['access_token'] = $tokendata;
            $this->update_options($options);
        }
        
        return $client;
    }

    /**
     * 
     * @return array
     */
    private function get_cached_publications()
    {
        $publications = get_option($this->plugin_slug . '-cache');
        return $publications;
    }

    /**
     * @deprecated 
     * 
     * Since we don't have a full text search functionality in mendeley document endpoint
     * we use the indexed data stored in database
     * 
     * @param array $params
     * @return void|NULL|mixed
     */
    private function get_remote_publications($params = array())
    {
        $client = $this->getClient();
        
        $options = $this->get_options();
        if (false == $options) { // if cannot get options
            return array();
        }
        
        // the group to fetch..
        $params['group_id'] = $options['group_id'];
        
        $publications = $client->get_group_publications($params);
        
        $this->update_cache($publications);
        
        return $publications;
    }

    /**
     *
     * @param array $params
     * @return array
     */
    public function search($params = array())
    {
        global $wpdb;
        
        $data = array();
        $data['params'] = $params;
        $data['options'] = $this->get_options();
        
        try {
            
            $data['items'] = array();
            
            $table_name = $this->get_db_tablename();
            
            $sql = "SELECT * FROM $table_name WHERE 1=1";
            
            
            if (isset($params['query'])) {
                
                // split the query
                
                $query = trim($params['query']);
                
                $terms = explode(' ', $query);
                
                foreach ($terms as $term) {
                    if(count($term) > 2){
                        $sql .= " AND fullsearch LIKE '%$term%' ";
                    }
                }
            }
            
            // retrunan object collection
            $results = $wpdb->get_results($sql);
            
            if (! is_array($results) || empty($results)) {
                throw new Exception("No Documents Found");
            }
            
            foreach ($results as $doc) {
                
                $dedoc = json_decode($doc->serialized, 1);
                $data['items'][] = $this->formatDocument($dedoc);
            }
            
            $sortfield = 'id';
            if (isset($params['sortfield'])) {
                $sortfield = $params['sortfield'];
            }
            
            $sortorder = null;
            if (isset($params['sortdirection'])) {
                $sortorder = $params['sortdirection'];
            }
            
            $sorter = new FieldSorter($sortfield, $sortorder);
            usort($data['items'], array(
                $sorter,
                "cmp"
            ));
            
            //
        } catch (Exception $ex) {
            $data['error'] = $ex->getMessage();
        }
        
        return $data;
    }

    /**
     * /mendeleydownload
     */
    public function handleDownloadAction()
    {
        if (isset($_GET['mendeleydownload'])) {
            $params = $_GET;
            
            $client = $this->getClient();
            $client->setOptions($this->get_options());
            $info = $client->get_file($params['id']);
            
            header('Location: ' . $info['redirect_url']);
            exit();
        }
    }

    /**
     * /mendeleyview
     */
    public function handleViewAction()
    {
        if (isset($_GET['mendeleyview'])) {
            
            $params = $_GET;
            
            $data = array();
            $data['items'] = array();
            
            $results = $this->loadDocumentFile($params['id']);
            
            foreach ($results as $result) {
                $data['items'][] = $this->formatFileInfo($result);
            }
            
            header('Content-Type: application/json');
            echo json_encode($data);
            
            exit();
        }
    }

    /**
     * /?mendeley
     * /mendeleysearch
     */
    public function handleSearchAction()
    {
        if (isset($_GET['mendeleysearch'])) {
            
            // copy the get
            $params = json_decode(json_encode($_GET), true);
            
            unset($params['mendeleysearch']);
            
            // search..
            $data = $this->search($params);
            
            header('Content-Type: application/json');
            echo json_encode($data);
            
            exit();
        }
    }

    /*----------------------------------------------------------------------------/
     *
     * Utilities
     *
     *---------------------------------------------------------------------------*/
    public function get_options()
    {
        $opts = get_option($this->plugin_slug);
        return $opts;
    }

    /**
     * Update options array with db data (if present)
     * Simple wrapper for the update_option wordpress function
     *
     * @param $options
     */
    public function update_options($options)
    {
        update_option($this->plugin_slug, $options);
    }

    /**
     * 
     * @param array $data
     */
    public function update_cache($data, $cacheid = 'cache')
    {
        $options = $this->get_options();
        
        if (isset($options['cache'])) {
            if ($options['cache'] == true) {
                add_option($this->plugin_slug . '-' . $cachename, $options);
            }
        }
    }

    /**
     *
     * @param unknown $doc
     * @return unknown
     */
    private function formatFileInfo(array $doc)
    {
        if (isset($doc['size'])) {
            $doc['sizestr'] = $this->formatBytes($doc['size']);
        }
        
        return $doc;
    }

    /**
     * Apply formatting to the doc..
     * @param unknown $doc
     * @return unknown
     */
    private function formatDocument($doc)
    {
        $doc['authorsstrr'] = '';
        
        if (isset($doc['authors'])) {
            foreach ($doc['authors'] as $author) {
                $doc['authorsstrr'] .= '';
                
                if (isset($author['last_name'])) {
                    $doc['authorsstrr'] .= $author['last_name'];
                    if (isset($author['first_name'])) {
                        $doc['authorsstrr'] .= ' ';
                        $doc['authorsstrr'] .= $author['first_name'];
                    }
                    
                    $doc['authorsstrr'] .= ', ';
                }
            }
        }
        
        $doc['authors'] = $doc['authorsstrr'];
        unset($doc['authorsstrr']);
        
        $doc['short'] = (isset($doc['abstract'])) ? $doc['abstract'] : '';
        $doc['short'] = $this->limitText($doc['short'], 80);
        
        $doc['publication'] = $doc['title'];
        unset($doc['title']);
        
        $doc['viewurl'] = '?mendeleyview&id=' . $doc['id'];
        return $doc;
    }

    /**
     *
     * @param unknown $text
     * @param unknown $limit
     * @return string
     */
    private function limitText($text, $limit)
    {
        if (str_word_count($text, 0) > $limit) {
            $words = str_word_count($text, 2);
            $pos = array_keys($words);
            $text = substr($text, 0, $pos[$limit]) . '...';
        }
        return $text;
    }

    /**
     *
     * @param unknown $bytes
     * @param number $precision
     * @return string
     */
    private function formatBytes($bytes, $decimals = 2)
    {
        $size = array(
            'B',
            'kB',
            'MB',
            'GB',
            'TB',
            'PB',
            'EB',
            'ZB',
            'YB'
        );
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}

class FieldSorter
{

    public $field;

    public $order;

    function __construct($field, $order = 'asc')
    {
        $this->field = $field;
        $this->order = $order;
    }

    function cmp($a, $b)
    {
        if ($a[$this->field] == $b[$this->field]) {
            return 0;
        }
        
        if ($this->order == 'asc') {
            return ($a[$this->field] > $b[$this->field]) ? 1 : - 1;
        }
        
        if ($this->order == 'desc') {
            return ($a[$this->field] < $b[$this->field]) ? 1 : - 1;
        }
    }
}
