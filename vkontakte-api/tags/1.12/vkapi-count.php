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
			update_post_meta($post_id, 'vkapi_comm', $num, FALSE);
		} 
	}
?>