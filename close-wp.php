<div id="comments">
<?php
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
	
	global $post;
		$postid = $post->ID;
		$vkapi_get_comm = get_post_meta($postid, vkapi_comments, true);
		if ( $vkapi_get_comm == '1' || $vkapi_get_comm === '' )
			if ( comments_open() ) {
				$vkapi_some_desktop = get_option( 'vkapi_some_desktop' );
				$att;
				$att2 = get_option( 'vkapi_comm_autoPublish' );
				if ( get_option( 'vkapi_comm_graffiti' ) ) $att .= '"graffiti';
				if ( get_option( 'vkapi_comm_photo' ) ) $att .= ( empty( $att{0} ) ) ? '"photo' : ',photo';
				if ( get_option( 'vkapi_comm_audio' ) ) $att .= ( empty( $att{0} ) ) ? '"audio' : ',audio';
				if ( get_option( 'vkapi_comm_video' ) ) $att .= ( empty( $att{0} ) ) ? '"video' : ',video';
				if ( get_option( 'vkapi_comm_link' ) ) $att .= ( empty( $att{0} ) ) ? '"link' : ',link';	
				if ( ( empty( $att{0} ) ) ) $att = 'false'; else $att .= '"';
				if ( ( empty( $att2{0} ) ) ) $att2 = '0'; else $att2 = '1';
				if ( $vkapi_some_desktop ) {
					echo '<script type="text/javascript">
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
					jQuery("button.vkapi_vk").html(\'Комментарии Vkontakte (\'+num+\')\');
				};
				function onChange(num,last_comment,data,hash){
					last_comment = last_comment.replace(new RegExp("&#33;",\'g\'),"!");
							last_comment = html_entity_decode(last_comment);
					if (window.webkitNotifications.checkPermission() == 0) {
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
				echo '<script type="text/javascript">
					function onChangeRecalc(num,last_comment,data,hash){
					jQuery("button.vkapi_vk").html(\'Комментарии Vkontakte (\'+num+\')\');
					}';
				};
				$vkapi_url = get_bloginfo('wpurl');
				$vkapi_comm = get_post_meta($postid, 'vkapi_comm', TRUE);
				if ( $vkapi_comm ) $vkapi_comm_show = ' ('.$vkapi_comm.')';
				echo '</script>
				<button style="display: none" id="submit" onclick="showVK()" class="vkapi_vk" vkapi_notify="'.$postid.'" vkapi_url="'.$vkapi_url.'">'.$vkapi_button.$vkapi_comm_show.'</button>
				<div id="vkapi" onclick="showNotification()"></div>
				<script type="text/javascript">
					VK.Widgets.Comments(\'vkapi\', {width: '.get_option('vkapi_comm_width').', limit: '.get_option('vkapi_comm_limit').', attach: '.$att.', autoPublish: '.$att2.', height: '.get_option('vkapi_comm_height').', mini:1},'.$postid.');
				</script>';
				echo '<audio id="vkapi_sound" preload="auto" style="display: none">
						<source src="http://vk.com/mp3/bb2.mp3">
					</audio>';
			}
?>
</div>