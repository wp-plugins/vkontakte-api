// Comments switcher
function showVK(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery("#vkapi").show(Tshow);
    jQuery(".fb-comments").hide(Thide);
    jQuery("#comments").hide(Thide);
    jQuery("#respond").hide(Thide);
};
function showFB(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery(".fb-comments").show(Tshow);
    jQuery("#vkapi").hide(Thide);
    jQuery("#comments").hide(Thide);
    jQuery("#respond").hide(Thide);
};
function showWP(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery("#comments").show(Tshow);
    jQuery("#respond").show(Tshow);
    jQuery("#vkapi").hide(Thide);
    jQuery(".fb-comments").hide(Thide);
};

// Mail callback + count plus
function vkapi_comm_plus(id, num, last_comment, datee, sign) {
    jQuery(function () {
        if (num) {
            var vkdata = {
                social:'vk',
                id:id,
                num:num,
                last_comment:last_comment,
                date:datee,
                sign:sign
            };
        } else {
            var vkdata = {
                social:'fb',
                id:id
            };
        }
        var wpurl = jQuery('#vkapi_wrapper').attr('vkapi_url');
        jQuery.post(wpurl + '/wp-content/plugins/vkontakte-api/vkapi-mail.php', vkdata, function () {
        });
    });
};

// Count minus
function vkapi_comm_minus(id, num, last_comment, datee, sign) {
    jQuery(function () {
        if (num) {
            onChangeRecalc(num, last_comment, datee, sign);
            var vkdata = {
                social:'vk',
                id:id,
                num:num,
                last_comment:last_comment,
                date:datee,
                sign:sign
            };
        } else {
            var vkdata = {
                social:'fb',
                id:id
            };
        }
        var wpurl = jQuery("#vkapi_wrapper").attr("vkapi_url");
        jQuery.post(wpurl + '/wp-content/plugins/vkontakte-api/vkapi-count.php', vkdata, function () {
        });
    });
};

// Comments padding
jQuery(function () {
    jQuery("#comments-title").css("padding", "0 0");
});

// On VK add comment
function onChangePlus(num, last_comment, datee, sign) {
    var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
    vkapi_comm_plus(id, num, last_comment, datee, sign);
    // last_comment = html_entity_decode(last_comment);
    onChange(num, last_comment, datee, sign);
    onChangeRecalc(num, last_comment, datee, sign);
};
// On VK del comment
function onChangeMinus(num, last_comment, datee, sign) {
    var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
    // last_comment = html_entity_decode(last_comment);
    vkapi_comm_minus(id, num, last_comment, datee, sign);
};

// On FB add comment
function onChangePlusFB(array) {
    var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
    vkapi_comm_plus(id, 0, 0, 0, 0);
}
// On FB del comment
function onChangeMinusFB(array) {
    var id = jQuery("#vkapi_wrapper").attr("vkapi_notify");
    vkapi_comm_minus(id, 0, 0, 0, 0);
}

// On log in
function onSignon(response) {
    if (response.session) {
        var vkdata = {
            mid:response.session.mid
        };

        var parts = window.location.search.substr(1).split("&");
        var $_GET = {};
        for (var i = 0; i < parts.length; i++) {
            var temp = parts[i].split("=");
            $_GET[decodeURIComponent(temp[0])] = decodeURIComponent(temp[1]);
        }

        var wpurl = jQuery("button.vkapi_vk_widget").attr("vkapi_url");
        jQuery.post(wpurl + '/wp-content/plugins/vkontakte-api/vkapi-connect.php', vkdata, function (text) {
            if (jQuery.trim(text) == 'Ok') {
                jQuery("#vkapi_status").html("<span style='color:green'>Result: ✔ " + text + "</span>");
                if ($_GET['redirect_to']) {
                    document.location.href = $_GET['redirect_to'];
                } else {
                    document.location.href = document.location.href;
                }
            } else {
                jQuery("#vkapi_status").html('<span style="color:red">Result: ' + text + '</span>');
            }
        });
    } else {
        VK.Auth.login(onSignon);
    }
};

// Decode like php
function html_entity_decode(str) {
    var text_area = document.createElement('textarea');
    text_area.innerHTML = str;
    return text_area.value;
}

// Subcsriber
function myVKinit() {
    VK.Observer.subscribe('widgets.comments.new_comment', onChangePlus);
    VK.Observer.subscribe('widgets.comments.delete_comment', onChangeMinus);
};

function myFBinit() {
    FB.Event.subscribe('comment.create', onChangePlusFB);
    FB.Event.subscribe('comment.remove', onChangeMinusFB);
};

// .center
jQuery.fn.center = function ($) {
    this.css("position", "absolute");
    this.css("top", (($(window).height() - this.outerHeight()) / 2) + $(window).scrollTop() + "px");
    this.css("left", (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft() + "px");
    return this;
}
// popup
function kwk_popup() {
    jQuery.each(jQuery.find('jshow'), function () {
        this.dialog({
            position:['right', 'bottom'],
            show:"blind",
            hide:"explode",
            stack:false
        });
    });
    return this;
}
kwk_popup();

// buben inside
jQuery(function ($) {
    $('body').prepend($('#vkapi_body'));
});