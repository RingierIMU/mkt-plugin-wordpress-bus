<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
      onsubmit="return confirm('Every image will be hashed again on its next event. On a site with offloaded media that means downloading each image once more. Continue?');">
    <?php wp_nonce_field('flush_image_hashes_nonce'); ?>
    <input type="hidden" name="action" value="flush_image_hashes">
    <button type="submit" class="button">Flush Image Content Hashes</button>
</form>
