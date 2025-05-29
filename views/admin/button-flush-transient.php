<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('flush_transients_nonce'); ?>
    <input type="hidden" name="action" value="flush_all_transients">
    <button type="submit" class="button button-secondary">Flush Auth Token</button>
</form>
