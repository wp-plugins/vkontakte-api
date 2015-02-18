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
	last_comment = html_entity_decode ( last_comment );
	vkapi_comm_plus (id,num,last_comment,datee,sign);
};

// On del comment
function onChangeMinus (num,last_comment,datee,sign) {
	var id = jQuery("button.vkapi_vk").attr("vkapi_notify");
	last_comment = html_entity_decode ( last_comment );
	vkapi_comm_minus (id,num,last_comment,datee,sign);
};

// On log in 
function onSignon (response) {
	if (response.session) {
		var vkdata = {
			mid: response.session.mid
		};
		var wpurl = jQuery("button.vkapi_vk_widget").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-connect.php', vkdata, function( text ) {
			if ( text == 'Ok' ) {
				jQuery("#vkapi_status").html("<span style='color:green'>Result: ✔ "+text+"</span>");
				location.reload(true);
			} else {
				jQuery("#vkapi_status").html("<span style='color:red'>Result: "+text+"</span>");
			};
		});
	} else {
	VK.Auth.login(onSignon);
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