<?php
/*
Plugin Name: VKontakte API
Plugin URI: http://www.kowack.info/projects/vk_api
Description: Add API functions from vk.com in your own blog. <br /><strong><a href="options-general.php?page=vkapi_settings">Settings!</a></strong>
Version: 3.4
Author: kowack
Author URI: http://www.kowack.info/
*/

/*
	Copyright 2011  Evgen Zabrodkyi  (email: kowack@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/** todo-dx:
 * стили соц.кнопок
 * соц.кнопки слева\справа\центр
 * ширина блока комментариев в процентах
 * кросспост: твиттер, фейсбук, гугл
 */
function vkapi_can_start()
{
    global $wp_version;
    if (version_compare($wp_version, '3.5.1', '<')) {
        function vkapi_notice_update()
        {
            global $wp_version;
            $link = get_bloginfo('url', 'display');
            echo
            "<div class='error'>
                    <p>
                        VKontakte API plugin requires WordPress 3.5.1 or newer.
                        <a href='{$link}wp-admin/update-core.php'>Please update!</a>
                        (current version is {$wp_version})
                    </p>
		        </div>";
        }

        // todo-dx: deactivate plugin
        add_action('admin_notices', 'vkapi_notice_update');

        return false;
    }

    if (!defined('DB_NAME')) {
        die('Error: Plugin does not support standalone calls.');
        // todo-dx: redirect somewhere
    }

    if (isset($VK_api)) {
        function vkapi_notice_isSet()
        {
            echo '<div class="error"><p>VK_api already set!</p></div>';
        }

        add_action('admin_notices', 'vkapi_notice_isSet');

        return false;
    }

    return true;
}

$VK_api = vkapi_can_start() ? new VK_api() : null;

class VK_api
{
    private $plugin_domain = 'vkapi';
    private $plugin_url;
    private $plugin_path;
    private $vkapi_page_menu;
    private $vkapi_page_settings;
    private $vkapi_page_comments;
    private $vkapi_server = 'https://api.vk.com/method/';

    function __construct()
    {
        // init
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/translate/');
        // actions and hooks
        register_activation_hook(__FILE__, array('VK_api', 'install'));
        register_uninstall_hook(__FILE__, array('VK_api', 'uninstall'));
        register_deactivation_hook(__FILE__, array('VK_api', 'pause'));
        add_action('admin_menu', array(&$this, 'admin_menu'), 1); # menu and pages
        add_action('wp_print_scripts', array(&$this, 'add_head')); # init styles and scripts in header
        add_action('widgets_init', array(&$this, 'widget_init')); # widget
        add_action('wp_dashboard_setup', array(&$this, 'widget_dashboard')); # widget dashboard
        add_action('save_post', array(&$this, 'post_save'), 1, 2); # check meta_box
        add_filter('wp_insert_post_data', array(&$this, 'post_insert'), 1, 2); # crosspost me
        add_action('admin_notices', array(&$this, 'post_notice')); # fix admin notice
        add_action('do_meta_boxes', array(&$this, 'add_custom_box'), 1); # add meta_box
        $option = get_option('vkapi_login');
        if ($option == 'true') {
            add_action('profile_personal_options', array(&$this, 'add_profile_html')); # profile echo
            add_action('admin_footer', array(&$this, 'add_profile_js'), 88); # profile js
            add_action('wp_ajax_vkapi_update_user_meta', array(&$this, 'vkapi_update_user_meta')); # update user meta
            add_action('login_form', array(&$this, 'add_login_form')); # login
            add_action('register_form', array(&$this, 'add_login_form')); # register
            add_action('admin_bar_menu', array(&$this, 'user_links')); # admin bar add
        }
        add_action('wp_enqueue_scripts', array(&$this, 'site_enqueue_scripts'), 1); # enqueue script
        add_action('post_submitbox_misc_actions', array(&$this, 'add_post_submit')); # add before post submit
        add_action('wp_footer', array(&$this, 'vkapi_body_fix'), 1); # body fix
        add_filter('the_content', array(&$this, 'add_buttons'), 888); # buttons
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'own_actions_links')); # plug links
        add_filter('plugin_row_meta', array(&$this, 'plugin_meta'), 1, 2); # plugin meta
        add_filter('login_headerurl', array(&$this, 'login_href')); # login href
        $option = get_option('vkapi_close_wp');
        if ($option) {
            add_filter('comments_template', array(&$this, 'close_wp'), 1); # vkapi comments only
            add_filter('get_comments_number', array(&$this, 'do_empty'), 1); # recount
        } else {
            add_action('comments_template', array(&$this, 'add_tabs'), 888); # add comments
            add_filter('get_comments_number', array(&$this, 'do_non_empty'), 1); # recount
        }
        // V V V V V V check V V V V V V
        $vkapi_some_revision_d = get_option('vkapi_some_revision_d');
        if ($vkapi_some_revision_d) {
            add_action(
                'admin_init',
                create_function('', "define ('WP_POST_REVISIONS', false);")
            );
            remove_action('pre_post_update', 'wp_save_post_revision');
        }

        $appid = get_option('vkapi_appid');
        if (empty ($appid{0})) {
            add_action(
                'admin_notices',
                create_function(
                    '',
                    "echo '<div class=\"error\"><p>" . sprintf(
                        __('VKontakte API Plugin needs <a href="%s">configuration</a>.', $this->plugin_domain),
                        admin_url('admin.php?page=vkapi_settings')
                    ) . "</p></div>';"
                )
            );
        }
    }

