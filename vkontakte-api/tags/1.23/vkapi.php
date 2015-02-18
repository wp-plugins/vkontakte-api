<?php
/*
Plugin Name: Vkontakte API
Plugin URI: http://www.kowack.info/projects/vk_api
Description: Add api functions from vkontakte.ru\vk.com in your own blog. <strong><a href="options-general.php?page=vkapi_settings">Settings!</a></strong>
Version: 1.23
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
		add_action( 'admin_menu', array( &$this, 'create_menu' ), 1 ); /* menu */
		add_action( 'wp_print_scripts', array( &$this, 'add_head' ) ); /* head */
		add_action( 'init', array( &$this, 'widget_init' ) ); /* widget */
		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widgets' ) ); /* main dashboard */
		add_action( 'admin_init', array( &$this, 'add_css' ) ); /* admin css register */
		add_action( 'wp_print_styles', array( &$this, 'add_css_admin' ) ); /* admin css enqueue */
		add_action(	'save_post', array( &$this, 'save_postdata' ) ); /* check meta_box */
		add_action(	'do_meta_boxes', array( &$this, 'add_custom_box' ), 15, 2); /* add meta_box */
		$vkapi_login = get_option( 'vkapi_login' );
		if ( $vkapi_login == 'true' ) {
			add_action( 'profile_personal_options', array( &$this, 'vkapi_personal_options' ) ); /* profile echo */
			add_action( 'admin_footer', array( &$this, 'add_profile_js' ), 88 ); /* profile js */
			add_action( 'login_form', array( &$this, 'add_login_form' ) ); /* login */
			add_action( 'wp_ajax_update_vkapi_user_meta', array( &$this, 'update_vkapi_user_meta' ) ); /* update_vkapi_user_meta */
		};
		add_action( 'admin_bar_menu', array( &$this, 'user_links' ) ); /* admin bar */
		add_filter( 'the_content', array( &$this, 'add_buttons' ), 88 ); /* buttons */
		add_filter( 'contextual_help', array( &$this, 'vkapi_contextual_help' ), 1, 3 ); /* help */
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'own_actions_links' ) ); /* plugin links */
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_meta' ), 10, 2); /* plugin meta */
		wp_enqueue_script ( 'jquery' );
		wp_register_script ( 'vkapi_callback', self::$plugin_url . 'js/callback.js');
		wp_register_script ( 'userapi', 'http://userapi.com/js/api/openapi.js' );
		wp_register_script ( 'share', 'http://vk.com/js/api/share.js' );
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
			add_filter( 'comments_template', 'close_wp' );
			add_filter( 'get_comments_number', array( &$this, 'do_empty'), 1 );
		} else {
			add_action( 'comments_template', array( &$this, 'add_tabs') );
			add_filter( 'get_comments_number', array( &$this, 'do_non_empty'), 1 );
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
				"echo '<div class=\"error\"><p>".sprintf(__('Vkontakte API Plugin needs <a href="%s">configuration</a>.', self::$plugin_domain), admin_url('admin.php?page=vkapi_settings'))."</p></div>';" ) );
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
		add_option( 'vkapi_appid', '' );
		add_option( 'vkapi_api_secret', '' );
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
		add_option( 'vkapi_like_type', 'full' );
		add_option( 'vkapi_like_verb', '0' );
		add_option( 'vkapi_like_cat', '0' );
		add_option( 'vkapi_like_bottom', '1' );
		add_option( 'vkapi_share_cat' );
		add_option( 'vkapi_share_type', 'round' );
		add_option( 'vkapi_share_text', 'Сохранить' );
		add_option( 'vkapi_align', 'left' );
		add_option( 'vkapi_show_comm', 'true' );
		add_option( 'vkapi_show_like', 'true' );
		add_option( 'vkapi_show_share', 'false' );
		add_option( 'vkapi_some_logo_e', '1' );
		add_option( 'vkapi_some_logo', self::$plugin_url.'images/wordpress-logo.jpg' );
		add_option( 'vkapi_some_desktop', '1' );
		add_option( 'vkapi_some_autosave_d', '1' );
		add_option( 'vkapi_some_revision_d', '1' );
		add_option( 'vkapi_close_wp', '0' );
		add_option( 'vkapi_login', '1' );
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
		delete_option( 'vkapi_show_share' );
		delete_option( 'vkapi_some_logo_e' );
		delete_option( 'vkapi_some_logo' );
		delete_option( 'vkapi_some_desktop' );
		delete_option( 'vkapi_some_autosave_d' );
		delete_option( 'vkapi_some_revision_d' );
		delete_option( 'vkapi_close_wp' );
		delete_option( 'vkapi_login' );
	}
	
	function settings_page() {		
		require_once( 'vkapi-options.php' );
	}
	
	function comments_page() {		
		echo '
			<div class="wrap">
				<div class="icon32"><img src="'.self::$plugin_url.'images/set.png" /></div>
				<h2 style="margin: 0px 0px 20px 50px">'.__( 'Vkontakte API Plugin - Comments', self::$plugin_domain).'</h2>
				<div id="vkapi_comments"></div>
				<script type="text/javascript">
				VK.Widgets.CommentsBrowse(\'vkapi_comments\', { mini: 1});
				</script>
			</div>
			';
	}
	
	function add_head() {
		if ( !is_admin() || defined('IS_PROFILE_PAGE') ) {
			$appId = get_option( 'vkapi_appId' );
			echo "<meta property='vk:app_id' content='$appId' />\n";
			wp_enqueue_script ( 'userapi' );
		}
		$share = get_option( 'vkapi_show_share' );
		if ( !is_admin() && $share == 'true' ) 
			wp_enqueue_script ( 'share' );
	}
	
	function create_menu() {
		self::$vkapi_page_menu = add_menu_page( 
				__('Vkontakte API', self::$plugin_domain),
				__('Vkontakte API', self::$plugin_domain),
				'manage_options',
				'vkapi_settings',
				array( &$this, 'settings_page'),
				'http://vk.com/favicon.ico'
			);
		self::$vkapi_page_settings = add_submenu_page( 
				'vkapi_settings', 
				__( 'Vkontakte API Plugin - Settings', self::$plugin_domain),
				__('Settings', self::$plugin_domain),
				'manage_options',
				'vkapi_settings',
				array( &$this, 'settings_page') 
			);
		self::$vkapi_page_comments = add_submenu_page( 
				'vkapi_settings', 
				__( 'Vkontakte API Plugin - Comments', self::$plugin_domain),
				__('Last Comments', self::$plugin_domain),
				'manage_options',
				'vkapi_comments',
				array( &$this, 'comments_page') 
			);
		add_action( 'admin_print_styles-' . self::$vkapi_page_menu, array( &$this, 'add_css_admin' ) );
		add_action( 'admin_print_styles-' . self::$vkapi_page_settings, array( &$this, 'add_css_admin' ) );
		add_action( 'admin_print_styles-' . self::$vkapi_page_comments, array( &$this, 'add_css_admin_comm' ) );
	}
	
	function add_css () {
		wp_register_style ( 'vkapi_admin', plugins_url('css/admin.css', __FILE__) );
		wp_register_script ( 'userapi', 'http://userapi.com/js/api/openapi.js' );
	}
	
	function add_css_admin () {
		wp_enqueue_style ( 'vkapi_admin' );
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
				По результатам этой кнопки есть <a href="'.admin_url("widgets.php").'">виждет</a> "Популярное" со статистикой.</p>';
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
			$help = '<p>Все вопросики и пожелания <strong><a href="http://www.kowack.info/projects/vk_api/" title="Vkontakte API Home">сюдатачки</a></strong> или <strong><a href="http://vk.com/vk_wp" title="Vkontakte API Club">тутачки</a></strong>.</p>';
			$screen->add_help_tab( array(
				'id'	=> 'vkapi_help',
				'title'	=> __( 'Help', self::$plugin_domain ),
				'content'	=> $help
				) );
		};
		return '';
	}
	
	function add_tabs() {
		global $post;
		$postid = $post->ID;
		$vkapi_get_comm = get_post_meta($postid, vkapi_comments, true);
		$vkapi_show_comm = get_option( 'vkapi_show_comm' );
		if ( $vkapi_show_comm == 'true' && ( $vkapi_get_comm == '1' || $vkapi_get_comm === '' ) ) {
			if ( comments_open() ) {
				$vkapi_some_desktop = get_option( 'vkapi_some_desktop' );
				$postid = $post->ID;
				$att;
				$att2 = get_option( 'vkapi_comm_autoPublish' );
				$vkapi_button = __('Vkontakte comments', self::$plugin_domain);
				if ( get_option( 'vkapi_comm_graffiti' ) ) $att .= '"graffiti';
				if ( get_option( 'vkapi_comm_photo' ) ) $att .= ( empty( $att{0} ) ) ? '"photo' : ',photo';
				if ( get_option( 'vkapi_comm_audio' ) ) $att .= ( empty( $att{0} ) ) ? '"audio' : ',audio';
				if ( get_option( 'vkapi_comm_video' ) ) $att .= ( empty( $att{0} ) ) ? '"video' : ',video';
				if ( get_option( 'vkapi_comm_link' ) ) $att .= ( empty( $att{0} ) ) ? '"link' : ',link';	
				if ( ( empty( $att{0} ) ) ) $att = 'false'; else $att .= '"';
				if ( ( empty( $att2{0} ) ) ) $att2 = '0'; else $att2 = '1';
				echo '<script type="text/javascript">
				function showVK(){
					jQuery("#vkapi").show(2000);
					jQuery("#comments").hide(2500);
					jQuery("#respond").hide(2500);
					};
				function showComments(){
					jQuery("#comments").show(2000);
					jQuery("#respond").show(2000);
					jQuery("#vkapi").hide(2000);
					};';
				if ( $vkapi_some_desktop ) {
					echo 'function vkapi_checkPermission() {
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
					jQuery("button.vkapi_vk").html(\''.$vkapi_button.' (\'+num+\')\');
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
				};';
				} else {
				echo 'function onChangeRecalc(num,last_comment,data,hash){
					jQuery("button.vkapi_vk").html(\''.$vkapi_button.' (\'+num+\')\');
					}';
				};
				$vkapi_url = get_bloginfo('wpurl');
				$vkapi_comm = get_post_meta($postid, 'vkapi_comm', TRUE);
				$comm_wp = get_comments_number() - $vkapi_comm;
				if ( $vkapi_comm ) $vkapi_comm_show = ' ('.$vkapi_comm.')';
				echo '</script>
				<br />
				<button id="submit" onclick="showVK()" class="vkapi_vk" vkapi_notify="'.$postid.'" vkapi_url="'.$vkapi_url.'">'.$vkapi_button.$vkapi_comm_show.'</button>
				<button id="submit" onclick="showComments()">'.__('WordPress comments', self::$plugin_domain).' ('.$comm_wp.') </button><br /><br /><br />
				<div id="vkapi" onclick="showNotification()"></div>
				<script type="text/javascript">
					VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$att.', autoPublish: '.$att2.', height: '.get_option('vkapi_comm_height').', mini:1, pageUrl: "'.get_permalink().'"},'.$postid.');
				</script>';
				add_action ( 'wp_footer', array( &$this, 'add_footer' ) );
			}
		}
	}

	function add_footer () {
		if ( get_option( 'vkapi_comm_show' ) == 1 ) 
			echo '<script type="text/javascript">window.onload=showVK;</script>';
		else echo '<script type="text/javascript">window.onload=showComments;</script>';
		echo '<audio id="vkapi_sound" preload="auto" style="display: none">
				<source src="http://vk.com/mp3/bb2.mp3">
			</audio>';
	}
	
	function add_buttons ( $args ) {
		if ( !is_feed() ) { 
			// share
			$share_cat = get_option( 'vkapi_share_cat' );
			if ( $share_cat ) self::vkapi_button_share( &$args ); 
			else if ( !is_home() && !is_category() && !is_archive() ) {
				self::vkapi_button_share( &$args );
			};
			// like
			$like_cat = get_option( 'vkapi_like_cat' );
			if ( $like_cat ) self::vkapi_button_like( &$args ); 
			else if ( !is_home() && !is_category() && !is_archive() ) {
				self::vkapi_button_like( &$args );
			};
		}
		return $args;
	}
	
	function vkapi_button_like ( $args ) {
		// like button
		$like = get_option( 'vkapi_show_like' );
			if( $like == 'true' ) {
				global $post;
				$postid = $post->ID;
				$valign = get_option( 'vkapi_like_bottom' );
				$align = get_option( 'vkapi_align' );
				$vkapi_args = "<div style=\"float:$align\"><div id=\"vkapi_like_$postid\"></div></div>";
				$type = get_option( 'vkapi_like_type' );
				$verb = get_option( 'vkapi_like_verb' );
				$vkapi_title = addslashes ( $post->post_title );
				$vkapi_descr = str_replace( "\r\n", "<br />", $post->post_excerpt );
				$vkapi_descr = strip_tags( $vkapi_descr );
				$vkapi_descr = substr( $vkapi_descr, 0, 130 );
				$vkapi_descr = addslashes ( $vkapi_descr );
				$vkapi_url = get_permalink();
				$vkapi_text = str_replace( "\r\n", "<br />", $post->post_content );
				$vkapi_text = strip_tags( $vkapi_text );
				$vkapi_text = substr( $vkapi_text, 0, 130 );
				$vkapi_text = addslashes ( $vkapi_text );
				// pageImage
				$echo = "
						<script type=\"text/javascript\">
							<!-- 
								VK.Widgets.Like('vkapi_like_$postid', {
								width: 1, 
								type: '$type', 
								verb: '$verb',
								pageTitle: '$vkapi_title',
								pageDescription: '$vkapi_descr',
								pageUrl: '$vkapi_url',
								text: '$vkapi_text'						
							}, $postid); 
							-->
						</script>";
				if ( $valign ) $args .= $vkapi_args; 
				else {
					$vkapi_args .= $args;
					$args = $vkapi_args;
				}
			}
		return $args .= $echo;
	}
	
	function vkapi_button_share ( $args ) {
		$share = get_option( 'vkapi_show_share' );
			if( $share == 'true' ) {
				global $post;
				$postid = $post->ID;
				$valign = get_option( 'vkapi_like_bottom' );
				$align = get_option( 'vkapi_align' );
				$vkapi_args = "<div style=\"float:$align\"><div id=\"vkapi_share_$postid\"></div></div>";
				$vkapi_url = get_permalink();
				$vkapi_title = addslashes ( $post->post_title );
				$vkapi_descr = str_replace( "\r\n", "<br />", $post->post_content );
				$vkapi_descr = strip_tags( $vkapi_descr );
				$vkapi_descr = substr( $vkapi_descr, 0, 130 );
				$vkapi_descr = addslashes ( $vkapi_descr );
				$vkapi_type = get_option( 'vkapi_share_type' );
				$vkapi_text = get_option( 'vkapi_share_text' );
				$vkapi_text = addslashes ( $vkapi_text );
				$echo = "
							<style type=\"text/css\">
								#vkapi_share_$postid {
									padding:0px 3px 0px 0px;
								}
								#vkapi_share_$postid td,
								#vkapi_share_$postid tr {
									border:0px !important;
									padding:0px !important;
									margin:0px !important;
								}
							</style>
							<script type=\"text/javascript\">
								<!--
								jQuery(document).ready(function() {
									document.getElementById('vkapi_share_$postid').innerHTML = VK.Share.button({
										url: '$vkapi_url',
										title: '$vkapi_title',
										description: '$vkapi_descr'
									},{
										type: '$vkapi_type',
										text: '$vkapi_text'
									});
								});
								-->
							</script>";
			if ( $valign ) $args .= $vkapi_args; 
				else {
					$vkapi_args .= $args;
					$args = $vkapi_args;
				}
			}
		return $args .= $echo;
	}
	
	function change_login_logo() {
		$logo = get_option( 'vkapi_some_logo' );
		echo '<style type="text/css">
			#login { width: 380px !important}
			h1 a { background-image:url('.$logo.') !important; width: 380px !important; height: 130px !important;}
		</style>';
	}
	
	function widget_init() {
		$vkapi_login = get_option( 'vkapi_login' );
		register_widget( 'VKAPI_Community' );
		register_widget( 'VKAPI_Recommend' );
		if ( $vkapi_login == 'true' ) register_widget( 'VKAPI_Login' );
		register_widget( 'VKAPI_Comments' );
		register_widget( 'VKAPI_Cloud' );
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
	
	
	/* start meta_box */
		function save_postdata( $post_id ) {
	if ( !wp_verify_nonce( $_REQUEST['vkapi_noncename'], plugin_basename(__FILE__) )) 
		return $post_id;
	if ( 'page' == $_REQUEST['post_type'] ) {
		if ( !current_user_can( 'edit_page', $post_id ))
			return $post_id;
	} else {
		if ( !current_user_can( 'edit_post', $post_id ))
			return $post_id;
	}
	update_post_meta($post_id, 'vkapi_comments', $_REQUEST['vkapi_comments']);
	}

	function add_custom_box($page,$context) {
		add_meta_box( 'vkapi_meta_box', __('VK.com comments',self::$plugin_domain),array($this,'vkapi_inner_custom_box'), 'post', 'advanced' );
		add_meta_box( 'vkapi_meta_box', __('VK.com comments',self::$plugin_domain),array($this,'vkapi_inner_custom_box'), 'page', 'advanced' );
	}

	function vkapi_inner_custom_box() {
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
	/* end meta_box */
	
	/* start recount comments number */
	function do_empty ( $args ) {
		global $post;
		$postid = $post->ID;
		$vkapi_comm = get_post_meta ( $postid, 'vkapi_comm', TRUE );
		return $vkapi_comm;
	}
	
	function do_non_empty ( $args ) {
		global $post;
		$postid = $post->ID;
		$vkapi_comm = get_post_meta ( $postid, 'vkapi_comm', TRUE );
		if ( !$vkapi_comm ) return $args;
		return $vkapi_comm+$args;
	}
	/* end recount comments number */
	
	/* start profile*/
	function vkapi_personal_options ( $profile ) { 
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label><?php _e( 'Vkontakte', self::$plugin_domain ); ?></label></th>
		<?php
			$conn = get_user_meta( $profile->ID, 'vkapi_uid', TRUE );
			if (empty($conn)) {
		?>
				<td>
					<div id="vkapi_login_button" style="padding:0px;border:0px" onclick="VK.Auth.getLoginStatus(onSignonProfile)"></div>
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
					<input type="button" class="button-primary" value="<?php _e( 'Disconnect from Vkontakte', self::$plugin_domain ); ?>" onclick="vkapi_profile_update(1)" />
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
			$member = self::$authOpenAPIMember();
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
	/* end profile*/
	
	/* start login */
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
		if ( $action == 'login' && is_user_logged_in() ) {
			$vkapi_url = get_bloginfo('wpurl');
			wp_redirect( $vkapi_url );
			exit;
		};
	}
	/* end login */
	
	/* start bar menu */
	function user_links( $wp_admin_bar ) {
		$user = wp_get_current_user();
		$vkapi_user = get_user_meta($user->ID, 'vkapi_uid', TRUE);
		if ( $vkapi_user ) {
			$wp_admin_bar->add_node( array(
					'id'     => 'vkapi-profile',
					'parent' => 'user-actions',
					'title'  => __( 'Vkontakte Profile', self::$plugin_domain ),
					'href'   => "http://vk.com/id$vkapi_user",
					'meta'   => array(
						'target' => '_blank',
		)
				) );
		};
	}
	/* end bar menu */
	
	/* start plugin meta */
	function plugin_meta( $links, $file ) {
		if ( $file == plugin_basename(__FILE__) ) {
			$links[] = '<a href="'.admin_url('options-general.php?page=vkapi_settings').'">'.__( 'Settings', self::$plugin_domain ).'</a>';
			$links[] = 'Code is poetry!';
		}
		return $links;
	}
	/* end plugin meta*/
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
elseif ( class_exists( 'VK_api' ) )
	$VK_api = new VK_api();

/* =Vkapi Widgets 
-------------------------------------------------------------- */

/* Community Widget */
class VKAPI_Community extends WP_Widget {

	static $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Information about VKontakte group', self::$plugin_domain ) );
		parent::WP_Widget( 'vkapi_community', $name = __( 'VKapi: Community Users', self::$plugin_domain ), $widget_ops );
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

		?><p><label for="<?php echo self::$get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'title' ); ?>" name="<?php echo self::$get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'gid' ); ?>"><?php _e( 'ID of group (can be seen by reference to statistics):', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'gid' ); ?>" name="<?php echo self::$get_field_name( 'gid' ); ?>" type="text" value="<?php echo $gid; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'width' ); ?>"><?php _e( 'Width:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'width' ); ?>" name="<?php echo self::$get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'height' ); ?>"><?php _e( 'Height:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'height' ); ?>" name="<?php echo self::$get_field_name( 'height' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>
		
		<p>
        <label for="<?php echo self::$get_field_id( 'type' ); ?>"><?php _e( 'Layout:', self::$plugin_domain ); ?></label>
        <select name="<?php echo self::$get_field_name( 'type' ); ?>" id="<?php echo self::$get_field_id( 'type' ); ?>" class="widefat">
        <option value="users"<?php selected( $instance['type'], 'users' ); ?>><?php _e( 'Members', self::$plugin_domain ); ?></option>
	    <option value="news"<?php selected( $instance['type'], 'news' ); ?>><?php _e( 'News', self::$plugin_domain ); ?></option>	                                
		<option value="name"<?php selected( $instance['type'], 'name' ); ?>><?php _e( 'Only Name', self::$plugin_domain ); ?></option>
        </select>
		</p>
		<?php }
}
/* Recommend Widget */
class VKAPI_Recommend extends WP_Widget {

	static $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Top site on basis of "I like" statistics', self::$plugin_domain ) );
		parent::WP_Widget( 'vkapi_recommend', $name = __( 'VKapi: Recommends' , self::$plugin_domain), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_limit = $instance['limit'];
		$vkapi_period = $instance['period'];
		$vkapi_verb = $instance['verb'];
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo '</div>
		<script type="text/javascript">
			VK.Widgets.Recommended("'.$vkapi_divid.'", {limit: '.$vkapi_limit.', period: \''.$vkapi_period.'\', verb: '.$vkapi_verb.', target: "blank"});
		</script>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'limit' => '5', 'period' => 'month', 'verb' => '0' ) );
		$title = esc_attr( $instance['title'] );
		$limit = esc_attr( $instance['limit'] );

		?><p><label for="<?php echo self::$get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'title' ); ?>" name="<?php echo self::$get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'limit' ); ?>"><?php _e( 'Number of posts:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'limit' ); ?>" name="<?php echo self::$get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" />
		</label></p>
		
		<p>
        <label for="<?php echo self::$get_field_id( 'period' ); ?>"><?php _e( 'Selection period:', self::$plugin_domain ); ?></label>
        <select name="<?php echo self::$get_field_name( 'period' ); ?>" id="<?php echo self::$get_field_id( 'period' ); ?>" class="widefat">
        <option value="day"<?php selected( $instance['period'], 'day' ); ?>><?php _e( 'Day', self::$plugin_domain ); ?></option>
	    <option value="week"<?php selected( $instance['period'], 'week' ); ?>><?php _e( 'Week', self::$plugin_domain ); ?></option>	                                
		<option value="month"<?php selected( $instance['period'], 'month' ); ?>><?php _e( 'Month', self::$plugin_domain ); ?></option>
        </select>
		</p>
		
		<p>
        <label for="<?php echo self::$get_field_id( 'verb' ); ?>"><?php _e( 'Formulation:', self::$plugin_domain ); ?></label>
        <select name="<?php echo self::$get_field_name( 'verb' ); ?>" id="<?php echo self::$get_field_id( 'verb' ); ?>" class="widefat">
        <option value="0"<?php selected( $instance['verb'], '0' ); ?>><?php _e( '... people like this', self::$plugin_domain ); ?></option>
	    <option value="1"<?php selected( $instance['verb'], '1' ); ?>><?php _e( '... people find it intersting', self::$plugin_domain ); ?></option>	                                
        </select>
		</p>
		<?php 
	}
}
/* Login Widget */
class VKAPI_Login extends WP_Widget {

	static $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Login widget', self::$plugin_domain ) );
		parent::WP_Widget( 'vkapi_login', $name = __( 'VKapi: Login' , self::$plugin_domain), $widget_ops);
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
					echo "<a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __( 'Profile' , self::$plugin_domain ) . "</a><br /><br />";
					echo "<a href='" . wp_logout_url( get_permalink() ) . "' title=''>" . __( 'Logout' , self::$plugin_domain ) . "</a><br /><br /></div>";
				} else {
					echo "<div style='float:left; padding-right:20px'>" . get_avatar( $vkapi_wp_id, 75 ) . "</div>";          
					echo "<br />\r\n<div>";
					echo "<a href='" . site_url('/wp-admin/profile.php') . "' title=''>" . __( 'Profile' , self::$plugin_domain ) . "</a><br /><br />";
					echo "<a href='" . wp_logout_url( get_permalink() ) . "' title=''>" . __( 'Logout' , self::$plugin_domain ) . "</a><br /><br /></div>";
				}
		} else {
			self::$vkapi_link_vk();
		}
	echo '</div>' . $after_widget;
	}
	
	function vkapi_link_vk () {
		$vkapi_url = get_bloginfo('wpurl');
		echo '<button style="display: none" id="submit" class="vkapi_vk_widget" vkapi_url="'.$vkapi_url.'"></button>';
		echo '<a href="' . wp_login_url( get_permalink() ) . '" title="">' . __( 'Login' , self::$plugin_domain ) . '</a>';
		echo '<br /><br />
		<div id="vkapi_status"></div>
		<div id="login_button" style="padding:0px;border:0px" onclick="VK.Auth.getLoginStatus(onSignon)"></div>
		<style type="text/css">
			#login_button td, #login_button tr {
				padding:0px !important;
				margin:0px !important;
			}
		</style>
		<script language="javascript">
			VK.UI.button(\'login_button\');
		</script>';
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'Message' => 'What\'s up' ) );
		$title = esc_attr( $instance['Message'] );
		
		?><p><label for="<?php echo self::$get_field_id( 'Message' ); ?>"><?php _e( 'Message:' ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'Message' ); ?>" name="<?php echo self::$get_field_name( 'Message' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		<?php 
	}
}
/* Comments Widget */
class VKAPI_Comments extends WP_Widget {

