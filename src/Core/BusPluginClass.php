<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

use RingierBusPlugin\Bus\Fields;
use WP_Post;

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
    }

    /**
     * Should remove any scheduled events
     * NOTE: The database data cleaning is handled by uninstall.php
     *
     * @static
     */
    public static function plugin_deactivation()
    {
        // TODO: Remove any scheduled cron jobs?
    }

    /**
     * triggered when a user has deactivated the plugin
     */
    public static function plugin_uninstall()
    {
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
                    Enum::FIELD_VALIDATION_PUBLICATION_REASON => 'on',
                    Enum::FIELD_VALIDATION_ARTICLE_LIFETIME => 'on',
                ]
            );
        }
        //Now do normal stuff
        add_action('admin_menu', [self::class, 'handleAdminUI']);

        //enqueue custom editor js scripts
        add_action('admin_enqueue_scripts', [self::class, 'add_javascript_to_article_dashboard']);

        /*
         * Hide the "Quick Edit" button on Post List screen (wp-admin/edit.php)
         * Because with Quick Edit button, we can change an article from draft to publish,
         * bypassing the checklist - which we do not want
         */
        add_action('post_row_actions', [self::class, 'hide_quick_edit_button'], PHP_INT_MAX);

        /*
         * Register Bus API Mechanism
         * Note: commented out because we are now fetching values from the UI (dashboard) itself
         */
//        BusHelper::load_vars_into_env();
    }

    /**
     * Hook reference: https://developer.wordpress.org/reference/hooks/post_row_actions/
     *
     * @param array $actions
     *
     * @return array
     */
    public static function hide_quick_edit_button(array $actions): array
    {
        unset($actions['inline hide-if-no-js']);

        return $actions;
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

    public static function add_meta_boxes_for_custom_fields(string $post_type, WP_Post $post)
    {
        //to show meta box on all current & future custom post_type
        $args = [
            'post_type' => 'page',
            'public' => false,
        ];
        $screens = get_post_types($args, 'names', 'not'); //pay attention to the operator (last param)
        //we do not want to show it on "Page"
        if (in_array('page', $screens)) {
            unset($screens['page']);
        }
        //Remove any other non desired custom post_type that came through
        if (in_array('attachment', $screens)) {
            unset($screens['attachment']);
        }
        add_meta_box('event_bus_meta_box', __('Ringier BUS'), [self::class, 'render_meta_box_for_custom_fields'], $screens, 'side');
    }

    public static function render_meta_box_for_custom_fields(WP_Post $post)
    {
        wp_nonce_field(Enum::ACF_NONCE_ACTION, Enum::ACF_NONCE_FIELD);
        self::renderHtmlForArticleLifetimeField($post);
        self::renderHtmlForPublicationReasonField($post);
        self::renderHtmlForHiddenPostStatusField($post);
    }

    public static function renderHtmlForArticleLifetimeField(WP_Post $post)
    {
        $field_key = sanitize_text_field(Enum::ACF_ARTICLE_LIFETIME_KEY);
        $field_key_list = Enum::ACF_ARTICLE_LIFETIME_VALUES;
        self::doSelectBox($post, 'Article lifetime', $field_key, $field_key_list);
    }
    public static function renderHtmlForPublicationReasonField(WP_Post $post)
    {
        $field_key = sanitize_text_field(Enum::FIELD_PUBLICATION_REASON_KEY);
        $field_key_list = Enum::FIELD_PUBLICATION_REASON_VALUES;
        self::doSelectBox($post, 'Publication reason', $field_key, $field_key_list);
    }

    public static function renderHtmlForHiddenPostStatusField(WP_Post $post)
    {
        $field_key = sanitize_text_field(Enum::ACF_IS_POST_NEW_KEY);
        $input_value = Enum::ACF_IS_POST_VALUE_NEW; //'is_new';

        $field_from_db = sanitize_text_field(get_post_meta($post->ID, $field_key, true));
        if (!empty($field_from_db)) {
            $input_value = $field_from_db;
        }

        //parent div
        echo '<div class="bus-hidden-text-field" data-name="' . $field_key . '" data-type="text" data-key="' . $field_key . '" style="margin: 10px 0;display:none">';

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

    /**
     * Reusable HTML structure for generating Select box for the widget
     *
     * @param WP_Post $post
     * @param string $label
     * @param string $field_key
     * @param array $field_key_list
     */
    private static function doSelectBox(WP_Post $post, string $label, string $field_key, array $field_key_list): void
    {
        $field_from_db = sanitize_text_field(get_post_meta($post->ID, $field_key, true));

        //parent div
        echo '<div class="bus-select-field" data-name="' . $field_key . '" data-type="select" data-key="' . $field_key . '" style="margin-bottom:20px;">';
        echo '<label class="components-base-control__label" for="' . $field_key . '">' . $label . '</label>';

        //select field
        echo '<div class="bus-select">';
        echo '<select id="' . $field_key . '" name="' . $field_key . '" style="width:100%;padding:4px 5px;margin:0;margin-top:5px;box-sizing:border-box;border-color:#2b689e;font-size:14px;line-height:1.4">';

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

    /**
     * Add the javascript validation on Gutenberg
     * This is a callback function invoked by `admin_enqueue_scripts` above
     */
    public static function add_javascript_to_article_dashboard(): void
    {
        $fieldsObject = new Fields();
        //Enqueue scripts ONLY IF Bus Plugin is enabled
        if ($fieldsObject->is_bus_enabled === true) {
            /** @var \WP_Screen $screen */
            $screen = get_current_screen();

            // load on NEW & EDIT screens of all post types
            if (('post' === $screen->base) && ($screen->post_type != 'page')) {
                //Publication reason
                if ($fieldsObject->field_validation_publication_reason != 'off') {
                    wp_enqueue_script('ringier-validation-publication-reason', RINGIER_BUS_PLUGIN_DIR_URL . 'assets/js/validation-publication_reason.js', ['jquery', 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-edit-post', 'word-count'], _S_CACHE_NONCE);
                }

                //Article lifetime
                if ($fieldsObject->field_validation_article_lifetime != 'off') {
                    wp_enqueue_script('ringier-validation-article-lifetime', RINGIER_BUS_PLUGIN_DIR_URL . 'assets/js/validation-article_lifetime.js', ['jquery', 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-edit-post', 'word-count'], _S_CACHE_NONCE);
                }
            }
        }
    }
}
