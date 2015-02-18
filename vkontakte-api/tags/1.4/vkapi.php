<?php
/*
Plugin Name: Vkontakte API
Plugin URI: http://http://www.kowack.info/projects/vk_api
Description: Add api functions from vkontakte.ru\vk.com in your own blog.
Version: 1.4
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

// Need PHP > 5.2.3

if (!class_exists('VK_api')) :

class VK_api {

	var $plugin_domain = 'vkapi';
	var $plugin_url;
		
	function vk_api(){
	
	$this->plugin_url = trailingslashit(WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)));
	global $wp_version;
	$exit_msg = __('VKontakte API plugin requires Wordpress 2.8 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update!</a>', $this->plugin_domain);
	if (version_compare($wp_version,"2.8","<")){
	exit ($exit_msg);
	}
	if (is_admin())
	$this->load_domain();
	
	register_activation_hook(__FILE__, array(&$this, 'install'));
	register_deactivation_hook(__FILE__, array(&$this, 'pause'));
	register_uninstall_hook(__FILE__, array(&$this, 'deinstall'));
	add_action('admin_menu', array(&$this, 'create_menu'),1);
	add_action('wp_print_scripts', array(&$this, 'add_head')); 
	add_action('init', array(&$this, 'widget_init'));
	add_action('comments_template', array(&$this, 'add_tabs'));
	add_filter('the_content', array(&$this, 'add_buttons'),88);
	wp_enqueue_script('jquery');
	}
	
	function pause() {  
		unregister_widget('VKAPI_Community');
		unregister_widget('VKAPI_Recommend');
	}
	
	function install(){
		add_option('vkapi_appid', 'Need Your AppId !!!');
		add_option('vkapi_comm_width', '600');
		add_option('vkapi_comm_limit', '15');
		add_option('vkapi_comm_graffiti', '1');
		add_option('vkapi_comm_photo', '1');
		add_option('vkapi_comm_audio', '1');
		add_option('vkapi_comm_video', '1');
		add_option('vkapi_comm_link', '1');
		add_option('vkapi_comm_autoPublish', '1');
		add_option('vkapi_comm_height', '0');
		add_option('vkapi_comm_show', '0');
		add_option('vkapi_like_type', 'full');
		add_option('vkapi_like_verb', '0');
		add_option('vkapi_align', 'left');
		add_option('vkapi_show_comm', 'true');
		add_option('vkapi_show_like', 'true');
	}
	
	function deinstall() {
		delete_option('vkapi_appId');
		delete_option('vkapi_comm_width');
		delete_option('vkapi_comm_limit');
		delete_option('vkapi_comm_graffiti', '1');
		delete_option('vkapi_comm_photo', '1');
		delete_option('vkapi_comm_audio', '1');
		delete_option('vkapi_comm_video', '1');
		delete_option('vkapi_comm_link', '1');
		delete_option('vkapi_comm_autoPublish');
		delete_option('vkapi_comm_height');
		delete_option('vkapi_comm_show');
		delete_option('vkapi_like_type');
		delete_option('vkapi_like_verb');
		delete_option('vkapi_align');
		delete_option('vkapi_show_comm');
		delete_option('vkapi_show_like');
	}
	
	function create_menu() {
		add_options_page(__('Vkontate API Plugin Settings', $this->plugin_domain), __('Vkontakte API', $this->plugin_domain), 'administrator', __FILE__, array(&$this, 'settings_page'));	
	}
	
	function settings_page() {		
		include('vkapi-options.php');
	}
	
	function add_head() {
		if (!is_admin()) {
			$appId = get_option('vkapi_appId');
			echo '<meta property="vk:app_id" content="'.$appId.'" />';
			echo '<script type="text/javascript" src="http://userapi.com/js/api/openapi.js"></script>';
			echo '<script type="text/javascript" src="http://vkontakte.ru/js/api/share.js?9" charset="windows-1251"></script>';
		}
	}
	
	function add_tabs() {
		$vkapi_show_comm = get_option('vkapi_show_comm');
		if($vkapi_show_comm=='true'){
			global $post;
			$postid = $post->ID;
			$att;
			$att2 = get_option('vkapi_comm_autoPublish');
			if(get_option('vkapi_comm_graffiti')=='1')$att.= '"graffiti';
			if(get_option('vkapi_comm_photo')=='1')$att.= (empty($att{0}))?'"photo':',photo';
			if(get_option('vkapi_comm_audio')=='1')$att.= (empty($att{0}))?'"audio':',audio';
			if(get_option('vkapi_comm_video')=='1')$att.= (empty($att{0}))?'"video':',video';
			if(get_option('vkapi_comm_link')=='1')$att.= (empty($att{0}))?'"link':',link';	
			if((empty($att{0})))$att='false';else $att .= '"';
			if((empty($att2{0})))$att2='0';else $att2 = '1';
			$echo='<script type="text/javascript">
			jQuery(document).ready(function() {
			jQuery("#comments-title").css("padding","0 0");
			});
			function showVK(){
				jQuery("#vkapi").show(2000);
				jQuery("#comments").hide(2500);
				jQuery("#respond").hide(2500);
				};
			function showComments(){
				jQuery("#comments").show(2000);
				jQuery("#respond").show(2000);
				jQuery("#vkapi").hide(2000);
				};
			</script>
			<br />
			<button id="submit" onclick="showVK()">Комментарии Vkontakte</button>
			<button id="submit" onclick="showComments()">Комментарии Wordpress</button><br /><br /><br />
			<div id="vkapi"></div>
			<script type="text/javascript">
				VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$att.', autoPublish: '.$att2.', height: '.get_option('vkapi_comm_height').'},'.$postid.');
			</script>';
			echo $echo;	
			if(get_option('vkapi_comm_show')==1)echo '<script type="text/javascript">window.onload=showVK;</script>'; else echo '<script type="text/javascript">window.onload=showComments;</script>';
		}
	}

	function add_buttons ($args) {
		if(!is_feed() && !is_home()){
			$like = get_option('vkapi_show_like');
			if($like=='true'){
				global $post;
				$postid = $post->ID;
				$align = get_option('vkapi_align');
				$type = get_option('vkapi_like_type');
				$verb = get_option('vkapi_like_verb');
				$args.="<div float=\"$align\"><div id=\"vkapi_like\"></div></div>
				<script type=\"text/javascript\">
					VK.Widgets.Like('vkapi_like', {width: 200, type: '$type', verb: '$verb'}, $postid);
				</script>";
			}
		}
		return $args;
	}
	
	function widget_init(){
		register_widget('VKAPI_Community');
		register_widget('VKAPI_Recommend');
		do_action('widgets_init');
	}
	
	function load_domain() {
		$mofile = dirname(__FILE__) . '/lang/' . $this->plugin_domain . '-' . get_locale() . '.mo';
		load_textdomain($this->plugin_domain, $mofile);
	}
}

else :

	exit(__('Class VK_api already declared!', $this->plugin_domain));
	
endif;


if (class_exists('VK_api')) :
	
	$VK_api = new VK_api();

endif;

class VKAPI_Community extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'vkapi_community', 'description' => __('Информация о группе вконтакте', $this->plugin_domain) );
		parent::__construct('vkapi_community', $name = __('VK: Community Users', 'vkapi'), $widget_ops);
	}

	function widget($args, $instance) {
		extract( $args );

		echo $before_title
		. $instance['title']
		. $after_title;
		$vkapi_divid = $args['widget_id'];
		$vkapi_mode = 2;
		$vkapi_gid = $instance['gid'];
		$vkapi_width = $instance['width'];
		if($instance['type']=='users')$vkapi_mode = 0;
		if($instance['type']=='news')$vkapi_mode = 2;
		if($instance['type']=='name')$vkapi_mode = 1;
		echo '<br /><div id="'.$vkapi_divid.'"></div>
		<script type="text/javascript">
			VK.Widgets.Group("'.$vkapi_divid.'", {mode: '.$vkapi_mode.', width: "'.$vkapi_width.'", height: "1"}, '.$vkapi_gid.');
		</script><br />';
	}

	function update($new_instance, $old_instance) {
		return $new_instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'type' => 'users', 'title' => '', 'width' => '0','gid' => '28197069') );
		$title = esc_attr( $instance['title'] );
		$gid = esc_attr( $instance['gid'] );
		$width = esc_attr( $instance['width'] );

		?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id('gid'); ?>"><?php _e('ID группы (видно по ссылке на статистику):'); ?>
		<input class="widefat" id="<?php echo $this->get_field_id('gid'); ?>" name="<?php echo $this->get_field_name('gid'); ?>" type="text" value="<?php echo $gid; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('ID группы (видно по ссылке на статистику):'); ?>
		<input class="widefat" id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" type="text" value="<?php echo $width; ?>" />
		</label></p>
		
		<p>
        <label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'Тип виджета:' ); ?></label>
        <select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
        <option value="users"<?php selected( $instance['type'], 'users' ); ?>><?php _e('Список пользователей'); ?></option>
	    <option value="news"<?php selected( $instance['type'], 'news' ); ?>><?php _e('Новости группы'); ?></option>	                                
		<option value="name"<?php selected( $instance['type'], 'name' ); ?>><?php _e('Только название' ); ?></option>
        </select>
		</p>
		<?php }
}

class VKAPI_Recommend extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'vkapi_recommend', 'description' => __('Топ сайта на основе "Мне нравиться"', $this->plugin_domain) );
		parent::__construct('vkapi_recommend', $name = __('VK: Recommends (Beta)', 'vkapi'), $widget_ops);
		}

		function widget($args, $instance) {
		extract( $args );

		echo $before_title
		. $instance['title']
		. $after_title;
		$vkapi_divid = $args['widget_id'];
		$vkapi_limit = $instance['limit'];
		$vkapi_period = $instance['period'];
		$vkapi_verb = $instance['verb'];
		echo '<br /><div id="'.$vkapi_divid.'"></div>
		<script type="text/javascript">
			VK.Widgets.Recommended("'.$vkapi_divid.'", {limit: '.$vkapi_limit.', period: \''.$vkapi_period.'\', verb: '.$vkapi_verb.'});
		</script><br />';
	}

	function update($new_instance, $old_instance) {
		return $new_instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'limit' => '5', 'period' => 'month', 'verb' => '0') );
		$title = esc_attr( $instance['title'] );
		$limit = esc_attr( $instance['limit'] );

		?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</label></p>
		
		<p><label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Количество:'); ?>
		<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
		</label></p>
		
		<p>
        <label for="<?php echo $this->get_field_id('period'); ?>"><?php _e( 'Период:' ); ?></label>
        <select name="<?php echo $this->get_field_name('period'); ?>" id="<?php echo $this->get_field_id('period'); ?>" class="widefat">
        <option value="day"<?php selected( $instance['period'], 'day' ); ?>><?php _e('Сутки'); ?></option>
	    <option value="week"<?php selected( $instance['period'], 'week' ); ?>><?php _e('Неделя'); ?></option>	                                
		<option value="month"<?php selected( $instance['period'], 'month' ); ?>><?php _e('Месяц' ); ?></option>
        </select>
		</p>
		
		<p>
        <label for="<?php echo $this->get_field_id('verb'); ?>"><?php _e( 'Формулировка:' ); ?></label>
        <select name="<?php echo $this->get_field_name('verb'); ?>" id="<?php echo $this->get_field_id('verb'); ?>" class="widefat">
        <option value="0"<?php selected( $instance['verb'], '0' ); ?>><?php _e('Понравилось ...'); ?></option>
	    <option value="1"<?php selected( $instance['verb'], '1' ); ?>><?php _e('Интересно ...'); ?></option>	                                
        </select>
		</p>
		<?php 
	}
}
?>