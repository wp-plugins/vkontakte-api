<?php
if (isset($_POST['social'])) {
    // Include WordPress
    define('WP_USE_THEMES', false);
    require_once('../../../wp-blog-header.php');
    status_header(200);
    nocache_headers();
    if ($_POST['social'] == 'vk') {
        $post_id = trim(rawurldecode($_POST['id']));
        $num = trim(rawurldecode($_POST['num']));
        $last_comment = trim(rawurldecode($_POST['last_comment']));
        $date = trim(rawurldecode($_POST['date']));
        $sign = trim(rawurldecode($_POST['sign']));
        $api_secret = get_option('vkapi_api_secret');
        $hash = md5($api_secret . $date . $num . $last_comment);
        //if ( strcmp($hash, $sign) == 0  ) {
        update_post_meta($post_id, 'vkapi_comm', $num, false);
        //}
    } else {
        $post_id = trim(rawurldecode($_POST['id']));
        $data = wp_remote_get('https://graph.facebook.com/?ids=' . get_permalink($post_id));
        if (is_wp_error($data)) {
            exit;
        }
        $resp = json_decode($data['body'], true);
        foreach ($resp as $key => $value) {
            $num = $value['comments'];
        }
        update_post_meta($post_id, 'fbapi_comm', $num, false);
    }
}