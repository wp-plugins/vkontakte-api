<?php
	if ( isset( $_POST['id'] ) ) {
		header('HTTP/1.1 200 OK');
		$post_id = $_POST['id'];
		$num = (string) $_POST['num'];
		$last_comment = urldecode ( $_POST['last_comment'] );
		$date = urldecode ( $_POST['date'] );
		$sign = $_POST['sign'];
		// Include WordPress 
			//define('WP_USE_THEMES', false);
			require_once('../../../wp-load.php');
		$api_secret = get_option('vkapi_api_secret');
		$hash = md5($api_secret.$date.$num.$last_comment);
		//if ( strcmp($hash, $sign) == 0  ) {
			update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
		//} 
	}
?>