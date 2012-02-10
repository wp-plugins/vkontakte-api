<?php
	if ( isset( $_POST['id'] ) ) {
		$post_id = urldecode ( $_POST['id'] );
		$num = urldecode ( $_POST['num'] );
		$last_comment = urldecode ( $_POST['last_comment'] );
		$date = urldecode ( $_POST['date'] );
		$sign = urldecode ( $_POST['sign'] );
		// Include WordPress 
			define('WP_USE_THEMES', false);
			require_once('../../../wp-blog-header.php');
			status_header(200);
			nocache_headers();
		$api_secret = get_option('vkapi_api_secret');
		$hash = md5($api_secret.$date.$num.$last_comment);
		if ( strcmp($hash, $sign) == 0  ) {
			update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
			$email = get_bloginfo('admin_email');
			$blogurl = site_url();
			if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
			if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
				$mofile = dirname( __FILE__ ) . '/lang/' . 'vkapi' . '-' . get_locale() . '.mo';
			load_plugin_textdomain( 'vkapi', $mofile );
			$notify_message  = __( 'VKapi: Page has just commented!', 'vkapi' ). "\r\n";
				$notify_message .= get_permalink( $post_id ) . "\r\n\r\n";
				$notify_message .= __( 'Comment: ', 'vkapi' ) . "\r\n" . $last_comment . "\r\n\r\n";
			$subject = __( '[VKapi] Website:', 'vkapi' );
			$subject .= ' "';
			$subject .= $blogurl;
			$subject .=  '"';
			wp_mail( $email, $subject, $notify_message );
		} else {
			update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
			$email = get_bloginfo('admin_email');
			$blogurl = site_url();
			if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
			if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
				$mofile = dirname( __FILE__ ) . '/lang/' . 'vkapi' . '-' . get_locale() . '.mo';
			load_plugin_textdomain( 'vkapi', $mofile );
			$notify_message  = __( 'VKapi: Page has just commented!', 'vkapi' ). "\r\n";
				$notify_message .= get_permalink( $post_id ) . "\r\n\r\n";
				$notify_message .= __( 'Comment: ', 'vkapi' ) . "\r\n" . $last_comment . "\r\n\r\n";
			$subject = __( '[VKapi] Website:', 'vkapi' );
			$subject .= ' "';
			$subject .= $blogurl;
			$subject .=  '"';
			$notify_message .= "\r\n\r\n\r\nsign not true";
			wp_mail( $email, $subject, $notify_message );
		}
	}
?>