	static $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Last Comments', self::$plugin_domain ) );
		parent::WP_Widget( 'vkapi_comments', $name = __( 'VKapi: Last Comments' , self::$plugin_domain), $widget_ops);
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

		?><p><label for="<?php echo self::$get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'title' ); ?>" name="<?php echo self::$get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'limit' ); ?>"><?php _e( 'Number of comments:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'limit' ); ?>" name="<?php echo self::$get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'width' ); ?>"><?php _e( 'Width:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'width' ); ?>" name="<?php echo self::$get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'height' ); ?>"><?php _e( 'Height:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'height' ); ?>" name="<?php echo self::$get_field_name( 'height' ); ?>" type="text" value="<?php echo $height; ?>" />
		</label></p>
		<?php 
	}
}
/* Cloud Widget */
class VKAPI_Cloud extends WP_Widget {

	static $plugin_domain = 'vkapi';

	function __construct() {
		load_plugin_textdomain( self::$plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Flash cloud of tags and cats', self::$plugin_domain ) );
		parent::WP_Widget( 'vkapi_tag_cloud', $name = __( 'VKapi: Tags Cloud' , self::$plugin_domain), $widget_ops);
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
				so.addVariable("distr", "false");
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
				'cats' => '1'
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

		?><p><label for="<?php echo self::$get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'title' ); ?>" name="<?php echo self::$get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'width' ); ?>"><?php _e( 'Width:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'width' ); ?>" name="<?php echo self::$get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'height' ); ?>"><?php _e( 'Height:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'height' ); ?>" name="<?php echo self::$get_field_name( 'height' ); ?>" type="text" value="<?php echo $height; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'color1' ); ?>"><?php _e( 'Tag color:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'color1' ); ?>" name="<?php echo self::$get_field_name( 'color1' ); ?>" type="text" value="<?php echo $color1; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'color2' ); ?>"><?php _e( 'Gradient color:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'color2' ); ?>" name="<?php echo self::$get_field_name( 'color2' ); ?>" type="text" value="<?php echo $color2; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'color3' ); ?>"><?php _e( 'Highlight color:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'color3' ); ?>" name="<?php echo self::$get_field_name( 'color3' ); ?>" type="text" value="<?php echo $color3; ?>" />
		</label></p>
		
		<p><label for="<?php echo self::$get_field_id( 'speed' ); ?>"><?php _e( 'Speed:', self::$plugin_domain ); ?>
		<input class="widefat" id="<?php echo self::$get_field_id( 'speed' ); ?>" name="<?php echo self::$get_field_name( 'speed' ); ?>" type="text" value="<?php echo $speed; ?>" />
		</label></p>
		
		<p>
        <label for="<?php echo self::$get_field_id( 'tags' ); ?>"><?php _e( 'Show tags:', self::$plugin_domain ); ?></label>
        <select name="<?php echo self::$get_field_name( 'tags' ); ?>" id="<?php echo self::$get_field_id( 'tags' ); ?>" class="widefat">
			<option value="1"<?php selected( $instance['tags'], '1' ); ?>><?php _e( 'Show', self::$plugin_domain ); ?></option>
			<option value="0"<?php selected( $instance['tags'], '0' ); ?>><?php _e( 'Dont show', self::$plugin_domain ); ?></option>
        </select>
		</p>
		
		<p>
        <label for="<?php echo self::$get_field_id( 'cats' ); ?>"><?php _e( 'Show categories:', self::$plugin_domain ); ?></label>
        <select name="<?php echo self::$get_field_name( 'cats' ); ?>" id="<?php echo self::$get_field_id( 'cats' ); ?>" class="widefat">
			<option value="1"<?php selected( $instance['cats'], '1' ); ?>><?php _e( 'Show', self::$plugin_domain ); ?></option>
			<option value="0"<?php selected( $instance['cats'], '0' ); ?>><?php _e( 'Dont show', self::$plugin_domain ); ?></option>	                                
        </select>
		</p>
		<?php 
	}
}
?>