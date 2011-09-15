<?php
/*
Plugin Name: Vkontakte API
Plugin URI: http://www.kowack.info/projects/vk_api
Description: Add api functions from vkontakte.ru\vk.com in your own blog. <strong><a href="options-general.php?page=vkapi">Settings!</a></strong>
Version: 1.7
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

	var $plugin_domain = 'vkapi';
	var $plugin_url;
		
	function vk_api() {
		$this->plugin_url = trailingslashit( WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)) );
		global $wp_version;
		if ( version_compare( $wp_version, "3.0", "<" ) ) {
			$exit_msg = printf( __( 'VKontakte API plugin requires Wordpress 3.0 or newer. <a href="%s">Please update!</a>', $this->plugin_domain ), bloginfo('url').'wp-admin/update-core.php' );
			exit ($exit_msg);
		}
		
		$this->load_domain();
		
		register_activation_hook( __FILE__, array( &$this, 'install' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'pause' ) );
		register_uninstall_hook( __FILE__, array( &$this, 'deinstall' ) );
		add_action( 'admin_menu', array( &$this, 'create_menu' ), 1 );
		add_action( 'wp_print_scripts', array( &$this, 'add_head' ) ); 
		add_action( 'init', array( &$this, 'widget_init' ) );
		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widgets' ) );
		add_action( 'admin_init', array( &$this, 'add_css' ) );
		add_action( 'wp_print_styles', array( &$this, 'add_css_admin' ) );
		add_filter( 'the_content', array( &$this, 'add_buttons'), 88 );
		add_filter( 'contextual_help', array( &$this, 'vkapi_contextual_help' ), 1, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'own_actions_links' ) );
		wp_enqueue_script( 'jquery' );
		wp_register_script( 'vkapi_callback', $this->plugin_url . '/js/callback.js');
		if ( !is_admin() ) wp_enqueue_script ( 'vkapi_callback' );
		
		function close_wp ( $file ) {
			global $post;
			if ( !( is_singular() && ( have_comments() || comments_open() ) ) ) {
				return;
			}
			return dirname(__FILE__) . '/close-wp.php';
		}
		
		function do_empty ( $args ) {
			return;
		}

		$vkapi_close_wp = get_option( 'vkapi_close_wp' );
		if ( $vkapi_close_wp ) {
			add_filter( 'comments_template', 'close_wp' );
			add_filter( 'comments_number', 'do_empty' );
			}
		else add_action( 'comments_template', array( &$this, 'add_tabs') );
		
		$logo_e = get_option( 'vkapi_some_logo_e' );
		if ( $logo_e ) add_action( 'login_head', array(&$this, 'change_login_logo') );
		
		$autosave_d = get_option( 'vkapi_some_autosave_d' );
		if ( $autosave_d ) add_action( 'wp_print_scripts', array(&$this, 'disable_autosave') );
		
		$vkapi_some_revision_d = get_option( 'vkapi_some_revision_d' );
		if ( $vkapi_some_revision_d ) {
			define ( 'WP_POST_REVISIONS', 0 );
			remove_action( 'pre_post_update', 'wp_save_post_revision' );
		}
	}
	
	function disable_autosave() {
		wp_deregister_script( 'autosave' );
	}
	
	function pause() {  
		unregister_widget( 'VKAPI_Community' );
		unregister_widget( 'VKAPI_Recommend' );
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
		add_option( 'vkapi_align', 'left' );
		add_option( 'vkapi_show_comm', 'true' );
		add_option( 'vkapi_show_like', 'true' );
		add_option( 'vkapi_some_logo_e', '1' );
		add_option( 'vkapi_some_logo', $this->plugin_url.'images/wordpress-logo.jpg' );
		add_option( 'vkapi_some_desktop', '1' );
		add_option( 'vkapi_some_autosave_d', '1' );
		add_option( 'vkapi_some_revision_d', '1' );
		add_option( 'vkapi_close_wp', '0' );
	}
	
	function deinstall() {
		delete_option( 'vkapi_appId' );
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
		delete_option( 'vkapi_align' );
		delete_option( 'vkapi_show_comm' );
		delete_option( 'vkapi_show_like' );
		delete_option( 'vkapi_some_logo_e' );
		delete_option( 'vkapi_some_logo' );
		delete_option( 'vkapi_some_desktop' );
		delete_option( 'vkapi_some_autosave_d' );
		delete_option( 'vkapi_some_revision_d' );
		delete_option( 'vkapi_close_wp' );
	}
	
	function settings_page() {		
		include( 'vkapi-options.php' );
	}
	
	function add_head() {
		if ( !is_admin() ) {
			$appId = get_option( 'vkapi_appId' );
			echo '<meta property="vk:app_id" content="'.$appId.'" />';
			echo '<script type="text/javascript" src="http://userapi.com/js/api/openapi.js"></script>';
		}
	}
	
	function create_menu() {
		global $vkapi_page;
		$vkapi_page = add_options_page(__( 'Vkontate API Plugin Settings', $this->plugin_domain), __('Vkontakte API', $this->plugin_domain), 'manage_options', 'vkapi', array( &$this, 'settings_page') );
		add_action( 'admin_print_styles-' . $vkapi_page, array( &$this, 'add_css_admin' ) );
	}
	
	function add_css () {
		wp_register_style( 'vkapi_admin', plugins_url('css/admin.css?ver=3.2.3', __FILE__) );
	}
	
	function add_css_admin () {
		wp_enqueue_style( 'vkapi_admin' );
	}
	
	function vkapi_contextual_help( $contextual_help, $screen_id, $screen ) {
		global $vkapi_page;
		if ( $screen_id == $vkapi_page ) {
			$contextual_help = '
			<strong>"Disable Autosave Post Script"</strong> - Выключает астосохранение при редактировании/добавлении новой записи(поста).<br />
			Это полезно тем, что теперь не будет тучи бесполезных черновиков(ведь зачем заполнять ими нашу базу данных?)<br />
			<strong>Disable Revision Post Save:</strong> - устанавливает количество выше упомянутых черновиков в ноль.<br /><br />
			Все вопросики и пожелания <strong><a href="http://www.kowack.info/projects/vk_api/" title="Vkontakte API Home">сюдатачки</a></strong> или <strong><a href="http://vkontakte.ru/vk_wp" title="Vkontakte API Club">тутачки</a></strong>.';
		}
		return $contextual_help;
	}
	
	function add_tabs() {
		$vkapi_show_comm = get_option( 'vkapi_show_comm' );
		if ( $vkapi_show_comm == 'true' ) {
			global $post;
			if ( comments_open() ) {
				$vkapi_some_desktop = get_option( 'vkapi_some_desktop' );
				$postid = $post->ID;
				$att;
				$att2 = get_option( 'vkapi_comm_autoPublish' );
				$vkapi_button = __('Vkontakte comments', $this->plugin_domain);
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
							"http://vkontakte.ru/images/lnkinner32.gif", "Успех",
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
				function onChange(num,last_comment,data,hash){
					if (window.webkitNotifications.checkPermission() == 0) {
							last_comment = last_comment.replace(new RegExp("&#33;",\'g\'),"!");
							last_comment = last_comment.replace(new RegExp("&#39;",\'g\'),"\'");
							last_comment = last_comment.replace(new RegExp("&#036;",\'g\'),"$");
							last_comment = last_comment.replace(new RegExp("&#092;",\'g\'),"\\\");
							last_comment = last_comment.replace(new RegExp("&quot;",\'g\'),\'"\');
						Time = new Date();
						Hour = Time.getHours();
						Min = Time.getMinutes();
						Sec = Time.getSeconds();
						var notification = window.webkitNotifications.createNotification(
						"http://vkontakte.ru/images/lnkinner32.gif", "Время "+Hour+":"+Min+":"+Sec,
						last_comment);
						notification.show();
						setTimeout(function(){notification.cancel();}, \'10000\');
					} else {
						jQuery("#vkapi").append(\'<button id="submit" class="vkapi_remove" onclick="vkapi_requestPermission()">Разрешить всплывающие сообщения</button>\');
					};
					jQuery("button.vkapi_vk").html(\''.$vkapi_button.' (\'+num+\')\'); 
				};';
				} else {
				echo 'function onChange(num,last_comment,data,hash){
					jQuery("button.vkapi_vk").html(\''.$vkapi_button.' (\'+num+\')\');
					}';
				};
				echo '</script>
				<br />
				<button id="submit" onclick="showVK()" class="vkapi_vk" vkapi_notify="'.$postid.'">'.$vkapi_button.'</button>
				<button id="submit" onclick="showComments()">'.__('WordPress comments', $this->plugin_domain).' ('.get_comments_number().') </button><br /><br /><br />
				<div id="vkapi" onclick="showNotification()"></div>
				<script type="text/javascript">
					VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$att.', autoPublish: '.$att2.', height: '.get_option('vkapi_comm_height').'},'.$postid.');
				</script>';
				add_action ( 'wp_footer', array( &$this, 'add_footer' ) );
			}
		}
	}

	function add_footer () {
		if ( get_option( 'vkapi_comm_show' ) == 1 ) 
			echo '<script type="text/javascript">window.onload=showVK;</script>';
		else echo '<script type="text/javascript">window.onload=showComments;</script>';
	}
	
	function add_buttons ( $args ) {
		if ( !is_feed() ) { 
			$like_cat = get_option( 'vkapi_like_cat' );
			if ( $like_cat ) $this->vkapi_buttons( &$args ); 
			else if ( !is_home() && !is_category() && !is_archive() ) {
				$this->vkapi_buttons( &$args );
			}
		}
		return $args;
	}
	
	function vkapi_buttons ( $args ) {
		$like = get_option( 'vkapi_show_like' );
			if( $like == 'true' ) {
				global $post;
				$postid = $post->ID;
				$align = get_option( 'vkapi_align' );
				$type = get_option( 'vkapi_like_type' );
				$verb = get_option( 'vkapi_like_verb' );
				$args .= "<div float=\"$align\"><div id=\"vkapi_like_$postid\"></div></div><br />
				<script type=\"text/javascript\">
					VK.Widgets.Like('vkapi_like_$postid', {width: 200, type: '$type', verb: '$verb'}, $postid);
				</script>";
			}
		return $args;
	}
	
	function change_login_logo() {
		$logo = get_option( 'vkapi_some_logo' );
		echo '<style type="text/css">
			#login { width: 380px !important}
			h1 a { background-image:url('.$logo.') !important; width: 380px !important; height: 130px !important;}
		</style>';
	}
	
	function widget_init() {
		register_widget( 'VKAPI_Community' );
		register_widget( 'VKAPI_Recommend' );
		do_action( 'widgets_init' );
	}

	function dashboard_widget_function() {
		echo "<script src=\"http://widgets.twimg.com/j/2/widget.js\"></script>
			<script>
			new TWTR.Widget({
			  version: 2,
			  type: 'profile',
			  rpp: 5,
			  interval: 4000,
			  width: 'auto',
			  height: 250,
			  theme: {
				shell: {
				  background: '#fff',
				  color: '#464646'
				},
				tweets: {
				  background: '#fff',
				  color: '#333',
				  links: '#21759B'
				}
			  },
			  features: {
				scrollbar: false,
				loop: false,
				live: true,
				hashtags: true,
				timestamp: true,
				avatars: false,
				behavior: 'default'
			  }
			}).render().setUser('kowackinfo').start();
			</script>";
	}
	
	function own_actions_links( $links ) {
		$settings_link = '&nbsp;<a href="options-general.php?page=vkapi"><img src="'.$this->plugin_url.'images/set.png" width="20" />&nbsp;</a>';
		array_push( $links, $settings_link ); 
		return $links;
	}

	function add_dashboard_widgets() {
		wp_add_dashboard_widget( 'vkapi_dashboard_widget', 'VKAPI: Новости', array( &$this,'dashboard_widget_function') );	
		global $wp_meta_boxes;
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$vkapi_widget_backup = array( 'vkapi_dashboard_widget' => $normal_dashboard['vkapi_dashboard_widget'] );
		unset( $normal_dashboard['vkapi_dashboard_widget'] );
		$sorted_dashboard = array_merge( $vkapi_widget_backup, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}
	
	function load_domain() {
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}
	
}

else :

	exit( __( 'Class VK_api already declared!', $this->plugin_domain ) );
	
endif;


if ( class_exists( 'VK_api' ) ) :
	
	$VK_api = new VK_api();

endif;

class VKAPI_Community extends WP_Widget {

	function __construct() {
		$plugin_domain = 'vkapi';
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Information about VKontakte group', $this->plugin_domain ) );
		parent::__construct( 'vkapi_community', $name = __( 'VKAPI: Community Users', $this->plugin_domain ), $widget_ops );
	}
	
	function widget($args, $instance) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_mode = 2;
		$vkapi_gid = $instance['gid'];
		$vkapi_width = $instance['width'];
		if( $instance['type'] == 'users' ) $vkapi_mode = 0;
		if( $instance['type'] == 'news' ) $vkapi_mode = 2;
		if( $instance['type'] == 'name' ) $vkapi_mode = 1;
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo '<br /><div id="'.$vkapi_divid.'"></div>
		<script type="text/javascript">
			VK.Widgets.Group("'.$vkapi_divid.'", {mode: '.$vkapi_mode.', width: "'.$vkapi_width.'", height: "1"}, '.$vkapi_gid.');
		</script><br />';
		echo $after_widget . '</div>';
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'type' => 'users', 'title' => '', 'width' => '0','gid' => '28197069' ) );
		$title = esc_attr( $instance['title'] );
		$gid = esc_attr( $instance['gid'] );
		$width = esc_attr( $instance['width'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id( 'gid' ); ?>"><?php _e( 'ID of group (can be seen by reference to statistics):', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'gid' ); ?>" name="<?php echo $this->get_field_name( 'gid' ); ?>" type="text" value="<?php echo $gid; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" />
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

class VKAPI_Recommend extends WP_Widget {

	function __construct() {
		$plugin_domain = 'vkapi';
		load_plugin_textdomain( $this->plugin_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$widget_ops = array( 'classname' => 'widget_vkapi', 'description' => __( 'Top site on basis of "I like" statistics', $this->plugin_domain ) );
		parent::__construct( 'vkapi_recommend', $name = __( 'VKAPI: Recommends' , $this->plugin_domain), $widget_ops);
		}

		function widget( $args, $instance ) {
		extract( $args );
		$vkapi_divid = $args['widget_id'];
		$vkapi_limit = $instance['limit'];
		$vkapi_period = $instance['period'];
		$vkapi_verb = $instance['verb'];
		echo $before_widget . $before_title . $instance['title'] . $after_title . '<div id="'.$vkapi_divid.'_wrapper">';
		$vkapi_divid .= "_wrapper";
		echo '<br /><div id="'.$vkapi_divid.'"></div>
		<script type="text/javascript">
			VK.Widgets.Recommended("'.$vkapi_divid.'", {limit: '.$vkapi_limit.', period: \''.$vkapi_period.'\', verb: '.$vkapi_verb.'});
		</script><br />';
		echo '</div>' . $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'limit' => '5', 'period' => 'month', 'verb' => '0' ) );
		$title = esc_attr( $instance['title'] );
		$limit = esc_attr( $instance['limit'] );

		?><p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Number of posts:', $this->plugin_domain ); ?>
		<input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" />
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
?>