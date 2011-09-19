function vkapi_callback(id,num,last_comment,datee,sign) {
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

jQuery(document).ready(function() {
	jQuery("#comments-title").css("padding","0 0");
});

function onChangePlus (num,last_comment,datee,sign) {
	var id = jQuery("button.vkapi_vk").attr("vkapi_notify");
	vkapi_callback (id,num,last_comment,datee,sign);
};

jQuery(document).ready(function() {
	VK.Observer.subscribe('widgets.comments.new_comment',onChangePlus);
});