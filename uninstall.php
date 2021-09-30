<?php
/**
 * Automatically invoked when plugin is uninstalled
 * We want to clear options saved in db.
 *
 * ref: https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @author Wasseem Khayrattee (@wkhayrattee)
 */

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
