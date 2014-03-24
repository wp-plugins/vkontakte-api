<div id="vkapi_comments">
    <?php do_action('vkapi_comments_template') ?>
</div>
<?php global $post;
echo "<div id=\"vkapi_wrapper\" data-vkapi-notify=\"{$post->ID}\"></div>"; ?>