<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

use RingierBusPlugin\Bus\BusHelper;

class BusPluginClass
{
    /**
     * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
     *
     * @static
     */
    public static function plugin_activation()
    {
        add_option(Enum::PLUGIN_KEY, true);
        ringier_infologthis('Activation: set plugin_key to true');
        //nothing for now
        ringier_infologthis('bus_plugin_activated');
    }

    /**
     * Should remove any scheduled events
     * NOTE: The database data cleaning is handled by uninstall.php
     *
     * @static
     */
    public static function plugin_deactivation()
    {
        ringier_infologthis('bus_plugin_deactivated');

        // TODO: Remove any scheduled cron jobs.
//        $my_cron_events = array(
//            'my_schedule_cron_recheck', //todo: use our Enum for this (wasseem)
//            'my_scheduled_delete',
//        );
//
//        foreach ( $my_cron_events as $current_cron_event ) {
//            $timestamp = wp_next_scheduled( $current_cron_event );
//
//            if ( $timestamp ) {
//                wp_unschedule_event( $timestamp, $current_cron_event );
//            }
//        }
    }

    /**
     * triggered when a user has deactivated the plugin
     */
    public static function plugin_uninstall()
    {
        ringier_infologthis('plugin_uninstall hook called');
        delete_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        delete_option(Enum::PLUGIN_KEY);
    }

    /**
     * Render the admin pages
     */
    public static function adminInit()
    {
        //if on plugin activation
        if (get_option(Enum::PLUGIN_KEY)) {
            ringier_infologthis('POST Activation: delete plugin_key now');
            delete_option(Enum::PLUGIN_KEY);

            //initially turn the BUS_API OFF
            update_option(
                Enum::SETTINGS_PAGE_OPTION_NAME,
                [
                    Enum::FIELD_BUS_STATUS => 'off',
                    Enum::FIELD_APP_LOCALE => 'en_KE',
                    Enum::FIELD_APP_KEY => 'MUUK-STAGING',
                    Enum::FIELD_SLACK_BOT_NAME => 'MUUK-STAGING',
                    Enum::FIELD_BACKOFF_DURATION => 30,
                ]
            );
        }
        //Now do normal stuff
        add_action('admin_menu', [self::class, 'handleAdminUI']);

        //Register Bus API Mechanism
//        BusHelper::load_vars_into_env();
    }

    public static function handleAdminUI()
    {
        //The "Ringier Bus API Settings" main-PAGE
        $adminSettingsPage = new AdminSettingsPage();
        $adminSettingsPage->handleAdminUI();

        //The "Log" sub-PAGE
        $adminLogPage = new AdminLogPage();
        $adminLogPage->handleAdminUI();
    }

    public static function register_wp_bus_plugin_scripts()
    {
        // Load styles
        // WordPress has many defaults here: https://developer.wordpress.org/themes/basics/including-css-javascript/#default-scripts-included-and-registered-by-wordpress
    }

    /**
     * We will leverage some magic from ACF plugin
     * Plugin url: https://wordpress.org/plugins/advanced-custom-fields/
     *
     * We want to offload responsibility of managing custom fields to ACF
     * Simply because it does it so nicely and with powerful flexibility
     */
    public static function injectACF()
    {
        // Define path and URL to the ACF plugin.
        define('RINGIER_BUS_PLUGIN_ACF_PATH', RINGIER_BUS_PLUGIN_DIR . 'includes/acf/');
        define('RINGIER_BUS_PLUGIN_ACF_URL', RINGIER_BUS_PLUGIN_DIR_URL . 'includes/acf/');

        // Include the ACF plugin.
        include_once RINGIER_BUS_PLUGIN_ACF_PATH . 'acf.php';

        // Customize the url setting to fix incorrect asset URLs.
        add_filter('acf/settings/url', [self::class, 'acf_settings_url']);

        //Hide the ACF admin menu item | not necessary for now
//        add_filter('acf/settings/show_admin', [self::class, 'acf_settings_show_admin']);

        //Load our custom fields using php structure
        add_action('acf/init', [self::class, 'register_acf_custom_fields']);

        //Load json storage
        //NOTE: this does not seem to work, bypassing to use PHP way instead (above line)
//        add_filter('acf/settings/load_json', [self::class, 'acf_local_json_storage']);
//        add_filter('acf/settings/save_json', [self::class, 'acf_local_json_storage']);
    }

    /**
     * Customize the url setting to fix incorrect asset URLs
     * ref: https://www.advancedcustomfields.com/resources/including-acf-within-a-plugin-or-theme/
     *
     * @param $url
     *
     * @return string
     */
    public static function acf_settings_url($url)
    {
        return RINGIER_BUS_PLUGIN_ACF_URL;
    }

    /**
     * Hide the ACF admin menu item
     *
     * @param $show_admin
     *
     * @return false
     */
    public static function acf_settings_show_admin($show_admin)
    {
        return false;
    }

    /**
     * Local JSON saves field group and field settings as .json files
     * Idea is similar to caching & dramatically speeds up ACF + allows for versioning our field settings
     * ref: https://www.advancedcustomfields.com/resources/local-json/
     *
     * @param mixed $paths
     *
     * @return string
     */
    public static function acf_local_json_storage($paths)
    {
        // remove original path (optional)
        unset($paths[0]);
        // path to our json exports having our custom fields
        $paths[] = RINGIER_BUS_PLUGIN_DIR . 'includes/acf-json';

        return $paths;
    }

    /**
     * register the is_post_new custom field
     * definition of our custom field here
     */
    public static function register_acf_custom_fields()
    {
        acf_add_local_field_group([
            'key' => 'group_61681d4e14096',
            'title' => 'Event Bus',
            'fields' => [
                [
                    'key' => 'field_61681d5ac63ee',
                    'label' => 'Article Lifetime',
                    'name' => 'article_lifetime',
                    'type' => 'select',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'choices' => [
                        'evergreen' => 'evergreen',
                        'seasonal' => 'seasonal',
                        'time-limited' => 'time-limited',
                    ],
                    'default_value' => false,
                    'allow_null' => true,
                    'multiple' => 0,
                    'ui' => 0,
                    'return_format' => 'value',
                    'ajax' => 0,
                    'placeholder' => '',
                ],

                [
                    'key' => 'field_609a883e62def',
                    'label' => 'Hidden Field',
                    'name' => 'is_post_new',
                    'type' => 'text',
                    'instructions' => '(DO NOT USE) This field is used internally by the Bus Plugin.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => 'not_new',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ],
                ],
            ],
            'menu_order' => 10,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [
                0 => 'permalink',
                1 => 'excerpt',
                2 => 'discussion',
                3 => 'comments',
                4 => 'revisions',
                5 => 'slug',
                6 => 'author',
                7 => 'format',
                8 => 'page_attributes',
                9 => 'featured_image',
                10 => 'categories',
                11 => 'tags',
                12 => 'send-trackbacks',
            ],
            'active' => true,
            'description' => '',
        ]);
    }
}
