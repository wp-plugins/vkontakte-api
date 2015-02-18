// Mail callback + count plus
function vkapi_comm_plus(id,num,last_comment,datee,sign) {
	jQuery(document).ready(function() {
		var vkdata = {
			id: id,
			num: num,
			last_comment: last_comment,
			date: datee,
			sign: sign
		};
		var wpurl = jQuery("button.vkapi_vk").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-mail.php', vkdata, function() {});
	});
};

// Count minus
function vkapi_comm_minus(id,num,last_comment,datee,sign) {
	jQuery(document).ready(function() {
		var vkdata = {
			id: id,
			num: num,
			last_comment: last_comment,
			date: datee,
			sign: sign
		};
		var wpurl = jQuery("button.vkapi_vk").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-count.php', vkdata, function() {});
	});
};

// Comments padding
jQuery(document).ready(function() {
	jQuery("#comments-title").css("padding","0 0");
});

// On add comment 
function onChangePlus (num,last_comment,datee,sign) {
	var id = jQuery("button.vkapi_vk").attr("vkapi_notify");
	vkapi_comm_plus (id,num,last_comment,datee,sign);
};

// On del comment
function onChangeMinus (num,last_comment,datee,sign) {
	var id = jQuery("button.vkapi_vk").attr("vkapi_notify");
	vkapi_comm_minus (id,num,last_comment,datee,sign);
};

// On log in
function onSignon (response) {
	if (response.session) {
		var vkdata = {
			method: 'getProfiles',
			id: response.session.mid,
			params: 'uid,first_name,nickname,last_name,screen_name,photo_medium_rec'
		};
		var wpurl = jQuery("button.vkapi_vk").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-connect.php', vkdata, function() {});
	};
};

// Decode like php
function html_entity_decode(str) {
  var tarea=document.createElement('textarea');
  tarea.innerHTML = str;
  return tarea.value;
}

// Subcsriber
jQuery(document).ready(function() {
	VK.Observer.subscribe('widgets.comments.new_comment',onChangePlus);
	VK.Observer.subscribe('widgets.comments.delete_comment',onChangeMinus);
});