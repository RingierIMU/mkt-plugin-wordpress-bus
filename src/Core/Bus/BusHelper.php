<?php
/**
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class BusHelper
{
    /**
     * Used in in ArticleEvent class
     *
     * @param $post_ID
     * @param string $image_size_name
     * @param mixed $isHero
     *
     * @return array
     */
    public static function getImageArrayForApi($post_ID, $image_size_name = 'large_rectangle', $isHero = 'false')
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
        $fieldsObject = new Fields();
        //Register Bus Events ONLY IF it is enabled
        if ($fieldsObject->is_bus_enabled === true) {
            add_action('save_post', [self::class, 'save_custom_fields'], 10, 3);
            add_action('transition_post_status', [self::class, 'cater_for_custom_post'], 10, 3);
            add_action('rest_after_insert_post', [self::class, 'triggerArticleEvent'], 10, 1);
            add_action('publish_to_trash', [self::class, 'triggerArticleDeletedEvent'], 10, 3);
            add_action(Enum::HOOK_NAME_SCHEDULED_EVENTS, [self::class, 'cronSendToBusScheduled'], 10, 3);
        }
    }

    /**
     * To save our custom fields like Article Lifetime & is_post_new
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @param bool $update
     *
     * @throws \Exception
     */
    public static function save_custom_fields(int $post_id, \WP_Post $post, bool $update)
    {
        //bail if a page
        if (strcmp($post->post_type, 'page') == 0) {
            return;
        }
        if (empty($post->post_type)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Bail if we're working on a draft or trashed item
        $wordpress_post_status = $post->post_status;
        if (in_array($wordpress_post_status, ['auto-draft', 'draft', 'inherit', 'trash'])) {
            return;
        }

        //if nonce not verified, possible malicious attempt or API trying to re-save, bail out
        if (!isset($_POST[Enum::ACF_NONCE_FIELD])
            || !wp_verify_nonce($_POST[Enum::ACF_NONCE_FIELD], Enum::ACF_NONCE_ACTION)) {
            return;
        }

        if (strcmp($wordpress_post_status, 'publish') == 0) { //save only when in publish state, no for drafts..etc
            $post_id = Utils::getParentPostId($post_id);

            //save custom field: article_lifetime
            if (isset($_POST[Enum::ACF_ARTICLE_LIFETIME_KEY])) {
                $article_lifetime_value = sanitize_text_field($_POST[Enum::ACF_ARTICLE_LIFETIME_KEY]);
                if (in_array($article_lifetime_value, Enum::ACF_ARTICLE_LIFETIME_VALUES)) {
                    update_post_meta($post_id, Enum::ACF_ARTICLE_LIFETIME_KEY, $article_lifetime_value);
                } else {
                    ringier_errorlogthis('[error] BUS: article_lifetime field value not in whitelist');
                }
            }

            //save custom field: is_post_new | for this we do not care if it is set or nnot, it;'s a hidden field
            update_post_meta($post_id, Enum::ACF_IS_POST_NEW_KEY, Enum::ACF_IS_POST_VALUE_EXISTED);
        } else {
            ringier_errorlogthis('[error] BUS: could not save custom fields');
        }
    }

    /**
     * To cater for custom post_type only
     *
     * @param $new_status
     * @param $old_status
     * @param $post
     */
    public static function cater_for_custom_post($new_status, $old_status, $post)
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

//        ringier_errorlogthis('post_type for this trigger: ' . print_r($post->post_type, true));
        add_action('rest_after_insert_' . trim($post->post_type), [self::class, 'triggerArticleEvent'], 15, 1);

        return;
    }

    /**
     * This action will be invoked ONLY when a post in being Created/Updated
     * (Codex for save_post: https://developer.wordpress.org/reference/hooks/save_post/)
     *
     *  hook `rest_after_insert_post` fires after post meta data has been saved by Gutenberg to the WP API
     *
     * Gutenberg now saves content directly via the WordPress API, so there is no POST data to intercept
     * hence previously hook `save_post` was of a not flexible way for us.
     *
     * @param int $post_ID
     * @param \WP_Post $post
     * @param bool $update
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
     * @github wkhayrattee
     */
    public static function triggerArticleEvent(\WP_Post $post)
    {
        $wordpress_post_status = $post->post_status;
        //we don't want to trigger the event if it is a draft, only when in public
        if (strcmp($wordpress_post_status, 'publish') == 0) {
            ringier_infologthis('triggerArticleEvent called');
            $post_ID = $post->ID;

            $post_ID = Utils::getParentPostId($post_ID);
            $articleTriggerMode = 'ArticleCreated';
            /*
             * This conditioning helps us get context if the post is in mode NEW or EDIT
             * There is no other way around this as of this date of coding (Apr 2021)
             * Hope in the future WordPress exposes a better way for us to get this context
             */
            if (is_object($post)) {
                $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
                if (Utils::isPostNew($post_ID) === true) {
                    $articleTriggerMode = Enum::EVENT_ARTICLE_CREATED;
                } else {
                    $articleTriggerMode = Enum::EVENT_ARTICLE_EDITED;
                }
                ringier_infologthis('$articleTriggerMode is: ' . $articleTriggerMode);
            }
            /*
             * we will now schedule the event after 1min instead of instantly executing it, because:
             * not all meta data of the article are updated correctly when:
             *      - article is first created,
             *      - when article meta are changed
             */
            self::scheduleSendToBus($articleTriggerMode, $post_ID, 0, 1);

            //push to SLACK
            $message = <<<EOF
            $blogKey: $articleTriggerMode queued for article (ID: $post_ID)
            Scheduled to run in the next minute(s)
        EOF;
            Utils::l($message);
        } else {
            ringier_infologthis('Event not sent due to post_status being: ' . $wordpress_post_status);
        }
    }

    /**
     * This action will be invoked ONLY when a post in being Created/Updated
     * I made use of Transitions
     * (Codex for Status Transitions: https://codex.wordpress.org/Post_Status_Transitions)
     *
     * @param \WP_Post $post
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     */
    public static function triggerArticleDeletedEvent(\WP_Post $post)
    {
        ringier_infologthis('publish_to_trash called');
        $post_ID = Utils::getParentPostId($post->ID);
        self::sendToBus(Enum::EVENT_ARTICLE_DELETED, $post_ID, $post);
    }

    /**
     * The action to run when the hook (scheduledHookName()) is invoked
     *
     * @param $articleTriggerMode
     * @param $post_ID
     * @param $countCalled
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     */
    public static function cronSendToBusScheduled($articleTriggerMode, $post_ID, $countCalled)
    {
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
        $message = <<<EOF
            $blogKey: Now attempting to execute Queued "Push-to-BUS" for article (ID: $post_ID)..
                    
            NOTE: 
                If no error follows, means push-to-BUS was successful
                (else task will be re-queued)
        EOF;

        Utils::l($message); //push to SLACK

        self::sendToBus($articleTriggerMode, $post_ID, get_post($post_ID), $countCalled);
    }

    /**
     * A refactored method to be used with above triggerArticleDeletedEvent() and triggerArticleEvent()
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     *
     * @param string $articleTriggerMode
     * @param int $post_ID
     * @param \WP_Post $post
     * @param int $countCalled to keep track of how many times this function was called by the cron
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function sendToBus(string $articleTriggerMode, int $post_ID, \WP_Post $post, int $countCalled = 1): void
    {
        try {
            $authClient = new Auth();
            $authClient->setParameters($_ENV[Enum::ENV_BUS_ENDPOINT], $_ENV[Enum::ENV_VENTURE_CONFIG], $_ENV[Enum::ENV_BUS_API_USERNAME], $_ENV[Enum::ENV_BUS_API_PASSWORD]);

            $result = $authClient->acquireToken();
            if ($result === true) {
                $articleEvent = new ArticleEvent($authClient);

                if (class_exists('Brand_settings')) {
                    $articleEvent->brandSettings = new \Brand_settings();
                }

                $articleEvent->setEventType($articleTriggerMode);
                $articleEvent->sendToBus($post_ID, $post);
            } else {
                ringier_infologthis('[error] A problem with Auth Token');
                ringier_errorlogthis('[error] A problem with Auth Token');

                throw new \Exception('A problem with Auth Token');
            }
        } catch (\Exception $exception) {
            ringier_infologthis('[warning] failed to call BUS, rescheduling');
            self::scheduleSendToBus($articleTriggerMode, $post_ID, $countCalled);
        }
    }

    /**
     * Called as part of back-off strategy
     * Will (re)queue the current task of sending request to bus for X minutes
     *
     * @author Wasseem<wasseemk@ringier.co.za>
     *
     * @param string $articleTriggerMode
     * @param int $post_ID
     * @param int $countCalled
     * @param mixed $run_after_minutes
     */
    public static function scheduleSendToBus(string $articleTriggerMode, int $post_ID, int $countCalled = 1, $run_after_minutes = false)
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
         * timestmap of any already scheduled event with SAME args
         *      - needs to be uniquely identified will return false if not scheduled
         */
        $alreadyScheduledTimestamp = wp_next_scheduled($hookSendToBus, $args);
        if ($alreadyScheduledTimestamp === false) { //means first time this cron is being scheduled
            wp_schedule_single_event($currentTimestampForAction, $hookSendToBus, $args, true);
        } else { //is not on first time
            //unschedule current
            wp_unschedule_event($alreadyScheduledTimestamp, $hookSendToBus, $args); //we want to remove any pre existing ones

            //re-schedule same for another time
            $args = [$articleTriggerMode, $post_ID, ++$countCalled]; //2nd time called, need to increment count
            wp_schedule_single_event($currentTimestampForAction, $hookSendToBus, $args, true);
        }

        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
        $message = <<<EOF
            $blogKey: [Queuing] Push-to-BUS for article (ID: $post_ID) has just been queued.
            And will run in the next ($minutesToRun)mins..
            
            Args:
        EOF;
        Utils::l($message . print_r($args, true)); //push to SLACK
    }

    /**
     * The hook name of our Scheduled Task for back-off strategy
     *
     * @return string
     */
    public static function scheduledHookName()
    {
        return Enum::HOOK_NAME_SCHEDULED_EVENTS;
    }
}
