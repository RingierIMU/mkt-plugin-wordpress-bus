<?php
/**
 * Automatically invoked when plugin is uninstalled
 * We want to clear options saved in db.
 *
 * ref: https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @author Wasseem Khayrattee (@wkhayrattee)
 *
 *
 * NOTE to self:
 * A plugin should always have an uninstall.php - I QUOTE:
 *
 * "If the plugin can not be written without running code within the plugin, then
 * the plugin should create a file named 'uninstall.php' in the base plugin
 * folder. This file will be called, if it exists, during the uninstall process
 * bypassing the uninstall hook. The plugin, when using the 'uninstall.php'
 * should always check for the 'WP_UNINSTALL_PLUGIN' constant, before
 * executing."
 *
 * Source: https://github.com/WordPress/WordPress/blob/9dcd0110fb23b72ac4715ec1b527ba66db6ca7e4/wp-includes/plugin.php#L686-691
 */

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

//we are going to use the register_uninstall_hook() as this seems sufficient for our use-case for now
