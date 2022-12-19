<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

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
            ringier_infologthis('POST Activation phase');
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

    public static function loadCustomMetaBox()
    {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes_for_custom_fields'], 10, 2);
    }

    public static function add_meta_boxes_for_custom_fields(string $post_type, \WP_Post $post)
    {
        add_meta_box('event_bus_meta_box', __('BUS Event Fields'), [self::class, 'render_meta_box_for_custom_fields'], 'post', 'side');
    }

    public static function render_meta_box_for_custom_fields(\WP_Post $post)
    {
        wp_nonce_field('event_bus_meta_box_nonce_action', 'event_bus_meta_box_nonce_field');
        self::renderHtmlForArticleLifetimeField($post);
        self::renderHtmlForHiddenPostStatusField($post);
    }

    public static function renderHtmlForArticleLifetimeField(\WP_Post $post)
    {
        $field_key = sanitize_text_field(Enum::ACF_ARTICLE_LIFETIME_KEY);
        $field_from_db = sanitize_text_field(get_post_meta($post->ID, $field_key, true));

        //parent div
        echo '<div class="bus-select-field" data-name="' . $field_key . '" data-type="select" data-key="' . $field_key . '">';

        //label
        echo '<div class="bus-label">';
        echo '<label for="' . $field_key . '" style="color:#2b689e;font-weight:bold;">Article Lifetime</label>';
        echo '</div>';

        //select field
        echo '<div class="bus-select">';
        echo '<select id="' . $field_key . '" name="' . $field_key . '" style="width:100%;padding:4px 5px;margin:0;margin-top:5px;box-sizing:border-box;border-color:#2b689e;font-size:14px;line-height:1.4">';

        $field_key_list = Enum::ACF_ARTICLE_LIFETIME_VALUES;
        echo '<option value="-1">- Select -</option>';
        foreach ($field_key_list as $field_value) {
            $field_value = sanitize_text_field($field_value);
            $is_field_selected = '';
            if (strcmp($field_from_db, $field_value) == 0) {
                $is_field_selected = 'selected="selected"';
            }
            echo '<option value="' . $field_value . '" ' . $is_field_selected . '>' . $field_value . '</option>';
        }

        echo '</select>';
        echo '</div>';

        //close parent div
        echo '</div>';
    }

    public static function renderHtmlForHiddenPostStatusField(\WP_Post $post)
    {
        $field_key = sanitize_text_field(Enum::ACF_IS_POST_NEW_KEY);
        $input_value = Enum::ACF_IS_POST_VALUE_NEW; //'is_new';

        $field_from_db = sanitize_text_field(get_post_meta($post->ID, $field_key, true));
        if (!empty($field_from_db)) {
            $input_value = $field_from_db;
        }

        //parent div
        echo '<div class="bus-hidden-text-field" data-name="' . $field_key . '" data-type="text" data-key="' . $field_key . '" style="margin: 10px 0;">';

        //label
        echo '<div class="bus-label bus-hidden" style="color: #a29f9f">';
        echo '<label for="' . $field_key . '">Article status (internal use)</label>';
        echo '</div>';

        //field
        echo '<div class="bus-text">';
        echo '<input type="text" disabled id="' . $field_key . '" name="' . $field_key . '" value="' . $input_value . '">';
        echo '</div>';

        //close parent div
        echo '</div>';
    }
}