##### MAIN
    function install()
    {
        add_rewrite_rule('^login/*+$', 'wp-login.php', 'top');
        add_rewrite_rule('^admin/*+$', 'wp-admin.php', 'top');
        flush_rewrite_rules();
        // todo-dx: check with options.php
        // init platform
        add_option('vkapi_appid', '');
        add_option('vkapi_api_secret', '');
        add_option('vkapi_at');
        add_option('fbapi_appid', '');
        // comments
        add_option('vkapi_comm_width', '600');
        add_option('vkapi_comm_limit', '15');
        add_option('vkapi_comm_graffiti', '1');
        add_option('vkapi_comm_photo', '1');
        add_option('vkapi_comm_audio', '1');
        add_option('vkapi_comm_video', '1');
        add_option('vkapi_comm_link', '1');
        add_option('vkapi_comm_autoPublish', '1');
        add_option('vkapi_comm_height', '0');
        add_option('vkapi_show_first', 'wp');
        // button align
        add_option('vkapi_align', 'left');
        add_option('vkapi_like_top', '0');
        add_option('vkapi_like_bottom', '1');
        // vk like
        add_option('vkapi_like_type', 'full');
        add_option('vkapi_like_verb', '0');
        // vk share
        add_option('vkapi_share_type', 'round');
        add_option('vkapi_share_text', 'Сохранить');
        // facebook
        add_option('fbapi_admin_id', '');
        // show ?
        add_option('vkapi_show_first', 'true');
        add_option('vkapi_show_like', 'true');
        add_option('vkapi_show_share', 'false');
        add_option('fbapi_show_like', 'false');
        add_option('fbapi_show_comm', 'false');
        add_option('gpapi_show_like', 'false');
        add_option('tweet_show_share', 'false');
        add_option('mrc_show_share', 'false');
        add_option('ya_show_share', 'false');
        // over
        add_option('vkapi_some_logo_e', '0');
        add_option('vkapi_some_logo', plugin_dir_url(__FILE__) . 'images/wordpress-logo.jpg');
        add_option('vkapi_some_desktop', '1');
        add_option('vkapi_some_revision_d', '1');
        add_option('vkapi_close_wp', '0');
        add_option('vkapi_login', '1');
        // categories
        add_option('vkapi_like_cat', '0');
        add_option('vkapi_share_cat', '0');
        add_option('fbapi_like_cat', '0');
        add_option('gpapi_like_cat', '0');
        add_option('tweet_share_cat', '0');
        add_option('mrc_share_cat', '0');
        add_option('ya_share_cat', '0');
        // tweet
        add_option('tweet_account', '');
        // crosspost
        add_option('vkapi_vk_group', '');
        add_option('vkapi_crosspost_default', '0');
        add_option('vkapi_crosspost_length', '888');
        add_option('vkapi_crosspost_link', '0');
    }

    function uninstall()
    {
        delete_option('vkapi_appid');
        delete_option('vkapi_api_secret');
        delete_option('vkapi_comm_width');
        delete_option('vkapi_comm_limit');
        delete_option('vkapi_comm_graffiti');
        delete_option('vkapi_comm_photo');
        delete_option('vkapi_comm_audio');
        delete_option('vkapi_comm_video');
        delete_option('vkapi_comm_link');
        delete_option('vkapi_comm_autoPublish');
        delete_option('vkapi_comm_height');
        delete_option('vkapi_show_first');
        delete_option('vkapi_like_type');
        delete_option('vkapi_like_verb');
        delete_option('vkapi_like_cat');
        delete_option('vkapi_like_top');
        delete_option('vkapi_like_bottom');
        delete_option('vkapi_share_cat');
        delete_option('vkapi_share_type');
        delete_option('vkapi_share_text');
        delete_option('vkapi_align');
        delete_option('vkapi_show_comm');
        delete_option('vkapi_show_like');
        delete_option('fbapi_show_comm');
        delete_option('vkapi_show_share');
        delete_option('vkapi_some_logo_e');
        delete_option('vkapi_some_logo');
        delete_option('vkapi_some_desktop');
        delete_option('vkapi_some_revision_d');
        delete_option('vkapi_close_wp');
        delete_option('vkapi_login');
        delete_option('fbapi_admin_id');
        delete_option('tweet_show_share');
        delete_option('tweet_account');
        delete_option('tweet_share_cat');
        delete_option('gpapi_show_like');
        delete_option('fbapi_like_cat');
        delete_option('fbapi_show_like');
        delete_option('gpapi_like_cat');
        delete_option('mrc_show_share');
        delete_option('mrc_share_cat');
        delete_option('ya_show_share');
        delete_option('ya_share_cat');
        delete_option('vkapi_vk_group');
        delete_option('vkapi_at');
        delete_option('vkapi_crosspost_default');
        delete_option('vkapi_crosspost_length');
        delete_option('vkapi_crosspost_link');
    }

    function pause()
    {
        // todo-dx: notice about help page and link to group
    }

    function widget_init()
    {
        $vkapi_login = get_option('vkapi_login');
        if ($vkapi_login == 'true') {
            register_widget('VKAPI_Login');
        }
        register_widget('VKAPI_Community');
        register_widget('VKAPI_Recommend');
        register_widget('VKAPI_Comments');
        register_widget('VKAPI_Cloud');
        register_widget('FBAPI_LikeBox');
    }

    function widget_dashboard()
    {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'vkapi_dashboard_widget',
                'VKapi: ' . 'Новости',
                array(&$this, 'widget_dashboard_admin')
            );
        }
        // todo-dx: add widget with settings, show last comments
    }

    function widget_dashboard_admin()
    {
        echo
        '<div id="vkapi_groups"></div>
			<script type="text/javascript">
				jQuery(window).on("vkapi_vk", function(){
					VK.Widgets.Group("vkapi_groups", {mode: 2, width: "auto", height: "290"}, 28197069);
				});
			</script>';
        if (get_option('vkapi_appid')):
            ?>
        <div id="vk_api_transport"></div>
        <script type="text/javascript">
            window.vkAsyncInit = function () {
                VK.init({
                    apiId: <?php echo get_option('vkapi_appid') . "\n"; ?>
                });
                jQuery(window).trigger('vkapi_vk');
            };

            setTimeout(function () {
                var el = document.createElement("script");
                el.type = "text/javascript";
                el.src = "https://vk.com/js/api/openapi.js";
                el.async = true;
                document.getElementById("vk_api_transport").appendChild(el);
            }, 0);
        </script>
        <?php endif;
    }

    function add_head()
    {
        $temp = get_option('vkapi_login');
        // VK API
        if (!is_admin() || defined('IS_PROFILE_PAGE') && $temp == 'true') {
            $id = get_option('vkapi_appid');
            echo "<meta property='vk:app_id' content='{$id}' />\n";
        }
        // Fix WP 3.4 Bug
        // todo-dx: check in WP 3.5
        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php')) && $temp == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_vkapi'));
            wp_enqueue_script('jquery');
            wp_enqueue_script('vkapi', plugins_url('js/callback.js'));
        }
        // FB API
        $temp = get_option('fbapi_show_comm');
        if (!is_admin() && $temp == 'true') {
            $id = get_option('fbapi_admin_id');
            echo '<meta property="fb:admins" content="' . $id . '"/>' . "\n";
        }
    }

    function admin_menu()
    {
        $this->vkapi_page_menu =
            add_menu_page(
                'VKontakte API',
                'VKontakte API',
                'manage_options',
                'vkapi_settings',
                array(&$this, 'settings_page'),
                'https://vk.com/favicon.ico'
            );
        $this->vkapi_page_settings =
            add_submenu_page(
                'vkapi_settings',
                'VKontakte API - ' . __('Settings', $this->plugin_domain),
                __('Settings', $this->plugin_domain),
                'manage_options',
                'vkapi_settings',
                array(&$this, 'settings_page')
            );
        $this->vkapi_page_comments =
            add_submenu_page(
                'vkapi_settings',
                'VKontakte API - ' . __('Comments', $this->plugin_domain),
                __('Last Comments', $this->plugin_domain),
                'manage_options',
                'vkapi_comments',
                array(&$this, 'comments_page')
            );
        add_action('admin_print_styles-' . $this->vkapi_page_settings, array(&$this, 'add_css_admin'));
        add_action('admin_print_styles-' . $this->vkapi_page_comments, array(&$this, 'add_css_admin_comm'));
        add_action('load-' . $this->vkapi_page_settings, array(&$this, 'contextual_help'));
        add_action('admin_init', array(&$this, 'register_settings'));
    }

    function add_css_admin()
    {
        wp_enqueue_style('vkapi_admin', plugins_url('css/admin.css', __FILE__));
        add_action('vkapi_body', array(&$this, 'js_async_vkapi'));
        add_action('vkapi_body', array(&$this, 'js_async_fbapi'));
    }

    function add_css_admin_comm()
    {
        $appid = get_option('vkapi_appid');
        echo "<meta property='vk:app_id' content='{$appid}' />\n";
        add_action('vkapi_body', array(&$this, 'js_async_vkapi'));
    }

    function post_save($post_id, $post)
    {
        // do meta box
        if (!wp_is_post_revision($post_id)) {
            if (isset($_REQUEST['vkapi_comments'])) {
                update_post_meta($post_id, 'vkapi_comments', $_REQUEST['vkapi_comments']);
            }
            if (isset($_REQUEST['vkapi_buttons'])) {
                update_post_meta($post_id, 'vkapi_buttons', $_REQUEST['vkapi_buttons']);
            }
        }
        // check what user want
        $temp = isset($_REQUEST['vkapi_crosspost_submit']) ? $_REQUEST['vkapi_crosspost_submit'] : false;
        if ($temp != '1') {
            return $post_id;
        }
        // check if need
        if ($post->post_status != 'publish') {
            return $post_id;
        }
        // check already
        $temp = get_post_meta($post_id, 'vkapi_crossposted', true);
        if ($temp == 'true') {
            self::notice_notice('VKapi: ' . __('Already crossposted.', $this->plugin_domain));

            return $post_id;
        }
        // ckeck access token
        $vk_at = get_option('vkapi_at');
        if (empty($vk_at)) {
            self::notice_notice('VKapi: ' . __('Access Token is empty.', $this->plugin_domain));

            return $post_id;
        }

        // Start !!!
        self::crosspost($vk_at, $post_id, $post->post_title, $post->post_content);

        // End.
        return $post_id;
    }

    function post_insert($data, $post)
    {
        // check if need
        if ($post['post_status'] != 'publish') {
            return $data;
        }
        // check option
        $temp = get_option('vkapi_crosspost_default');
        if ($temp != '1') {
            return $data;
        }
        // check already
        $temp = get_post_meta($post['ID'], 'vkapi_crossposted', true);
        if ($temp == 'true') {
            return $data;
        }
        // check access token
        $vk_at = get_option('vkapi_at');
        if (empty($vk_at)) {
            return $data;
        }

        // Start !!!
        self::crosspost($vk_at, $post['ID'], $data['post_title'], $data['post_content']);

        // End.
        return $data;
    }

    function post_notice()
    {
        $array = get_option('vkapi_msg');
        if (empty($array)) {
            return;
        }
        foreach ($array as $temp) {
            $type = $temp['type'];
            $msg = $temp['msg'];
            echo "<div class='{$type}'><p>✔ {$msg}</p></div>";
        }
        delete_option('vkapi_msg');
    }

    function add_custom_box($page)
    {
        add_meta_box(
            'vkapi_meta_box_comm',
            'VKapi: ' . __('Comments', $this->plugin_domain),
            array(&$this, 'vkapi_inner_custom_box_comm'),
            $page,
            'advanced'
        );
        add_meta_box(
            'vkapi_meta_box_comm',
            'VKapi: ' . __('Comments', $this->plugin_domain),
            array(&$this, 'vkapi_inner_custom_box_comm'),
            $page,
            'advanced'
        );
        add_meta_box(
            'vkapi_meta_box_butt',
            'VKapi: ' . __('Social buttons', $this->plugin_domain),
            array(&$this, 'vkapi_inner_custom_box_butt'),
            $page,
            'advanced'
        );
        add_meta_box(
            'vkapi_meta_box_butt',
            'VKapi: ' . __('Social buttons', $this->plugin_domain),
            array(&$this, 'vkapi_inner_custom_box_butt'),
            $page,
            'advanced'
        );
    }

    function add_login_form()
    {
        global $action;
        if ($action == 'login' || $action == 'register') {
            $wp_url = get_bloginfo('wpurl');
            echo
            "<br style='display: none' id='vkapi_connect' data-vkapi-url='{$wp_url}' />
			    <div id='vkapi_status'></div>
			    <div id='login_button' onclick='VK.Auth.login(onSignon)'>
			        <a>
			            ВойтиВКонтакте
			        </a>
			    </div>
                <script language='text/javascript'>
                    VK.UI.button('login_button');
                </script>
                <br />";
        }
        if (($action == 'login' || $action == 'register') && is_user_logged_in()) {
            wp_safe_redirect(home_url());
            exit;
        }
    }

    function user_links(&$wp_admin_bar)
    {
        /** @var $wp_admin_bar WP_Admin_Bar */
        $user = wp_get_current_user();
        $vkapi_user = get_user_meta($user->ID, 'vkapi_uid', true);
        if (!empty($vkapi_user)) {
            $wp_admin_bar->add_node(
                array(
                    'id' => 'vkapi-profile',
                    'parent' => 'user-actions',
                    'title' => __('VKontakte Profile', $this->plugin_domain),
                    'href' => "http://vk.com/id{$vkapi_user}",
                    'meta' => array(
                        'target' => '_blank',
                    )
                )
            );
        }
        $wp_admin_bar->add_menu(
            array(
                'id' => 'vkapi',
                'parent' => 'site-name',
                'title' => '-',
                'href' => false,
                /*'meta' => array(
                    'html' => '',
                    'class' => '',
                    'onclick' => '',
                    'target' => '',
                    'title' => ''
                )*/
            )
        );
    }

    function site_enqueue_scripts()
    {
        // todo-dx: move social buttons action on vkapi_body to their call
        wp_enqueue_script('vkapi_callback', $this->plugin_url . 'js/callback.js', array('jquery'));
        add_action('vkapi_body', array(&$this, 'js_async_vkapi'));
        $option = get_option('vkapi_show_share');
        if ($option == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_vkshare'));
        }
        $option = get_option('gpapi_show_like');
        if ($option == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_plusone'));
        }
        $option = get_option('fbapi_show_like');
        if ($option == 'true' || get_option('fbapi_show_comm') == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_fbapi'));
        }
        $option = get_option('tweet_show_share');
        if ($option == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_tw'));
        }
        $option = get_option('mrc_show_share');
        if ($option == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_mrc'));
        }
        $option = get_option('ya_show_share');
        if ($option == 'true') {
            add_action('vkapi_body', array(&$this, 'js_async_ya'));
        }
    }

    function add_post_submit()
    {
        // todo-dx: check uses this option in $this->crosspost
        $temp1 = get_option('vkapi_vk_group');
        $temp2 = get_option('vkapi_at');
        if (!(empty($temp1) || empty($temp2))) {
            ?>
        <div class="misc-pub-section">
            <input type="checkbox"
                   value="1"
                   name="vkapi_crosspost_submit"
                <?php echo get_option('vkapi_crosspost_default') ? 'checked' : '';?>
                    />
            <?php _e('CrossPost to VK.com Wall', $this->plugin_domain); ?>
            <br/>
            <?php _e('Text length:', $this->plugin_domain); ?>
            <input type="text"
                   name="vkapi_crosspost_length"
                   value="<?php echo get_option('vkapi_crosspost_length'); ?>"
                    />
            <br/>
            <input type="checkbox"
                   name="vkapi_crosspost_link"
                   value="1"
                <?php echo get_option('vkapi_crosspost_link') ? 'checked' : '';?>
                    /> <?php _e('Show Post link:', $this->plugin_domain); ?>
        </div><?php } else { ?>
        <div class="misc-pub-section">
            <p>Cross-Post <a href="options-general.php?page=vkapi_settings#vkapi_vk_group">not configured<a><p>
        </div><?php
        }
    }

    function vkapi_body_fix()
    {
        echo '<div id="vkapi_body">';
        do_action('vkapi_body');
        echo '</div>';
    }

    function own_actions_links($links)
    {
        //unset($links['deactivate']);
        unset($links['edit']);
        $settings_link =
            "&nbsp;
                <a href='options-general.php?page=vkapi_settings'>
                   <img src='{$this->plugin_url}images/set.png' width='20' />
                   &nbsp;
                </a>";
        array_push($links, $settings_link);

        return $links;
    }

    function plugin_meta($links, $file)
    {
        if ($file == plugin_basename(__FILE__)) {
            $links[] = '<a href="' . admin_url('options-general.php?page=vkapi_settings') . '">' . __(
                'Settings',
                $this->plugin_domain
            ) . '</a>';
            $links[] = 'Code is poetry!';
        }

        return $links;
    }

    function login_href()
    {
        return home_url('/');
    }

    function close_wp($file)
    {
        if (!(is_singular() && comments_open())) {
            return $file;
        }

        return dirname(__FILE__) . '/php/close-wp.php';
    }

    function do_empty()
    {
        // todo-dx: add fb comment number
        global $post;
        $vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', true);

        return (int)$vkapi_comm;
    }

    function do_non_empty($args)
    {
        global $post;
        $vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', true);
        $fbapi_comm = get_post_meta($post->ID, 'fbapi_comm', true);

        return (int)($args + $vkapi_comm + $fbapi_comm);
    }

    function add_tabs()
    {
        global $post;
        $vkapi_get_comm = get_post_meta($post->ID, 'vkapi_comments', true);
        if (comments_open() && $vkapi_get_comm !== '0') {
            // NEED WP?
            $count = 0;
            // VK
            $show_comm = get_option('vkapi_show_comm');
            if ($show_comm == 'true') {
                add_action('add_tabs_button_action', array(&$this, 'add_tabs_button_vk'), 5);
                add_action('add_tabs_comment_action', array(&$this, 'add_vk_comments'));
                $count++;
            }
            // FB
            $show_comm = get_option('fbapi_show_comm');
            if ($show_comm == 'true') {
                add_action('add_tabs_button_action', array(&$this, 'add_tabs_button_fb'), 5);
                add_action('add_tabs_comment_action', array(&$this, 'add_fb_comments'));
                $count++;
            }
            // hook start buttons
            if ($count > 0) {
                add_action('add_tabs_button_action', array(&$this, 'add_tabs_button_start'), 1);
                add_action('add_tabs_button_action', array(&$this, 'add_tabs_button_wp'), 5);
                add_action('add_tabs_button_action', create_function('', 'echo \'</table>\';'), 888);
            }
            do_action('add_tabs_button_action');
            do_action('add_tabs_comment_action');
        }
    }

    function add_tabs_button_start()
    {
        global $post;
        $vkapi_url = get_bloginfo('wpurl');
        $text = __('Comments:', $this->plugin_domain);
        echo "<table
                id='vkapi_wrapper'
                style='width:auto; margin:10px auto 20px 0;'
                data-vkapi-notify='{$post->ID}'
                data-vkapi-url='{$vkapi_url}'>
                <td style='font-weight:800; white-space:nowrap'>
                    {$text}
                </td>";
    }

    function add_tabs_button_vk()
    {
        global $post;
        $vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', true);
        if (!$vkapi_comm) {
            $vkapi_comm = 0;
        }
        $text = __('VKontakte', $this->plugin_domain);
        echo "<td>
			    <button style='white-space:nowrap'
			            class='submit vk_recount'
			            onclick='showVK()'>
			        {$text} ({$vkapi_comm})
			    </button>
			</td>";
    }

    function add_tabs_button_fb()
    {
        $url = get_permalink();
        $text = __('Facebook', $this->plugin_domain);
        echo "<td>
			    <button style='white-space:nowrap'
			            class='submit'
			            onclick='showFB()'>
			        {$text} (<fb:comments-count href='{$url}'>X</fb:comments-count>)
			    </button>
			</td>";
    }

    function add_tabs_button_wp()
    {
        global $post;
        $vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', true);
        $fbapi_comm = get_post_meta($post->ID, 'fbapi_comm', true);
        $comm_wp = get_comments_number() - $vkapi_comm - $fbapi_comm;
        $text = __('Site', $this->plugin_domain);
        echo "<td>
			    <button style='white-space:nowrap'
			            class='submit'
			            onclick='showWP()'>
			        {$text} ({$comm_wp})
			    </button>
			</td>";
    }

    function add_vk_comments()
    {
        global $post;
        $vkapi_button = __('VKontakte', $this->plugin_domain);
        $vkapi_some_desktop = get_option('vkapi_some_desktop');
        $attach = array();
        if (get_option('vkapi_comm_graffiti')) {
            $attach[] = 'graffiti';
        }
        if (get_option('vkapi_comm_photo')) {
            $attach[] = 'photo';
        }
        if (get_option('vkapi_comm_audio')) {
            $attach[] = 'audio';
        }
        if (get_option('vkapi_comm_video')) {
            $attach[] = 'video';
        }
        if (get_option('vkapi_comm_link')) {
            $attach[] = 'link';
        }
        if (empty($attach)) {
            $attach = 'false';
        } else {
            $attach = '"' . implode(',', $attach) . '"';
        }
        $autoPublish = get_option('vkapi_comm_autoPublish');
        if ((empty($autoPublish{0}))) {
            $autoPublish = '0';
        } else {
            $autoPublish = '1';
        }

        if ($vkapi_some_desktop) {
            echo "
                <script type='text/javascript'>
                    // todo-dx: check all function is calling
                    function vkapi_checkPermission() {
                        if(window.webkitNotifications.checkPermission()==0){
                            window.webkitNotifications.createNotification(
                                'https://vk.com/images/lnkinner32.gif',
                                'Успех',
                                'Сообщения разрешены')
                                .show();
                            clearInterval(vkapi_interval);
                        }
                    }
                    if(window.webkitNotifications.checkPermission()>0){
                        var vkapi_interval = setInterval(vkapi_checkPermission,500);
                    }
                    function vkapi_requestPermission(){
                        window.webkitNotifications.requestPermission();
                        jQuery('button.vkapi_remove').remove();
                    }
                    function onChangeRecalc(num){
                        jQuery('button.vk_recount').html('{$vkapi_button} ('+num+')');
                    }
                    function onChange(num,last_comment,data,hash){
                        if (window.webkitNotifications.checkPermission() == 0) {
                            last_comment = html_entity_decode(last_comment);
                            Time = new Date();
                            Hour = Time.getHours();
                            Min = Time.getMinutes();
                            Sec = Time.getSeconds();
                            var notification = window.webkitNotifications.createNotification(
                                'https://vk.com/images/lnkinner32.gif',
                                'Время '+Hour+':'+Min+':'+Sec,
                                last_comment);
                            notification.show();
                            document.getElementById('vkapi_sound').play();
                            setTimeout(function(){notification.cancel()}, '10000');
                        } else {
                            jQuery('#vkapi')
                                .append(\"<button class='submit' class='vkapi_remove'\" +
                                \"onclick='vkapi_requestPermission()'>Разрешить всплывающие сообщения</button>\");
                        }
                    }
                </script>";
        } else {
            echo "
				<script type='text/javascript'>
                    function onChangeRecalc(num){
                        jQuery('button.vk_recount').html('{$vkapi_button} ('+num+')');
                    }
				</script>";
        }
        $width = get_option('vkapi_comm_width');
        $height = get_option('vkapi_comm_height');
        $limit = get_option('vkapi_comm_limit');
        $url = get_permalink();
        echo "
			<div id='vkapi' onclick='showNotification()'></div>
			<script type='text/javascript'>
				jQuery(window).on('vkapi_vk', function(){
                    VK.Widgets.Comments(
                        'vkapi', {
                            width: {$width},
                            height: {$height},
                            limit: {$limit},
                            attach: {$attach},
                            autoPublish: {$autoPublish},
                            mini: 1,
                            pageUrl: '{$url}'
                        }, {$post->ID});
				});
			</script>";
        add_action('wp_footer', array(&$this, 'add_footer'));
    }

    function add_fb_comments()
    {
        $width = get_option('vkapi_comm_width');
        $limit = get_option('vkapi_comm_limit');
        $url = get_permalink();
        echo "
			<div style='background:white'
			     class='fb-comments'
			     data-href='{$url}'
			     data-num-posts='{$limit}'
			     data-width='{$width}'
			     colorscheme='light'>
			</div>";
    }

    function add_footer()
    {
        switch (get_option('vkapi_show_first')) {
            case 'vk':
                echo '<script type="text/javascript">jQuery(function(){showVK(0,0)});</script>';
                break;
            case 'fb':
                echo '<script type="text/javascript">jQuery(function(){showFB(0,0)});</script>';
                break;
            case 'wp':
            default:
                echo '<script type="text/javascript">jQuery(function(){showWP(0,0)});</script>';
                break;
        }
        echo '
                <audio id="vkapi_sound" preload="auto" style="display: none; visibility: hidden;">
				    <source src="https://vk.com/mp3/bb2.mp3">
			    </audio >
        ';
    }

    function settings_page()
    {
        require('php/options.php');
    }

    function comments_page()
    {
        $text = __('Comments', $this->plugin_domain);
        echo "
			<div class='wrap'>
				<div class='icon32'>
				    <img src='{$this->plugin_url}images/set.png' />
                </div>
				<h2 style='margin: 0 0 20px 50px'>
				    VKontakte API - $text
				</h2>
				<div id='vkapi_comments'></div>
				<script type='text/javascript' src='//vk.com/js/api/openapi.js'></script>
				<script type='text/javascript'>
					function VK_Widgets_CommentsBrowse() {
					    if ( typeof VK !== 'undefined' )
					        VK.Widgets.CommentsBrowse('vkapi_comments', { mini: 1});
						else
						    setTimeout(VK_Widgets_CommentsBrowse,1000);
				    };
				    VK_Widgets_CommentsBrowse();
				</script>
			</div>";
    }

##### PROFILE USER INTEGRATIONS
    function add_profile_html($profile)
    {
        ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label>
                    <?php _e('VKontakte', $this->plugin_domain); ?>
                </label>
            </th>
            <?php
            $uid = get_user_meta($profile->ID, 'vkapi_uid', true);
            if (empty($uid)) {
                ?>
                <td>
                    <div id="vkapi_login_button"
                         onclick="VK.Auth.login(onSignonProfile)">
                        <a>
                            ВойтиВКонтакте
                        </a>
                    </div>
                    <div id="vkapi_status_off"></div>
                    <style type="text/css">
                        #vkapi_login_button {
                            padding: 0 !important;
                            border: 0 !important;
                        }

                        .form-table td #vkapi_login_button td {
                            padding: 0 !important;
                            margin: 0 !important;
                        }
                    </style>
                    <script language="text/javascript">
                        jQuery(window).on('vkapi_vk', function () {
                            VK.UI.button('vkapi_login_button');
                        });
                    </script>
                </td>
                <?php
            } else {
                ?>
                <td>
                    <p>
                        <?php _e('Connected User Id: ', $this->plugin_domain); echo $uid; ?>
                    </p>
                    <input type="button"
                           class="button-primary"
                           style="display: inline-block"
                           value="<?php _e('Disconnect from VKontakte', $this->plugin_domain); ?>"
                           onclick="vkapi_profile_update(0)"/>

                    <div id="vkapi_status_on"></div>
                </td>
                <?php
            }
            ?>
        </tr>
    </table>
    <?php
    }

    function add_profile_js()
    {
        if (defined('IS_PROFILE_PAGE')) {
            ?>
        <script type="text/javascript">
            function vkapi_profile_update(args) {
                var ajax_url = ' <?php echo admin_url("admin-ajax.php"); ?>';
                var data = {
                    action:'vkapi_update_user_meta',
                    vkapi_action:args
                };
                if (args == 0) {
                    jQuery.post(ajax_url, data, function (response) {
                        if (response == 'Ok') {
                            jQuery("#vkapi_status_on").html("<span style='color:green'>Result: ✔ " + response + "</span>");
                            document.location.reload(true);
                        } else {
                            jQuery("#vkapi_status_on").html("<span style='color:red'>Result: " + response + "</span>");
                        }
                    });
                }
                if (args == 1) {
                    jQuery.post(ajax_url, data, function (response) {
                        if (response == 'Ok') {
                            jQuery("#vkapi_status_off").html("<span style='color:green'>Result: ✔ " + response + "</span>");
                            document.location.reload(true);
                        } else {
                            jQuery("#vkapi_status_off").html("<span style='color:red'>Result: " + response + "</span>");
                        }
                    });
                }
            }

            function onSignonProfile(response) {
                if (response.session) {
                    vkapi_profile_update(1);
                } else {
                    VK.Auth.login(onSignonProfile);
                }
            }
        </script>
        <?php
        }
    }

    function vkapi_update_user_meta()
    {
        $user = wp_get_current_user();
        $vkapi_action = (int)($_POST['vkapi_action']);
        if ($vkapi_action == 0) {
            $vkapi_result = delete_user_meta($user->ID, 'vkapi_uid');
            if ($vkapi_result) {
                echo 'Ok';
            } else {
                echo 'Failed delete meta data';
            }
            exit;
        }
        if ($vkapi_action == 1) {
            $member = $this->authOpenAPIMember();
            if ($member === false) {
                echo 'User Authorization Failed';
                exit;
            }
            $return = add_user_meta($user->ID, 'vkapi_uid', $member['id'], true);
            if ($return) {
                echo 'Ok';
            } else {
                echo 'Failed add meta data';
            }
            exit;
        }
    }

