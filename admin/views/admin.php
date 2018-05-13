<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   WaauMendeleyPlugin
 * @author    Matteo Monti, credits to Davide Parisi, Nicola Musicco
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 --
 */

// date_default_timezone_set( !empty(get_option( 'timezone_string' )) ? get_option( 'timezone_string' ) : 'Europe/Rome' );
?>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>


	<label for="callback-url"><b>Redirect url</b> (<em>Register a new app at <a target="_blank" href="https://dev.mendeley.com/myapps.html">Mendeley</a> and enter this when asked for a redirect URL
	</em>)</label><br /> <br /> <input type="text" value="<?php echo $this->callback_url; ?>" readonly size="85" />

	<form action="options.php" method="post">
		<?php settings_fields( $this->plugin_slug ); ?>
		<?php do_settings_sections( $this->plugin_slug ); ?>
		<?php submit_button( 'Save keys to DB (will reset access token)' ); ?>
	</form>

	
	<?php

$options = get_option($this->plugin_slug);
$groups = get_option($this->plugin_slug . '-groups');

$html = '';

if (isset($options['access_token'])) {
    
    $access_token_data = $options['access_token']['result'];
    $expires_at = $options['expire_time'];
    $expired = (time() > $expires_at);
    
    $html = '<label for="access_token">Access Token:</label>';
    $html .= '<br/>';
    // $html .= '<p><em>Import Publications button, save on local cache documents details from your <b>Mendeley account</b></em></p>';
    $html .= '<input id="access_token" type="text" readonly value="' . $access_token_data['access_token'] . '" size="85" />';
    $html .= '<br/>';
    $html .= '<p class="' . (! $expired ? "token-updated" : "token-expired") . '"><b>Expire time: </b>' . date('d-n-Y H:i:s', $expires_at) . '</p>';
    // $html .= '<p>' . date( 'd-n-Y H:i:s', time() ) . '</p>';
    
    $html .= '<br/>';
    
    $html .= '<form action="' . admin_url("admin.php") . '" method="post">';
    $html .= '<span><button type="submit" name="action"  value="reset_all_data" class="button-primary">Reset all data</button></span>&nbsp;&nbsp;';
    
    if (! $expired) {
        
        if ($groups) {
            
            if (isset($options['group_id'])) {
                $html .= '<button type="submit" name="action"  value="import_publications" class="button-primary">Index all Publications in group</button>';
                $html .= '&nbsp;&nbsp;<span><button type="submit" name="action"  value="do_expire_access_token" class="button-primary">Expire access token</button></span>';
            }
            if (isset($options['last-import'])) {
                $html .= '<p></p><p class="token-updated">Last indexed: ' . $options['last-import'] . '</p>';
                $html .= '<p class="token-updated">Indexed Publications: ' . $options['indexed-count'] . '</p>';
            }
            
            if (isset($options['last-import-error'])) {
                $html .= '<p class="token-expired">Last import: ' . $options['last-import-error'] . '</p>';
            }
        } else {
            $html .= '<button type="submit" name="action"  value="load_groups" class="button-primary">Load groups</button>';
        }
    } else {
        
        $html .= '<button type="submit" name="action"  value="refresh_token" class="button-primary">Refresh access token</button>';
    }
    
    $html .= '</form>';
} else {
    
    $html = '';
    
    if (! empty($options['client_id']) && ! empty($options['client_secret'])) {
        
        $html .= '<h2>Access Token</h2>';
        $html .= '<p><em>No access token have been requested for this account</em></p>';
        $html .= '<p><em>When API key are stored in the db you can request your <b>Mendeley access token</b></em></p>';
        
        $html .= '<form action="' . admin_url("admin.php") . '" method="post">';
        
        $html .= '<input type="hidden" name="action" value="request_token"/>';
        $html .= '<input type="submit" value="Request Access token" class="button-primary" ';
        $html .= ($options['client_id'] == '' || $options['client_secret'] == '') ? "disabled" : "";
        $html .= '" />';
        
        $html .= '</form>';
    }
}
echo $html;
?>

</div>
