<?php

define('VKAPI_AT', 'https://api.vk.com/oauth/access_token');
define('VKAPI_M', 'https://api.vk.com/method/');
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
status_header(200);
nocache_headers();
main();

function authOpenAPIMember()
{
    $session = array();
    $member = false;
    $valid_keys = array('expire', 'mid', 'secret', 'sid', 'sig');
    $app_cookie = $_COOKIE['vk_app_' . get_option('vkapi_appid')];
    if ($app_cookie) {
        $session_data = explode('&', $app_cookie, 10);
        foreach ($session_data as $pair) {
            list($key, $value) = explode('=', $pair, 2);
            if (empty($key) || empty($value) || !in_array($key, $valid_keys)) {
                continue;
            }
            $session[$key] = $value;
        }
        foreach ($valid_keys as $key) {
            if (!isset($session[$key])) {
                return $member;
            }
        }
        ksort($session);

        $sign = '';
        foreach ($session as $key => $value) {
            if ($key != 'sig') {
                $sign .= ($key . '=' . $value);
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

function doHttpRequest($urlreq)
{
    $ch = curl_init(); // start
    curl_setopt($ch, CURLOPT_URL, "$urlreq"); // where
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // why
    $request_result = curl_exec($ch); // do this
    curl_close($ch); // close, free memory
    return $request_result; // profit
}

function params($params)
{
    $pice = array();
    foreach ($params as $k => $v) {
        $pice[] = $k . '=' . urlencode($v);
    }

    return implode('&', $pice);
}

function get_VkMethod($method_name, $parameters = array())
{
    ksort($parameters);
    $parameters = params($parameters);
    $urlreq = VKAPI_M . $method_name . "?" . $parameters; // . "&access_token=" . $access_token;
    $result = doHttpRequest($urlreq);
    $result = urldecode($result);
    $data = json_decode($result, true);

    return $data["response"];
}

function main()
{

    $member = authOpenAPIMember();

    if ($member === false) {
        echo 'sign not true';
        die;
        bitch;
        die;
    }

    global $wpdb;
    $get_id = $wpdb->get_var(
        $wpdb->prepare(
            "
SELECT `user_id`
FROM {$wpdb->usermeta}
WHERE `meta_key` = 'vkapi_uid'
AND `meta_value` = %s
				",
            $member['id']
        )
    );
    if ($get_id !== null) {
        wp_set_auth_cookie($get_id);
        echo 'Ok';
    } else {
        oauth_new_user($member['id']);
    }
}

function oauth_new_user($id)
{
    $users = get_VkMethod(
        'getProfiles',
        array('uids' => $id, 'fields' => 'uid,first_name,nickname,last_name,screen_name,photo_medium_rec')
    );
    $user = $users[0];
    $data = array();
    $data['user_pass'] = wp_generate_password();
    $data['user_login'] = 'vk_' . $user['screen_name'];
    $data['user_email'] = $data['user_login'] . '@vk.com';
    $data['nickname'] = $user['nickname'];
    $data['first_name'] = $user['first_name'];
    $data['last_name'] = $user['last_name'];
    $data['rich_editing'] = true;
    $data['jabber'] = $data['user_email'];
    $data['display_name'] = "{$data['first_name']} {$data['last_name']}";
    $uid = wp_insert_user($data);
    if (is_wp_error($uid)) {
        echo $uid->get_error_message();
        exit;
    }
    add_user_meta($uid, 'vkapi_ava', $user['photo_medium_rec'], false);
    add_user_meta($uid, 'vkapi_uid', $user, true);

    $creds = array();
    $creds['user_login'] = $data['user_login'];
    $creds['user_password'] = $data['user_pass'];
    $creds['remember'] = true;
    $user = wp_signon($creds);
    if (is_wp_error($user)) {
        echo $user->get_error_message();
    } else {
        echo 'Ok';
    }
}