##### JS ASYNC LOADING
    function js_async_vkapi()
    {
        if (get_option('vkapi_appid')):
            ?>
        <div id="vk_api_transport"></div>
        <script type="text/javascript">
            window.vkAsyncInit = function () {
                VK.init({
                    apiId: <?php echo get_option('vkapi_appid') . "\n"; ?>
                });
                jQuery(window).trigger('vkapi_vk');
            };

            setTimeout(function () {
                var el = document.createElement("script");
                el.type = "text/javascript";
                el.src = "https://vk.com/js/api/openapi.js";
                el.async = true;
                document.getElementById("vk_api_transport").appendChild(el);
            }, 0);
        </script>
        <?php endif;
    }

    function js_async_vkshare()
    {
        ?>
    <div id="vk_share_transport"></div>
    <script type="text/javascript">
        setTimeout(function () {
            var el = document.createElement("script");
            el.type = "text/javascript";
            el.src = "https://vk.com/js/api/share.js";
            el.async = true;
            document.getElementById("vk_share_transport").appendChild(el);
        }, 0);
    </script>
    <?php
    }

    function js_async_plusone()
    {
        ?>
    <div id="gp_plusone_transport"></div>
    <script type="text/javascript">
        setTimeout(function () {
            var el = document.createElement("script");
            el.type = "text/javascript";
            el.src = "https://apis.google.com/js/plusone.js";
            el.async = true;
            document.getElementById("gp_plusone_transport").appendChild(el);
        }, 0);
    </script>
    <?php
    }

    function js_async_fbapi()
    {
        if (get_option('fbapi_appid')):
            ?>
        <div id="fb-root"></div>
        <script>
            window.fbAsyncInit = function () {
                FB.init({
                    appId: <?php echo get_option('fbapi_appid') . "\n"; ?>,
                    status:true,
                    cookie:true,
                    xfbml:true
                });
                jQuery(window).trigger('vkapi_fb');
            };

            (function (d) {
                var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
                if (d.getElementById(id)) {
                    return;
                }
                js = d.createElement('script');
                js.id = id;
                js.async = true;
                js.src = "//connect.facebook.net/ru_RU/all.js";
                ref.parentNode.insertBefore(js, ref);
            }(document));
        </script>
        <?php endif;
    }

    function js_async_tw()
    {
        ?>
    <script type="text/javascript"> !function (d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (!d.getElementById(id)) {
            js = d.createElement(s);
            js.id = id;
            js.src = "//platform.twitter.com/widgets.js";
            fjs.parentNode.insertBefore(js, fjs);
        }
    }(document, "script", "twitter-wjs");</script>
    <?php
    }

    function js_async_mrc()
    {
        ?>
    <script src="https://connect.mail.ru/js/loader.js"
            type="text/javascript"
            charset="UTF-8">
    </script>
    <?php
    }

    function js_async_ya()
    {
        ?>
    <script charset="utf-8"
            type="text/javascript">
        if (window.Ya && window.Ya.Share) {
            Ya.Share.update();
        } else {
            (function () {
                if (!window.Ya) {
                    window.Ya = {}
                }
                Ya.STATIC_BASE = 'https:\/\/yandex.st\/wow\/2.7.7\/static';
                Ya.START_BASE = 'https:\/\/my.ya.ru\/';
                var shareScript = document.createElement("script");
                shareScript.type = "text/javascript";
                shareScript.async = "true";
                shareScript.charset = "utf-8";
                shareScript.src = Ya.STATIC_BASE + "/js/api/Share.js";
                (document.getElementsByTagName("head")[0] || document.body).appendChild(shareScript);
            })();
        }
    </script>
    <?php
    }

