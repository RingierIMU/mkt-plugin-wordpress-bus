<?php
/**
 * ringier-bus
 *
 * @author Wasseem Khayrattee
 * @copyright 2021 Ringier
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Ringier Bus
 * Plugin URI: https://github.com/RingierIMU/mkt-plugin-wordpress-bus
 * Description: A plugin to push events to Ringier CDE via the BUS API whenever an article is created, updated or deleted
 * Version: 1.1.1
 * Requires at least: 4.7.0
 * Author: Ringier SA, Wasseem Khayrattee
 * Author URI: https://www.ringier.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ringier-bus
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
define('RINGIER_BUS_DS', DIRECTORY_SEPARATOR);
define('RINGIER_BUS_PLUGIN_VERSION', '1.1.1');
define('RINGIER_BUS_PLUGIN_MINIMUM_WP_VERSION', '4.7');
define('RINGIER_BUS_PLUGIN_DIR_URL', plugin_dir_url(__FILE__)); //has trailing slash at end
define('RINGIER_BUS_PLUGIN_DIR', plugin_dir_path(__FILE__)); //has trailing slash at end
define('RINGIER_BUS_PLUGIN_BASENAME', plugin_basename(RINGIER_BUS_PLUGIN_DIR));
define('RINGIER_BUS_PLUGIN_VIEWS', RINGIER_BUS_PLUGIN_DIR . 'views' . RINGIER_BUS_DS);
define('RINGIER_BUS_PLUGIN_CACHE_DIR', WP_CONTENT_DIR . RINGIER_BUS_DS . 'cache' . RINGIER_BUS_DS);

/**
 * load our main file now with composer autoloading
 */
require_once RINGIER_BUS_PLUGIN_DIR . RINGIER_BUS_DS . 'includes/vendor/autoload.php';

/**
 * Register main Hooks
 */
register_activation_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_activation']);
register_deactivation_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_deactivation']);
//the below will be handled by uninstall.php
//register_uninstall_hook(__FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_uninstall']);

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
