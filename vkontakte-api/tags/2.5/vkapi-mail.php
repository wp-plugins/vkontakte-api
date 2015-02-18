<?php
	if ( isset( $_POST['social'] ) ) {
		// Include WordPress
				define('WP_USE_THEMES', false);
				require_once('../../../wp-blog-header.php');
				status_header(200);
				nocache_headers();
		if ( $_POST['social'] == 'vk' ) {
			$post_id = trim( rawurldecode( $_POST['id'] ) );
			$num = trim( rawurldecode ( $_POST['num'] ) );
			$last_comment = trim( rawurldecode ( $_POST['last_comment'] ) );
			$date = trim( rawurldecode ( $_POST['date'] ) );
			$sign = trim( rawurldecode ( $_POST['sign'] ) );
			$api_secret = get_option('vkapi_api_secret');
			$hash = md5( $api_secret.$date.$num.$last_comment );
			if ( strcmp($hash, $sign) == 0  ) {
				update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
				$email = get_bloginfo('admin_email');
				$blogurl = site_url();
				if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
				if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
				load_plugin_textdomain( 'vkapi', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
				$notify_message  = __( 'VKapi: Page has just commented!', 'vkapi' ). "<br />";
					$notify_message .= rawurldecode( get_permalink( $post_id ) ) . "<br /><br />";
					$notify_message .= __( 'Comment: ', 'vkapi' ) . "<br />" . $last_comment . "<br /><br />";
				$subject = __( '[VKapi] Website:', 'vkapi' );
				$subject .= ' "';
				$subject .= $blogurl;
				$subject .=  '"';
				add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
				wp_mail( $email, $subject, $notify_message );
			} else {
				update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
				$email = get_bloginfo('admin_email');
				$blogurl = site_url();
				if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
				if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
				load_plugin_textdomain( 'vkapi', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
				$notify_message  = __( 'VKapi: Page has just commented!', 'vkapi' ). "<br />";
					$notify_message .= rawurldecode( get_permalink( $post_id ) ) . "<br /><br />";
					$notify_message .= __( 'Comment: ', 'vkapi' ) . "<br />" . $last_comment . "<br /><br />";
				$subject = __( '[VKapi] Website:', 'vkapi' );
				$subject .= ' "';
				$subject .= $blogurl;
				$subject .=  '"';
				$notify_message .= "<br /><br /><br />sign not true";
				add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
				wp_mail( $email, $subject, $notify_message );
			};
		} else {
			$post_id = trim( rawurldecode( $_POST['id'] ) );
			$data = wp_remote_get( 'https://graph.facebook.com/?ids='.get_permalink($post_id) );
			if (is_wp_error($data)) {
				echo $data->get_error_message();
				exit;
			};
			$resp = json_decode($data['body'], true);
			foreach ($resp as $key => $value) {
				$num = $value['comments'];
			};
			update_post_meta($post_id, 'fbapi_comm', $num, FALSE);
			$email = get_bloginfo('admin_email');
			$blogurl = site_url();
			if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
			if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
			load_plugin_textdomain( 'vkapi', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
			$notify_message  = __( 'FBapi: Page has just commented!', 'vkapi' ). "<br />";
				$notify_message .= rawurldecode( get_permalink( $post_id ) ) . '<br /><br />';
				$notify_message .= 'Появился новый комментарий' . '<br /><br />';
			$subject = __( '[VKapi] Website:', 'vkapi' );
			$subject .= ' "';
			$subject .= $blogurl;
			$subject .=  '"';
			$notify_message .= "<br /><br /><br />sign not true";
			add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
			wp_mail( $email, $subject, $notify_message );
		};
	}