##### SOCIAL BUTTONS
    function add_buttons($args)
    {
        global $post;
        $count = 0;
        $vkapi_get_butt = get_post_meta($post->ID, 'vkapi_buttons', true);
        if (
            !is_feed()
            && $vkapi_get_butt !== '0'
            && !(in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php')))
        ) {
            // mrc share
            if (get_option('mrc_show_share') == 'true') {
                $count++;
                $in_cat = get_option('mrc_share_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'mrc_button_share'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'mrc_button_share'), 5);
                    }
                }
            }
            // gp +
            if (get_option('gpapi_show_like') == 'true') {
                $count++;
                $in_cat = get_option('gpapi_like_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'gpapi_button_like'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'gpapi_button_like'), 5);
                    }
                }
            }
            // fb like
            if (get_option('fbapi_show_like') == 'true') {
                $count++;
                $in_cat = get_option('fbapi_like_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'fbapi_button_like'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'fbapi_button_like'), 5);
                    }
                }
            }
            // tweet me
            if (get_option('tweet_show_share') == 'true') {
                $count++;
                $in_cat = get_option('tweet_share_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'tweet_button_share'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'tweet_button_share'), 5);
                    }
                }
            }
            // ya share
            if (get_option('ya_show_share') == 'true') {
                $count++;
                $in_cat = get_option('ya_share_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'ya_button_share'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'ya_button_share'), 5);
                    }
                }
            }
            // vk share
            if (get_option('vkapi_show_share') == 'true') {
                $count++;
                $in_cat = get_option('vkapi_share_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'vkapi_button_share'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'vkapi_button_share'), 5);
                    }
                }
            }
            // vk like
            if (get_option('vkapi_show_like') == 'true') {
                $count++;
                $in_cat = get_option('vkapi_like_cat');
                if ($in_cat) {
                    add_action('add_social_button_action', array(&$this, 'vkapi_button_like'), 5);
                } else {
                    if (!is_home() && !is_category() && !is_archive()) {
                        add_action('add_social_button_action', array(&$this, 'vkapi_button_like'), 5);
                    }
                }
            }
            // shake
            if ($count) {
                add_action('add_social_button_action', array(&$this, 'social_button_start'), 1);
                add_action('add_social_button_action', array(&$this, 'social_button_end'), 888);
                add_action('wp_footer', array(&$this, 'social_button_style'), 888);

                ob_start();
                do_action('add_social_button_action');
                $echo = ob_get_clean();

                $temp = get_option('vkapi_like_top');
                if ($temp) {
                    $args = $echo . $args;
                }
                $temp = get_option('vkapi_like_bottom');
                if ($temp) {
                    $args .= $echo;
                }
            }
        }

        return $args;
    }

    function social_button_style()
    {
        ?>
    <style type="text/css">
        ul.nostyle,
        ul.nostyle li {
            list-style: none;
            background: none;
        }

        ul.nostyle li {
            height: 20px;
            line-height: 20px;
            padding: 5px;
            margin: 0;
            /*display: inline-block;*/
            float: left;
            content: '';
        }

        ul.nostyle li div table {
            margin: 0;
            padding: 0;
        }

        .vkapishare {
            padding: 0 3px 0 0;
        }

        .vkapishare td,
        .vkapishare tr {
            border: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            vertical-align: top !important;
        }
    </style>
    <?php
    }

    function social_button_start()
    {
        $temp = get_option("vkapi_align");
        echo "<!--noindex--><div style='clear:both;'><ul class='nostyle' style='float:{$temp}'>";
    }

    function social_button_end()
    {
        echo '</ul></div><br style="clear:both;"><!--/noindex-->';
    }

    function vkapi_button_like()
    {
        global $post;
        $post_id = $post->ID;
        $div_id = "vkapi_like_{$post_id}_" . mt_rand();
        echo "<li><div id='$div_id'></div></li>";
        $type = get_option('vkapi_like_type');
        $verb = get_option('vkapi_like_verb');
        $vkapi_title = addslashes(do_shortcode($post->post_title));
        $vkapi_url = get_permalink();
        $vkapi_text = str_replace(array("\r\n", "\n", "\r"), ' <br />', do_shortcode($post->post_content));
        $vkapi_text = strip_tags($vkapi_text);
        $vkapi_text = addslashes($vkapi_text);
        $vkapi_descr = $vkapi_text = mb_substr($vkapi_text, 0, 139);
        $vkapi_image = $this->first_postimage($vkapi_text);
        // pageImage
        echo "
						<script type=\"text/javascript\">
							<!--
							    jQuery(window).on('vkapi_vk', function(){
							        var temp = Math.random()%1;
								    jQuery('#{$div_id}').attr('id',temp);
									VK.Widgets.Like(temp, {
										width: 1,
										height: 20,
										type: '{$type}',
										verb: '{$verb}',
										pageTitle: '{$vkapi_title}',
										pageDescription: '{$vkapi_descr}',
										pageUrl: '{$vkapi_url}',
										pageImage: '{$vkapi_image}',
										text: '{$vkapi_text}'
									}, {$post_id});
							    });
							-->
						</script>";
    }

    function vkapi_button_share()
    {
        global $post;
        $post_id = $post->ID;
        $div_id = "vkapi_share_{$post_id}_" . mt_rand();
        echo "<li><div class='vkapishare' id='$div_id'></div></li>";
        $vkapi_url = get_permalink();
        $vkapi_title = addslashes(do_shortcode($post->post_title));
        $vkapi_descr = str_replace(array("\r\n", "\n", "\r"), ' <br />', do_shortcode($post->post_content));
        $vkapi_descr = strip_tags($vkapi_descr);
        $vkapi_descr = addslashes($vkapi_descr);
        $vkapi_descr = mb_substr($vkapi_descr, 0, 139);
        $vkapi_image = $this->first_postimage($vkapi_descr);
        $vkapi_type = get_option('vkapi_share_type');
        $vkapi_text = get_option('vkapi_share_text');
        $vkapi_text = addslashes($vkapi_text);
        echo "
							<script type=\"text/javascript\">
								<!--
									function VK_Share_button_$div_id() {
										if ( typeof VK !== 'undefined' && typeof VK.Share !== 'undefined') {
											var temp = Math.random()%1;
											jQuery('#{$div_id}').attr('id',temp);
											document.getElementById(temp).innerHTML = VK.Share.button({
												url: '{$vkapi_url}',
												title: '{$vkapi_title}',
												description: '{$vkapi_descr}',
												image: '{$vkapi_image}'
											},{
												type: '{$vkapi_type}',
												text: '{$vkapi_text}'
											});
										} else {
											setTimeout(VK_Share_button_$div_id,1000);
										};
									};
									VK_Share_button_$div_id();
								-->
							</script>";
    }

    function fbapi_button_like()
    {
        $url = get_permalink();
        echo "
			<li>
                <div
                    class='fb-like'
                    data-href='{$url}'
                    data-send='false'
                    data-layout='button_count'
                    data-width='100'
                    data-show-faces='true'
                    >
                </div>
			</li>";
    }

    function gpapi_button_like()
    {
        $url = get_permalink();
        echo "
			<li>
                <div
                    class='g-plusone'
                    data-size='medium'
                    data-annotation='none'
                    data-href='{$url}'>
                </div>
			</li>";
    }

    function tweet_button_share()
    {
        global $post;
        $url = get_permalink();
        $who = get_option('tweet_account');
        $vkapi_title = addslashes(do_shortcode($post->post_title));
        echo "
			<li>
                <div>
                    <a
                        style='border:none'
                        rel='nofollow'
                        href='https://twitter.com/share'
                        class='twitter-share-button'
                        data-url='{$url}'
                        data-text='{$vkapi_title}'
                        data-lang='ru'
                        data-via='{$who}'
                        data-dnt='true'
                        data-count='none'>
                            Tweet
                    </a>
                </div>
			</li>";
    }

    function mrc_button_share()
    {
        // todo-dx: check new version
        $url = rawurlencode(get_permalink());
        echo "
			<li>
			    <div style='width:130px'>
			        <a
                        rel='nofollow'
                        target='_blank'
                        class='mrc__plugin_uber_like_button'
                        href='{$url}'
                        data-mrc-config=\"{'nt' : 1, 'cm' : 1, 'ck' : 1, 'sz' : 20, 'st' : 1, 'tp' : 'combo'}\">
                            Нравится
                    </a>
			    </div>
			</li>";
    }

    function ya_button_share()
    {
        $url = get_permalink();
        // todo-dx: lookup for new version
        echo "
			<li>
			    <div>
			        <a
                        style='text-decoration: none'
                        rel='nofollow'
                        counter='yes'
                        type='icon'
                        size='large'
                        share_url='{$url}'
                        name='ya-share'>
                            &nbsp;
                    </a>
			    </div>
			</li>";
    }

