<?php
/**
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\MissingExtensionException;
use Psr\Cache\InvalidArgumentException;
use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;
use WP_Post;

class BusHelper
{
    /**
     * Used in in ArticleEvent class
     *
     * @param int $post_ID
     * @param string $image_size_name
     * @param string $isHero
     *
     * @return array
     */
    public static function getImageArrayForApi(int $post_ID, string $image_size_name = 'large_rectangle', string $isHero = 'false'): array
    {
        $image_id = get_post_thumbnail_id($post_ID);
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $imageUrl = get_the_post_thumbnail_url(get_post($post_ID), $image_size_name);
        //        $image_title = get_the_title($image_id);

        if ($image_size_name == 'large_rectangle') {
            $isHero = 'true';
        }

        return [
            'url' => Utils::returnEmptyOnNullorFalse($imageUrl),
            'size' => $image_size_name,
            'alt_text' => Utils::returnEmptyOnNullorFalse($image_alt),
            'hero' => $isHero,
            'content_hash' => Utils::returnEmptyOnNullorFalse(Utils::hashImage($imageUrl)),
        ];
    }

    /**
     * Registers the BUS API action within WordPress
     */
    public static function registerBusApiActions(): void
    {
        /*
         * the logic of saving custom fields like publication_reason or lifetime, etc
         * should work as soon as the plugin is active, irrespective if the BUS sync is OFF
         */
        add_action('save_post', [self::class, 'save_custom_fields'], 10, 3);

        $fieldsObject = new Fields();
        //Register Bus Events ONLY IF it is enabled
        if ($fieldsObject->is_bus_enabled === true) {
            /**
             * Article events
             */
            //            add_action('transition_post_status', [self::class, 'cater_for_custom_post'], 10, 3);
            //            add_action('rest_after_insert_post', [self::class, 'triggerArticleEvent'], 10, 1);
            add_action('transition_post_status', [self::class, 'trigger_bus_event_on_post_change'], 10, 3);
            add_action('future_to_publish', [self::class, 'cater_for_manually_scheduled_post'], 10, 1);
            add_action('publish_to_trash', [self::class, 'triggerArticleDeletedEvent'], 10, 3);
            add_action(Enum::HOOK_NAME_SCHEDULED_EVENTS, [self::class, 'cronSendToBusScheduled'], 10, 3);

            // Fetch some options from the settings page
            $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
            $enable_author_events = isset($options[Enum::FIELD_ENABLE_AUTHOR_EVENTS]) && strcmp($options[Enum::FIELD_ENABLE_AUTHOR_EVENTS], 'on') === 0;
            $enable_terms_events = isset($options[Enum::FIELD_ENABLE_TERMS_EVENTS]) && strcmp($options[Enum::FIELD_ENABLE_TERMS_EVENTS], 'on') === 0;

            /**
             * Author events
             */
            if ($enable_author_events === true) {
                add_filter('insert_user_meta', function (array $meta, \WP_User $user, bool $update, array $userdata) {
                    /**
                     * This is used to help us determine if the user was just created
                     * when the hook profile_update gets called in the hook user_register
                     * (wordpress being wordpress calls profile_update a bazillion times)
                     */
                    if (!$update) {
                        // Set transient to mark that this user was just created
                        set_transient("user_just_created_{$user->ID}", true, 5);
                    }

                    return $meta;
                }, 10, 4);
                // ref: https://developer.wordpress.org/reference/hooks/user_register/
                add_action('user_register', [self::class, 'triggerUserCreatedEvent'], Enum::RUN_LAST, 2);
                // ref: https://developer.wordpress.org/reference/hooks/profile_update/
                add_action('profile_update', [self::class, 'triggerUserUpdatedEvent'], Enum::RUN_LAST, 3);
                // ref: https://developer.wordpress.org/reference/hooks/delete_user/
                add_action('delete_user', [self::class, 'triggerUserDeletedEvent'], Enum::RUN_LAST, 1);
            }

            /**
             * Category & Tag events
             */
            if ($enable_terms_events === true) {
                // ref: https://developer.wordpress.org/reference/hooks/create_term/
                add_action('create_term', [self::class, 'triggerTermCreatedEvent'], Enum::RUN_LAST, 4);
                // ref: https://developer.wordpress.org/reference/hooks/edited_term/
                add_action('edited_term', [self::class, 'triggerTermUpdatedEvent'], Enum::RUN_LAST, 4);
                // ref: https://developer.wordpress.org/reference/hooks/delete_term/
                add_action('delete_term', [self::class, 'triggerTermDeletedEvent'], Enum::RUN_LAST, 5);
            }
        }
    }

    /**
     * Triggered by hook: create_category
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @param array $args
     */
    public static function triggerTermCreatedEvent(int $term_id, int $tt_id, string $taxonomy, array $args): void
    {
        // Bail out if term is not a category or tag
        if (!in_array($taxonomy, Enum::TOPIC_TERM_LIST, true)) {
            return;
        }

        update_term_meta($term_id, Enum::DB_CREATED_AT, current_time('mysql'));
        update_term_meta($term_id, Enum::DB_UPDATED_AT, current_time('mysql'));

        $term = get_term($term_id, $taxonomy);
        if (!($term instanceof \WP_Term) || is_wp_error($term)) {
            return;
        }

        $term_type = match (mb_strtolower($taxonomy)) {
            Enum::TERM_TYPE_CATEGORY => Enum::TERM_TYPE_CATEGORY,
            Enum::TERM_TYPE_TAG => Enum::TERM_TYPE_TAG,
            default => mb_strtolower($term->taxonomy),
        };

        self::dispatchTermEvent($term, $term_type, Enum::EVENT_TOPIC_CREATED);
    }

    /**
     * Triggered by hook: edited_category
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @param array $args
     */
    public static function triggerTermUpdatedEvent(int $term_id, int $tt_id, string $taxonomy, array $args): void
    {
        // Bail out if term is not a category or tag
        if (!in_array($taxonomy, Enum::TOPIC_TERM_LIST, true)) {
            return;
        }

        update_term_meta($term_id, Enum::DB_UPDATED_AT, current_time('mysql'));

        $term = get_term($term_id, $taxonomy);
        if (!($term instanceof \WP_Term) || is_wp_error($term)) {
            return;
        }

        $term_type = match (mb_strtolower($taxonomy)) {
            Enum::TERM_TYPE_CATEGORY => Enum::TERM_TYPE_CATEGORY,
            Enum::TERM_TYPE_TAG => Enum::TERM_TYPE_TAG,
            'post_tag' => Enum::TERM_TYPE_TAG,
            default => mb_strtolower($term->taxonomy),
        };

        self::dispatchTermEvent($term, $term_type, Enum::EVENT_TOPIC_UPDATED);
    }

    /**
     * Triggered by hook: delete_category
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @param \WP_Term $deleted_term
     * @param array $object_ids
     */
    public static function triggerTermDeletedEvent(int $term_id, int $tt_id, string $taxonomy, \WP_Term $deleted_term, array $object_ids): void
    {
        // Bail out if term is not a category or tag
        if (!in_array($taxonomy, Enum::TOPIC_TERM_LIST, true)) {
            return;
        }

        update_term_meta($term_id, Enum::DB_UPDATED_AT, current_time('mysql'));

        $term = $deleted_term;
        if (!($term instanceof \WP_Term) || is_wp_error($term)) {
            return;
        }

        $term_type = match (mb_strtolower($taxonomy)) {
            Enum::TERM_TYPE_CATEGORY => Enum::TERM_TYPE_CATEGORY,
            Enum::TERM_TYPE_TAG => Enum::TERM_TYPE_TAG,
            'post_tag' => Enum::TERM_TYPE_TAG,
            default => mb_strtolower($term->taxonomy),
        };

        self::dispatchTermEvent($term, $term_type, Enum::EVENT_TOPIC_DELETED);
    }

    protected static function dispatchTermEvent(\WP_Term $term, string $term_type, string $event_type): void
    {
        $endpointUrl = $_ENV[Enum::ENV_BUS_ENDPOINT];
        if (empty($endpointUrl)) {
            ringier_errorlogthis($term_type . '-Event: endpointUrl is empty');
            Utils::pushToSlack($term_type . '-Event: endpointUrl is empty', Enum::LOG_ERROR);

            return;
        }

        $busToken = new BusTokenManager();
        $busToken->setParameters($endpointUrl, $_ENV[Enum::ENV_VENTURE_CONFIG], $_ENV[Enum::ENV_BUS_API_USERNAME], $_ENV[Enum::ENV_BUS_API_PASSWORD]);
        $result = $busToken->acquireToken();
        if (!$result) {
            ringier_errorlogthis($term_type . '-Event: a problem with Bus Token');
            Utils::pushToSlack($term_type . '-Event: a problem with Bus Token', Enum::LOG_ERROR);

            return;
        }

        $page_status = Enum::JSON_FIELD_STATUS_ONLINE;
        if (strcmp($event_type, Enum::EVENT_TOPIC_DELETED) === 0) {
            $page_status = Enum::JSON_FIELD_STATUS_OFFLINE;
        }

        $topic_data = [
            'id' => $term->term_id,
            'status' => $page_status,
            'created_at' => get_term_meta($term->term_id, Enum::DB_CREATED_AT, true),
            'updated_at' => get_term_meta($term->term_id, Enum::DB_UPDATED_AT, true),
            'url' => get_term_link($term),
            'title' => $term->name,
            'slug' => $term->slug,
            'page_type' => $term_type,
        ];

        $term_event = new TermEvent($busToken, $endpointUrl, $term_type);
        $term_event->setEventType($event_type);
        $term_event->sendToBus($topic_data);
    }

    /**
     * Triggered by hook: user_register
     *
     * @param int $user_id
     * @param array $userdata
     */
    public static function triggerUserCreatedEvent(int $user_id, array $userdata): void
    {
        // Bail out if we're working on a draft or trashed item
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Bail out if BUS events for user are not considered as an Author
        if (!Utils::user_has_any_role($user_id, Enum::AUTHOR_ROLE_LIST)) {
            return;
        }

        self::dispatchAuthorEvent($user_id, $userdata, Enum::EVENT_AUTHOR_CREATED);
    }

    /**
     * Triggered by hook: profile_update
     *
     * @param int $user_id
     * @param \WP_User $old_user_data
     * @param array $userdata
     */
    public static function triggerUserUpdatedEvent(int $user_id, \WP_User $old_user_data, array $userdata): void
    {
        // Bail out if we're working on a draft or trashed item
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Bail out if BUS events for user are not considered as an Author
        if (!Utils::user_has_any_role($user_id, Enum::AUTHOR_ROLE_LIST)) {
            return;
        }

        // Bail if this user was just created during this request
        if (Utils::wasRecentlyCreated($user_id)) {
            // This is a new user, so we don't want to trigger an update event
            return;
        }

        // Bail out to prevent duplicate execution
        if (get_transient('bus_user_update_' . $user_id)) {
            return;
        }
        // Set a transient to mark this hook as processed for this user
        set_transient('bus_user_update_' . $user_id, true, 5); // 5 seconds validity

        // Update the last modified date
        update_user_meta($user_id, Enum::DB_FIELD_AUTHOR_LAST_MODIFIED_DATE, current_time('mysql'));

        // Check of the new user data has the email
        if (empty($userdata['user_email'])) {
            $userdata['user_email'] = $old_user_data->user_email;
        }

        // Now let's build the required author info for the BUS
        self::dispatchAuthorEvent($user_id, $userdata, Enum::EVENT_AUTHOR_UPDATED);
    }

    /**
     * Triggered by hook: delete_user
     *
     * @param int $user_id
     * @param \WP_User $old_user_data
     * @param array $userdata
     */
    public static function triggerUserDeletedEvent(int $user_id): void
    {
        // Bail out if we're working on a draft or trashed item
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Bail out if BUS events for user are not considered as an Author
        if (!Utils::user_has_any_role($user_id, Enum::AUTHOR_ROLE_LIST)) {
            return;
        }

        // Now let's build the required author info for the BUS
        $user = get_userdata($user_id);
        if (!$user) {
            ringier_errorlogthis("AuthorDeleted: User with ID $user_id not found", Enum::LOG_WARNING);

            return;
        }
        $userdata = [
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'user_registered' => $user->user_registered,
        ];
        self::dispatchAuthorEvent($user_id, $userdata, Enum::EVENT_AUTHOR_DELETED);
    }

    /**
     * To save our custom fields like Article Lifetime & is_post_new
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     *
     * @throws Exception
     */
    public static function save_custom_fields(int $post_id, WP_Post $post, bool $update): void
    {
        if (strcmp($post->post_type, 'page') == 0) {
            return;
        }
        if (empty($post->post_type)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $wordpress_post_status = $post->post_status;
        if (in_array($wordpress_post_status, ['auto-draft', 'inherit', 'trash'])) {
            return;
        }

        //if nonce not verified, possible malicious attempt or API trying to re-save, bail out
        if (!isset($_POST[Enum::ACF_NONCE_FIELD])
            || !wp_verify_nonce($_POST[Enum::ACF_NONCE_FIELD], Enum::ACF_NONCE_ACTION)) {
            return;
        }

        //MKTC-1750 - should now allow saving when 'saving draft' and 'scheduled' posting
        if (in_array($wordpress_post_status, ['publish', 'draft', 'future'])) {
            $post_id = Utils::getParentPostId($post_id);

            //save custom field: article_lifetime
            if (isset($_POST[Enum::ACF_ARTICLE_LIFETIME_KEY])) {
                $article_lifetime_value = sanitize_text_field($_POST[Enum::ACF_ARTICLE_LIFETIME_KEY]);
                if (in_array($article_lifetime_value, Enum::ACF_ARTICLE_LIFETIME_VALUES)) {
                    update_post_meta($post_id, Enum::ACF_ARTICLE_LIFETIME_KEY, $article_lifetime_value);
                }
            }

            //save custom field: publication_reason
            if (isset($_POST[Enum::FIELD_PUBLICATION_REASON_KEY])) {
                $publication_reason_value = sanitize_text_field($_POST[Enum::FIELD_PUBLICATION_REASON_KEY]);
                if (in_array($publication_reason_value, Enum::FIELD_PUBLICATION_REASON_VALUES)) {
                    update_post_meta($post_id, Enum::FIELD_PUBLICATION_REASON_KEY, $publication_reason_value);
                }
            }

            //save custom field: is_post_new | for this we do not care if it is set or nnot, it;'s a hidden field
            update_post_meta($post_id, Enum::ACF_IS_POST_NEW_KEY, Enum::ACF_IS_POST_VALUE_EXISTED);
        } else {
            ringier_errorlogthis('BUS: could not save custom fields for post ID: ' . $post_id, 'WARNING');
        }
    }

    /**
     * In essence, this method is a merge of cater_for_custom_post() and triggerArticleEvent()
     * because the rest_after_insert_post hook does not get triggered in all WordPress contexts,
     * such as when updating a post using the Classic Editor (IMO disabled Gutenberg)
     * or programmatically without the REST API.
     *
     * Thus we ensure that the desired actions are consistently executed regardless
     * of the editing method or environment.
     *
     * @param string $new_status
     * @param string $old_status
     * @param WP_Post $post
     */
    public static function trigger_bus_event_on_post_change(string $new_status, string $old_status, WP_Post $post): void
    {
        // Check if it's a page or normal post and return
        if ($post->post_type === 'page' || empty($post->post_type)) {
            return;
        }

        // Bail on custom post types if events for them are not enabled (via settings page - choice of user)
        if (!Utils::isBusEventEnabledForPostType($post->post_type)) {
            return;
        }

        // Bail if we're working on a draft or trashed item
        if ($new_status == 'auto-draft' || $new_status == 'draft' || $new_status == 'inherit' || $new_status == 'trash') {
            return;
        }

        // Bail if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($new_status === 'publish') {
            $post_ID = $post->ID;
            $post_ID = Utils::getParentPostId($post_ID);
            $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];

            /*
             * This conditioning helps us get context if the post is in mode NEW or EDIT
             * There is no other way around this as of this date of coding (Apr 2021)
             * Hope in the future WordPress exposes a better way for us to get this context
             */
            $articleTriggerMode = Utils::isPostNew($post_ID) ? Enum::EVENT_ARTICLE_CREATED : Enum::EVENT_ARTICLE_EDITED;

            /**
             * This is a workaround to prevent the function from running more than once
             * In our testing, this function is called at least 2 times for the same post update
             * Only the first call has the correct custom meta data, the successive calls do not
             */
            // Check if this hook has already been run for this same post update
            if (get_transient('triggered_bus_event_' . $post->ID)) {
                return;
            }
            // Set a transient to mark this hook has been run for this same post update
            set_transient('triggered_bus_event_' . $post->ID, true, 25);

            //for CIET purposes we need to push event fast on new article creation
            if (($articleTriggerMode == Enum::EVENT_ARTICLE_CREATED)) {
                //Attempt to send the event immediately, queue it if it fails
                self::sendToBus($articleTriggerMode, $post_ID, get_post($post_ID), 0);
                //push to SLACK
                $message = <<<EOF
                    $blogKey: An instant event push has been done for article (ID: $post_ID)
                EOF;
                Utils::slackthat($message);
            }

            /*
             * we will now schedule the event push as well, because:
             * not all meta data of the article are updated correctly when:
             *      - article is first created,
             *      - when article meta are changed
             */
            self::scheduleSendToBus(Enum::EVENT_ARTICLE_EDITED, $post_ID, 0, 1);
            self::pushToSLACK($blogKey, $articleTriggerMode, $post_ID);
        }
    }

    /**
     * (No more used in favor of trigger_bus_event_on_post_change())
     *
     * To cater for custom post_type only
     * Triggered by hook: transition_post_status
     *
     * @param string $new_status
     * @param string $old_status
     * @param WP_Post $post
     */
    public static function cater_for_custom_post(string $new_status, string $old_status, WP_Post $post): void
    {
        //bail if a page
        if (strcmp($post->post_type, 'page') == 0) {
            return;
        }
        //bail is normal post, we are catering for custom posts
        if (strcmp($post->post_type, 'post') == 0) {
            return;
        }
        if (empty($post->post_type)) {
            return;
        }

        // Bail if we're working on a draft or trashed item
        if ($new_status == 'auto-draft' || $new_status == 'draft' || $new_status == 'inherit' || $new_status == 'trash') {
            return;
        }

        add_action('rest_after_insert_' . trim($post->post_type), [self::class, 'triggerArticleEvent'], 15, 1);
    }

    /**
     * (No more used in favor of trigger_bus_event_on_post_change())
     *
     * Triggered by Hook: rest_after_insert_post
     *
     * This action will be invoked ONLY when a post in being Created/Updated
     * (Codex for save_post: https://developer.wordpress.org/reference/hooks/save_post/)
     *
     *  hook `rest_after_insert_post` fires after post meta data has been saved by Gutenberg to the WP API
     *
     * Gutenberg now saves content directly via the WordPress API, so there is no POST data to intercept
     * hence previously hook `save_post` was of a not flexible way for us.
     *
     * @param WP_Post $post
     *
     * @throws Exception
     *
     * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
     *
     * @github wkhayrattee
     */
    public static function triggerArticleEvent(WP_Post $post): void
    {
        $wordpress_post_status = $post->post_status;

        //we don't want to trigger the event if it is a draft, only when in public
        if (strcmp($wordpress_post_status, 'publish') == 0) {
            $post_ID = $post->ID;

            $post_ID = Utils::getParentPostId($post_ID);
            $articleTriggerMode = 'ArticleCreated';
            /*
             * This conditioning helps us get context if the post is in mode NEW or EDIT
             * There is no other way around this as of this date of coding (Apr 2021)
             * Hope in the future WordPress exposes a better way for us to get this context
             */
            $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
            if (Utils::isPostNew($post_ID) === true) {
                $articleTriggerMode = Enum::EVENT_ARTICLE_CREATED;
            } else {
                $articleTriggerMode = Enum::EVENT_ARTICLE_EDITED;
            }
            /*
             * we will now schedule the event after 1min instead of instantly executing it, because:
             * not all meta data of the article are updated correctly when:
             *      - article is first created,
             *      - when article meta are changed
             */
            self::scheduleSendToBus($articleTriggerMode, $post_ID, 0, 1);

            //push to SLACK
            self::pushToSLACK($blogKey, $articleTriggerMode, $post_ID);
        }
    }

    /**
     * Triggered by hook: future_to_publish
     *
     * @param WP_Post $post
     */
    public static function cater_for_manually_scheduled_post(WP_Post $post): void
    {
        $wordpress_post_status = $post->post_status;
        if (strcmp($wordpress_post_status, 'publish') == 0) {
            $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
            $post_ID = $post->ID;
            $post_ID = Utils::getParentPostId($post_ID);
            $articleTriggerMode = 'ArticleCreated';
            self::scheduleSendToBus($articleTriggerMode, $post_ID, 0, 1);
            self::pushToSLACK($blogKey, $articleTriggerMode, $post_ID);
        }
    }

    /**
     * Triggered by hook: publish_to_trash
     *
     * This action will be invoked ONLY when a post in being Created/Updated
     * I made use of Transitions
     * (Codex for Status Transitions: https://codex.wordpress.org/Post_Status_Transitions)
     *
     * @param WP_Post $post
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     */
    public static function triggerArticleDeletedEvent(WP_Post $post): void
    {
        // Bail on custom post types if events for them are not enabled (via settings page - choice of user)
        if (!Utils::isBusEventEnabledForPostType($post->post_type)) {
            return;
        }

        $post_ID = Utils::getParentPostId($post->ID);
        self::sendToBus(Enum::EVENT_ARTICLE_DELETED, $post_ID, $post);

        //delete custom fields
        delete_post_meta($post_ID, Enum::ACF_IS_POST_NEW_KEY);
        delete_post_meta($post_ID, Enum::ACF_ARTICLE_LIFETIME_KEY);
    }

    /**
     * The action to run when the hook (scheduledHookName()) is invoked
     *
     * @param string $articleTriggerMode
     * @param int $post_ID
     * @param int $countCalled
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     */
    public static function cronSendToBusScheduled(string $articleTriggerMode, int $post_ID, int $countCalled): void
    {
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
        $message = <<<EOF
            $blogKey: Now attempting push events for article (ID: $post_ID).
                    
            NOTE: 
                If no error follows, means successful push
                (else task will be re-queued)
        EOF;

        Utils::slackthat($message); //push to SLACK

        self::sendToBus($articleTriggerMode, $post_ID, get_post($post_ID), $countCalled);
    }

    /**
     * A refactored method to be used with above triggerArticleDeletedEvent() and triggerArticleEvent()
     *
     * @param string $articleTriggerMode
     * @param int $post_ID
     * @param WP_Post $post
     * @param int $countCalled to keep track of how many times this function was called by the cron
     *
     * @throws GuzzleException|MissingExtensionException|InvalidArgumentException
     */
    public static function sendToBus(string $articleTriggerMode, int $post_ID, WP_Post $post, int $countCalled = 1): void
    {
        try {
            $authClient = new Auth();
            $authClient->setParameters($_ENV[Enum::ENV_BUS_ENDPOINT], $_ENV[Enum::ENV_VENTURE_CONFIG], $_ENV[Enum::ENV_BUS_API_USERNAME], $_ENV[Enum::ENV_BUS_API_PASSWORD]);

            $result = $authClient->acquireToken();
            if ($result === true) {
                $articleEvent = new ArticleEvent($authClient);

                // Internal to some of the ventures, so not all will have this object, hence the check
                if (class_exists('Brand_settings')) {
                    $articleEvent->brandSettings = new \Brand_settings();
                }

                $articleEvent->setEventType($articleTriggerMode);
                $articleEvent->sendToBus($post_ID, $post);
            } else {
                ringier_errorlogthis('A problem with Auth Token');

                throw new Exception('A problem with Auth Token');
            }
        } catch (Exception $exception) {
            self::scheduleSendToBus($articleTriggerMode, $post_ID, $countCalled);
        }
    }

    /**
     * Called as part of back-off strategy
     * Will (re)queue the current task of sending request to bus for X minutes
     *
     * @param string $articleTriggerMode
     * @param int $post_ID
     * @param int $countCalled
     * @param mixed $run_after_minutes
     *
     * @throws MissingExtensionException
     */
    public static function scheduleSendToBus(string $articleTriggerMode, int $post_ID, int $countCalled = 1, mixed $run_after_minutes = false): void
    {
        if ($run_after_minutes === false) {
            $minutesToRun = getenv(Enum::ENV_BACKOFF_FOR_MINUTES) ?: 30;
        }
        $minutesToRun = (int) $run_after_minutes;
        $timestampNow = date_timestamp_get(date_create()); //get a UNIX Timestamp for NOW

        /*
         * We use WordPress Time Constants
         * https://codex.wordpress.org/Easier_Expression_of_Time_Constants
         */
        $currentTimestampForAction = $timestampNow + ($minutesToRun * MINUTE_IN_SECONDS);
        $args = [$articleTriggerMode, $post_ID, $countCalled];
        $hookSendToBus = self::scheduledHookName();

        /*
         * timestamp of any already scheduled event with SAME args
         *      - needs to be uniquely identified will return false if not scheduled
         */
        $alreadyScheduledTimestamp = wp_next_scheduled($hookSendToBus, $args);
        if ($alreadyScheduledTimestamp !== false) { //is not on first time
            //unschedule current
            wp_unschedule_event($alreadyScheduledTimestamp, $hookSendToBus, $args); //we want to remove any pre existing ones

            //re-schedule same for another time
            $args = [$articleTriggerMode, $post_ID, ++$countCalled]; //2nd time called, need to increment count
        }
        wp_schedule_single_event($currentTimestampForAction, $hookSendToBus, $args, true);

        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
        $message = <<<EOF
            $blogKey: [Queuing] Push-to-BUS for article (ID: $post_ID) has just been queued.
            And will run in the next ($minutesToRun)mins.
            
            Passed Params (mode, article_id, count_for_time_invoked):
            
        EOF;
        Utils::slackthat($message . print_r($args, true)); //push to SLACK
    }

    /**
     * The hook name of our Scheduled Task for back-off strategy
     *
     * @return string
     */
    public static function scheduledHookName(): string
    {
        return Enum::HOOK_NAME_SCHEDULED_EVENTS;
    }

    /**
     * @param mixed $blogKey
     * @param string $articleTriggerMode
     * @param int $post_ID
     */
    private static function pushToSLACK(mixed $blogKey, string $articleTriggerMode, int $post_ID): void
    {
        $message = <<<EOF
            $blogKey: Event push queued for article (ID: $post_ID | Mode: $articleTriggerMode)
            Scheduled to run in the next minute(s)
        EOF;
        Utils::slackthat($message);
    }

    /**
     * @param int $user_id
     * @param array $userdata
     * @param string $event_type
     */
    public static function dispatchAuthorEvent(int $user_id, array $userdata, string $event_type): void
    {
        $author_data = Utils::buildAuthorInfo($user_id, $userdata);

        $endpointUrl = $_ENV[Enum::ENV_BUS_ENDPOINT] ?? '';
        if (empty($endpointUrl)) {
            ringier_errorlogthis($event_type . ': endpointUrl is empty');
            Utils::pushToSlack($event_type . ': endpointUrl is empty', Enum::LOG_ERROR);

            return;
        }

        $busToken = new BusTokenManager();
        $busToken->setParameters(
            $endpointUrl,
            $_ENV[Enum::ENV_VENTURE_CONFIG] ?? '',
            $_ENV[Enum::ENV_BUS_API_USERNAME] ?? '',
            $_ENV[Enum::ENV_BUS_API_PASSWORD] ?? ''
        );

        $result = $busToken->acquireToken();
        if (!$result) {
            ringier_errorlogthis($event_type . ': Failed to acquire BUS token');
            Utils::pushToSlack("[{$event_type}] Failed to acquire BUS token", Enum::LOG_ERROR);

            return;
        }

        $authorEvent = new AuthorEvent($busToken, $endpointUrl);
        $authorEvent->setEventType($event_type);
        $authorEvent->sendToBus($author_data);
    }
}
