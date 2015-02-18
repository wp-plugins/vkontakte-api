<?php

define('VKAPI_SERVER', 'https://api.vk.com/method/');
define('WP_USE_THEMES', false);
require_once('../../../../wp-load.php');
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

function doHttpRequest($url)
{
    $result = wp_remote_get($url);
    if (is_wp_error($result)) {
        echo $result->get_error_message();
        return false;
    }
    return $result['body'];
}

function params($params)
{
    $peace = array();
    foreach ($params as $k => $v) {
        $peace[] = $k . '=' . urlencode($v);
    }

    return implode('&', $peace);
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

    /** @var $wpdb wpdb */
    global $wpdb;
    $wp_user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `user_id` FROM {$wpdb->usermeta} WHERE `meta_key` = 'vkapi_uid' AND `meta_value` = %s LIMIT 1",
            $member['id']
        )
    );
    if ($wp_user_id !== null) {
        wp_set_auth_cookie($wp_user_id);
        do_action('wp_login', $wp_user_id);
        echo 'Ok';
    } else {
        oauth_new_user($member['id']);
    }
}

function oauth_new_user($id)
{
    $user = $_POST;
    if (is_numeric($id) && $id > 0 && is_array($user) && array_key_exists('nickname', $user)) {
        $data = array();
        $data['user_pass'] = wp_generate_password();
        $data['user_login'] = 'vk_id' . $id;
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
        add_user_meta($uid, 'vkapi_uid', $id, true);

        $array = array();
        $array['user_login'] = $data['user_login'];
        $array['user_password'] = $data['user_pass'];
        $array['remember'] = true;
        $user = wp_signon($array);
        if (is_wp_error($user)) {
            echo $user->get_error_message();
        } else {
            echo 'Ok';
        }
        return;
    } else {
        print_r($user);
    }
}