##### PRIVATE FUNCTION
    private function notice_error($msg = 'qwe123qwe')
    {
        if ($msg == 'qwe123qwe') {
            return;
        } else {
            $array = array();
            $temp = get_option('vkapi_msg');
            if (!empty($temp)) {
                $array = array_merge($array, $temp);
            }
            $array[] = array(
                'type' => 'error',
                'msg' => $msg
            );
            update_option('vkapi_msg', $array);
        }
    }

    private function notice_notice($msg = 'qwe123qwe')
    {
        if ($msg == 'qwe123qwe') {
            return;
        } else {
            $array = array();
            $temp = get_option('vkapi_msg');
            if (!empty($temp)) {
                $array = array_merge($array, $temp);
            }
            $array[] = $array[] = array(
                'type' => 'updated',
                'msg' => $msg
            );
            update_option('vkapi_msg', $array);
        }
    }

    private function crosspost($vk_at, $post_id, $post_title, $post_content)
    {
        $body = array();
        $body['access_token'] = $vk_at;
        $body['from_group'] = 1;
        // todo-dx: signed optioned
        $body['signed'] = 1;
        // todo-dx: crosspost to facebook, check some fb plugin
        $vk_group = get_option('vkapi_vk_group');
        if (!is_numeric($vk_group)) {
            $params = array();
            $params['access_token'] = $vk_at;
            $params['gid'] = $vk_group;
            $query = http_build_query($params);
            $result = wp_remote_get($this->vkapi_server . 'groups.getById?' . $query);
            if (is_wp_error($result)) {
                $msg = $result->get_error_message();
                self::notice_error('Crosspost: ' . $msg . ' wpx' . __LINE__);

                return false;
            }
            $r_data = json_decode($result['body'], true);
            if (!$r_data['response']) {
                $msg = $r_data['error']['error_msg'] . ' ' . $r_data['error']['error_code'];
                self::notice_error('Crosspost: API Error Code: ' . $msg . 'x' . __LINE__);

                return false;
            }
            $vk_group = $r_data['response'][0]['gid'];
            $vk_group_screen_name = $r_data['response'][0]['screen_name'];
        }
        $vk_group = -$vk_group;
        $body['owner_id'] = $vk_group;
        // Attachment
        $att = array();
        $image_path = $this->crosspost_get_image($post_id);
        if ($image_path) {
            $att[] = $this->vk_upload_photo($vk_at, $vk_group, $image_path);
        }
        $temp = get_option('vkapi_crosspost_link');
        if (!empty($temp)) {
            $temp = get_permalink($post_id);
            if (!class_exists('Punycode')) {
                require_once($this->plugin_path . 'php/punycode.php');
                $temp = Punycode::urldecode($temp);
            }
            $att[] = $temp;
        }
        if (!empty($att)) {
            $body['attachments'] = implode(',', $att);
        }
        // Text
        $text = do_shortcode($post_content);
        $text = $this->html2text($text);
        $temp = get_option('vkapi_crosspost_length');
        $text_len = mb_strlen($text);
        $text = mb_substr($text, 0, (int)$temp);
        $text = html_entity_decode($text, ENT_QUOTES);
        $last_pos = strrpos($text, ' ');
        if (!$last_pos) {
            $last_pos = strrpos($text, "\n");
        }
        if (!$last_pos) {
            $text = mb_substr($text, 0, $last_pos);
        }
        if (mb_strlen($text) != $text_len) {
            $text .= '...';
        }
        $text = $post_title . "\r\n\r\n" . $text . '...';
        $body['message'] = $text;
        // Call
        $curl = new Wp_Http_Curl();
        $result = $curl->request(
            $this->vkapi_server . 'wall.post',
            array(
                'body' => $body,
                'method' => 'POST'
            )
        );
        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            self::notice_error('Crosspost: ' . $msg . ' wpx' . __LINE__);

            return false;
        }
        $r_data = json_decode($result['body'], true);
        if (isset($r_data['error'])) {
            $msg = $r_data['error']['error_msg'] . ' ' . $r_data['error']['error_code'];
            self::notice_error('Crosspost: API Error Code: ' . $msg . 'x' . __LINE__);

            return false;
        }
        $temp = isset($vk_group_screen_name) ? $vk_group_screen_name : 'club' . $vk_group;
        $post_link = "http://vk.com/{$temp}?w=wall{$vk_group}_{$r_data['response']['post_id']}%2Fall";
        $post_href = "<a href='{$post_link}' target='_blank'>{$temp}</a>";
        self::notice_notice('Crosspost: Success ! ' . $post_href);
        update_post_meta($post_id, 'vkapi_crossposted', 'true');

        return true;
    }

    private function html2text($html)
    {
        $tags = array(
            0 => '~<h[123][^>]+>~si',
            1 => '~<h[456][^>]+>~si',
            2 => '~<table[^>]+>~si',
            3 => '~<tr[^>]+>~si',
            4 => '~<li[^>]+>~si',
            5 => '~<br[^>]+>~si',
            6 => '~<p[^>]+>~si',
            7 => '~<div[^>]+>~si',
        );
        $html = preg_replace($tags, "\n", $html);
        $html = preg_replace('~</t(d|h)>\s*<t(d|h)[^>]+>~si', ' - ', $html);
        $html = preg_replace('~<[^>]+>~s', '', $html);

        // reducing spaces
        //$html = preg_replace('~ +~s', ' ', $html);
        //$html = preg_replace('~^\s+~m', '', $html);
        //$html = preg_replace('~\s+$~m', '', $html);

        // reducing newlines
        //$html = preg_replace('~\n+~s',"\n",$html);
        return $html;
    }

    private function crosspost_get_image($post_id)
    {
        // need thumbnail? no problem!
        $file_id = get_post_thumbnail_id($post_id);
        if (empty($file_id)) {
            // get first image id
            $images = get_children(
                array(
                    'post_parent' => $post_id,
                    'post_type' => 'attachment',
                    'numberposts' => 1, // show all -1
                    'post_status' => 'inherit',
                    'post_mime_type' => 'image',
                    'order' => 'ASC',
                    'orderby' => 'menu_order id'
                )
            );
            if (!$images) {
                return false;
            }
            foreach ($images as $image) {
                $file_id = $image->ID;
            }
        }
        // get absolute path
        $image_url = get_attached_file($file_id);

        return $image_url;
    }

    private function vk_upload_photo($vk_at, $vk_group, $image_path)
    {
        // Get Wall Upload Server
        $params = array();
        $params['access_token'] = $vk_at;
        $params['gid'] = -$vk_group;
        $query = http_build_query($params);
        $result = wp_remote_get($this->vkapi_server . 'photos.getWallUploadServer?' . $query);
        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            self::notice_error('Crosspost: ' . $msg . ' wpx' . __LINE__);

            return false;
        }
        $data = json_decode($result['body'], true);
        if (!$data['response']) {
            $msg = $data['error']['error_msg'] . ' ' . $data['error']['error_code'];
            self::notice_error('Crosspost: API Error Code: ' . $msg . 'x' . __LINE__);

            return false;
        }
        // Upload Photo To Server
        $curl = new Wp_Http_Curl();
        $body['photo'] = '@' . $image_path;
        $result = $curl->request(
            $data['response']['upload_url'],
            array(
                'body' => $body,
                'method' => 'POST'
            )
        );
        if (is_wp_error($result)) {
            $msg = $data->get_error_message();
            self::notice_error('Crosspost: ' . $msg . ' wpx' . __LINE__);

            return false;
        }
        $data = json_decode($result['body'], true);
        if (!isset($data['photo'])) {
            $msg = $data['error']['error_msg'] . ' ' . $data['error']['error_code'];
            self::notice_error('Crosspost: API Error Code: ' . $msg . 'x' . __LINE__);

            return false;
        }
        // Save Wall Photo
        $params = array();
        $params['access_token'] = $vk_at;
        $params['gid'] = -$vk_group;
        $params['server'] = $data['server'];
        $params['photo'] = $data['photo'];
        $params['hash'] = $data['hash'];
        $query = http_build_query($params);
        $result = wp_remote_get($this->vkapi_server . 'photos.saveWallPhoto?' . $query);
        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            self::notice_error('Crosspost: ' . $msg . ' wpx' . __LINE__);

            return false;
        }
        $data = json_decode($result['body'], true);
        if (!$data['response']) {
            $msg = $data['error']['error_msg'] . ' ' . $data['error']['error_code'];
            self::notice_error('Crosspost: API Error Code: ' . $msg . 'x' . __LINE__);

            return false;
        }

        // Return Photo ID
        return $data['response'][0]['id'];
    }

    private function first_postImage(&$text)
    {
        if ((bool)preg_match('#<img[^>]+src=[\'"]([^\'"]+)[\'"]#', $text, $matches)) {
            return $matches[1];
        } else {
            return '';
        }
    }

    private function authOpenAPIMember()
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

