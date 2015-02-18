// console info
console.time('kowack: callback.js loaded');
// Comments switcher
function showVK(Tshow, Thide){
	if ( !Tshow ) Tshow = 1000;
	if ( !Thide ) Thide = 1500;
	jQuery("#vkapi").show(Tshow);
	jQuery(".fb-comments").hide(Tshow);
	jQuery("#comments").hide(Tshow);
	jQuery("#respond").hide(Tshow);
};
function showFB(Tshow, Thide){
	if ( !Tshow ) Tshow = 1000;
	if ( !Thide ) Thide = 1500;
	jQuery(".fb-comments").show(Tshow);
	jQuery("#vkapi").hide(Thide);
	jQuery("#comments").hide(Thide);
	jQuery("#respond").hide(Thide);
};
function showWP(Tshow, Thide){
	if ( !Tshow ) Tshow = 1000;
	if ( !Thide ) Thide = 1500;
	jQuery("#comments").show(Tshow);
	jQuery("#respond").show(Tshow);
	jQuery("#vkapi").hide(Thide);
	jQuery(".fb-comments").hide(Thide);
};

// Mail callback + count plus
function vkapi_comm_plus(id,num,last_comment,datee,sign) {
	jQuery(function() {
		if ( num ) {
			var vkdata = {
				id: encodeURIComponent(id),
				num: encodeURIComponent(num),
				last_comment: encodeURIComponent(last_comment),
				date: encodeURIComponent(datee),
				sign: encodeURIComponent(sign),
				social: 'vk'
			};
		} else {
			var vkdata = {
				id: id,
				social: 'fb'
			};
		};
		var wpurl = jQuery("#vkapi_wrapper").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-mail.php', vkdata, function(e) {alert(e)});
	});
};

// Count minus
function vkapi_comm_minus(id,num,last_comment,datee,sign) {
	jQuery(function() {
		if ( num ) {
			onChangeRecalc(num,last_comment,datee,sign);
			var vkdata = {
				id: encodeURIComponent(id),
				num: encodeURIComponent(num),
				last_comment: encodeURIComponent(last_comment),
				date: encodeURIComponent(datee),
				sign: encodeURIComponent(sign)
			};
		} else {
			var vkdata = {
				id: id,
				social: 'fb'
			};
		};
		var wpurl = jQuery("#vkapi_wrapper").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-count.php', vkdata, function() {});
	});
};

// Comments padding
jQuery(function() {
	jQuery("#comments-title").css("padding","0 0");
});

// On add comment
function onChangePlus (num,last_comment,datee,sign) {
	var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
	vkapi_comm_plus (id,num,last_comment,datee,sign);
		last_comment = html_entity_decode ( last_comment );
	onChange(num,last_comment,datee,sign);
	onChangeRecalc(num,last_comment,datee,sign);
};
// On FB add comment
function onChangePlusFB(array) {
	var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
	vkapi_comm_plus (id,0,0,0,0);
}

// On del comment
function onChangeMinus (num,last_comment,datee,sign) {
	var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
	last_comment = html_entity_decode ( last_comment );
	vkapi_comm_minus (id,num,last_comment,datee,sign);
};
//On FB del comment
function onChangeMinusFB(array) {
	var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
	vkapi_comm_minus (id,0,0,0,0);
}

// On log in
function onSignon (response) {
	if (response.session) {
		var vkdata = {
			mid: response.session.mid
		};

			var parts = window.location.search.substr(1).split("&");
			var $_GET = {};
			for (var i = 0; i < parts.length; i++) {
				var temp = parts[i].split("=");
				$_GET[decodeURIComponent(temp[0])] = decodeURIComponent(temp[1]);
			}

		var wpurl = jQuery("button.vkapi_vk_widget").attr("vkapi_url");
		jQuery.post(wpurl+'/wp-content/plugins/vkontakte-api/vkapi-connect.php', vkdata, function( text ) {
			if ( text == 'Ok' ) {
				jQuery("#vkapi_status").html("<span style='color:green'>Result: ✔ "+text+"</span>");
				if ( $_GET['redirect_to'] ) {
					document.location.href = $_GET['redirect_to'];
				} else {
					document.location.href = document.location.href;
				};
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
jQuery(function() {
	VK.Observer.subscribe('widgets.comments.new_comment',onChangePlus);
	VK.Observer.subscribe('widgets.comments.delete_comment',onChangeMinus);
});
function myFBinit() {
		FB.Event.subscribe('comment.create', onChangePlusFB);
		FB.Event.subscribe('comment.remove', onChangeMinusFB);
};

// .center
jQuery.fn.center = function ($) {
    this.css("position","absolute");
    this.css("top", (($(window).height() - this.outerHeight()) / 2) +
                                                $(window).scrollTop() + "px");
    this.css("left", (($(window).width() - this.outerWidth()) / 2) +
                                                $(window).scrollLeft() + "px");
    return this;
}
// popup
jQuery.fn.kwk_popup = function ($) {
    this.center()
    return this;
}

// console info
console.timeEnd('kowack: callback.js loaded');