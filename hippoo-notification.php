<?php

/**
 * Plugin Name: Hippoo Notification
 * Version: 1.0.2
 * Plugin URI: https://hippoo.app/
 * Description: Hippoo Notification with OneSignal
 * Text Domain: hippoo-notification
 * Author: Hippoo team
 * Author URI: https://hippoo.app/
 * License: GPL3
 * Domain Path: /languages
 **/

defined('ABSPATH') || exit('No direct script access allowed');
define( 'HIPPOO_NOTIFICATION_URL', plugins_url( 'hippoo-notification' ) . '/assets' );

// Load translation files
function hippoo_notification_load_textdomain()
{
	load_plugin_textdomain('hippoo-notification', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'hippoo_notification_load_textdomain');


// Add a new menu item in the WordPress admin area
function hippoo_notification_admin_page_menu()
{
	add_menu_page(
		__('Hippoo Notification', 'hippoo-notification'),
		__('Hippoo Notification', 'hippoo-notification'),
		'manage_options',
		'hippoo-notification',
		'hippoo_notification_admin_page_callback',
		( HIPPOO_NOTIFICATION_URL . '/images/icon.svg' )
	);
}
add_action('admin_menu', 'hippoo_notification_admin_page_menu');

// Render the admin page
function hippoo_notification_admin_page_callback()
{
	if (isset($_POST['save_settings'])) {

		// Verify the nonce
		if (!isset($_POST['hippoo_notification_settings_nonce']) || !wp_verify_nonce($_POST['hippoo_notification_settings_nonce'], 'hippoo_notification_save_settings')) {
			// Nonce verification failed, handle the error
			wp_die(__('Nonce verification failed.', 'hippoo-notification'), __('Error', 'hippoo-notification'), array('response' => 403));
		}

		$app_id = sanitize_text_field($_POST['app_id']);
		update_option('app_id', $app_id);

		$authorization_code = sanitize_text_field($_POST['authorization_code']);
		update_option('authorization_code', $authorization_code);

		echo wp_kses_post('<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'hippoo-notification') . '</p></div>');
	}

?>
	<div class="wrap">
		<h1><?php _e('Hippoo Notification', 'hippoo-notification'); ?></h1>
		<form method="post">

			<table class="form-table" role="presentation">

				<tbody>
					<tr>
						<th scope="row"><label for="blogname"><?php _e('App ID', 'hippoo-notification'); ?></label></th>
						<td><input type="text" name="app_id" value="<?php echo esc_attr(get_option('app_id')); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="blogname"><?php _e('Authorization Code', 'hippoo-notification'); ?></label></th>
						<td><input type="text" name="authorization_code" value="<?php echo esc_attr(get_option('authorization_code')); ?>" /></td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field('hippoo_notification_save_settings', 'hippoo_notification_settings_nonce'); ?>
			<input type="submit" name="save_settings" value="<?php _e('Save Settings', 'hippoo-notification'); ?>" class="button button-primary" />
		</form>
	</div>
<?php
}


// Enqueue JS
function hippoo_notification_enqueue_inline_external_js()
{
	wp_enqueue_script('hn-onesignal', 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js', array(), null, false);
	$inline_code = 'window.OneSignalDeferred = window.OneSignalDeferred || [];
	OneSignalDeferred.push(function(OneSignal) {
	  OneSignal.init({
		  appId: "' . esc_attr(get_option('app_id')) . '",
		  /*notifyButton: {
			  enable: false,
		  },*/
	  });
	});';
	wp_add_inline_script('hn-onesignal', $inline_code);
}
add_action('wp_enqueue_scripts', 'hippoo_notification_enqueue_inline_external_js', 10);


// Register REST API routes
function hippoo_notification_admin_page_register_rest_routes()
{
	register_rest_route(
		'wc/hippoo-notification/v1',
		'/settings',
		array(
			'methods'  => 'GET',
			'callback' => 'hippoo_notification_admin_page_get_settings',
			'permission_callback' => function () {
				return current_user_can('manage_options');
			},
		)
	);

	register_rest_route(
		'wc/hippoo-notification/v1',
		'/settings',
		array(
			'methods'             => 'PUT',
			'callback'            => 'hippoo_notification_admin_page_update_settings',
			'permission_callback' => function () {
				return current_user_can('manage_options');
			},
		)
	);

	register_rest_route(
		'wc/hippoo-notification/v1',
		'/status',
		array(
			'methods'  => 'GET',
			'callback' => 'hippoo_notification_admin_page_check_settings',
			'permission_callback' => function () {
				return current_user_can('manage_options');
			},
		)
	);

	register_rest_route(
		'wc/hippoo-notification/v1',
		'/send',
		array(
			'methods'             => 'POST',
			'callback'            => 'hippoo_notification_admin_page_send_notification',
			'permission_callback' => function () {
				return current_user_can('manage_options');
			},
		)
	);
}
add_action('rest_api_init', 'hippoo_notification_admin_page_register_rest_routes');

// Get the settings via REST API
function hippoo_notification_admin_page_get_settings()
{
	$settings = array(
		'app_id'             => esc_attr(get_option('app_id')),
		'authorization_code' => esc_attr(get_option('authorization_code')),
	);

	return rest_ensure_response($settings);
}

// Update the settings via REST API
function hippoo_notification_admin_page_update_settings(WP_REST_Request $request)
{
	$data               = $request->get_json_params();
	$app_id             = isset($data['app_id']) ? sanitize_text_field($data['app_id']) : '';
	$authorization_code = isset($data['authorization_code']) ? sanitize_text_field($data['authorization_code']) : '';

	update_option('app_id', $app_id);
	update_option('authorization_code', $authorization_code);

	return rest_ensure_response(__('Settings updated successfully.', 'hippoo-notification'));
}

// Check if the settings are stored via REST API
function hippoo_notification_admin_page_check_settings()
{
	$app_id             = esc_attr(get_option('app_id'));
	$authorization_code = esc_attr(get_option('authorization_code'));

	$is_stored          = !empty($app_id) && !empty($authorization_code);

	return rest_ensure_response($is_stored);
}

// Send notification via REST API
function hippoo_notification_admin_page_send_notification(WP_REST_Request $request)
{

	$data               = $request->get_json_params();
	$app_id             = esc_attr(get_option('app_id'));
	$authorization_code = esc_attr(get_option('authorization_code'));
	$title              = isset($data['title']) ? sanitize_text_field($data['title']) : '';
	$desc               = isset($data['desc']) ? sanitize_text_field($data['desc']) : '';
	$img                = isset($data['img']) ? sanitize_text_field($data['img']) : '';
	$url                = isset($data['url']) ? sanitize_text_field($data['url']) : '';

	// Call the hippoo_notification_send_push() function with the provided parameters
	$result = hippoo_notification_send_push($app_id, $authorization_code, $title, $desc, $img, $url);

	return rest_ensure_response($result);
}


function hippoo_notification_send_push($app_id, $authorization_code, $title, $desc, $img, $url)
{

	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => '{
        "app_id": "' . $app_id . '",
        "included_segments": [
            "Total Subscriptions"
        ],
        "headings": {
            "en": "' . $title . '"
        },
        "contents": {
            "en": "' . $desc . '"
        },
        "url": "' . $url . '",
        "chrome_web_image": "' . $img . '"
    }',
		CURLOPT_HTTPHEADER => array(
			'Authorization: Basic ' . $authorization_code,
			'accept: application/json',
			'content-type: application/json',
		),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return $response;
}