##### OTHER
    function register_settings()
    {
        register_setting('vkapi-settings-group', 'vkapi_appid');
        register_setting('vkapi-settings-group', 'fbapi_admin_id');
        register_setting('vkapi-settings-group', 'vkapi_api_secret');
        register_setting('vkapi-settings-group', 'vkapi_comm_width');
        register_setting('vkapi-settings-group', 'vkapi_comm_limit');
        register_setting('vkapi-settings-group', 'vkapi_comm_graffiti');
        register_setting('vkapi-settings-group', 'vkapi_comm_photo');
        register_setting('vkapi-settings-group', 'vkapi_comm_audio');
        register_setting('vkapi-settings-group', 'vkapi_comm_video');
        register_setting('vkapi-settings-group', 'vkapi_comm_link');
        register_setting('vkapi-settings-group', 'vkapi_comm_autoPublish');
        register_setting('vkapi-settings-group', 'vkapi_comm_height');
        register_setting('vkapi-settings-group', 'vkapi_show_first');
        register_setting('vkapi-settings-group', 'vkapi_like_type');
        register_setting('vkapi-settings-group', 'vkapi_like_verb');
        register_setting('vkapi-settings-group', 'vkapi_like_cat');
        register_setting('vkapi-settings-group', 'vkapi_like_bottom');
        register_setting('vkapi-settings-group', 'vkapi_share_cat');
        register_setting('vkapi-settings-group', 'vkapi_share_type');
        register_setting('vkapi-settings-group', 'vkapi_share_text');
        register_setting('vkapi-settings-group', 'vkapi_align');
        register_setting('vkapi-settings-group', 'vkapi_show_comm');
        register_setting('vkapi-settings-group', 'vkapi_show_like');
        register_setting('vkapi-settings-group', 'fbapi_show_comm');
        register_setting('vkapi-settings-group', 'vkapi_show_share');
        register_setting('vkapi-settings-group', 'vkapi_some_logo_e');
        register_setting('vkapi-settings-group', 'vkapi_some_logo');
        register_setting('vkapi-settings-group', 'vkapi_some_desktop');
        register_setting('vkapi-settings-group', 'vkapi_some_revision_d');
        register_setting('vkapi-settings-group', 'vkapi_close_wp');
        register_setting('vkapi-settings-group', 'vkapi_login');
        register_setting('vkapi-settings-group', 'tweet_show_share');
        register_setting('vkapi-settings-group', 'tweet_account');
        register_setting('vkapi-settings-group', 'tweet_share_cat');
        register_setting('vkapi-settings-group', 'gpapi_show_like');
        register_setting('vkapi-settings-group', 'fbapi_like_cat');
        register_setting('vkapi-settings-group', 'fbapi_show_like');
        register_setting('vkapi-settings-group', 'fbapi_appid');
        register_setting('vkapi-settings-group', 'gpapi_like_cat');
        register_setting('vkapi-settings-group', 'mrc_show_share');
        register_setting('vkapi-settings-group', 'mrc_share_cat');
        register_setting('vkapi-settings-group', 'ya_show_share');
        register_setting('vkapi-settings-group', 'ya_share_cat');
        register_setting('vkapi-settings-group', 'vkapi_vk_group');
        register_setting('vkapi-settings-group', 'vkapi_at');
        register_setting('vkapi-settings-group', 'vkapi_like_top');
        register_setting('vkapi-settings-group', 'vkapi_crosspost_default');
        register_setting('vkapi-settings-group', 'vkapi_crosspost_length');
        register_setting('vkapi-settings-group', 'vkapi_crosspost_link');
    }

    function contextual_help()
    {
        // todo-dx: edit contextual help
        $screen = get_current_screen();
        /* main */
        $help = '<p>Добавляет функционал API сайта ВКонтакте(vk.com) на ваш блог.<br />
                Комментарии, социальные кнопки, виджеты...</p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_main',
                'title' => __('Main', $this->plugin_domain),
                'content' => $help
            )
        );
        /* comments */
        $help = '<p>Появляется возможность переключения между комментариями WordPress-a и Вконтакта.<br />
				"Прячутся" и "показываются" блоки <b>div</b> с <b>id=comments</b> ( блок комментариев ) и <b>id=respond</b> ( блок формы ответа ) ( <i>по спецификации Wordpress-a</i> )<br />
				В наличии вся нужная настройка, а также возможность вовсе оставить лишь комментарии Вконтакта.</p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_comments',
                'title' => __('Comments', $this->plugin_domain),
                'content' => $help
            )
        );
        /* like button */
        $help = '<p>Собственно кнопка с настройкой позиции и вида.<br />
				По результатам этой кнопки есть <a href="' . admin_url("widgets.php") . '">виждет</a> "Популярное" со статистикой.<br /><br />
				Доступен шорткод [vk_like], по умолчанию берётся айдишник страницы.
				Но также можно указать [vk_like id="123456"] для уникальности кнопки.</p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_like',
                'title' => __('Like button', $this->plugin_domain),
                'content' => $help
            )
        );
        /* decor */
        $help = '<p>В браузерах Google Chrome и Safari ( движок WebKit ) есть возможность показывать всплывающее сообщение прямо на рабочем столе<br />
				А значит вы в любой момент можете узнать о новом сообщении.</p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_decor',
                'title' => __('Decor', $this->plugin_domain),
                'content' => $help
            )
        );
        /* other */
        $help = '<p><strong>В WordPress-e срабатывает астосохранение при редактировании/добавлении новой записи(поста).<br />
				Это плохо тем, что появляется туча бесполезных черновиков (копий, записей).
				А ведь зачем заполнять ими нашу базу данных?<br />
				<strong>Disable Revision Post Save</strong> - устанавливает количество выше упомянутых черновиков в ноль.<br /></p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_other',
                'title' => __('No Plugin Options', $this->plugin_domain),
                'content' => $help
            )
        );
        /* help */
        $help = '<p>Все вопросики и пожелания <strong><a href="http://www.kowack.info/projects/vk_api/" title="VKontakte API Home">сюдАтачки</a></strong> или <strong><a href="http://vk.com/vk_wp" title="VKontakte API Club">тУтачки</a></strong>.</p>';
        $screen->add_help_tab(
            array(
                'id' => 'vkapi_help',
                'title' => __('Help', $this->plugin_domain),
                'content' => $help
            )
        );
    }

    function vkapi_inner_custom_box_comm()
    {
        global $post;
        $temp = get_post_meta($post->ID, 'vkapi_comments', true);
        $temp = ($temp == '1' || $temp == '0') ? $temp : 1;
        echo '<input type="radio" name="vkapi_comments" value="1"';
        echo $temp == 1 ? ' checked />' : '/>';
        echo __('Enable', $this->plugin_domain) . '<br />';
        echo '<input type="radio" name="vkapi_comments" value="0"';
        echo $temp == 0 ? ' checked />' : '/>';
        echo __('Disable', $this->plugin_domain);
    }

    function vkapi_inner_custom_box_butt()
    {
        global $post;
        $temp = get_post_meta($post->ID, 'vkapi_buttons', true);
        $temp = ($temp == '1' || $temp == '0') ? $temp : 1;
        echo '<input type="radio" name="vkapi_buttons" value="1"';
        echo $temp == 1 ? ' checked />' : '/>';
        echo __('Enable', $this->plugin_domain) . '<br />';
        echo '<input type="radio" name="vkapi_buttons" value="0"';
        echo $temp == 0 ? ' checked />' : '/>';
        echo __('Disable', $this->plugin_domain);
    }
}

