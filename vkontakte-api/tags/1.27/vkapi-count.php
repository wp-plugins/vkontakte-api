<?php
	if ( isset( $_POST['id'] ) ) {
		$post_id = trim( urldecode ( $_POST['id'] ) );
		$num = trim( urldecode ( $_POST['num'] ) );
		$last_comment = trim( urldecode ( $_POST['last_comment'] ) );
		$date = trim( urldecode ( $_POST['date'] ) );
		$sign = trim( urldecode ( $_POST['sign'] ) );
		// Include WordPress 
			define('WP_USE_THEMES', false);
			require_once('../../../wp-blog-header.php');
			status_header(200);
			nocache_headers();
		$api_secret = get_option('vkapi_api_secret');
		$hash = md5($api_secret.$date.$num.$last_comment);
		//if ( strcmp($hash, $sign) == 0  ) {
			update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
		//} 
	}
?>