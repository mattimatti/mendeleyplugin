<?php
use OAuth2\Client;

/**
 * Waau Mendeley Plugin
 *
 * @package   MendeleyApi
 * @author    Matteo Monti, credits to Davide Parisi, Nicola Musicco
 * @copyright 2014 -2018
 */
class MendeleyApi
{

    const AUTHORIZE_ENDPOINT = 'https://api.mendeley.com/oauth/authorize';

    const TOKEN_ENDPOINT = 'https://api.mendeley.com/oauth/token';

    const API_ENDPOINT = 'https://api.mendeley.com/';

    /**
     * 
     * @var Client
     */
    protected $client = null;

    /**
     * 
     * @var array
     */
    protected $options = array();

    protected $client_id = '';

    protected $client_secret = '';

    protected $callback_url = '';

    /**
     * 
     * @var MendeleyApi
     */
    protected static $instance = null;

    function __construct()
    {
        $this->client_id = '';
        $this->client_secret = '';
        $this->callback_url = '';
    }

    /**
     * 
     * @return MendeleyApi
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
     * Set up needed components and startup the object
     *
     * @param $client_id
     * @param $client_secret
     * @param $callback_url
     */
    public function set_up($client_id, $client_secret, $callback_url)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->callback_url = $callback_url;
        $this->init();
        