/* =Vkapi Widgets
-------------------------------------------------------------- */

/* Community Widget */
class VKAPI_Community extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array(
            'classname' => 'widget_vkapi',
            'description' => __('Information about VKontakte group', $this->plugin_domain)
        );
        parent::WP_Widget(
            'vkapi_community',
            $name = 'VKapi: ' . __('Community Users', $this->plugin_domain),
            $widget_ops
        );
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_divid = $args['widget_id'];
        $vkapi_mode = 2;
        $vkapi_gid = $instance['gid'];
        $vkapi_width = $instance['width'];
        if ($vkapi_width < 1) {
            $vkapi_width = '';
        } else {
            $vkapi_width = "width: \"$vkapi_width\",";
        }
        $vkapi_height = $instance['height'];
        if ($instance['type'] == 'users') {
            $vkapi_mode = 0;
        }
        if ($instance['type'] == 'news') {
            $vkapi_mode = 2;
        }
        if ($instance['type'] == 'name') {
            $vkapi_mode = 1;
        }
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="' . $vkapi_divid . '_wrapper">';
        $vkapi_divid .= "_wrapper";
        echo '</div>
		<script type="text/javascript">
		    function VK_Widgets_Group() {
	            if (typeof VK != "undefined") {
    			    VK.Widgets.Group("' . $vkapi_divid . '", {mode: ' . $vkapi_mode . ', ' . $vkapi_width . ' height: "' . $vkapi_height . '"}, ' . $vkapi_gid . ');
			    } else {
			        setTimeout(VK_Widgets_Group,1000);
			    }
			};
			VK_Widgets_Group();
		</script>';
        echo $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array)$instance,
            array('type' => 'users', 'title' => '', 'width' => '0', 'height' => '1', 'gid' => '28197069')
        );
        $title = esc_attr($instance['title']);
        $gid = esc_attr($instance['gid']);
        $width = esc_attr($instance['width']);
        $height = esc_attr($instance['height']);

        ?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>"
               type="text"
               value="<?php echo $title; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('gid'); ?>"><?php _e(
        'ID of group (can be seen by reference to statistics):',
        $this->plugin_domain
    ); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('gid'); ?>"
               name="<?php echo $this->get_field_name('gid'); ?>"
               type="text"
               value="<?php echo $gid; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Width:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('width'); ?>"
               name="<?php echo $this->get_field_name('width'); ?>"
               type="text"
               value="<?php echo $width; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('height'); ?>"><?php _e('Height:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('height'); ?>"
               name="<?php echo $this->get_field_name('height'); ?>"
               type="text"
               value="<?php echo $height; ?>"/>
    </label></p>

    <p>
        <label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Layout:', $this->plugin_domain); ?></label>
        <select name="<?php echo $this->get_field_name('type'); ?>"
                id="<?php echo $this->get_field_id('type'); ?>"
                class="widefat">
            <option value="users"<?php selected($instance['type'], 'users'); ?>><?php _e(
                'Members',
                $this->plugin_domain
            ); ?></option>
            <option value="news"<?php selected($instance['type'], 'news'); ?>><?php _e(
                'News',
                $this->plugin_domain
            ); ?></option>
            <option value="name"<?php selected($instance['type'], 'name'); ?>><?php _e(
                'Only Name',
                $this->plugin_domain
            ); ?></option>
        </select>
    </p>
    <?php
    }
}

/* Recommend Widget */
class VKAPI_Recommend extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array(
            'classname' => 'widget_vkapi',
            'description' => __('Top site on basis of "I like" statistics', $this->plugin_domain)
        );
        parent::WP_Widget('vkapi_recommend', $name = 'VKapi: ' . __('Recommends', $this->plugin_domain), $widget_ops);
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_widgetid = str_replace('-', '_', $args['widget_id']);
        $vkapi_divid = $vkapi_widgetid . '_wrapper';
        $vkapi_limit = $instance['limit'];
        $vkapi_width = $instance['width'];
        $vkapi_period = $instance['period'];
        $vkapi_verb = $instance['verb'];
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['title'] . $after_title;
        if ($vkapi_width != '0') {
            echo "<div style=\"width:$vkapi_width\">";
        }
        echo '<div id="' . $vkapi_divid . '">';
        echo '
		<script type="text/javascript">
			function VK_Widgets_Recommended_' . $vkapi_widgetid . '() {
            if ( typeof VK !== "undefined" )
				VK.Widgets.Recommended("' . $vkapi_divid . '", {limit: ' . $vkapi_limit . ', period: \'' . $vkapi_period . '\', verb: ' . $vkapi_verb . ', target: "blank"});
			else setTimeout(VK_Widgets_Recommended_' . $vkapi_widgetid . ',1000);
			};
			VK_Widgets_Recommended_' . $vkapi_widgetid . '();
		</script>';
        if ($vkapi_width != '0') {
            echo '</div>';
        }
        echo '</div>' . $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array)$instance,
            array('title' => '', 'limit' => '5', 'period' => 'month', 'verb' => '0', 'width' => '0')
        );
        $title = esc_attr($instance['title']);
        $limit = esc_attr($instance['limit']);
        $width = esc_attr($instance['width']);

        ?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>"
               type="text"
               value="<?php echo $title; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of posts:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('limit'); ?>"
               name="<?php echo $this->get_field_name('limit'); ?>"
               type="text"
               value="<?php echo $limit; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Width:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('width'); ?>"
               name="<?php echo $this->get_field_name('width'); ?>"
               type="text"
               value="<?php echo $width; ?>"/>
    </label></p>

    <p>
        <label for="<?php echo $this->get_field_id('period'); ?>"><?php _e(
            'Selection period:',
            $this->plugin_domain
        ); ?></label>
        <select name="<?php echo $this->get_field_name('period'); ?>"
                id="<?php echo $this->get_field_id('period'); ?>"
                class="widefat">
            <option value="day"<?php selected($instance['period'], 'day'); ?>><?php _e(
                'Day',
                $this->plugin_domain
            ); ?></option>
            <option value="week"<?php selected($instance['period'], 'week'); ?>><?php _e(
                'Week',
                $this->plugin_domain
            ); ?></option>
            <option value="month"<?php selected($instance['period'], 'month'); ?>><?php _e(
                'Month',
                $this->plugin_domain
            ); ?></option>
        </select>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('verb'); ?>"><?php _e(
            'Formulation:',
            $this->plugin_domain
        ); ?></label>
        <select name="<?php echo $this->get_field_name('verb'); ?>"
                id="<?php echo $this->get_field_id('verb'); ?>"
                class="widefat">
            <option value="0"<?php selected($instance['verb'], '0'); ?>><?php _e(
                '... people like this',
                $this->plugin_domain
            ); ?></option>
            <option value="1"<?php selected($instance['verb'], '1'); ?>><?php _e(
                '... people find it intersting',
                $this->plugin_domain
            ); ?></option>
        </select>
    </p>
    <?php
    }
}

/* Login Widget */
class VKAPI_Login extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array('classname' => 'widget_vkapi', 'description' => __('Login widget', $this->plugin_domain));
        parent::WP_Widget('vkapi_login', $name = 'VKapi: ' . __('Login', $this->plugin_domain), $widget_ops);
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_divid = $args['widget_id'];
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['Message'] . $after_title . '<div id="' . $vkapi_divid . '_wrapper">';
        $vkapi_divid .= "_wrapper";
        if (is_user_logged_in()) {
            $vkapi_wp_id = get_current_user_id();
            $vkapi_meta_ava = get_user_meta($vkapi_wp_id, 'vkapi_ava', true);
            if (!empty($vkapi_meta_ava{0})) {
                echo "<div style='float:left; padding-right:20px'><img alt='' src='$vkapi_meta_ava' class='avatar avatar-75' height='75' width='75' /></div>";
                echo "\r\n<div>";
                echo '<ul style="list-style:none">';
                echo "<li><a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __(
                    'Profile',
                    $this->plugin_domain
                ) . "</a></li>";
                echo "<li><a href='" . wp_logout_url(home_url($_SERVER['REQUEST_URI'])) . "' title=''>" . __(
                    'Logout',
                    $this->plugin_domain
                ) . "</a></li></div>";
            } else {
                echo "<div style='float:left; padding-right:20px'>" . get_avatar($vkapi_wp_id, 75) . "</div>";
                echo "\r\n<div>";
                echo '<ul style="list-style:none">';
                echo "<li><a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __(
                    'Profile',
                    $this->plugin_domain
                ) . "</a></li>";
                echo "<li><a href='" . wp_logout_url(home_url($_SERVER['REQUEST_URI'])) . "' title=''>" . __(
                    'Logout',
                    $this->plugin_domain
                ) . "</a></li></div>";
            }
        } else {
            $this->vkapi_link_vk();
        }
        echo '</ul></div>' . $after_widget;
    }

    function vkapi_link_vk()
    {
        $vkapi_url = get_bloginfo('wpurl');
        echo '<button style="display:none" class="vkapi_vk_widget" vkapi_url="' . $vkapi_url . '"></button>';
        echo '<ul style="list-style:none">';
        echo '<li><a href="' . wp_login_url(home_url($_SERVER['REQUEST_URI'])) . '" title="">' . __(
            'Login',
            $this->plugin_domain
        ) . '</a></li>';
        echo '<li>';
        echo wp_register('', '', home_url($_SERVER['REQUEST_URI']));
        echo '</li>';
        echo '<li>
		<div id="vkapi_status"></div>
		<div id="login_button" style="padding: 0px; border: 0px; width: 125px;" onclick="VK.Auth.login(onSignon)"><a>ВойтиВКонтакте</a></div></li>
		<style type="text/css">
			#login_button td, #login_button tr {
				padding: 0px !important;
				margin: 0px !important;
				vertical-align: top !important;
			}
			.widget li {
				list-style: none !important;
			}
		</style>
		<script type="text/javascript">
			function VK_UI_button() {
				VK.UI.button("login_button");
				if ( typeof VK !== "undefined" )
				    VK_UI_button();
				else
			        setTimeout(VK_UI_button,1000);
			}
			VK_UI_button();
		</script>
		<div style="display:none" id="vk_auth"></div>
			<script type="text/javascript">
				function VK_Widgets_Auth() {
					VK.Widgets.Auth("vk_auth", {width: "200px", onAuth: function(data) {
						alert("user "+data["uid"]+" authorized");
					} });
					if ( typeof VK !== "undefined" )
					    VK_Widgets_Auth();
				    else
				        setTimeout(VK_Widgets_Auth,1000);
				};
				VK_Widgets_Auth();
		</script>';
    }

    function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args((array)$instance, array('Message' => 'What\'s up'));
        $title = esc_attr($instance['Message']);

        ?><p><label for="<?php echo $this->get_field_id('Message'); ?>"><?php _e('Message:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('Message'); ?>"
               name="<?php echo $this->get_field_name('Message'); ?>"
               type="text"
               value="<?php echo $title; ?>"/>
    </label></p>
    <?php
    }
}

