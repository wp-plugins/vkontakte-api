﻿<?php 	
	
	//header('HTTP/1.1 200 OK');
	// Include WordPress 
			//define('WP_USE_THEMES', false);
			require_once('../../../wp-load.php');
			status_header(200);
			nocache_headers();

	function authOpenAPIMember() {
		$session = array();
		$member = FALSE;
		$valid_keys = array('expire', 'mid', 'secret', 'sid', 'sig');
		$app_cookie = $_COOKIE['vk_app_'.get_option('vkapi_appid')];
		if ($app_cookie) {
			$session_data = explode ('&', $app_cookie, 10);
			foreach ($session_data as $pair) {
				list($key, $value) = explode('=', $pair, 2);
				if (empty($key) || empty($value) || !in_array($key, $valid_keys)) {
					continue;
				}	
				$session[$key] = $value;
			}
			foreach ($valid_keys as $key) {
				if (!isset($session[$key])) return $member;
			}
			ksort($session);
		
			$sign = '';
			foreach ($session as $key => $value) {
				if ($key != 'sig') {
					$sign .= ($key.'='.$value);
				}
			}
			$sign .= get_option('vkapi_api_secret');
			$sign = md5($sign);
			if ($session['sig'] == $sign && $session['expire'] > time()) {
				$member = array(
					'id' => intval($session['mid']),
					'secret' => $session['secret'],
					'sid' => $session['sid']
				);
			}
		}
	return $member;
	}

	function doHttpRequest( $urlreq )	{
		$ch = curl_init(); // start
		curl_setopt( $ch, CURLOPT_URL, "$urlreq" ); // where
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE ); // why
		$request_result = curl_exec( $ch ); // do this
		curl_close( $ch ); // close, free memory
		return $request_result; // profit
	}
	
	function params($params) {
		$pice = array();
		foreach($params as $k=>$v) {
			$pice[] = $k.'='.urlencode($v);
		}
		return implode('&',$pice);
	}
	
	function get_VkMethod ( $method_name, $parameters = array() ) {
		ksort( $parameters );
		$parameters = params( $parameters );
		$urlreq = VKAPI_M . $method_name . "?" . $parameters; // . "&access_token=" . $access_token;
		$result = doHttpRequest( $urlreq );
		$result = urldecode ( $result );
		$data = json_decode ( $result, TRUE );
		return $data["response"];
	}
	
	define ( 'VKAPI_AT', 'https://api.vkontakte.ru/oauth/access_token') ;
	define ( 'VKAPI_M', 'https://api.vkontakte.ru/method/' );
	
	main();
	function main () {
		
		$member = authOpenAPIMember();

		if( $member == FALSE ) {
			echo 'sign not true';
			exit;
		}
		
		if ( isset( $_POST['mid'] ) ) {
			global $wpdb;
			global $get_id;
			$post_mid = $_POST['mid'];
			$get_id = $wpdb->get_var( $wpdb->prepare( 
				"
					SELECT `user_id` 
					FROM $wpdb->usermeta
					WHERE `meta_key` = 'vkapi_uid'
					AND `meta_value` = %s
				", 
				$post_mid
				) );
			if ( $get_id != NULL ) {
				wp_set_auth_cookie( $get_id );
				echo 'Ok';
			} else {
				oauth_new_user ( $post_mid );
			}
		};
	};

	function oauth_new_user ( $user ) {
		$vkapi_user = get_VkMethod( 'getProfiles', array( 'uids' => $user, 'fields' => 'uid,first_name,nickname,last_name,screen_name,photo_medium_rec' ) );
		$vkapi_user = $vkapi_user[0];
		$user_pass = wp_generate_password();
		$user_login = $vkapi_user['screen_name'];
		$user_email = $user_login . '@vk.com';
		$nickname = $vkapi_user['nickname'];
		$first_name = $vkapi_user['first_name'];
		$last_name = $vkapi_user['last_name'];
		$rich_editing = TRUE;
		$role = 'Subscriber';
		$jabber = $user_login . '@vk.com';
		$display_name = "$first_name $last_name";
			$userdata = array (
				user_pass => $user_pass,
				user_login => $user_login,
				user_email => $user_email,
				nickname => $nickname,
				first_name => $first_name,
				last_name => $last_name,
				rich_editing => $rich_editing,
				role => $role,
				jabber => $jabber,
				display_name => $display_name
			);
		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error($user_id) ) {
			echo $user_id->get_error_message();
			exit;
		};
		add_user_meta( $user_id, 'vkapi_ava', $vkapi_user['photo_medium_rec'], FALSE );
		add_user_meta( $user_id, 'vkapi_uid', $user, TRUE );
		if( $user_id ) {
			$creds = array();
			$creds['user_login'] = $user_login;
			$creds['user_password'] = $user_pass;
			$creds['remember'] = TRUE;
			$user = wp_signon( $creds );
			if ( is_wp_error($user) ) {
				echo $user->get_error_message();
			} else {
				echo 'Ok';
			};
		};
	};
?>