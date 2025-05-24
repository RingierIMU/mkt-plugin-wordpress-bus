<?php
/**p
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

use DateInterval;

class Utils
{
    /**
     * Used in the API calls, as some values need to be an empty string when false or null
     * For IDs, need to be 0
     *
     * @param $string
     * @param false $is_id
     *
     * @return int|string
     */
    public static function returnEmptyOnNullorFalse($string, $is_id = false)
    {
        if (($string === false) || is_null($string)) {
            if ($is_id === true) {
                return 0;
            }

            return '';
        }

        return $string;
    }

    /**
     * Send an md5 hash of the image content
     * Mainly used as part of the Bus API request
     *
     * @param $image_url
     *
     * @return string
     */
    public static function hashImage($image_url): string
    {
        if (empty($image_url) || is_null($image_url)) {
            return '';
        }

        $ch = curl_init($image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout

        $imagelink = curl_exec($ch);

        if ($imagelink === false) {
            $error = curl_error($ch);
            error_log('Curl error: ' . $error);
            $imagelink = ''; // or any other fallback value
        }

        curl_close($ch);

        if ($imagelink === false) {
            return '';
        }

        return md5($imagelink);
    }

    /**
     * Sometimes a post_id is not the actual parent_id
     * So get the parent_id please
     *
     * @param $post_ID
     *
     * @return int
     */
    public static function getParentPostId($post_ID)
    {
        $parent_id = wp_is_post_revision($post_ID);
        if ($parent_id !== false) {
            return $parent_id;
        }

        return $post_ID;
    }

    /**
     * Property is any of the following:
     * WP_Term Object
     * (
     * [term_id] => 16
     * [name] => Agent Advice
     * [slug] => estate-agent-advice
     * [term_group] => 0
     * [term_taxonomy_id] => 16
     * [taxonomy] => category
     * [description] =>
     * [parent] => 0
     * [count] => 17
     * [filter] => raw
     * )
     *
     * @param $post_id
     * @param $property
     *
     * @return mixed
     */
    public static function getPrimaryCategoryProperty($post_id, $property): mixed
    {
        //some post_ids are simply revisions, so make sure we are actually using the parent id
        $post_id = self::getParentPostId($post_id);

        //first we check via Yoast, as this plugin does provide for selecting a primary category
        // in case a post has more than one category - this feature is not available by default in wordpress
        if (function_exists('yoast_get_primary_term_id')) {
            $primary_term_id = yoast_get_primary_term_id('category', $post_id);
            $term = get_term($primary_term_id);
            if (!is_wp_error($term) && !empty($term)) {
                return $term->{$property};
            }
        }

        $categories = get_the_terms($post_id, 'category');
        if ((!is_wp_error($categories)) && is_array($categories)) {
            $primaryCategory = $categories[0];

            return $primaryCategory->{$property};
        }

        ringier_errorlogthis('Warning: Could not find a category for article with ID: ' . $post_id);
        Utils::slackthat('Warning: Could not find a category for article with ID: ' . $post_id);

        return false;
    }

    /**
     * Check if a post is new - return true if YES, else false otherwise
     * We check this by adding a custom field named is_post_new
     * When the post UI is first loaded, this field will not exist.
     * After an article is saved, the field will exists
     *
     * @param $post_ID
     *
     * @throws \Exception
     *
     * @return bool
     */
    public static function isPostNew($post_ID): bool
    {
        global $wpdb;
        try {
            $sql = $wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->prefix}postmeta pm
            WHERE pm.post_id = %s AND pm.meta_key = %s",
                $post_ID,
                Enum::ACF_IS_POST_NEW_KEY
            );
            $results = $wpdb->get_row($sql);

            if (is_object($results)) {
                if (isset($results->meta_value)) {
                    return false;
                }
            }
        } catch (\Exception $exception) {
            ringier_errorlogthis($exception->errorMessage());
        }

        return true;
    }

    /**
     * "Publication reason" is a custom field attached to each article
     *
     * @param $post_ID
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function getPublicationReason($post_ID): string
    {
        global $wpdb;
        try {
            $sql = $wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->prefix}postmeta pm
            WHERE pm.post_id = %s AND pm.meta_key = %s",
                $post_ID,
                Enum::FIELD_PUBLICATION_REASON_KEY
            );
            $results = $wpdb->get_row($sql);

            if (is_object($results)) {
                if (isset($results->meta_value)) {
                    $publication_reason = sanitize_text_field($results->meta_value);

                    /**
                     * Gets the publication reason for a post.
                     *
                     * @hook ringier_bus_get_publication_reason
                     *
                     * @param string $publication_reason The publication reason.
                     * @param int $post_ID The ID of the post.
                     *
                     * @return string The publication reason.
                     */
                    return apply_filters('ringier_bus_get_publication_reason', $publication_reason, $post_ID);
                }
            }
        } catch (\Exception $exception) {
            ringier_errorlogthis($exception->errorMessage());
        }

        return 'none';
    }

    /**
     * Article_Lifetime is a custom field attached to each article
     *
     * @param $post_ID
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function getArticleLifetime($post_ID): string
    {
        global $wpdb;
        try {
            $sql = $wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->prefix}postmeta pm
            WHERE pm.post_id = %s AND pm.meta_key = %s",
                $post_ID,
                Enum::ACF_ARTICLE_LIFETIME_KEY
            );
            $results = $wpdb->get_row($sql);

            if (is_object($results)) {
                if (isset($results->meta_value)) {
                    $article_lifetime = sanitize_text_field($results->meta_value);

                    /**
                     * Gets the article lifetime for a post.
                     *
                     * @hook ringier_bus_get_article_lifetime
                     *
                     * @param string $article_lifetime The article lifetime.
                     * @param int $post_ID The ID of the post.
                     *
                     * @return string The article lifetime.
                     */
                    return apply_filters('ringier_bus_get_article_lifetime', $article_lifetime, $post_ID);
                }
            }
        } catch (\Exception $exception) {
            ringier_errorlogthis($exception->errorMessage());
        }

        return 'none';
    }

    /**
     * Send the log to Slack via Slack webhook
     *
     * @param $message
     * @param string $logLevel
     */
    public static function slackthat($message, string $logLevel = 'alert'): void
    {
        //Enable logging to Slack ONLY IF it was enabled
        if (isset($_ENV[Enum::ENV_SLACK_ENABLED]) && ($_ENV[Enum::ENV_SLACK_ENABLED] == 'ON')) {
            self::pushToSlack($message, $logLevel);
        }
    }

    /**
     * Sends a message to Slack using a webhook URL.
     *
     * @param string|array $message The message to send. Can be a string or an array of strings.
     * @param string       $level   The log level (e.g., 'info', 'error', 'warning'). Default is 'info'.
     */
    public static function pushToSlack(string|array $message, string $level = Enum::LOG_INFO): void
    {
        $webhook = $_ENV[Enum::ENV_SLACK_HOOK_URL] ?? null;
        $channel = $_ENV[Enum::ENV_SLACK_CHANNEL_NAME] ?? null;
        $botName = $_ENV[Enum::ENV_SLACK_BOT_NAME] ?? 'MyPluginBot';

        if (empty($webhook) || empty($message)) {
            return;
        }

        // Convert array to multi-line string
        if (is_array($message)) {
            $message = implode("\n", $message);
        }

        // Capture caller file:line using debug_backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null;
        $location = $trace ? basename($trace['file']) . ':' . $trace['line'] : 'unknown location';

        // Build the formatted message
        $slackMessage = sprintf(
            "*%s* (%s):\n```%s```",
            mb_strtoupper($level),
            $location,
            $message
        );

        try {
            $payload = json_encode([
                'text' => $slackMessage,
                'username' => $botName,
                'channel' => $channel,
                'icon_emoji' => ':warning:',
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            ringier_errorlogthis('(Slack JSON Encode Error) ' . $e->getMessage());

            return;
        }

        $response = wp_remote_post($webhook, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $payload,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            ringier_errorlogthis('(Slack Error) ' . $response->get_error_message());
        }
    }

    /**
     * Strip all html tags includind scripts
     * And also strip all wordpress shortcodes
     *
     * @param string $content
     *
     * @return string
     */
    public static function getRawContent(string $content): string
    {
        return strip_shortcodes(wp_strip_all_tags($content));
    }

    /**
     * Strip all HTML tags and shortcodes using getRawContent(),
     * decode HTML entities, and remove ellipsis or truncated indicators like […]
     *
     * @param string $content
     *
     * @return string
     */
    public static function getDecodedContent(string $content): string
    {
        // Strip shortcodes and HTML tags
        $raw_content = self::getRawContent($content);

        // Decode HTML entities (e.g: &hellip; becomes '...')
        $decoded_content = html_entity_decode($raw_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove ellipsis and truncation indicators like […] or (...)
        return preg_replace('/\[\&?hellip\;\]|\[…\]|\(\.\.\.\)/', '', $decoded_content);
    }

    /**
     * Get approximate word count for the post
     * We strip all tags using our getRawContent()
     *
     * @param $content
     *
     * @return int
     */
    public static function getContentWordCount($content): int
    {
        return str_word_count($content, 0, 'éëïöçñÉËÏÖÇÑ');
    }

    /**
     * Checks whether the value is not empty or not null
     *
     * @param $value
     *
     * @return bool
     */
    public static function notEmptyOrNull($value): bool
    {
        if (is_object($value) && !is_null($value)) {
            return true;
        }
        if (is_array($value)) {
            if (count($value) == 1) { //to cope with [''] and [' '] arrays
                if (self::isAssociative($value)) {
                    return true;
                } elseif (isset($value[0]) && self::notEmptyOrNull($value[0])) {
                    return true;
                }

                return false;
            }
            if (sizeof($value) > 0) {
                return true;
            }

            return true;
        } else {
            if ((is_string($value) || is_int($value)) && ($value != '') && ($value != 'NULL') && (mb_strlen(trim($value)) > 0)) {
                return true;
            }

            return false;
        }
    }

    /**
     * To verify if an array is associative
     *
     * @param $thatArray
     *
     * @return bool
     */
    public static function isAssociative($thatArray): bool
    {
        foreach ($thatArray as $key => $value) {
            if ($key !== (int) $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format date, by default we need in format: RFC3339 (ISO8601)
     *
     * @param $date
     * @param string $format
     *
     * @return string
     */
    public static function formatDate($date, $format = \DATE_RFC3339): string
    {
        $immutable_date = \date_create_immutable_from_format('Y-m-d H:i:s', $date, new \DateTimeZone('UTC'));

        if (!$immutable_date) {
            return $date;
        }

        return $immutable_date->format($format);
    }

    /**
     * To truncate a sentence or content for a specific length,
     * performing a multi-byte safe operation
     *
     * @param string $content
     * @param int $length
     *
     * @return string
     */
    public static function truncate(string $content, int $length): string
    {
        return mb_substr($content, 0, $length);
    }

    /**
     * @param string $video_id
     * @param string $api_key
     *
     * @throws \Exception
     *
     * @return array
     */
    public static function fetch_youtube_video_details(string $video_id, string $api_key): array
    {
        $cache_key = 'ringier_bus_youtube_video_' . $video_id;
        // Check if data in cached
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // If no cached data, proceed to fetching data via API request
        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id={$video_id}&key={$api_key}";

        // Add headers to the request since somehow a blank referer is being forwarded to the API - we restrict in referer for security reasons
        $args = [
            'headers' => [
                'Referer' => get_site_url(),
            ],
        ];

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            //log error to our custom log file - viewable via Admin UI
            ringier_errorlogthis('[Youtube API] ERROR occurred, below error thrown:');

            // Convert WP_Error to string
            $error_message = $response->get_error_message();
            ringier_errorlogthis($error_message);

            $request_headers = wp_remote_retrieve_headers($response);
            ringier_errorlogthis('Request Headers: ' . json_encode($request_headers));

            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'])) {
            ringier_errorlogthis('[Youtube API] Warning - data was empty, below details:');

            // Decode the response body to extract the error message
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['error'])) {
                $error_message = $decoded_body['error']['message'];
                $error_details = isset($decoded_body['error']['errors']) ? json_encode($decoded_body['error']['errors']) : 'No further error details';
                ringier_errorlogthis('Error Message: ' . $error_message);
                ringier_errorlogthis('Error Details: ' . $error_details);

                $request_headers = wp_remote_retrieve_headers($response);
                ringier_errorlogthis('Request Headers: ' . json_encode($request_headers));
            } else {
                ringier_errorlogthis('No error details available in response body.');
            }

            return [];
        }

        $video = $data['items'][0];

        // Prepare video array
        $video_details = [
            'reference' => $video_id,
            'content_url' => "https://www.youtube.com/watch?v={$video_id}",
            'embed_url' => "https://www.youtube.com/embed/{$video_id}",
            'thumbnail' => $video['snippet']['thumbnails']['standard']['url'],
            'title' => $video['snippet']['title'],
            'description' => $video['snippet']['description'],
            'duration' => self::convert_youtube_duration($video['contentDetails']['duration']),
        ];

        // Cache data for 24 hours (86400 seconds)
        set_transient($cache_key, $video_details, 86400);

        return $video_details;
    }

    /**
     * @param string $duration
     *
     * @throws \Exception
     *
     * @return float|int
     */
    public static function convert_youtube_duration(string $duration): float|int
    {
        // YouTube duration is in ISO 8601 format, e.g., PT1M30S
        $interval = new DateInterval($duration);

        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * @param string $content
     *
     * @return array
     */
    public static function extract_youtube_video_ids(string $content): array
    {
        // Pattern will match both standard youtube URLs and shortened youtu.be URLs
        $pattern = '/https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/i';
        preg_match_all($pattern, $content, $matches);

        // no matches found
        if (empty($matches[1])) {
            return []; // Return an empty array if
        }

        // Remove any duplicate IDs
        $video_ids = array_unique($matches[1]); // 2nd element in $matches is the video IDs

        return $video_ids;
    }

    /**
     * @param array $urls
     *
     * @return array
     */
    public static function get_youtube_ids_from_urls(array $urls): array
    {
        $video_ids = [];

        foreach ($urls as $url) {
            if (!empty($url)) {
                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                if (isset($query['v'])) {
                    $video_ids[] = $query['v'];
                }
            }
        }

        return $video_ids;
    }

    /**
     * Loads a PHP template file safely with extracted arguments.
     *
     * @param string $template_path Absolute path to the template file.
     * @param array $args Variables to extract into the template's local scope.
     * @param bool $require_once Whether to require_once or require. Default true.
     *
     * @throws \Exception
     */
    public static function load_tpl(string $template_path, array $args = [], bool $require_once = true): void
    {
        if (!file_exists($template_path)) {
            ringier_errorlogthis("[Template] Warning - template file not found: {$template_path}");
        }

        load_template($template_path, $require_once, $args);
    }

    /**
     * Checks whether Bus Events are enabled for a given post type.
     *
     * Core post types like 'post' are always enabled.
     * For custom post types, this checks whether:
     *   1. The global toggle for allowing custom post types is enabled.
     *   2. The specific post type is explicitly allowed in the settings.
     *
     * @param string $post_type The post type to check (e.g. 'post', 'event', 'interview').
     *
     * @return bool True if Bus Events are enabled for the given post type, false otherwise.
     */
    public static function isBusEventEnabledForPostType(string $post_type): bool
    {
        if ($post_type === 'post') {
            return true;
        }

        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $custom_enabled = !empty($options[Enum::FIELD_ALLOW_CUSTOM_POST_TYPES]) && $options[Enum::FIELD_ALLOW_CUSTOM_POST_TYPES] === 'on';
        $allowed_list = $options[Enum::FIELD_ENABLED_CUSTOM_POST_TYPE_LIST] ?? [];

        return $custom_enabled && !empty($allowed_list[$post_type]) && $allowed_list[$post_type] === 'on';
    }

    /**
     * @param int $user_id
     * @param array $userdata
     *
     * @return array
     */
    public static function buildAuthorInfo(int $user_id, array $userdata): array
    {
        /**
         * Retrieve the user meta for the key Enum::META_SHOW_PROFILE_PAGE_KEY if present
         * This applies only to Ringier's inhouse ventures who uses our AuthorAddon plugin
         */
        $show_profile_page = get_user_meta($user_id, Enum::META_SHOW_PROFILE_PAGE_KEY, true);
        // Default to offline
        $author_page_status = Enum::JSON_FIELD_STATUS_OFFLINE;
        // Check the value of $show_profile_page
        if ($show_profile_page === 'on') {
            $author_page_status = Enum::JSON_FIELD_STATUS_ONLINE;
        } elseif (empty($show_profile_page)) {
            // If empty or not set, assume the site is not using the AuthorAddon plugin
            $author_page_status = Enum::JSON_FIELD_STATUS_ONLINE;
        }

        /**
         * Get Author professional Name
         */
        $first_name = isset($userdata['first_name']) ? sanitize_text_field($userdata['first_name']) : '';
        $last_name = isset($userdata['last_name']) ? sanitize_text_field($userdata['last_name']) : '';
        $professional_name = trim($first_name . ' ' . $last_name);

        /**
         * Get Author page URL
         */
        $author_url = get_author_posts_url($user_id);

        /**
         * Get author creation date
         */
        $created_at = Utils::formatDate($userdata['user_registered']);

        /**
         * Get author updated date
         */
        $last_updated = Utils::formatDate(
            get_user_meta($user_id, Enum::DB_FIELD_AUTHOR_LAST_MODIFIED_DATE, true)
        );

        /**
         * Author Avatar URL
         */
        $author_email = $userdata['user_email'];
        $author_avatar = get_avatar_url($author_email);
        // todo: to fetch High res image from AuthorAddon plugin

        return [
            'id' => $user_id,
            'reference' => $user_id,
            'url' => $author_url,
            'name' => $professional_name,
            'writer_type' => Enum::WRITER_TYPE,
            'status' => $author_page_status,
            'created_at' => $created_at,
            'updated_at' => $last_updated,
            'image' => $author_avatar,
        ];
    }

    /**
     * Determines if a user has at least one of the specified roles.
     *
     * Iterates through the provided roles and checks if the user has any of them
     * using the native WordPress `user_can()` function.
     *
     * @param int $user The user ID to check.
     * @param array $roles An array of role slugs (e.g., 'editor', 'author', etc.).
     *
     * @return bool True if the user has at least one of the roles; false otherwise.
     */
    public static function user_has_any_role(int $user, array $roles): bool
    {
        return (bool) array_filter($roles, fn ($role) => user_can($user, $role));
    }
}
