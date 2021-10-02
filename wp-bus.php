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
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit;
}

/**
 * Some global constants for our use-case
 */
define('DS', DIRECTORY_SEPARATOR);
define('WP_BUS_RINGIER_VERSION', '1.0.0');
define('WP_BUS_RINGIER_MINIMUM_WP_VERSION', '4.0');
define('WP_BUS_RINGIER_PLUGIN_DIR', plugin_dir_path(__FILE__)); //has trailing slash at end
define( 'WP_BUS_RINGIER_BASENAME', plugin_basename( WP_BUS_RINGIER_PLUGIN_DIR ) );
define('WP_BUS_RINGIER_PLUGIN_VIEWS', WP_BUS_RINGIER_PLUGIN_DIR . DS . 'views' . DS);

/**
 * load our main file now wuth composer autoloading
 */
require_once WP_BUS_RINGIER_PLUGIN_DIR . 'src/wp-bus-main.php';

/**
 * Register main Hooks
 */
register_activation_hook( __FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_activation']);
register_deactivation_hook( __FILE__, ['RingierBusPlugin\\BusPluginClass', 'plugin_deactivation']);

/**
 * Load the admin page interface
 */
if (is_admin()) {
    add_action( 'init', ['RingierBusPlugin\\BusPluginClass', 'admin_init']);
}

/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */

/**
 * custom option and settings
 */
function wporg_settings_init() {
    // Register a new setting for "wporg" page.
    register_setting( 'wporg', 'wporg_options' );

    // Register a new section in the "wporg" page.
    add_settings_section(
        'wporg_section_developers',
        __( 'The Matrix has you.', 'wporg' ), 'wporg_section_developers_callback',
        'wporg'
    );

    // Register a new field in the "wporg_section_developers" section, inside the "wporg" page.
    add_settings_field(
        'wporg_field_pill', // As of WP 4.6 this value is used only internally.
        // Use $args' label_for to populate the id inside the callback.
        __( 'Pill', 'wporg' ),
        'wporg_field_pill_cb',
        'wporg',
        'wporg_section_developers',
        array(
            'label_for'         => 'wporg_field_pill',
            'class'             => 'wporg_row',
            'wporg_custom_data' => 'custom',
        )
    );
}

/**
 * Register our wporg_settings_init to the admin_init action hook.
 */
add_action( 'admin_init', 'wporg_settings_init' );


/**
 * Custom option and settings:
 *  - callback functions
 */


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
function wporg_section_developers_callback( $args ) {
    ?>
    <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Follow the white rabbit.', 'wporg' ); ?></p>
    <?php
}

/**
 * Pill field callbakc function.
 *
 * WordPress has magic interaction with the following keys: label_for, class.
 * - the "label_for" key value is used for the "for" attribute of the <label>.
 * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
 * Note: you can add custom key value pairs to be used inside your callbacks.
 *
 * @param array $args
 */
function wporg_field_pill_cb( $args ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'wporg_options' );
    ?>
    <select
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        data-custom="<?php echo esc_attr( $args['wporg_custom_data'] ); ?>"
        name="wporg_options[<?php echo esc_attr( $args['label_for'] ); ?>]">
        <option value="red" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'red', false ) ) : ( '' ); ?>>
            <?php esc_html_e( 'red pill', 'wporg' ); ?>
        </option>
        <option value="blue" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'blue', false ) ) : ( '' ); ?>>
            <?php esc_html_e( 'blue pill', 'wporg' ); ?>
        </option>
    </select>
    <p class="description">
        <?php esc_html_e( 'You take the blue pill and the story ends. You wake in your bed and you believe whatever you want to believe.', 'wporg' ); ?>
    </p>
    <p class="description">
        <?php esc_html_e( 'You take the red pill and you stay in Wonderland and I show you how deep the rabbit-hole goes.', 'wporg' ); ?>
    </p>
    <?php
}

/**
 * Add the top level menu page.
 */
function wporg_options_page() {
    add_menu_page(
        'WPOrg',
        'WPOrg Options',
        'manage_options',
        'wporg',
        'wporg_options_page_html'
    );
}


/**
 * Register our wporg_options_page to the admin_menu action hook.
 */
add_action( 'admin_menu', 'wporg_options_page' );


/**
 * Top level menu callback function
 */
function wporg_options_page_html() {
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // WordPress will add the "settings-updated" $_GET parameter to the url
    if ( isset( $_GET['settings-updated'] ) ) {
        // add settings saved message with the class of "updated"
        add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'wporg' ), 'updated' );
    }

    // show error/update messages
    settings_errors( 'wporg_messages' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "wporg"
            settings_fields( 'wporg' );
            // output setting sections and their fields
            // (sections are registered for "wporg", each field is registered to a specific section)
            do_settings_sections( 'wporg' );
            // output save settings button
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}
