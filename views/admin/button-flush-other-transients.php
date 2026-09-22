<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
      onsubmit="return confirm('This also clears the guards that stop one edit dispatching twice, so an article edited in the last few minutes may send a second event. Continue?');">
    <?php wp_nonce_field('flush_other_transients_nonce'); ?>
    <input type="hidden" name="action" value="flush_other_transients">
    <button type="submit" class="button">Flush Other Caches</button>
</form>
