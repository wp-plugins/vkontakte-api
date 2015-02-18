<?php
/*
Plugin Name: Vkontakte API
Plugin URI: http://http://www.kowack.info/projects/vk_api
Description: Add api functions from vkontakte.ru\vk.com in your own blog.
Version: 1.2
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
	register_deactivation_hook(__FILE__, array(&$this, 'deinstall'));
	add_action('admin_menu', array(&$this, 'create_menu'));
	add_action('wp_print_scripts', array(&$this, 'add_head'));
	add_filter('comments_template', array(&$this, 'add_tabs')); 
	wp_enqueue_script('jquery');
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
	}
	
	function create_menu() {
	add_options_page(__('Vkontate API Plugin Settings', $this->plugin_domain), __('Vkontakte API', $this->plugin_domain), 'administrator', __FILE__, array(&$this, 'settings_page'));	
	}
	
	function settings_page() {		
		include('vkapi-options.php');
	}
	
	function add_head() {
		if (!is_admin())
		{
			echo '<script type="text/javascript" src="http://userapi.com/js/api/openapi.js"></script>';
			//wp_enqueue_script('vkapi_api_script', 'http://userapi.com/js/api/openapi.js');
		}
	}
	
	function add_tabs($args) {
	global $post;
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
		VK.init({ 
			apiId: '.get_option('vkapi_appId').',
			onlyWidgets: true
		});
		VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$att.', autoPublish: '.$att2.', height: '.get_option('vkapi_comm_height').'},'.$post->ID.');
	</script>';
	echo $echo;	
	if(get_option('vkapi_comm_show')==1)echo '<script type="text/javascript">window.onload=showVK;</script>'; else echo '<script type="text/javascript">window.onload=showComments;</script>';
	//$args = dirname( __FILE__ ) . \'/empty.php';
	return $args;
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
?>