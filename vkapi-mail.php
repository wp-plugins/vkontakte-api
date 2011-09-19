<?php
	if ( isset( $_POST['id'] ) ) {
		$post_id = $_POST['id'];
		$num = (string) $_POST['num'];
		$last_comment = $_POST['last_comment'];
		$date = $_POST['date'];
		$sign = $_POST['sign'];
		require_once('../../../wp-blog-header.php');
		$api_secret = get_option('vkapi_api_secret');
		if ( $sign == md5(trim($api_secret.$date.$num.$last_comment)) ) {
			$email = get_bloginfo('admin_email');
			$blogurl = site_url();
			if (substr($blogurl,0,7)=='http://') $blogurl = substr($blogurl,7);
			if (substr($blogurl,0,8)=='https://') $blogurl = substr($blogurl,8);
				$mofile = dirname( __FILE__ ) . '/lang/' . 'vkapi' . '-' . get_locale() . '.mo';
			load_plugin_textdomain( 'vkapi', $mofile );
			$notify_message  = __( 'VKAPI: Page has just commented!', 'vkapi' ). "\r\n";
				$notify_message .= get_permalink( $post_id ) . "\r\n\r\n";
				$notify_message .= __( 'Comment: ', 'vkapi' ) . "\r\n" . $last_comment . "\r\n\r\n";
			$subject = __( '[VKAPI] Website:', 'vkapi' );
			$subject .= ' "';
			$subject .= $blogurl;
			$subject .=  '"';
			@wp_mail( $email, $subject, $notify_message );
		}
	}
?>