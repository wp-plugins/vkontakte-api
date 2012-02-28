<div class="wrap">
<div id="vkapi_icon32" class="icon32"></div>
<h2 style="margin: 0px 0px 10px 0px"><?php _e( 'VKontakte API Plugin - Settings', self::$plugin_domain); ?></h2>

<form method="post" action="options.php">

<?php wp_nonce_field( 'update-options' ); ?>

<table class="form-table">
	<span class="description"><?php _e("If you dont have <b>Application ID</b> and <b>Secure key</b> : go this <a href='http://vkontakte.ru/apps.php?act=add&site=1' target='_blank'>link</a> and register your site(blog). It's easy.", self::$plugin_domain); ?></span><br />
	<span class="description"><?php _e("If don't remember : go this <a href='http://vkontakte.ru/apps?act=settings' target='_blank'>link</a> and choose need application.", self::$plugin_domain); ?></span><br />
	<tr valign="top">
		<th scope="row"><label for="vkapi_appid"><?php _e('Application ID:', self::$plugin_domain); ?></label></th>
		<td><input type="text" name="vkapi_appid" value="<?php echo get_option('vkapi_appid'); ?>" /></td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="vkapi_api_secret"><?php _e('Secure key:', self::$plugin_domain); ?></label></th>
		<td><input type="text" name="vkapi_api_secret" value="<?php echo get_option('vkapi_api_secret'); ?>" /></td>
	</tr>
						<!-- Comments -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Comments: ', self::$plugin_domain); $comm = get_option( 'vkapi_show_comm' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td><select name="vkapi_show_comm" id="vkapi_show_comm" class="widefat">
			<option value="true"<?php selected( $comm, 'true' ); ?>><?php _e('Show', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $comm, 'false' ); ?>><?php _e('Dont show', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_comm_height"><?php _e('Height of widget(0=unlimited):', self::$plugin_domain); ?></label></th>
		<td><input size="10" type="text" name="vkapi_comm_height" value="<?php echo get_option('vkapi_comm_height'); ?>" /></td>
		<th scope="row"><label for="vkapi_comm_width"><?php _e('Block width in pixels(>300):', self::$plugin_domain) ?></label></th>
		<td><input size="10" type="text" name="vkapi_comm_width" value="<?php echo get_option('vkapi_comm_width'); ?>" /></td>
		<th scope="row"><label for="vkapi_comm_limit"><?php _e('Number of comments on the page (5-100):', self::$plugin_domain) ?></label></th>
		<td><input size="10" type="text" name="vkapi_comm_limit" value="<?php echo get_option('vkapi_comm_limit'); ?>" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_comm_autoPublish"><?php _e('AutoPublish to vk user wall', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_autoPublish" value="1" <?php echo get_option('vkapi_comm_autoPublish')?'checked':'';?> /></td>
		<th scope="row"><label for="vkapi_comm_show"><?php _e('Show first Vkontakte comments', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_show" value="1" <?php echo get_option('vkapi_comm_show')?'checked':'';?> /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_close_wp"><span style="color: red"><?php _e('Hide WordPress Comments', self::$plugin_domain); ?></span></label></th>
		<td><input type="checkbox" name="vkapi_close_wp" value="1" <?php echo get_option( 'vkapi_close_wp' )?'checked':'';?> /></td>
	</tr>
						<!-- Media -->
	<tr valign="top">
	<td class="section-title" colspan="6"><h3><?php _e('Media in comments:', self::$plugin_domain) ?></h3></td></tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_comm_graffiti"><?php _e('Graffiti:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_graffiti" value="1" <?php echo get_option('vkapi_comm_graffiti')?'checked':'';?> /></td>
		<th scope="row"><label for="vkapi_comm_photo"><?php _e('Photo:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_photo" value="1" <?php echo get_option('vkapi_comm_photo')?'checked':'';?> /></td>
		<th scope="row"><label for="vkapi_comm_audio"><?php _e('Audio:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_audio" value="1" <?php echo get_option('vkapi_comm_audio')?'checked':'';?> /></td>
		</tr>
	<tr valign="top"><th scope="row"><label for="vkapi_comm_video"><?php _e('Video:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_video" value="1" <?php echo get_option('vkapi_comm_video')?'checked':'';?> /></td>
		<th scope="row"><label for="vkapi_comm_link"><?php _e('Link:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_comm_link" value="1" <?php echo get_option('vkapi_comm_link')?'checked':'';?> /></td>
	</tr>
						<!-- SignOn -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Sign On: ', self::$plugin_domain); $like = get_option( 'vkapi_login' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td><select name="vkapi_login" id="vkapi_login" class="widefat">
			<option value="true"<?php selected( $like, 'true' ); ?>><?php _e('Enable', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $like, 'false' ); ?>><?php _e('Disable', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
						<!-- VK Like -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Like button: ', self::$plugin_domain); $like = get_option( 'vkapi_show_like' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td><select name="vkapi_show_like" id="vkapi_show_like" class="widefat">
			<option value="true"<?php selected( $like, 'true' ); ?>><?php _e('Show', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $like, 'false' ); ?>><?php _e('Dont show', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_like_bottom"><?php _e('Valign:', self::$plugin_domain); $valign = get_option('vkapi_like_bottom'); ?></label></th>
        <td colspan="2"><select name="vkapi_like_bottom" id="vkapi_like_bottom" class="widefat">
			<option value="0"<?php selected( $valign, '0' ); ?>><?php _e('top', self::$plugin_domain); ?></option>
			<option value="1"<?php selected( $valign, '1' ); ?>><?php _e('bottom', self::$plugin_domain); ?></option>	                                
        </select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_align"><?php _e('Align:', self::$plugin_domain); $align = get_option('vkapi_align'); ?></label></th>
        <td colspan="2"><select name="vkapi_align" id="vkapi_align" class="widefat">
			<option value="right"<?php selected( $align, 'right' ); ?>><?php _e('right', self::$plugin_domain); ?></option>
			<option value="left"<?php selected( $align, 'left' ); ?>><?php _e('left', self::$plugin_domain); ?></option>	                                
        </select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_like_type"><?php _e('Button style:', self::$plugin_domain); $type = get_option('vkapi_like_type'); ?></label></th>
        <td colspan="2"><select name="vkapi_like_type" id="vkapi_like_type" class="widefat">
			<option value="full"<?php selected( $type, 'full' ); ?>><?php _e('Button with text counter', self::$plugin_domain); ?></option>
			<option value="button"<?php selected( $type, 'button' ); ?>><?php _e('Button with mini counter', self::$plugin_domain); ?></option>	
			<option value="mini"<?php selected( $type, 'mini' ); ?>><?php _e('Mini button', self::$plugin_domain); ?></option>
			<option value="vertical"<?php selected( $type, 'vertical' ); ?>><?php _e('Mini button with counter at the top', self::$plugin_domain); ?></option>		
        </select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_like_verb"><?php _e('Statement:', self::$plugin_domain); $verb = get_option('vkapi_like_verb'); ?></label></th>
        <td colspan="2"><select name="vkapi_like_verb" id="vkapi_like_verb" class="widefat">
			<option value="0"<?php selected( $verb, '0' ); ?>><?php _e('I like', self::$plugin_domain); ?></option>
			<option value="1"<?php selected( $verb, '1' ); ?>><?php _e('It\'s interesting', self::$plugin_domain); ?></option>	                                
        </select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_like_cat"><?php _e('Show in Categories page and Home:', self::$plugin_domain); ?></label></th>
        <td colspan="2">
			<input type="checkbox" name="vkapi_like_cat" value="1" <?php echo get_option( 'vkapi_like_cat' )?'checked':'';?> />
        </td>
	</tr>
						<!-- VK Share -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Share button: ', self::$plugin_domain); $like = get_option( 'vkapi_show_share' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td><select name="vkapi_show_share" id="vkapi_show_share" class="widefat">
			<option value="true"<?php selected( $like, 'true' ); ?>><?php _e('Show', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $like, 'false' ); ?>><?php _e('Dont show', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_share_type"><?php _e('Button style:', self::$plugin_domain); $type = get_option('vkapi_share_type'); ?></label></th>
        <td colspan="2"><select name="vkapi_share_type" id="vkapi_share_type" class="widefat">
			<option value="round"<?php selected( $type, 'round' ); ?>><?php _e('Button', self::$plugin_domain); ?></option>
			<option value="round_nocount"<?php selected( $type, 'round_nocount' ); ?>><?php _e('Button without a Counter', self::$plugin_domain); ?></option>	
			<option value="button"<?php selected( $type, 'button' ); ?>><?php _e('Button Right Angles', self::$plugin_domain); ?></option>
			<option value="button_nocount"<?php selected( $type, 'button_nocount' ); ?>><?php _e('Button without a Counter Right Angles', self::$plugin_domain); ?></option>	
			<option value="link"<?php selected( $type, 'link' ); ?>><?php _e('Link', self::$plugin_domain); ?></option>
			<option value="link_noicon"<?php selected( $type, 'link_noicon' ); ?>><?php _e('Link without an Icon', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_share_text"><?php _e('Text on the button:', self::$plugin_domain); ?></label></th>
		<td><input type="text" name="vkapi_share_text" value="<?php echo get_option('vkapi_share_text'); ?>" /></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="vkapi_share_cat"><?php _e('Show in Categories page and Home:', self::$plugin_domain); ?></label></th>
        <td colspan="2">
			<input type="checkbox" name="vkapi_share_cat" value="1" <?php echo get_option( 'vkapi_share_cat' )?'checked':'';?> />
        </td>
	</tr>
						<!-- FB Like -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Facebook Like button: ', self::$plugin_domain); $like = get_option( 'fbapi_show_like' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td colspan="3">
			<span class="description"><?php _e("Facebook <b>App ID</b> : go this <a href='https://developers.facebook.com/apps'>link</a> and register your site(blog). It's easy.", self::$plugin_domain); ?></span><br />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="fbapi_appid"><?php _e('Facebook App ID:', self::$plugin_domain); ?></label></th>
		<td><input type="text" name="fbapi_appid" value="<?php echo get_option('fbapi_appid'); ?>" /></td>
	</tr>
	<tr valign="top">
		<td><select name="fbapi_show_like" id="fbapi_show_like" class="widefat">
			<option value="true"<?php selected( $like, 'true' ); ?>><?php _e('Show', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $like, 'false' ); ?>><?php _e('Dont show', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="fbapi_like_cat"><?php _e('Show in Categories page and Home:', self::$plugin_domain); ?></label></th>
        <td colspan="2">
			<input type="checkbox" name="fbapi_like_cat" value="1" <?php echo get_option( 'fbapi_like_cat' )?'checked':'';?> />
        </td>
	</tr>
						<!-- PlusOne -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('PlusOne button: ', self::$plugin_domain); $like = get_option( 'gpapi_show_like' ); ?></h3></td>
	</tr>
	<tr valign="top">
		<td><select name="gpapi_show_like" id="gpapi_show_like" class="widefat">
			<option value="true"<?php selected( $like, 'true' ); ?>><?php _e('Show', self::$plugin_domain); ?></option>
			<option value="false"<?php selected( $like, 'false' ); ?>><?php _e('Dont show', self::$plugin_domain); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
	    <th scope="row"><label for="gpapi_like_cat"><?php _e('Show in Categories page and Home:', self::$plugin_domain); ?></label></th>
        <td colspan="2">
			<input type="checkbox" name="gpapi_like_cat" value="1" <?php echo get_option( 'gpapi_like_cat' )?'checked':'';?> />
        </td>
	</tr>
						<!-- Decor -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('Decorations: ', self::$plugin_domain); ?></h3></td></tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_some_desktop"><?php _e('Desktop notifications:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_some_desktop" value="1" <?php echo get_option('vkapi_some_desktop')?'checked':'';?> /></td></tr>
						<!-- Non plagin -->
	<tr valign="top">
		<td class="section-title" colspan="6"><h3><?php _e('No Plugin Options: ', self::$plugin_domain); ?></h3></td></tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_some_logo_e"><?php _e('Custom login logo:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_some_logo_e" value="1" <?php echo get_option('vkapi_some_logo_e')?'checked':'';?> /></td>
	<th scope="row"><label for="vkapi_some_logo"><?php _e('Path :', self::$plugin_domain); ?></label></th>
	<td colspan="4">
		<a onclick='jQuery("#defpath").val("/wp-content/plugins/vkontakte-api/images/wordpress-logo.jpg");'>default</a>
		<br /><textarea id="defpath" rows="1" cols="65" placeholder="<?php _e('path to image...', self::$plugin_domain); ?>" name="vkapi_some_logo" ><?php echo get_option('vkapi_some_logo'); ?></textarea></td></tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_some_autosave_d"><?php _e('Disable Autosave Post Script:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_some_autosave_d" value="1" <?php echo get_option('vkapi_some_autosave_d')?'checked':'';?> /></td></tr>
	<tr valign="top">
		<th scope="row"><label for="vkapi_some_revision_d"><?php _e('Disable Revision Post Save:', self::$plugin_domain); ?></label></th>
		<td><input type="checkbox" name="vkapi_some_revision_d" value="1" <?php echo get_option( 'vkapi_some_revision_d' )?'checked':'';?> /></td></tr>
						<!-- Donate -->	
	<tr valign="top">
	<td colspan="6">
		<div class="infofooter">
			<div class="info">
				<span class="description"><?php _e('Support project (I need some eating...)', self::$plugin_domain) ?></span>
				<p><img class="webmoney" /><a href="wmk:payto?Purse=R771756795015&Amount=30&Desc=Поддержка%20разработки%20плагина%20VKontakte-API&BringToFront=Y"><?php _e('Donate Webmoney(R)', self::$plugin_domain) ?></a></p>
				<p><img class="webmoney" /><a href="wmk:payto?Purse=Z163761330315&Amount=3&Desc=Поддержка%20разработки%20плагина%20VKontakte-API&BringToFront=Y"><?php _e('Donate Webmoney(Z)', self::$plugin_domain) ?></a></p>
				<p><img class="webmoney" /><a href="wmk:payto?Purse=U247198770431&Amount=15&Desc=Поддержка%20разработки%20плагина%20VKontakte-API&BringToFront=Y"><?php _e('Donate Webmoney(U)', self::$plugin_domain) ?></a></p>
				<p><img class="yamoney" />Yandex-Money -> <b>410011126761075</b></p>
				<span class="description"><?php _e('Thanks...', self::$plugin_domain) ?></span>
			</div>
			<div class="kowack">
				<img src="https://ru.gravatar.com/userimage/19535946/ecd85e6141b40491d15f571e52c1cb77.jpeg" style="float:left"/>
				<p><span class="description">Разработчик:</span></p>	
				<p><span class="description"><a href="http://www.kowack.info/" target="_blank">Забродский Евгений (kowack).</a></span></p>		
			</div>
			<div class="sponsor">
				<img src="http://carabela.ru/carab.png" style="float:left"/>
				<p><span class="description">Неофициальный спонсор:</span></p>	
				<p><span class="description"><a href="http://carabela.ru/" target="_blank">"Пресс-центр и парусная флотилия "отряд "Каравелла" г. Екатеринбург.</a></span></p>		
			</div>
		</div>
	</td>
	</tr>
	<script>
		jQuery('div.kowack').hover(
			function(){
				jQuery(this).stop().fadeTo('fast', 1);
			},
			function(){
				jQuery(this).stop().fadeTo('slow', .2);
			}
		);
		jQuery('div.sponsor').hover(
			function(){
				jQuery(this).stop().fadeTo('fast', 1);
			},
			function(){
				jQuery(this).stop().fadeTo('slow', .2);
			}
		);
	</script>
	
</table>

<p class="submit">
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="vkapi_appid,vkapi_comm_width,vkapi_comm_limit,vkapi_comm_graffiti,vkapi_comm_photo,vkapi_comm_audio,vkapi_comm_video,vkapi_comm_link,vkapi_comm_autoPublish,vkapi_comm_height,vkapi_comm_show,,vkapi_align,vkapi_like_type,vkapi_like_verb,vkapi_show_like,vkapi_show_comm,vkapi_some_logo,vkapi_some_logo_e,vkapi_some_desktop,vkapi_some_autosave_d,vkapi_like_cat,vkapi_close_wp,vkapi_some_revision_d,vkapi_api_secret,vkapi_like_bottom,vkapi_login,vkapi_show_share,vkapi_share_cat,vkapi_share_type,vkapi_share_text,fbapi_appid,fbapi_show_like,fbapi_like_cat,gpapi_show_like,gpapi_like_cat" />
<input type="submit" class="button-primary" value="<?php _e('Save Changes', self::$plugin_domain) ?>" />
</p>

</form>
</div>