<?php
/**
 * WP-Bus
 *
 * @package RingiermuWPBus
 * @author Wasseem Khayrattee
 * @copyright 2021 Ringier
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WP-Bus
 * Plugin URI: https://github.com/wkhayrattee/wp-bus
 * Description: A plugin to push events to Ringier CDE via the BUS API whenever an article is created, updated or deleted
 * Version: 1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Wasseem Khayrattee
 * Author URI: https://github.com/wkhayrattee/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-bus
 * Domain Path: /languages
 *
 *
 * reference: https://developer.wordpress.org/plugins/
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
*/

/**
 * Make sure we don't expose any info if called directly
 */
if (!function_exists('add_action')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

/**
 * Some global constants for our use-case
 */
define('DS', DIRECTORY_SEPARATOR);
define('WP_BUS_RINGIER_VERSION', '1.0.0');
define('WP_BUS_RINGIER_MINIMUM_WP_VERSION', '4.0');
define('WP_BUS_RINGIER_PLUGIN_DIR_URL', plugin_dir_url(__FILE__)); //has trailing slash at end
define('WP_BUS_RINGIER_PLUGIN_DIR', plugin_dir_path(__FILE__)); //has trailing slash at end
define('WP_BUS_RINGIER_BASENAME', plugin_basename(WP_BUS_RINGIER_PLUGIN_DIR));
define('WP_BUS_RINGIER_PLUGIN_VIEWS', WP_BUS_RINGIER_PLUGIN_DIR . 'views' . DS);
define('WP_BUS_RINGIER_PLUGIN_CACHE_DIR', WP_CONTENT_DIR . DS . 'cache' . DS);

/**
 * load our main file now with composer autoloading
 */
require_once WP_BUS_RINGIER_PLUGIN_DIR . DS . 'includes/vendor/autoload.php';

/**
 * Register main Hooks
 */
register_activation_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_activation']);
register_deactivation_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_deactivation']);
register_uninstall_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_uninstall']);

/**
 * Leverage the ACF plugin superpowers
 */
\RingierBusPlugin\BusPluginClass::injectACF();

/**
 * Load the admin page interface
 */
if (is_admin()) {
    add_action('init', ['RingierBusPlugin\\BusPluginClass', 'adminInit']);
}

/**
 * Register our BUS API Mechanism
 */
\RingierBusPlugin\Bus\BusHelper::registerBusApiActions();