/* Comments Widget */
class VKAPI_Comments extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array('classname' => 'widget_vkapi', 'description' => __('Last Comments', $this->plugin_domain));
        parent::WP_Widget('vkapi_comments', $name = 'VKapi: ' . __('Last Comments', $this->plugin_domain), $widget_ops);
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_divid = $args['widget_id'];
        $vkapi_width = $instance['width'];
        if ($vkapi_width = '0') {
            $vkapi_width = '';
        } else {
            $vkapi_width = "width: '$vkapi_width',";
        }
        $vkapi_height = $instance['height'];
        $vkapi_limit = $instance['limit'];
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="' . $vkapi_divid . '_wrapper">';
        $vkapi_divid .= "_wrapper";
        echo "
			<div class=\"wrap\">
				<div id=\"vkapi_comments\"></div>
				<script type=\"text/javascript\">
					function VK_Widgets_CommentsBrowse() {
                        if ( typeof VK !== 'undefined' )
                            VK.Widgets.CommentsBrowse('vkapi_comments', {
                                {$vkapi_width}limit: '{$vkapi_limit}',
                                height: '{$vkapi_height}',
                                mini: 1
                            });
					    else
					        setTimeout(VK_Widgets_CommentsBrowse,1000);
					};
					VK_Widgets_CommentsBrowse();
				</script>
			</div>
			";
        echo '</div>' . $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array)$instance,
            array('title' => '', 'limit' => '5', 'width' => '0', 'height' => '1')
        );
        $title = esc_attr($instance['title']);
        $limit = esc_attr($instance['limit']);
        $width = esc_attr($instance['width']);
        $height = esc_attr($instance['height']);

        ?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>"
               type="text"
               value="<?php echo $title; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e(
        'Number of comments:',
        $this->plugin_domain
    ); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('limit'); ?>"
               name="<?php echo $this->get_field_name('limit'); ?>"
               type="text"
               value="<?php echo $limit; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Width:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('width'); ?>"
               name="<?php echo $this->get_field_name('width'); ?>"
               type="text"
               value="<?php echo $width; ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('height'); ?>"><?php _e('Height:', $this->plugin_domain); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('height'); ?>"
               name="<?php echo $this->get_field_name('height'); ?>"
               type="text"
               value="<?php echo $height; ?>"/>
    </label></p>
    <?php
    }
}

/* Cloud Widget */
class VKAPI_Cloud extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array(
            'classname' => 'widget_vkapi',
            'description' => __('HTML5 Cloud of tags and cats', $this->plugin_domain)
        );
        parent::WP_Widget('vkapi_tag_cloud', $name = 'VKapi: ' . __('Tags Cloud', $this->plugin_domain), $widget_ops);
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_div_id = $args['widget_id'];
        $textColour = $instance['textColor'];
        $activeLink = $instance['activeLink'];
        $shadow = $instance['shadow'];
        $width = $instance['width'];
        $height = $instance['height'];
        // tags
        ob_start();
        if ($instance['tags'] == 1) {
            wp_tag_cloud();
        }
        $tags = ob_get_clean();
        // cats
        ob_start();
        if ($instance['cats'] == 1) {
            wp_list_categories('title_li=&show_count=1&hierarchical=0&style=none');
        }
        $cats = ob_get_clean();
        // end
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="' . $vkapi_div_id . '_wrapper">';
        $path = WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/js';

        echo '</div>';
        echo "
<div id='vkapi_CloudCanvasContainer'>
    <canvas width='{$width}' height='{$height}' id='vkapi_cloud'>
        <p>http://www.kowack.info/</p>
    </canvas>
    <div id='vkapi_tags'>
        {$tags}
        {$cats}
    </div>
</div>
<script type='text/javascript' src='{$path}/jquery.tagcanvas.min.js'></script>
<script type='text/javascript'>
    if( ! jQuery('#vkapi_cloud').tagcanvas({
        reverse: true,
        // maxSpeed: .5,
        initial: [0.3,-0.3],
        minSpeed: .05,
        textColour: '{$textColour}',
        textFont: null,
        outlineColour: '{$activeLink}',
        pulsateTo: .9,
        wheelZoom: false,
        shadow: '{$shadow}',
        depth: 1.1,
        minBrightness: .5,
        weight: true,
        weightMode: 'colour',
        zoom: .888,
        weightSize: 3
    }, 'vkapi_tags')) {
        jQuery('#vkapi_CloudCanvasContainer').hide();
    }
 </script>
        ";
        echo $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        if ($old_instance['tags'] == 0 && $old_instance['cats'] == 0) {
            $new_instance['tags'] = 1;
        }

        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array)$instance,
            array(
                'title' => '',
                'width' => '200',
                'height' => '300',
                'textColor' => '#0066cc',
                'activeLink' => '#743399',
                'shadow' => '#666',
                'tags' => '1',
                'cats' => '1',
            )
        );

        $title = esc_attr($instance['title']);
        $width = esc_attr($instance['width']);
        $height = esc_attr($instance['height']);
        $textColor = esc_attr($instance['textColor']);
        $activeLink = esc_attr($instance['activeLink']);
        $shadow = esc_attr($instance['shadow']);
        $tags = esc_attr($instance['tags']);
        $cats = esc_attr($instance['cats']);

        ?>
    <p>
        <label for="<?php echo $this->get_field_id('title'); ?>">
            <?php _e('Title:'); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>"
               type="text"
               value="<?php echo $title; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('width'); ?>">
            <?php _e('Width:', $this->plugin_domain); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('width'); ?>"
               name="<?php echo $this->get_field_name('width'); ?>"
               type="text"
               value="<?php echo $width; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('height'); ?>">
            <?php _e('Height:', $this->plugin_domain); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('height'); ?>"
               name="<?php echo $this->get_field_name('height'); ?>"
               type="text"
               value="<?php echo $height; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('textColor'); ?>">
            <?php _e('Color of text:', $this->plugin_domain); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('textColor'); ?>"
               name="<?php echo $this->get_field_name('textColor'); ?>"
               type="text"
               value="<?php echo $textColor; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('activeLink'); ?>">
            <?php _e('Color of active link:', $this->plugin_domain); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('activeLink'); ?>"
               name="<?php echo $this->get_field_name('activeLink'); ?>"
               type="text"
               value="<?php echo $activeLink; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('shadow'); ?>">
            <?php _e('Color of shadow:', $this->plugin_domain); ?>
        </label>
        <input class="widefat"
               id="<?php echo $this->get_field_id('shadow'); ?>"
               name="<?php echo $this->get_field_name('shadow'); ?>"
               type="text"
               value="<?php echo $shadow; ?>"/>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('tags'); ?>">
            <?php _e('Show tags:', $this->plugin_domain); ?>
        </label>
        <select name="<?php echo $this->get_field_name('tags'); ?>"
                id="<?php echo $this->get_field_id('tags'); ?>"
                class="widefat">
            <option value="1"<?php selected($instance['tags'], '1'); ?>>
                <?php _e('Show', $this->plugin_domain); ?>
            </option>
            <option value="0"<?php selected($instance['tags'], '0'); ?>>
                <?php _e('Dont show', $this->plugin_domain); ?>
            </option>
        </select>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('cats'); ?>">
            <?php _e('Show categories:', $this->plugin_domain); ?>
        </label>
        <select name="<?php echo $this->get_field_name('cats'); ?>"
                id="<?php echo $this->get_field_id('cats'); ?>"
                class="widefat">
            <option value="1"<?php selected($instance['cats'], '1'); ?>>
                <?php _e('Show', $this->plugin_domain); ?>
            </option>
            <option value="0"<?php selected($instance['cats'], '0'); ?>>
                <?php _e('Dont show', $this->plugin_domain); ?>
            </option>
        </select>
    </p>
    <?php
    }
}

/* Facebook LikeBox Widget */
class FBAPI_LikeBox extends WP_Widget
{

    var $plugin_domain = 'vkapi';

    function __construct()
    {
        load_plugin_textdomain($this->plugin_domain, false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $widget_ops = array(
            'classname' => 'widget_vkapi',
            'description' => __('Information about Facebook group', $this->plugin_domain)
        );
        parent::WP_Widget('fbapi_recommend', $name = __('FBapi: Community Users', $this->plugin_domain), $widget_ops);
    }

    function widget($args, $instance)
    {
        extract($args);
        $vkapi_divid = $args['widget_id'];
        /** @var $before_widget string */
        /** @var $before_title string */
        /** @var $after_title string */
        /** @var $after_widget string */
        echo $before_widget . $before_title . $instance['title'] . $after_title;
        echo '<div id="' . $vkapi_divid . '_wrapper">';
        echo '
			<div
				style="background:white"
				class="fb-like-box"
				data-href="' . $instance['page'] . '"
				data-width="' . $instance['width'] . '"
				data-height="' . $instance['height'] . '"
				data-show-faces="' . $instance['face'] . '"
				data-stream="' . $instance['news'] . '"
				data-header="' . $instance['header'] . '">
			</div>
		</div>';
        echo $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array)$instance,
            array(
                'title' => '',
                'width' => '',
                'height' => '',
                'face' => 'true',
                'news' => 'false',
                'header' => 'true',
                'page' => 'https://www.facebook.com/thewordpress'
            )
        );

        ?>
    <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['title']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('page'); ?>"><?php _e('Facebook Page URL:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('page'); ?>"
               name="<?php echo $this->get_field_name('page'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['page']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Width:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('width'); ?>"
               name="<?php echo $this->get_field_name('width'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['width']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('height'); ?>"><?php _e('Height:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('height'); ?>"
               name="<?php echo $this->get_field_name('height'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['height']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('face'); ?>"><?php _e('Show Faces:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('face'); ?>"
               name="<?php echo $this->get_field_name('face'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['face']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('news'); ?>"><?php _e('Stream:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('news'); ?>"
               name="<?php echo $this->get_field_name('news'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['news']); ?>"/>
    </label></p>

    <p><label for="<?php echo $this->get_field_id('header'); ?>"><?php _e('Header:'); ?>
        <input class="widefat"
               id="<?php echo $this->get_field_id('header'); ?>"
               name="<?php echo $this->get_field_name('header'); ?>"
               type="text"
               value="<?php echo esc_attr($instance['header']); ?>"/>
    </label></p>
    <?php
    }
}