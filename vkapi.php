<?php
/*
Plugin Name: VKontakte API
Plugin URI: http://www.kowack.info/projects/vk_api
Description: Add api functions from vkontakte.ru\vk.com in your own blog. <strong><a href="options-general.php?page=vkapi_settings">Settings!</a></strong>
Version: 2.0
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
if (!class_exists('VK_api')) :

class VK_api {

	static $plugin_domain = 'vkapi';
	static $plugin_url;
	static $vkapi_page_menu;
	static $vkapi_page_settings;
	static $vkapi_page_comments;

	function __construct() {

		if( !defined('DB_NAME') )
			die('Error: Plugin does not support standalone calls, damned hacker.');

		self::$plugin_url = trailingslashit( WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)) );

		self::load_domain();

		register_activation_hook( __FILE__, array( 'VK_api', 'install' ) );
		register_deactivation_hook( __FILE__, array( 'VK_api', 'pause' ) );
		register_uninstall_hook( __FILE__, array( 'VK_api', 'deinstall' ) );
		add_action( 'admin_menu', array( &$this, 'create_menu' ), 1 ); # menu
		add_action( 'wp_print_scripts', array( &$this, 'add_head' ) ); # head
		add_action( 'init', array( &$this, 'widget_init' ) ); # widget
		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widgets' ) ); # main dashboard
		add_action( 'admin_init', array( &$this, 'add_css' ) ); # admin css register
		//add_action( 'wp_print_styles', array( &$this, 'add_css_admin' ) ); # admin css enqueue
		add_action(	'save_post', array( &$this, 'save_postdata' ) ); # check meta_box
		add_action(	'do_meta_boxes', array( &$this, 'add_custom_box' ), 1, 2); # add meta_box
		$option = get_option( 'vkapi_login' );
		if ( $option == 'true' ) {
			add_action( 'profile_personal_options', array( &$this, 'vkapi_personal_options' ) ); # profile echo
			add_action( 'admin_footer', array( &$this, 'add_profile_js' ), 88 ); # profile js
			add_action( 'login_form', array( &$this, 'add_login_form' ) ); # login
			add_action( 'register_form', array( &$this, 'add_login_form' ) ); # register
			add_action( 'wp_ajax_update_vkapi_user_meta', array( &$this, 'update_vkapi_user_meta' ) ); # update_vkapi_user_meta
		};
		if ( !is_admin() ) {
			$option = get_option( 'vkapi_show_share' );
			if ( $option == 'true' ) {
				wp_register_script ( 'share', 'http://vk.com/js/api/share.js' );
				wp_enqueue_script ( 'share' );
			};
			$option = get_option( 'gpapi_show_like' );
			if ( $option == 'true' ) {
				wp_register_script ( 'plusone', 'https://apis.google.com/js/plusone.js' );
				wp_enqueue_script ( 'plusone' );
			};
			$option = get_option( 'fbapi_show_like' );
			if ( $option == 'true' || get_option( 'fbapi_show_comm' ) == 'true' ) {
				add_action ( 'wp_footer', array( &$this, 'add_footer_fb' ) );
			};
			$option = get_option( 'tweet_show_share' );
			if ( $option == 'true' ) {
				add_action ( 'wp_footer', array( &$this, 'add_footer_tw' ) );
			};
			$option = get_option( 'mrc_show_share' );
			if ( $option == 'true' ) {
				add_action ( 'wp_footer', array( &$this, 'add_footer_mrc' ) );
			};
			$option = get_option( 'ya_show_share' );
			if ( $option == 'true' ) {
				add_action ( 'wp_footer', array( &$this, 'add_footer_ya' ) );
			};
		};
		add_action( 'admin_bar_menu', array( &$this, 'user_links' ) ); # admin bar
		add_action(	'wp_head', array( &$this, 'add_after_body' ), 1, 2); # add before body
		add_filter( 'the_content', array( &$this, 'add_buttons' ), 88 ); # buttons
		add_filter( 'contextual_help', array( &$this, 'vkapi_contextual_help' ), 1, 3 ); # help
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'own_actions_links' ) ); # plugin links
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_meta' ), 1, 2); # plugin meta
		add_action( 'edit_post_link',  array( &$this, 'add_crosspost' ), 1, 88); # crosspost
		wp_enqueue_script ( 'jquery' );
		wp_register_script ( 'vkapi_callback', self::$plugin_url . 'js/callback.js');
		wp_register_script ( 'userapi', 'http://userapi.com/js/api/openapi.js' );

		if ( !is_admin() ) wp_enqueue_script ( 'vkapi_callback' );

		function close_wp ( $file ) {
			global $post;
			if ( !( is_singular() && ( have_comments() || comments_open() ) ) ) {
				return;
			}
			return dirname(__FILE__) . '/close-wp.php';
		}

		$vkapi_close_wp = get_option( 'vkapi_close_wp' );
		if ( $vkapi_close_wp ) {
			add_filter( 'comments_template', 'close_wp' ); # vkapi comments only
			add_filter( 'get_comments_number', array( &$this, 'do_empty'), 1 ); # recount
		} else {
			add_action( 'comments_template', array( &$this, 'add_tabs'), 88 ); # add comments
			add_filter( 'get_comments_number', array( &$this, 'do_non_empty'), 1 ); # recount
		};

		$logo_e = get_option( 'vkapi_some_logo_e' );
		if ( $logo_e ) add_action( 'login_head', array(&$this, 'change_login_logo') );

		$autosave_d = get_option( 'vkapi_some_autosave_d' );
		if ( $autosave_d ) add_action( 'wp_print_scripts', array(&$this, 'disable_autosave') );

		$vkapi_some_revision_d = get_option( 'vkapi_some_revision_d' );
		if ( $vkapi_some_revision_d ) {
			define ( 'WP_POST_REVISIONS', 0 );
			remove_action ( 'pre_post_update', 'wp_save_post_revision' );
		}

		$appId = get_option( 'vkapi_appId' );
		if ( empty ( $appId{0} ) ) {
			add_action ( 'admin_notices',
				create_function( '',
				"echo '<div class=\"error\"><p>".sprintf(__('VKontakte API Plugin needs <a href="%s">configuration</a>.', self::$plugin_domain), admin_url('admin.php?page=vkapi_settings'))."</p></div>';" ) );
		}
	}

	function disable_autosave() {
		wp_deregister_script( 'autosave' );
	}

	function pause() {
		unregister_widget( 'VKAPI_Community' );
		unregister_widget( 'VKAPI_Recommend' );
		unregister_widget( 'VKAPI_Login' );
	}

	function install(){
		// wp_load_alloptions()
		// init platform
		add_option( 'vkapi_appid', '' );
		add_option( 'vkapi_api_secret', '' );
		add_option( 'fbapi_appid', '' );
		// comments
		add_option( 'vkapi_comm_width', '600' );
		add_option( 'vkapi_comm_limit', '15' );
		add_option( 'vkapi_comm_graffiti', '1' );
		add_option( 'vkapi_comm_photo', '1' );
		add_option( 'vkapi_comm_audio', '1' );
		add_option( 'vkapi_comm_video', '1' );
		add_option( 'vkapi_comm_link', '1' );
		add_option( 'vkapi_comm_autoPublish', '1' );
		add_option( 'vkapi_comm_height', '0' );
		add_option( 'vkapi_comm_show', '0' );
		// button align
		add_option( 'vkapi_align', 'left' );
		add_option( 'vkapi_like_bottom', '1' );
		// vk like
		add_option( 'vkapi_like_type', 'full' );
		add_option( 'vkapi_like_verb', '0' );
		// vk share
		add_option( 'vkapi_share_type', 'round' );
		add_option( 'vkapi_share_text', 'Сохранить' );
		// facebook
		add_option( 'fbapi_admin_id', '' );
		// show ?
		add_option( 'vkapi_show_comm', 'true' );
		add_option( 'vkapi_show_like', 'true' );
		add_option( 'vkapi_show_share', 'false' );
		add_option( 'fbapi_show_like', 'false' );
		add_option( 'fbapi_show_comm', 'false' );
		add_option( 'gpapi_show_like', 'false' );
		add_option( 'tweet_show_share', 'false' );
		add_option( 'mrc_show_share', 'false' );
		add_option( 'ya_show_share', 'false' );
		// over
		add_option( 'vkapi_some_logo_e', '1' );
		add_option( 'vkapi_some_logo', self::$plugin_url.'images/wordpress-logo.jpg' );
		add_option( 'vkapi_some_desktop', '1' );
		add_option( 'vkapi_some_autosave_d', '1' );
		add_option( 'vkapi_some_revision_d', '1' );
		add_option( 'vkapi_close_wp', '0' );
		add_option( 'vkapi_login', '1' );
		// categories
		add_option( 'vkapi_like_cat', '0' );
		add_option( 'vkapi_share_cat', '0' );
		add_option( 'fbapi_like_cat', '0' );
		add_option( 'gpapi_like_cat', '0' );
		add_option( 'tweet_share_cat', '0' );
		add_option( 'mrc_share_cat', '0' );
		add_option( 'ya_share_cat', '0' );
		// tweet
		add_option( 'tweet_account', '' );
	}

	function deinstall() {
		delete_option( 'vkapi_appid' );
		delete_option( 'vkapi_api_secret' );
		delete_option( 'vkapi_comm_width' );
		delete_option( 'vkapi_comm_limit' );
		delete_option( 'vkapi_comm_graffiti' );
		delete_option( 'vkapi_comm_photo' );
		delete_option( 'vkapi_comm_audio' );
		delete_option( 'vkapi_comm_video' );
		delete_option( 'vkapi_comm_link' );
		delete_option( 'vkapi_comm_autoPublish' );
		delete_option( 'vkapi_comm_height' );
		delete_option( 'vkapi_comm_show' );
		delete_option( 'vkapi_like_type' );
		delete_option( 'vkapi_like_verb' );
		delete_option( 'vkapi_like_cat' );
		delete_option( 'vkapi_like_bottom' );
		delete_option( 'vkapi_share_cat' );
		delete_option( 'vkapi_share_type' );
		delete_option( 'vkapi_share_text' );
		delete_option( 'vkapi_align' );
		delete_option( 'vkapi_show_comm' );
		delete_option( 'vkapi_show_like' );
		delete_option( 'fbapi_show_comm' );
		delete_option( 'vkapi_show_share' );
		delete_option( 'vkapi_some_logo_e' );
		delete_option( 'vkapi_some_logo' );
		delete_option( 'vkapi_some_desktop' );
		delete_option( 'vkapi_some_autosave_d' );
		delete_option( 'vkapi_some_revision_d' );
		delete_option( 'vkapi_close_wp' );
		delete_option( 'vkapi_login' );
		delete_option( 'fbapi_admin_id' );
		delete_option( 'tweet_show_share' );
		delete_option( 'tweet_account' );
		delete_option( 'tweet_share_cat' );
		delete_option( 'gpapi_show_like' );
		delete_option( 'fbapi_like_cat' );
		delete_option( 'fbapi_show_like' );
		delete_option( 'gpapi_like_cat' );
		delete_option( 'mrc_show_share' );
		delete_option( 'mrc_share_cat' );
		delete_option( 'ya_show_share' );
		delete_option( 'ya_share_cat' );
	}

	function settings_page() {
		require( 'vkapi-options.php' );
	}

	function comments_page() {
		echo '
			<div class="wrap">
				<div class="icon32"><img src="'.self::$plugin_url.'images/set.png" /></div>
				<h2 style="margin: 0px 0px 20px 50px">'.__( 'VKontakte API Plugin - Comments', self::$plugin_domain).'</h2>
				<div id="vkapi_comments"></div>
				<script type="text/javascript">
				VK.Widgets.CommentsBrowse(\'vkapi_comments\', { mini: 1});
				</script>
			</div>
			';
	}

	function add_head() {
		// VK API
		if ( !is_admin() || defined('IS_PROFILE_PAGE') ) {
			$id = get_option( 'vkapi_appId' );
			echo '<meta property="vk:app_id" content="'.$id.'" />'."\n";
			wp_enqueue_script ( 'userapi' );
		}
		// FB API
		$temp = get_option( 'fbapi_show_comm' );
		if ( !is_admin() && $temp == 'true' ) {
			$id = get_option( 'fbapi_admin_id' );
			echo '<meta property="fb:admins" content="'.$id.'"/>'."\n";
		}
	}

	function create_menu() {
		self::$vkapi_page_menu = add_menu_page(
				__('VKontakte API', self::$plugin_domain),
				__('VKontakte API', self::$plugin_domain),
				'manage_options',
				'vkapi_settings',
				array( &$this, 'settings_page'),
				'http://vk.com/favicon.ico'
			);
		self::$vkapi_page_settings = add_submenu_page(
				'vkapi_settings',
				__( 'VKontakte API Plugin - Settings', self::$plugin_domain),
				__('Settings', self::$plugin_domain),
				'manage_options',
				'vkapi_settings',
				array( &$this, 'settings_page')
			);
		self::$vkapi_page_comments = add_submenu_page(
				'vkapi_settings',
				__( 'VKontakte API Plugin - Comments', self::$plugin_domain),
				__('Last Comments', self::$plugin_domain),
				'manage_options',
				'vkapi_comments',
				array( &$this, 'comments_page')
			);
		add_action( 'admin_print_styles-' . self::$vkapi_page_menu, array( &$this, 'add_css_admin' ) );
		add_action( 'admin_print_styles-' . self::$vkapi_page_settings, array( &$this, 'add_css_admin' ) );
		add_action( 'admin_print_styles-' . self::$vkapi_page_comments, array( &$this, 'add_css_admin_comm' ) );
		add_action ( 'admin_init', array( &$this, 'register_settings' ) );
	}

	function register_settings () {
		register_setting( 'vkapi-settings-group', 'vkapi_appid' );
		register_setting( 'vkapi-settings-group', 'fbapi_admin_id' );
		register_setting( 'vkapi-settings-group', 'vkapi_api_secret' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_width' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_limit' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_graffiti' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_photo' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_audio' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_video' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_link' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_autoPublish' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_height' );
		register_setting( 'vkapi-settings-group', 'vkapi_comm_show' );
		register_setting( 'vkapi-settings-group', 'vkapi_like_type' );
		register_setting( 'vkapi-settings-group', 'vkapi_like_verb' );
		register_setting( 'vkapi-settings-group', 'vkapi_like_cat' );
		register_setting( 'vkapi-settings-group', 'vkapi_like_bottom' );
		register_setting( 'vkapi-settings-group', 'vkapi_share_cat' );
		register_setting( 'vkapi-settings-group', 'vkapi_share_type' );
		register_setting( 'vkapi-settings-group', 'vkapi_share_text' );
		register_setting( 'vkapi-settings-group', 'vkapi_align' );
		register_setting( 'vkapi-settings-group', 'vkapi_show_comm' );
		register_setting( 'vkapi-settings-group', 'vkapi_show_like' );
		register_setting( 'vkapi-settings-group', 'fbapi_show_comm' );
		register_setting( 'vkapi-settings-group', 'vkapi_show_share' );
		register_setting( 'vkapi-settings-group', 'vkapi_some_logo_e' );
		register_setting( 'vkapi-settings-group', 'vkapi_some_logo' );
		register_setting( 'vkapi-settings-group', 'vkapi_some_desktop' );
		register_setting( 'vkapi-settings-group', 'vkapi_some_autosave_d' );
		register_setting( 'vkapi-settings-group', 'vkapi_some_revision_d' );
		register_setting( 'vkapi-settings-group', 'vkapi_close_wp' );
		register_setting( 'vkapi-settings-group', 'vkapi_login' );
		register_setting( 'vkapi-settings-group', 'tweet_show_share' );
		register_setting( 'vkapi-settings-group', 'tweet_account' );
		register_setting( 'vkapi-settings-group', 'tweet_share_cat' );
		register_setting( 'vkapi-settings-group', 'gpapi_show_like' );
		register_setting( 'vkapi-settings-group', 'fbapi_like_cat' );
		register_setting( 'vkapi-settings-group', 'fbapi_show_like' );
		register_setting( 'vkapi-settings-group', 'fbapi_appid' );
		register_setting( 'vkapi-settings-group', 'gpapi_like_cat' );
		register_setting( 'vkapi-settings-group', 'mrc_show_share' );
		register_setting( 'vkapi-settings-group', 'mrc_share_cat' );
		register_setting( 'vkapi-settings-group', 'ya_show_share' );
		register_setting( 'vkapi-settings-group', 'ya_share_cat' );
	}

	function add_css () {
		wp_register_style ( 'vkapi_admin', plugins_url('css/admin.css', __FILE__) );
		wp_register_script ( 'userapi', 'http://userapi.com/js/api/openapi.js' );
	}

	function add_css_admin () {
		wp_enqueue_style ( 'vkapi_admin' );
		$fbapi_appid = get_option( 'fbapi_appid' );
		echo '
			<div id="fb-root"></div>
			<script>(function(d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) return;
				js = d.createElement(s); js.id = id;
				js.src = "//connect.facebook.net/ru_RU/all.js#xfbml=1&appId='.$fbapi_appid.'";
				fjs.parentNode.insertBefore(js, fjs);
			}(document, \'script\', \'facebook-jssdk\'));</script>
		';
	}

	function add_css_admin_comm () {
		$appId = get_option( 'vkapi_appId' );
		echo "<meta property='vk:app_id' content='$appId' />\n";
		wp_enqueue_script ( 'userapi' );
	}

	function vkapi_contextual_help( $contextual_help, $screen_id, $screen ) {
		if ( $screen_id == self::$vkapi_page_menu || $screen_id == self::$vkapi_page_settings ) {
			/* main */
			$help = '<p>Добавляет функционал API сайта vkontakte.ru(vk.com) на ваш блог. Комментарии, кнопки, виджеты...</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_main',
				'title'	=> __( 'Main', self::$plugin_domain ),
				'content'	=> $help
				) );
			/* comments */
			$help = '<p>Появляется возможность переключения между комментариями WordPress-a и Вконтакта.<br />
				"Прячутся" и "показываются" блоки <b>div</b> с <b>id=comments</b> ( блок комментариев ) и <b>id=respond</b> ( блок формы ответа ) ( <i>по спецификации Wordpress-a</i> )<br />
				В наличии вся нужная настройка, а также возможность вовсе оставить лишь комментарии Вконтакта.</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_comments',
				'title'	=> __( 'Comments', self::$plugin_domain ),
				'content'	=> $help
				) );
			/* like button */
			$help = '<p>Собственно кнопка с настройкой позиции и вида.<br />
				По результатам этой кнопки есть <a href="'.admin_url("widgets.php").'">виждет</a> "Популярное" со статистикой.<br /><br />
				Доступен шорткод [vk_like], по умолчанию берётся айдишник страницы.
				Но также можно указать [vk_like id="123456"] для уникальности кнопки.</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_like',
				'title'	=> __( 'Like button', self::$plugin_domain ),
				'content'	=> $help
				) );
			/* decor */
			$help = '<p>В браузерах Google Chrome и Safari ( движок WebKit ) есть возможность показывать всплывающее сообщение прямо на рабочем столе<br />
				А значит вы в любой момент можете узнать о новом сообщении.</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_decor',
				'title'	=> __( 'Decor', self::$plugin_domain ),
				'content'	=> $help
				) );
			/* other */
			$help = '<p><strong>Disable Autosave Post Script</strong> - выключает астосохранение при редактировании/добавлении новой записи(поста).<br />
				Это полезно тем, что теперь не будет тучи бесполезных черновиков(ведь зачем заполнять ими нашу базу данных?)<br />
				<strong>Disable Revision Post Save</strong> - устанавливает количество выше упомянутых черновиков в ноль.<br /></p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_other',
				'title'	=> __( 'No Plugin Options', self::$plugin_domain ),
				'content'	=> $help
				) );
			/* help */
			$help = '<p>Все вопросики и пожелания <strong><a href="http://www.kowack.info/projects/vk_api/" title="VKontakte API Home">сюдАтачки</a></strong> или <strong><a href="http://vk.com/vk_wp" title="VKontakte API Club">тУтачки</a></strong>.</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_help',
				'title'	=> __( 'Help', self::$plugin_domain ),
				'content'	=> $help
				) );
		};
		return '';
	}

	function add_tabs_button_start() {
		global $post;
		$vkapi_url = get_bloginfo('wpurl');
		echo
			'<!--noindex--><table id="vkapi_wrapper" vkapi_notify="'.$post->ID.'" vkapi_url="'.$vkapi_url.'"><br />
			<td style="font-weight:800">'
			.__('Comments:', self::$plugin_domain).
			'</td>';
	}

	function add_tabs_button_vk() {
		global $post;
		$vkapi_button = __('VKontakte', self::$plugin_domain);
		$vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', TRUE);
		if ( !$vkapi_comm ) $vkapi_comm = 0;
		$vkapi_comm_show = ' ('.$vkapi_comm.')';
		echo '
			<td>
			<button id="submit" class="vk_recount" onclick="showVK()" >'.$vkapi_button.$vkapi_comm_show.'</button>
			</td>
			';
	}

	function add_tabs_button_fb() {
		echo '
			<td>
			<button id="submit" onclick="showFB()" >'.__('Facebook', self::$plugin_domain).' (<fb:comments-count href='.get_permalink().'></fb:comments-count>)</button>
			</td>
			';
	}

	function add_tabs_button_wp() {
		global $post;
		$vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', TRUE);
		$fbapi_comm = get_post_meta($post->ID, 'fbapi_comm', TRUE);
		$comm_wp = get_comments_number() - $vkapi_comm - $fbapi_comm;
		echo '
			<td>
			<button id="submit" onclick="showWP()">'.__('Site', self::$plugin_domain).' ('.$comm_wp.') </button>
			</td>
			';
	}

	function add_tabs() {
		if ( comments_open() ) {
			// hook start buttons
			add_action ( 'add_tabs_button_action', array( &$this, 'add_tabs_button_start' ) );
			// VK
				global $post;
				$vkapi_get_comm = get_post_meta($post->ID, vkapi_comments, true);
				$show_comm = get_option( 'vkapi_show_comm' );
			if ( $show_comm == 'true' && ( $vkapi_get_comm == '1' || $vkapi_get_comm === '' ) ) {
				//self::add_vk_comments();
				add_action ( 'add_tabs_button_action', array( &$this, 'add_tabs_button_vk' ) );
				add_action ( 'add_tabs_comment_action', array( &$this, 'add_vk_comments' ) );
			};
			// FB
				$show_comm = get_option( 'fbapi_show_comm' );
			if ( $show_comm == 'true' ) {
				//self::add_fb_comments();
				add_action ( 'add_tabs_button_action', array( &$this, 'add_tabs_button_fb' ) );
				add_action ( 'add_tabs_comment_action', array( &$this, 'add_fb_comments' ) );
			};
			// hook end buttons
			add_action ( 'add_tabs_button_action', array( &$this, 'add_tabs_button_wp' ) );
			add_action ( 'add_tabs_button_action', create_function( '', 'echo \'</table><br /><br /><!--/noindex-->\';') );
			do_action( 'add_tabs_button_action' );
			do_action( 'add_tabs_comment_action' );
		};
	}

	function add_vk_comments() {
		global $post;
		$vkapi_button = __('VKontakte', self::$plugin_domain);
		$vkapi_some_desktop = get_option( 'vkapi_some_desktop' );
		$attach = array();
		if ( get_option( 'vkapi_comm_graffiti' ) ) $attach[] = 'graffiti';
		if ( get_option( 'vkapi_comm_photo' ) ) $attach[] = 'photo';
		if ( get_option( 'vkapi_comm_audio' ) ) $attach[] = 'audio';
		if ( get_option( 'vkapi_comm_video' ) ) $attach[] = 'video';
		if ( get_option( 'vkapi_comm_link' ) ) $attach[] = 'link';
		if ( empty($attach) ) $attach = 'false'; else $attach = '"' . implode ( ',' , $attach ) . '"';
		$autoPublish = get_option( 'vkapi_comm_autoPublish' );
		if ( ( empty( $autoPublish{0} ) ) ) $autoPublish = '0'; else $autoPublish = '1';

		if ( $vkapi_some_desktop ) {
			echo '
			<script type="text/javascript">
			function vkapi_checkPermission() {
				if(window.webkitNotifications.checkPermission()==0){
					window.webkitNotifications.createNotification(
						"http://vk.com/images/lnkinner32.gif", "Успех",
						"Сообщения разрешены").show();
					clearInterval(vkapi_interval);
				}
			};
			if(window.webkitNotifications.checkPermission()>0){
				var vkapi_interval = setInterval(vkapi_checkPermission,500);
			};
			function vkapi_requestPermission(){
				window.webkitNotifications.requestPermission();
				jQuery("button.vkapi_remove").remove();
			};
			function onChangeRecalc(num,last_comment,data,hash){
				jQuery("button.vk_recount").html(\''.$vkapi_button.' (\'+num+\')\');
			};
			function onChange(num,last_comment,data,hash){
				if (window.webkitNotifications.checkPermission() == 0) {
						last_comment = html_entity_decode(last_comment);
					Time = new Date();
					Hour = Time.getHours();
					Min = Time.getMinutes();
					Sec = Time.getSeconds();
					var notification = window.webkitNotifications.createNotification(
					"http://vk.com/images/lnkinner32.gif", "Время "+Hour+":"+Min+":"+Sec,
					last_comment);
					notification.show();
					document.getElementById(\'vkapi_sound\').play();
					setTimeout(function(){notification.cancel();}, \'10000\');
				} else {
					jQuery("#vkapi").append(\'<button id="submit" class="vkapi_remove" onclick="vkapi_requestPermission()">Разрешить всплывающие сообщения</button>\');
				};
			};
			</script>';
		} else {
			echo 'function onChangeRecalc(num,last_comment,data,hash){
				jQuery("button.vk_recount").html(\''.$vkapi_button.' (\'+num+\')\');
				};
				</script>';
		};
			$vkapi_comm = get_post_meta($post->ID, 'vkapi_comm', TRUE);
			$comm_wp = get_comments_number() - $vkapi_comm;
			if ( $vkapi_comm ) $vkapi_comm_show = ' ('.$vkapi_comm.')';
			if ( is_singular() ) $url = site_url().$_SERVER['REQUEST_URI']; else $url = get_permalink();
			echo '
			<div id="vkapi" onclick="showNotification()"></div>
			<script type="text/javascript">
				VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$attach.', autoPublish: '.$autoPublish.', height: '.get_option('vkapi_comm_height').', mini:1, pageUrl: "'.$url.'"},'.$post->ID.');
			</script>';
			add_action ( 'wp_footer', array( &$this, 'add_footer' ) );
	}

	function add_fb_comments() {
		echo '
			<div style="background:white" class="fb-comments" data-href="'.get_permalink().'" data-num-posts="'.get_option('vkapi_comm_limit').'" data-width="'.get_option('vkapi_comm_width').'" colorscheme="light"></div>
			';
	}

	function add_footer () {
		if ( get_option( 'vkapi_comm_show' ) == 1 ) {
			echo '<script type="text/javascript">jQuery(function(){showVK(0,0)});</script>';
		} else if ( get_option( 'fbapi_show_comm' ) == 1 ) {
			echo '<script type="text/javascript">jQuery(function(){showFB(0,0)});</script>';
		} else {
			echo '<script type="text/javascript">jQuery(function(){showWP(0,0)});</script>';
		}
		echo '<audio id="vkapi_sound" preload="auto" style="display: none">
				<source src="http://vk.com/mp3/bb2.mp3">
			</audio>';
	}

########## start social buttons
	function add_buttons ( $args ) {
		global $post;
		$vkapi_get_butt = get_post_meta($post->ID, vkapi_buttons, true);
		if ( !is_feed() && !( in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) && ( $vkapi_get_butt == '1' || $vkapi_get_butt === '' ) ) {
			add_action ( 'add_social_button_action', create_function( '', 'echo \'<!--noindex--><table class="nostyle" style="margin:auto">\';'), 1 );
			// gp +
			if ( get_option( 'gpapi_show_like' ) == 'true' ) {
				$in_cat = get_option( 'gpapi_like_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'gpapi_button_like' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'gpapi_button_like' ), 5 );
				};
			};
			// fb like
			if ( get_option( 'fbapi_show_like' ) == 'true' ) {
				$in_cat = get_option( 'fbapi_like_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'fbapi_button_like' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'fbapi_button_like' ), 5 );
				};
			};
			// tweet me
			if ( get_option( 'tweet_show_share' ) == 'true' ) {
				$in_cat = get_option( 'tweet_share_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'tweet_button_share' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'tweet_button_share' ), 5 );
				};
			};
			// ya share
			if ( get_option( 'ya_show_share' ) == 'true' ) {
				$in_cat = get_option( 'ya_share_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'ya_button_share' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'ya_button_share' ), 5 );
				};
			};
			// vk share
			if ( get_option( 'vkapi_show_share' ) == 'true' ) {
				$in_cat = get_option( 'vkapi_share_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'vkapi_button_share' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'vkapi_button_share' ), 5 );
				};
			};
			// vk like
			if ( get_option( 'vkapi_show_like' ) == 'true' ) {
				$in_cat = get_option( 'vkapi_like_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'vkapi_button_like' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'vkapi_button_like' ), 5 );
				};
			};
			// mrc share
			if ( get_option( 'mrc_show_share' ) == 'true' ) {
				$in_cat = get_option( 'mrc_share_cat' );
				if ( $in_cat ) add_action ( 'add_social_button_action', array( &$this, 'mrc_button_share' ), 5 );
				else if ( !is_home() && !is_category() && !is_archive() ) {
					add_action ( 'add_social_button_action', array( &$this, 'mrc_button_share' ), 5 );
				};
			};
			// shake
			add_action ( 'add_social_button_action', create_function( '', 'echo \'</table><!--/noindex-->\';'), 88 );
			ob_start();
			do_action( 'add_social_button_action' );
?>
<style>
	table.nostyle {
		border:0;
		padding:0;
	}

	.nostyle tbody {
		vertical-align:top;
	}

	.nostyle td {
		border:0;
		margin:0;
		padding:0 20px 0 0;
	}
</style>
<?php
			$echo = ob_get_clean();

		}
		$valign = get_option( 'vkapi_like_bottom' );
		if ( $valign ) {
			$args .= $echo;
			return $args;
		} else {
			$echo .= $args;
			return $echo;
		}
	}

	function vkapi_button_like ( $args ) {
				global $post;
				$postid = $post->ID;
				echo "<td><div id=\"vkapi_like_$postid\"></div></td>";
				$type = get_option( 'vkapi_like_type' );
				$verb = get_option( 'vkapi_like_verb' );
				$vkapi_title = addslashes ( do_shortcode($post->post_title) );
				$vkapi_descr = str_replace( "\r\n", "<br />", do_shortcode($post->post_excerpt) );
				$vkapi_descr = strip_tags( $vkapi_descr );
				$vkapi_descr = addslashes ( $vkapi_descr );
				$vkapi_descr = substr( $vkapi_descr, 0, 139 );
				$vkapi_url = get_permalink();
				$vkapi_image = self::first_postimage($postid);
				$vkapi_text = str_replace( "\r\n", "<br />", do_shortcode($post->post_content) );
				$vkapi_text = strip_tags( $vkapi_text );
				$vkapi_text = addslashes ( $vkapi_text );
				$vkapi_text = substr( $vkapi_text, 0, 139 );
				// pageImage
				echo "
						<script type=\"text/javascript\">
							<!--
								VK.Widgets.Like('vkapi_like_$postid', {
								width: 1,
								height: 20,
								type: '$type',
								verb: '$verb',
								pageTitle: '$vkapi_title',
								pageDescription: '$vkapi_descr',
								pageUrl: '$vkapi_url',
								pageImage: '$vkapi_image',
								text: '$vkapi_text'
							}, $postid);
							-->
						</script>";
	}

	function vkapi_button_share ( $args ) {
				global $post;
				$postid = $post->ID;
				echo "<td><div id=\"vkapi_share_$postid\"></div></td>";
				$vkapi_url = get_permalink();
				$vkapi_title = addslashes ( do_shortcode($post->post_title) );
				$vkapi_descr = str_replace( "\r\n", "<br />", do_shortcode($post->post_content) );
				$vkapi_descr = strip_tags( $vkapi_descr );
				$vkapi_descr = addslashes ( $vkapi_descr );
				$vkapi_descr = substr( $vkapi_descr, 0, 139 );
				$vkapi_type = get_option( 'vkapi_share_type' );
				$vkapi_image = self::first_postimage($postid);
				$vkapi_text = get_option( 'vkapi_share_text' );
				$vkapi_text = addslashes ( $vkapi_text );
				echo "
							<style type=\"text/css\">
								#vkapi_share_$postid {
									padding:0px 3px 0px 0px;
								}
								#vkapi_share_$postid td,
								#vkapi_share_$postid tr {
									border:0px !important;
									padding:0px !important;
									margin:0px !important;
									vertical-align: top !important;
								}
							</style>
							<script type=\"text/javascript\">
								<!--
								jQuery(document).ready(function() {
									document.getElementById('vkapi_share_$postid').innerHTML = VK.Share.button({
										url: '$vkapi_url',
										title: '$vkapi_title',
										description: '$vkapi_descr',
										image: '$vkapi_image'
									},{
										type: '$vkapi_type',
										text: '$vkapi_text'
									});
								});
								-->
							</script>";
	}

	function fbapi_button_like ( $args ) {
		$url = get_permalink();
		echo '
			<td>
			<div
				class="fb-like"
				data-href="'.$url.'"
				data-send="false"
				data-layout="button_count"
				data-width="100"
				data-show-faces="true"
				>
			</div>
			</td>';
	}

	function gpapi_button_like ( $args ) {
		$url = get_permalink();
		echo '
			<td>
			<div
				class="g-plusone"
				data-size="medium"
				data-annotation="none"
				data-href="'.$url.'">
			</div>
			</td>';
	}

	function tweet_button_share() {
		$url = get_permalink();
		$who = get_option('tweet_account');
		echo '
			<td>
			<a
				rel="nofollow"
				href="https://twitter.com/share"
				class="twitter-share-button"
				data-url="'.$url.'"
				data-text="title"
				data-lang="ru"
				data-via="'.$who.'"
				data-dnt="true"
				data-count="none">Tweet</a>
			</td>';
	}

	function mrc_button_share() {
		$url = rawurlencode ( get_permalink() );
		echo '
			<td>
			<a
				rel="nofollow"
				target="_blank"
				class="mrc__plugin_uber_like_button"
				href="'.$url.'"
				data-mrc-config="{\'type\' : \'button\', \'caption-mm\' : \'2\', \'caption-ok\' : \'1\', \'counter\' : \'true\', \'text\' : \'true\', \'width\' : \'250px\', \'show_faces\': \'1\', \'show_text\': \'1\'}">Нравится</a>
			</td>';
	}

	function ya_button_share() {
		$url = get_permalink();
		echo '
			<td>
			<a
				rel="nofollow"
				counter="yes"
				type="icon"
				size="large"
				share_url="'.$url.'"
				name="ya-share"> </a>
			</td>';
	}
	#end social button

	function change_login_logo() {
		$logo = get_option( 'vkapi_some_logo' );
		echo '<style type="text/css">
			#login { width: 380px !important}
			.login h1 a { background:url('.$logo.') !important; width: 380px !important; height: 130px !important;}
		</style>';
	}

	function widget_init() {
		$vkapi_login = get_option( 'vkapi_login' );
		register_widget( 'VKAPI_Community' );
		register_widget( 'VKAPI_Recommend' );
		if ( $vkapi_login == 'true' ) register_widget( 'VKAPI_Login' );
		register_widget( 'VKAPI_Comments' );
		register_widget( 'VKAPI_Cloud' );
		register_widget( 'FBAPI_LikeBox' );
		do_action( 'widgets_init' );
	}

	function dashboard_widget_function() {
		echo '<script type="text/javascript" src="http://userapi.com/js/api/openapi.js"></script>
			<div id="vkapi_groups"></div>
			<script type="text/javascript">
				VK.Widgets.Group("vkapi_groups", {mode: 2, width: "auto", height: "290"}, 28197069);
			</script>';
	}

	function own_actions_links( $links ) {
		$settings_link = '&nbsp;<a href="options-general.php?page=vkapi_settings"><img src="'.self::$plugin_url.'images/set.png" width="20" />&nbsp;</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	function add_dashboard_widgets() {
		wp_add_dashboard_widget( 'vkapi_dashboard_widget', 'VKapi: Новости', array( &$this,'dashboard_widget_function') );
		global $wp_meta_boxes;
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$vkapi_widget_backup = array( 'vkapi_dashboard_widget' => $normal_dashboard['vkapi_dashboard_widget'] );
		unset( $normal_dashboard['vkapi_dashboard_widget'] );
		$sorted_dashboard = array_merge( $vkapi_widget_backup, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	function load_domain() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}


	##### start meta_box
	function save_postdata( $post_id ) {
		// check
		if ( !wp_verify_nonce( $_REQUEST['vkapi_noncename'], plugin_basename(__FILE__) ))
			return $post_id;
		if ( 'page' == $_REQUEST['post_type'] ) {
			if ( !current_user_can( 'edit_page', $post_id ))
				return $post_id;
		} else {
			if ( !current_user_can( 'edit_post', $post_id ))
				return $post_id;
		}
		// do
		update_post_meta($post_id, 'vkapi_comments', $_REQUEST['vkapi_comments']);
		update_post_meta($post_id, 'vkapi_buttons', $_REQUEST['vkapi_buttons']);
	}

	function add_custom_box($page,$context) {
		add_meta_box( 'vkapi_meta_box_comm', __('VKapi: Comments',self::$plugin_domain),array($this,'vkapi_inner_custom_box_comm'), 'post', 'advanced' );
		add_meta_box( 'vkapi_meta_box_comm', __('VKapi: Comments',self::$plugin_domain),array($this,'vkapi_inner_custom_box_comm'), 'page', 'advanced' );
		add_meta_box( 'vkapi_meta_box_butt', __('VKapi: Social buttons',self::$plugin_domain),array($this,'vkapi_inner_custom_box_butt'), 'post', 'advanced' );
		add_meta_box( 'vkapi_meta_box_butt', __('VKapi: Social buttons',self::$plugin_domain),array($this,'vkapi_inner_custom_box_butt'), 'page', 'advanced' );
	}

	function vkapi_inner_custom_box_comm() {
		global $post;
		echo '<input type="hidden" name="vkapi_noncename" id="vkapi_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
		$vkapi_comments = get_post_meta( $post->ID, 'vkapi_comments', true );
		if ( $vkapi_comments === '' ) $vkapi_comments = 1;
		echo '<input type="radio" name="vkapi_comments" value="1"';
		if ( $vkapi_comments == 1 ) echo ' checked ';
		echo'/>' . __( 'Enable', self::$plugin_domain ).'<br /><input type="radio" name="vkapi_comments" value="0"';
		if ( $vkapi_comments == 0 ) echo ' checked ';
		echo '/>' . __( 'Disable', self::$plugin_domain );
	}

	function vkapi_inner_custom_box_butt() {
		global $post;
		echo '<input type="hidden" name="vkapi_noncename" id="vkapi_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
		$vkapi_buttons = get_post_meta( $post->ID, 'vkapi_buttons', true );
		if ( $vkapi_buttons === '' ) $vkapi_buttons = 1;
		echo '<input type="radio" name="vkapi_buttons" value="1"';
		if ( $vkapi_buttons == 1 ) echo ' checked ';
		echo'/>' . __( 'Enable', self::$plugin_domain ).'<br /><input type="radio" name="vkapi_buttons" value="0"';
		if ( $vkapi_buttons == 0 ) echo ' checked ';
		echo '/>' . __( 'Disable', self::$plugin_domain );
	}
	# end meta_box

	##### start recount comments number
	function do_empty ( $args ) {
		global $post;
		$vkapi_comm = get_post_meta ( $post->ID, 'vkapi_comm', TRUE );
		return $vkapi_comm;
	}

	function do_non_empty ( $args ) {
		global $post;
		$vkapi_comm = get_post_meta ( $post->ID, 'vkapi_comm', TRUE );
		$fbapi_comm = get_post_meta ( $post->ID, 'fbapi_comm', TRUE );
		return $args+$vkapi_comm+$fbapi_comm;
	}
	# end recount comments number

	##### start profile
	function vkapi_personal_options ( $profile ) {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label><?php _e( 'VKontakte', self::$plugin_domain ); ?></label></th>
		<?php
			$conn = get_user_meta( $profile->ID, 'vkapi_uid', TRUE );
			if (empty($conn)) {
		?>
				<td>
					<div id="vkapi_login_button" style="padding:0px;border:0px" onclick="VK.Auth.login(onSignonProfile)"></div>
					<div id="vkapi_status"></div>
			<style type="text/css">
				.form-table td #vkapi_login_button td {
					padding:0px !important;
					margin:0px !important;
				}
			</style>
			<script language="javascript">
				VK.UI.button('vkapi_login_button');
			</script>
					<div style="display:none" id="vk_auth"></div>
			<script type="text/javascript">
				VK.Widgets.Auth("vk_auth", {width: "200px", onAuth: function(data) {
					alert('user '+data['uid']+' authorized');
				} });
			</script>
				</td>
			</tr>
		<?php
			} else {
		?>
				<td><p><?php _e( 'Connected User Id : ', self::$plugin_domain ); echo $conn; ?></p>
					<input type="button" class="button-primary" value="<?php _e( 'Disconnect from VKontakte', self::$plugin_domain ); ?>" onclick="vkapi_profile_update(1)" />
					<div id="vkapi_status"></div>
				</td>
			</tr>
		<?php }
		?>
		</table>
		<?php
	}

	function add_profile_js() {
		if ( defined('IS_PROFILE_PAGE') ) {
		?>
			<script type="text/javascript">
			function vkapi_profile_update ( args ) {
				var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
				var data = {
					action: 'update_vkapi_user_meta',
					vkapi_action: args
				}
				if ( args == '1' ) {
					jQuery.post(ajax_url, data, function(response) {
						if (response == 'Ok') {
							jQuery("#vkapi_status").html("<span style='color:green'>Result: ✔ "+response+"</span>");
							document.location.reload(true);
						} else {
							jQuery("#vkapi_status").html("<span style='color:red'>Result: "+response+"</span>");
						};
					});
				};
				if ( args == '0' ) {
					jQuery.post(ajax_url, data, function(response) {
						if (response == 'Ok') {
							jQuery("#vkapi_status").html("<span style='color:green'>Result: ✔ "+response+"</span>");
							document.location.reload(true);
						} else {
							jQuery("#vkapi_status").html("<span style='color:red'>Result: "+response+"</span>");
						};
					});
				};
			};

			function onSignonProfile ( response ) {
				if (response.session) {
					vkapi_profile_update (0);
				} else {
				VK.Auth.login(onSignonProfile);
				};
			};
			</script>
			<?php
		}
	}

	function authOpenAPIMember() {
		$session = array();
		$member = FALSE;
		$valid_keys = array('expire', 'mid', 'secret', 'sid', 'sig');
		$app_cookie = $_COOKIE['vk_app_'.get_option('vkapi_appid')];
		if ($app_cookie) {
			$session_data = explode ('&', $app_cookie, 10);
			foreach ($session_data as $pair) {
				list($key, $value) = explode('=', $pair, 2);
				if (empty($key) || empty($value) || !in_array($key, $valid_keys)) {
					continue;
				}
				$session[$key] = $value;
			}
			foreach ($valid_keys as $key) {
				if (!isset($session[$key])) return $member;
			}
			ksort($session);

			$sign = '';
			foreach ($session as $key => $value) {
				if ($key != 'sig') {
					$sign .= ($key.'='.$value);
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

	function update_vkapi_user_meta () {
		$user = wp_get_current_user();
		$vkapi_action = (int)($_POST['vkapi_action']);
		if ( $vkapi_action == '1' ) {
			$vkapi_result = delete_user_meta( $user->ID, 'vkapi_uid' );
			if ( $vkapi_result ) echo 'Ok';
			exit();
		}
		if ( $vkapi_action == '0' ) {
			$member = self::authOpenAPIMember();
			if ( $member == FALSE ) {
				echo 'False';
				exit();
			};
			$return = add_user_meta( $user->ID, 'vkapi_uid', $member['id'], TRUE );
			if ( $return ) {
				echo 'Ok';
			} else {
				echo 'False add meta';
			};
			exit();
		}
	}
	# end profile

	##### start login
	function add_login_form() {
		global $action;
		if ( $action == 'login' || $action == 'register' ) {
			$vkapi_url = get_bloginfo('wpurl');
			echo '
			<button style="display: none" class="vkapi_vk_widget" vkapi_url="'.$vkapi_url.'"></button>
			<div id="vkapi_status"></div>
			<div id="login_button" onclick="VK.Auth.getLoginStatus(onSignon)"></div>
			<script language="javascript">
				VK.UI.button(\'login_button\');
			</script>
			<div style="display:none" id="vk_auth"></div>
			<script type="text/javascript">
				VK.Widgets.Auth("vk_auth", {width: "200px", onAuth: function(data) {
					alert("user "+data["uid"]+" authorized");
				} });
			</script><br />';
		};
		if ( ( $action == 'login' || $action == 'register' ) && is_user_logged_in() ) {
			wp_safe_redirect ( home_url() );
			exit;
		};
	}
	# end login

	##### start bar menu
	function user_links( $wp_admin_bar ) {
		$user = wp_get_current_user();
		$vkapi_user = get_user_meta($user->ID, 'vkapi_uid', TRUE);
		if ( $vkapi_user ) {
			$wp_admin_bar->add_node( array(
					'id'     => 'vkapi-profile',
					'parent' => 'user-actions',
					'title'  => __( 'VKontakte Profile', self::$plugin_domain ),
					'href'   => "http://vk.com/id$vkapi_user",
					'meta'   => array(
						'target' => '_blank',
		)
				) );
		};
	}
	# end bar menu

	##### start plugin meta
	function plugin_meta( $links, $file ) {
		if ( $file == plugin_basename(__FILE__) ) {
			$links[] = '<a href="'.admin_url('options-general.php?page=vkapi_settings').'">'.__( 'Settings', self::$plugin_domain ).'</a>';
			$links[] = 'Code is poetry!';
		}
		return $links;
	}
	# end plugin meta

	##### start footer
	function add_footer_fb() {
		$fbapi_appid = get_option( 'fbapi_appid' );
		echo '
			<div id="fb-root"></div>
			<script>
				window.fbAsyncInit = function() {
					FB.init({
						appId      : '.$fbapi_appid.', // App ID
						status     : true, // check login status
						cookie     : true, // enable cookies to allow the server to access the session
						xfbml      : true  // parse XFBML
					});

					myFBinit();
				};

			  (function(d){
				 var js, id = \'facebook-jssdk\', ref = d.getElementsByTagName(\'script\')[0];
				 if (d.getElementById(id)) {return;}
				 js = d.createElement(\'script\'); js.id = id; js.async = true;
				 js.src = "//connect.facebook.net/ru_RU/all.js";
				 ref.parentNode.insertBefore(js, ref);
			   }(document));
			</script>
		';
	}

	function add_footer_tw() {
		?><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script><?php
	}

	function add_footer_ya() {
		?><script charset="utf-8" type="text/javascript">if (window.Ya && window.Ya.Share) {Ya.Share.update();} else {(function(){if(!window.Ya) { window.Ya = {} };Ya.STATIC_BASE = 'http:\/\/yandex.st\/wow\/2.7.7\/static';Ya.START_BASE = 'http:\/\/my.ya.ru\/';var shareScript = document.createElement("script");shareScript.type = "text/javascript";shareScript.async = "true";shareScript.charset = "utf-8";shareScript.src = Ya.STATIC_BASE + "/js/api/Share.js";(document.getElementsByTagName("head")[0] || document.body).appendChild(shareScript);})();}</script><?php
	}

	function add_footer_mrc() {
		?><script src="http://cdn.connect.mail.ru/js/loader.js" type="text/javascript" charset="UTF-8"></script><?php
	}
	# end footer

	##### start shortcodes
	function sc__vk_like ( $atts ) {
		global $post;
		extract( shortcode_atts( array(
			'id' => $post->ID
			), $atts ) );
		$postid = esc_attr($id);
		$echo = "<div id=\"vkapi_like_$postid\"></div>";
		$type = get_option( 'vkapi_like_type' );
		$verb = get_option( 'vkapi_like_verb' );
		$vkapi_title = addslashes ( $post->post_title );
		$vkapi_descr = str_replace( "\r\n", "<br />", $post->post_excerpt );
		$vkapi_descr = strip_tags( $vkapi_descr );
		$vkapi_descr = substr( $vkapi_descr, 0, 130 );
		$vkapi_descr = addslashes ( $vkapi_descr );
		$vkapi_url = get_permalink();
		//$vkapi_image = self::first_postimage($postid); pageImage: '$vkapi_image',
		$vkapi_text = str_replace( "\r\n", "<br />", $post->post_content );
		$vkapi_text = strip_tags( $vkapi_text );
		$vkapi_text = substr( $vkapi_text, 0, 130 );
		$vkapi_text = addslashes ( $vkapi_text );
		// pageImage
		$echo .= "
				<script type=\"text/javascript\">
					<!--
						VK.Widgets.Like('vkapi_like_$postid', {
						width: 1,
						height: 20,
						type: '$type',
						verb: '$verb',
						pageTitle: '$vkapi_title',
						pageDescription: '$vkapi_descr',
						pageUrl: '$vkapi_url',
						text: '$vkapi_text'
					}, $postid);
					-->
				</script>";
		return strip_shortcodes($echo);
	}
	# end shortcodes

	##### start post img url
	function first_postimage($id){
		$args = array(
			'post_parent' => $id,
			'post_type' => 'attachment',
			'numberposts' => 1,
			'post_mime_type' => 'image'
			);
		if( $images=get_posts($args) )
			foreach( $images as $image )
				$link = wp_get_attachment_url($image->ID);

		return $link;
	}
	# start post img url

	##### start add after body
	function add_after_body () { ?>
		<div id="vk_api_transport"></div>
		<script type="text/javascript">
			window.vkAsyncInit = function() {
				VK.init({
					apiId: <?php echo get_option( 'vkapi_appId' )."\n";?>
				});
			};

			setTimeout(function() {
				var el = document.createElement("script");
				el.type = "text/javascript";
				el.src = "http://vkontakte.ru/js/api/openapi.js";
				el.async = true;
				document.getElementById("vk_api_transport").appendChild(el);
			}, 0);
		</script>
		<?php
	}
	# end add after body
	
	##### start crosspost
	function add_crosspost() {
	echo ' - <a href="#" onclick="vk_crosspost(); return false">VKcrossPOST</a>
		<script>
		function vk_crosspost() {
			VK.Api.call(\'wall.post\', {
				message:\'text me, baby, test this API\'
			}, function(data) {
				if (data.response) { // если получен ответ
                    alert(\'Сообщение отправлено! ID сообщения: \' + data.response.post_id);
                } else { // ошибка при отправке сообщения
                    alert(\'Ошибка! \' + data.error.error_code + \' \' + data.error.error_msg);
                }
			});
		};
		</script>';
	}	
	# end crosspost
}

else :
{
	function vkapi_notice_declared(){
		echo '<div class="error">
		<p>Class VK_api already declared!.</p>
		</div>';
	}
	add_action( 'admin_notices', 'vkapi_notice_declared' );
}

endif;

global $wp_version;
if ( version_compare( $wp_version, "3.3", "<" ) ) {
	function vkapi_notice_update() {
		echo '
		<div class="error">
		<p>VKontakte API plugin requires Wordpress 3.3 or newer. <a href="'.bloginfo('url').'wp-admin/update-core.php'.'">Please update!</a></p>
		<p>Plugin not activated!</p>
		</div>
		';
	}
	add_action( 'admin_notices', 'vkapi_notice_update' );
}
elseif ( class_exists( 'VK_api' ) ) {
		$VK_api = new VK_api();
		add_shortcode( 'vk_like', array( 'VK_api', 'sc__vk_like' ) );
	}

/* =Vkapi Widgets
-------------------------------------------------------------- */

/* Community Widget */
class VKAPI_Community extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Information about VKontakte group', $this->plugin_domain ) );
		parent::WP_Widget( 'vkapi_community', $name = __( 'VKapi: Community Users', $this->plugin_domain ), $widget_ops );
	}

	function widget($args, $instance) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_mode = 2;
		$vkapi_gid = $instance['gid'];
		$vkapi_width = $instance['width'];
		if ( $vkapi_width < 1 ) {
			$vkapi_width = '';
		} else {
			$vkapi_width = "width: \"$vkapi_width\",";
		};
		$vkapi_height = $instance['height'];
		if( $instance['type'] == 'users' ) $vkapi_mode = 0;
		if( $instance['type'] == 'news' ) $vkapi_mode = 2;
		if( $instance['type'] == 'name' ) $vkapi_mode = 1;
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo '</div>
		<script type="text/javascript">
			VK.Widgets.Group("'.$vkapi_divid.'", {mode: '.$vkapi_mode.', '.$vkapi_width.' height: "'.$vkapi_height.'"}, '.$vkapi_gid.');
		</script>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'type' => 'users', 'title' => '', 'width' => '0', 'height' => '1', 'gid' => '28197069' ) );
		$title = esc_attr( $instance['title'] );
		$gid = esc_attr( $instance['gid'] );
		$width = esc_attr( $instance['width'] );
		$height = esc_attr( $instance['height'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'gid' ); ?>"><?php _e( 'ID of group (can be seen by reference to statistics):', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'gid' ); ?>" name="<?php echo $this->get_field_name( 'gid' ); ?>" type="text" value="<?php echo $gid; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php _e( 'Height:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="text" value="<?php echo $height; ?>" />
		</label></p>

		<p>
        <label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Layout:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'type' ); ?>" id="<?php echo $this->get_field_id( 'type' ); ?>" class="widefat">
        <option value="users"<?php selected( $instance['type'], 'users' ); ?>><?php _e( 'Members', $this->plugin_domain ); ?></option>
	    <option value="news"<?php selected( $instance['type'], 'news' ); ?>><?php _e( 'News', $this->plugin_domain ); ?></option>
		<option value="name"<?php selected( $instance['type'], 'name' ); ?>><?php _e( 'Only Name', $this->plugin_domain ); ?></option>
        </select>
		</p>
		<?php }
}

/* Recommend Widget */
class VKAPI_Recommend extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Top site on basis of "I like" statistics', $this->plugin_domain ) );
		parent::WP_Widget( 'vkapi_recommend', $name = __( 'VKapi: Recommends' , $this->plugin_domain), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_limit = $instance['limit'];
		$vkapi_width = $instance['width'];
		$vkapi_period = $instance['period'];
		$vkapi_verb = $instance['verb'];
		echo $before_widget . $before_title . $instance['title'] . $after_title;
		if ( $vkapi_width != '0' ) echo "<div style=\"width:$vkapi_width\">";
		echo '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo '</div>';
		if ( $vkapi_width != '0' ) echo '</div>';
		echo '
		<script type="text/javascript">
			VK.Widgets.Recommended("'.$vkapi_divid.'", {limit: '.$vkapi_limit.', period: \''.$vkapi_period.'\', verb: '.$vkapi_verb.', target: "blank"});
		</script>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'limit' => '5', 'period' => 'month', 'verb' => '0', 'width' => '0' ) );
		$title = esc_attr( $instance['title'] );
		$limit = esc_attr( $instance['limit'] );
		$width = esc_attr( $instance['width'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Number of posts:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>

		<p>
        <label for="<?php echo $this->get_field_id( 'period' ); ?>"><?php _e( 'Selection period:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'period' ); ?>" id="<?php echo $this->get_field_id( 'period' ); ?>" class="widefat">
        <option value="day"<?php selected( $instance['period'], 'day' ); ?>><?php _e( 'Day', $this->plugin_domain ); ?></option>
	    <option value="week"<?php selected( $instance['period'], 'week' ); ?>><?php _e( 'Week', $this->plugin_domain ); ?></option>
		<option value="month"<?php selected( $instance['period'], 'month' ); ?>><?php _e( 'Month', $this->plugin_domain ); ?></option>
        </select>
		</p>

		<p>
        <label for="<?php echo $this->get_field_id( 'verb' ); ?>"><?php _e( 'Formulation:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'verb' ); ?>" id="<?php echo $this->get_field_id( 'verb' ); ?>" class="widefat">
        <option value="0"<?php selected( $instance['verb'], '0' ); ?>><?php _e( '... people like this', $this->plugin_domain ); ?></option>
	    <option value="1"<?php selected( $instance['verb'], '1' ); ?>><?php _e( '... people find it intersting', $this->plugin_domain ); ?></option>
        </select>
		</p>
		<?php
	}
}

/* Login Widget */
class VKAPI_Login extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Login widget', $this->plugin_domain ) );
		parent::WP_Widget( 'vkapi_login', $name = __( 'VKapi: Login' , $this->plugin_domain), $widget_ops);
		}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		echo $before_widget . $before_title . $instance['Message'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		if ( is_user_logged_in() ) {
				$vkapi_wp_id = get_current_user_id();
				$vkapi_meta_ava = get_user_meta($vkapi_wp_id, 'vkapi_ava', TRUE);
				if ( !empty( $vkapi_meta_ava{0} ) ) {
					echo "<div style='float:left; padding-right:20px'><img alt='' src='$vkapi_meta_ava' class='avatar avatar-75' height='75' width='75' /></div>";
					echo "<br />\r\n<div>";
					echo "<a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __( 'Profile' , $this->plugin_domain ) . "</a><br /><br />";
					echo "<a href='" . wp_logout_url( home_url($_SERVER['REQUEST_URI']) ) . "' title=''>" . __( 'Logout' , $this->plugin_domain ) . "</a><br /><br /></div>";
				} else {
					echo "<div style='float:left; padding-right:20px'>" . get_avatar( $vkapi_wp_id, 75 ) . "</div>";
					echo "<br />\r\n<div>";
					echo "<a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __( 'Profile' , $this->plugin_domain ) . "</a><br /><br />";
					echo "<a href='" . wp_logout_url( home_url($_SERVER['REQUEST_URI']) ) . "' title=''>" . __( 'Logout' , $this->plugin_domain ) . "</a><br /><br /></div>";
				}
		} else {
			$this->vkapi_link_vk();
		}
	echo '</div>' . $after_widget;
	}

	function vkapi_link_vk () {
		$vkapi_url = get_bloginfo('wpurl');
		echo '<button style="display: none" id="submit" class="vkapi_vk_widget" vkapi_url="'.$vkapi_url.'"></button>';
		echo '<a href="' . wp_login_url( home_url($_SERVER['REQUEST_URI']) ) . '" title="">' . __( 'Login' , $this->plugin_domain ) . '</a>';
		echo '<br /><br />';
		echo wp_register( '','',home_url($_SERVER['REQUEST_URI']) );
		echo '<br /><br />
		<div id="vkapi_status"></div>
		<div id="login_button" style="padding:0px;border:0px;width:125px;" onclick="VK.Auth.login(onSignon)"></div>
		<style type="text/css">
			#login_button td, #login_button tr {
				padding:0px !important;
				margin:0px !important;
			}
		</style>
		<script language="javascript">
			VK.UI.button(\'login_button\');
		</script>
		<div style="display:none" id="vk_auth"></div>
			<script type="text/javascript">
				VK.Widgets.Auth("vk_auth", {width: "200px", onAuth: function(data) {
					alert("user "+data["uid"]+" authorized");
				} });
		</script>';
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'Message' => 'What\'s up' ) );
		$title = esc_attr( $instance['Message'] );

		?><p><label for="<?php echo $this->get_field_id( 'Message' ); ?>"><?php _e( 'Message:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Message' ); ?>" name="<?php echo $this->get_field_name( 'Message' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		<?php
	}
}

/* Comments Widget */
class VKAPI_Comments extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Last Comments', $this->plugin_domain ) );
		parent::WP_Widget( 'vkapi_comments', $name = __( 'VKapi: Last Comments' , $this->plugin_domain), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_width = $instance['width'];
		if ( $vkapi_width = '0' ) {
			$vkapi_width = '';
		} else {
			$vkapi_width = "width: '$vkapi_width',";
		};
		$vkapi_height = $instance['height'];
		$vkapi_limit = $instance['limit'];
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo "
			<div class=\"wrap\">
				<div id=\"vkapi_comments\"></div>
				<script type=\"text/javascript\">
				VK.Widgets.CommentsBrowse('vkapi_comments', {
					$vkapi_width
					limit: '$vkapi_limit',
					height: '$vkapi_height',
					mini: 1
				});
				</script>
			</div>
			";
		echo '</div>' . $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'limit' => '5', 'width' => '0', 'height' => '1' ) );
		$title = esc_attr( $instance['title'] );
		$limit = esc_attr( $instance['limit'] );
		$width = esc_attr( $instance['width'] );
		$height = esc_attr( $instance['height'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Number of comments:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php _e( 'Height:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="text" value="<?php echo $height; ?>" />
		</label></p>
		<?php
	}
}

/* Cloud Widget */
class VKAPI_Cloud extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Flash cloud of tags and cats', $this->plugin_domain ) );
		parent::WP_Widget( 'vkapi_tag_cloud', $name = __( 'VKapi: Tags Cloud' , $this->plugin_domain), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_width = $instance['width'];
		$vkapi_height = $instance['height'];
		$vkapi_color1 = $instance['color1'];
		$vkapi_color2 = $instance['color2'];
		$vkapi_color3 = $instance['color3'];
		$vkapi_speed = $instance['speed'];
		$vkapi_distr = $instance['distr'];
		// tags
		ob_start();
		if ( $instance['tags'] == 1 ) {
			wp_tag_cloud();
			$vkapi_mode = 'tags';
		};
		$vkapi_tags = urlencode( str_replace( "&nbsp;", " ", ob_get_clean() ) );
		$vkapi_tags = urlencode( '<tags>' ) . $vkapi_tags . urlencode( '</tags>' );
		// cats
		ob_start();
		if ( $instance['cats'] == 1 ) {
			wp_list_categories('title_li=&show_count=1&hierarchical=0&style=none');
			if ( $vkapi_mode == 'tags' ) {
				$vkapi_mode = 'both';
			} else {
				$vkapi_mode = 'tags';
			};
		};
		$vkapi_cats = urlencode( ob_get_clean() );
		// end
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		$path = WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/swf';

		echo '</div>';
		$rnumber = '?r=' . rand(0,9999999);
		echo '
			<script type="text/javascript" src="'.$path.'/swfobject.js"></script>
			<script type="text/javascript">
				var rnumber = Math.floor(Math.random()*9999999);
				var so = new SWFObject("'.$path.'/tagcloud.swf'.$rnumber.'", "tagcloudflash", "'.$vkapi_width.'", "'.$vkapi_height.'", "9", "#000000");
				so.addParam("allowScriptAccess", "always");
				so.addParam("wmode", "transparent");
				so.addVariable("tspeed", "'.$vkapi_speed.'");
				so.addVariable("distr", "'.$vkapi_distr.'");
				so.addVariable("mode", "'.$vkapi_mode.'");
				so.addVariable("tcolor", "'.$vkapi_color1.'");
				so.addVariable("tcolor2", "'.$vkapi_color2.'");
				so.addVariable("hicolor", "'.$vkapi_color3.'");
				so.addVariable("tagcloud", "'.$vkapi_tags.'");
				so.addVariable("categories", "'.$vkapi_cats.'");
				so.write("'.$vkapi_divid.'");
			</script>
			';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		if ( $old_instance['tags'] == 0 && $old_instance['cats'] == 0 )
			$new_instance['tags'] = 1;
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array(
				'title' => '',
				'width' => '400',
				'height' => '300',
				'color1' => '0xFF141C',
				'color2' => '0x4659FF',
				'color3' => '0x255613',
				'speed' => '88',
				'tags' => '1',
				'cats' => '1',
				'distr' => 'false'
		) );
		$title = esc_attr( $instance['title'] );
		$width = esc_attr( $instance['width'] );
		$height = esc_attr( $instance['height'] );
		$color1 = esc_attr( $instance['color1'] );
		$color2 = esc_attr( $instance['color2'] );
		$color3 = esc_attr( $instance['color3'] );
		$speed = esc_attr( $instance['speed'] );
		$tags = esc_attr( $instance['tags'] );
		$cats = esc_attr( $instance['cats'] );
		$distr = esc_attr( $instance['distr'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php _e( 'Height:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="text" value="<?php echo $height; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'color1' ); ?>"><?php _e( 'Tag color:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'color1' ); ?>" name="<?php echo $this->get_field_name( 'color1' ); ?>" type="text" value="<?php echo $color1; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'color2' ); ?>"><?php _e( 'Gradient color:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'color2' ); ?>" name="<?php echo $this->get_field_name( 'color2' ); ?>" type="text" value="<?php echo $color2; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'color3' ); ?>"><?php _e( 'Highlight color:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'color3' ); ?>" name="<?php echo $this->get_field_name( 'color3' ); ?>" type="text" value="<?php echo $color3; ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'speed' ); ?>"><?php _e( 'Speed:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'speed' ); ?>" name="<?php echo $this->get_field_name( 'speed' ); ?>" type="text" value="<?php echo $speed; ?>" />
		</label></p>

		<p>
        <label for="<?php echo $this->get_field_id( 'distr' ); ?>"><?php _e( 'Distribute on sphere:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'distr' ); ?>" id="<?php echo $this->get_field_id( 'distr' ); ?>" class="widefat">
			<option value="true"<?php selected( $instance['distr'], 'true' ); ?>><?php _e( 'Evenly', $this->plugin_domain ); ?></option>
			<option value="false"<?php selected( $instance['distr'], 'false' ); ?>><?php _e( 'Dont evenly', $this->plugin_domain ); ?></option>
        </select>
		</p>

		<p>
        <label for="<?php echo $this->get_field_id( 'tags' ); ?>"><?php _e( 'Show tags:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'tags' ); ?>" id="<?php echo $this->get_field_id( 'tags' ); ?>" class="widefat">
			<option value="1"<?php selected( $instance['tags'], '1' ); ?>><?php _e( 'Show', $this->plugin_domain ); ?></option>
			<option value="0"<?php selected( $instance['tags'], '0' ); ?>><?php _e( 'Dont show', $this->plugin_domain ); ?></option>
        </select>
		</p>

		<p>
        <label for="<?php echo $this->get_field_id( 'cats' ); ?>"><?php _e( 'Show categories:', $this->plugin_domain ); ?></label>
        <select name="<?php echo $this->get_field_name( 'cats' ); ?>" id="<?php echo $this->get_field_id( 'cats' ); ?>" class="widefat">
			<option value="1"<?php selected( $instance['cats'], '1' ); ?>><?php _e( 'Show', $this->plugin_domain ); ?></option>
			<option value="0"<?php selected( $instance['cats'], '0' ); ?>><?php _e( 'Dont show', $this->plugin_domain ); ?></option>
        </select>
		</p>
		<?php
	}
}

/* Facebook LikeBox Widget */
class FBAPI_LikeBox extends WP_Widget {

	var $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Information about Facebook group', $this->plugin_domain ) );
		parent::WP_Widget( 'fbapi_recommend', $name = __( 'FBapi: Community Users' , $this->plugin_domain), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		echo $before_widget . $before_title . $instance['title'] . $after_title;
		echo '<div id="'.$vkapi_divid.'_wrapper">';
		echo '
			<div
				style="background:white"
				class="fb-like-box"
				data-href="'.$instance['page'].'"
				data-width="'.$instance['width'].'"
				data-height="'.$instance['height'].'"
				data-show-faces="'.$instance['face'].'"
				data-stream="'.$instance['news'].'"
				data-header="'.$instance['header'].'"></div>
			</div>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'width' => '', 'height' => '', 'face' => 'true', 'news' => 'false', 'header' => 'true', 'page' => 'http://www.facebook.com/thewordpress' ) );

		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id( 'page' ); ?>"><?php _e( 'Facebook Page URL:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'page' ); ?>" name="<?php echo $this->get_field_name( 'page' ); ?>" type="text" value="<?php echo esc_attr( $instance['page'] ); ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo esc_attr( $instance['width'] ); ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php _e( 'Height:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="text" value="<?php echo esc_attr( $instance['height'] ); ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'face' ); ?>"><?php _e( 'Show Faces:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'face' ); ?>" name="<?php echo $this->get_field_name( 'face' ); ?>" type="text" value="<?php echo esc_attr( $instance['face'] ); ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'news' ); ?>"><?php _e( 'Stream:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'news' ); ?>" name="<?php echo $this->get_field_name( 'news' ); ?>" type="text" value="<?php echo esc_attr( $instance['news'] ); ?>" />
		</label></p>

		<p><label for="<?php echo $this->get_field_id( 'header' ); ?>"><?php _e( 'Header:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'header' ); ?>" name="<?php echo $this->get_field_name( 'header' ); ?>" type="text" value="<?php echo esc_attr( $instance['header'] ); ?>" />
		</label></p>
		<?php
	}
}