        return $this;
    }

    /**
     * 
     * @param unknown $client
     */
    public function init($client = null)
    {
        if (! $client) {
            $client = new \OAuth2\Client($this->get_client_id(), $this->get_client_secret());
        }
        $this->setClient($client);
    }

    /**
     * 
     */
    public function start_authorization_flow()
    {
        $url = $this->client->getAuthenticationUrl(self::AUTHORIZE_ENDPOINT, $this->callback_url, array(
            'scope' => 'all'
        ));
        // go to api.mendeley.com/oauth
        wp_redirect($url);
        exit();
    }

    /**
     * get a long lived access code..
     * 
     * @see https://dev.mendeley.com/reference/topics/authorization_auth_code.html
     * @param unknown $auth_code
     * @return array
     */
    public function get_access_token($auth_code)
    {
        // set request parameters
        $params = array(
            'code' => $auth_code,
            'redirect_uri' => $this->callback_url
        );
        $response = $this->client->getAccessToken(self::TOKEN_ENDPOINT, 'authorization_code', $params);
        
        return $response;
    }

    /**
     * 
     * @param unknown $access_token
     */
    public function set_client_access_token($access_token)
    {
        $this->client->setAccessToken($access_token);
    }

    /**
     * @see https://dev.mendeley.com/reference/topics/authorization_auth_code.html
     * @param unknown $refresh_token
     * @return array
     */
    public function refresh_access_token($refresh_token)
    {
        $params = array(
            'refresh_token' => $refresh_token
        );
        
        $response = $this->client->getAccessToken(self::TOKEN_ENDPOINT, 'refresh_token', $params);
        
        return $response;
    }

    /**
     * Utility function to parse headers looking for links.
     * @param unknown $linkHeader
     * @return number[]|unknown[]|mixed[]
     */
    public function parseLinkHeader($linkHeader)
    {
        preg_match('/<([^>]+)>;\s*rel=[\'"]([a-z]*)[\'"]/', $linkHeader, $matches);
        $resourceParts = explode('/', $matches[1]);
        return [
            'fullResource' => $matches[1],
            'resourceName' => $resourceParts[count($resourceParts) - 2],
            'resourceId' => (int) $resourceParts[count($resourceParts) - 1],
            'rel' => $matches[2]
        ];
    }

    /**
     * Truncate the db tabel with publications
     */
    public function reset_group_publications()
    {
        global $wpdb;
        
        $table_name = WaauMendeleyPlugin::get_instance()->get_db_tablename();
        
        // clanup the table
        $wpdb->query("TRUNCATE $table_name");
    }

    /**
     * Load all the publications 
     * 
     * @param array $params
     */
    public function index_group_publications($params = array())
    {
        $options = $this->getOptions();
        
        // set the pagesize
        $params['limit'] = 100;
        
        // limit to the group..
        $params['group_id'] = $options['group_id'];
        
        if (! isset($options['group_id'])) {
            throw new Exception("Group Id Must be specified");
        }
        
        $publications = $this->get_group_publications_recursive($params);
        
        $this->reset_group_publications();
        
        foreach ($publications as $publication) {
            
            $this->index_publication($publication);
        }
        
        return $publications;
    }

    /**
     * Parse and store a publication in database for fulltext lookup
     * 
     * @param array $publication
     */
    public function index_publication(array $publication)
    {
        global $wpdb;
        
        $table_name = WaauMendeleyPlugin::get_instance()->get_db_tablename();
        
        $documentid = $publication['id'];
        
        $serialized = json_encode($publication);
        $deserialized = json_decode($serialized, true);
        
        $this->unsetKeys($deserialized, 'website');
        $this->unsetKeys($deserialized, 'volume');
        $this->unsetKeys($deserialized, 'issue');
        $this->unsetKeys($deserialized, 'last_modified');
        $this->unsetKeys($deserialized, 'created');
        $this->unsetKeys($deserialized, 'type');
        $this->unsetKeys($deserialized, 'profile_id');
        $this->unsetKeys($deserialized, 'group_id');
        
        // flatten recursively to array values
        $deserialized = $this->array_values_recursive($deserialized);
        
        $fullsearch = implode(', ', $deserialized);
        
        $fullsearch = addslashes($fullsearch);
        $serialized = addslashes($serialized);
        
        $wpdb->query("INSERT INTO $table_name (documentid, fullsearch, serialized) VALUES ('" . $documentid . "','" . $fullsearch . "','" . $serialized . "')");
    }

    /**
     * Load publications in a recursive way following rest links.
     * 
     * @param array $params
     * @return array
     */
    public function get_group_publications_recursive($params = array())
    {
        $publications = array();
        $count = 0;
        $nextLink = true;
        
        while ($nextLink != false) {
            
            if (is_string($nextLink)) {
                $count ++;
                
                $parsed = parse_url($nextLink);
                parse_str($parsed['query'], $get_array);
                unset($get_array['access_token']);
                
                $batch = $this->get_group_publications($get_array, true);
            } else {
                $count ++;
                $batch = $this->get_group_publications($params, true);
            }
            
            $nextLink = false;
            
            $publications = array_merge($publications, $batch['result']);
            
            if (isset($batch['headers']['link'])) {
                $links = array();
                
                $linklines = $batch['headers']['link'];
                // var_dump($linklines);
                
                foreach ($linklines as $linkline) {
                    
                    $linkinfo = $this->parseLinkHeader($linkline);
                    $links[] = $linkinfo;
                    
                    if (isset($linkinfo['rel']) && $linkinfo['rel'] == 'next') {
                        $nextLink = $linkinfo['fullResource'];
                    }
                }
            }
        }
        
        return $publications;
    }

    /**
     * 
     * @param array $params
     * @return NULL|mixed
     */
    public function get_group_publications($params = array(), $returnCompleteResponse = false)
    {
        $baseparams = array(
            'view' => 'bib',
            'limit' => 20
        );
        
        $params = array_merge($baseparams, $params);
        
        $url = self::API_ENDPOINT . '/documents';
        
        $response = $this->fetch($url, $params);
        
        if ($returnCompleteResponse) {
            return $response;
        }
        
        if ($response['code'] != 200) {
            return null;
        }
        
        $documents = $response['result'];
        return $documents;
    }

    public function get_document($id)
    {
        $url = self::API_ENDPOINT . 'documents/' . $id;
        $document = $this->fetch($url);
        
        return $document;
    }

    /**
     * 
     * @return mixed
     */
    public function load_groups()
    {
        $url = self::API_ENDPOINT . 'groups';
        $data = $this->fetch($url);
        
        if ($data['code'] = 200) {
            return $data['result'];
        } else {
            throw new Exception($data['result']);
        }
        
        return array();
    }

    /**
     * TODO: check if can be done with the oauth client
     * @param string $file_id
     * 
     * @return array
     */
    public function get_file($file_id)
    {
        $options = $this->getOptions();
        
        $curl = curl_init(self::API_ENDPOINT . "files/" . $file_id);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $options['access_token']['result']['access_token']
        ));
        $auth = curl_exec($curl);
        $info = curl_getinfo($curl);
        
        return $info;
    }

    /**
     *
     * @param unknown $doc_id
     * @return number|NULL
     */
    public function get_file_info($doc_id)
    {
        $url = self::API_ENDPOINT . 'files';
        
        $params = array(
            'document_id' => $doc_id,
            'view' => 'bib'
        );
        
        $file = $this->fetch($url, $params);
        
        if ($file['code'] == 200) {
            return $file['result'];
        }
        
        return array();
    }

    /**
     * 
     * @return mixed|NULL
     */
    public function get_account_info()
    {
        $url = self::API_ENDPOINT . 'profiles/me';
        $info = $this->fetch($url);
        
        if ($info['code'] == 200) {
            return $info['result'];
        }
        
        return null;
    }

    /*----------------------------------------------------------------------------/
     *
     * Accessors
     *
     *---------------------------------------------------------------------------*/
    
    /**
     * @return the $options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param multitype: $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        
        if (! isset($options['access_token'])) {
            return "you must set up mendeley plugin before using this shortcode...";
        }
        
        $token_data_array = $options['access_token']['result'];
        if (! isset($token_data_array)) {
            return "you must set up mendeley plugin before using this shortcode...";
        }
        
        $token = $token_data_array['access_token'];
        
        return $this;
    }

    /**
     * 
     * @param array $options
     * @return array the access token
     */
    public function checkAccessToken()
    {
        $options = $this->getOptions();
        
        $token_data_array = $options['access_token'];
        
        if (isset($token_data_array['refresh_token'])) {
            
            if (time() > $options['expire_time']) {
                
                $token_data_array = $this->refresh_access_token($token_data_array['refresh_token']);
                
                // if there is a problem with the response
                if ($token_data_array['code'] != 200) {
                    // return void..
                    return null;
                }
            }
        }
        
        $token_data = $token_data_array['result'];
        $this->set_client_access_token($token_data['access_token']);
        
        return $token_data_array;
    }

    /**
     * @return the $client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function get_callback_url()
    {
        return $this->callback_url;
    }

    /**
     * @param string $callback_url
     */
    public function set_callback_url($callback_url)
    {
        $this->callback_url = $callback_url;
    }

    /**
     * @return string
     */
    public function get_client_id()
    {
        return $this->client_id;
    }

    /**
     * @param string $client_id
     */
    public function set_client_id($client_id)
    {
        $this->client_id = $client_id;
    }

    /**
     * @return string
     */
    public function get_client_secret()
    {
        return $this->client_secret;
    }

    /**
     * @param string $client_secret
     */
    public function set_client_secret($client_secret)
    {
        $this->client_secret = $client_secret;
    }

    /*----------------------------------------------------------------------------/
     *
     * Utilities
     *
     *---------------------------------------------------------------------------*/
    
    /**
     * 
     * @param string $url
     * @param array $parameters
     * @param string $method
     */
    public function fetch($url, $parameters = array(), $method = 'GET')
    {
        return $this->client->fetch($url, $parameters, $method);
    }

    /**
     * 
     * @param unknown $hystack
     * @param unknown $key
     */
    private function unsetKeys(&$hystack, $key)
    {
        if (isset($hystack[$key])) {
            unset($hystack[$key]);
        }
    }

    /**
     * 
     * @param unknown $array
     * @return array|unknown[]
     */
    private function array_values_recursive($array)
    {
        $flat = array();
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $flat = array_merge($flat, $this->array_values_recursive($value));
            } else {
                $flat[] = $value;
            }
        }
        return $flat;
    }
}