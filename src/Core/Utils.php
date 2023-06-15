<?php
/**
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RingierBusPlugin\Bus\LoggingHandler;

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

        $imagelink = file_get_contents($image_url);
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
     * Fetch the property of a category
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
     * @return false|mixed
     */
    public static function getPrimaryCategoryProperty($post_id, $property)
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
        if ((!is_wp_error($term)) && is_array($categories)) {
            $primaryCategory = $categories[0];

            return $primaryCategory->{$property};
        }

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
                    return sanitize_text_field($results->meta_value);
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
                    return sanitize_text_field($results->meta_value);
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
     * @param array $context
     *
     * @throws \Monolog\Handler\MissingExtensionException
     */
    public static function l($message, $logLevel = 'alert', array $context = []): void
    {
        //Enable logging to Slack ONLY IF it was enabled
        if (isset($_ENV[Enum::ENV_SLACK_ENABLED]) && ($_ENV[Enum::ENV_SLACK_ENABLED] == 'ON')) {
            LoggingHandler::getInstance()->log($logLevel, $message, $context);
        } else {
            ringier_errorlogthis('[info] - did not to Slack, it is probably OFF');
        }
    }

    /**
     * Fetch a uuid in the form "1ee9aa1b-6510-4105-92b9-7171bb2f3089"
     *
     * @return UuidInterface
     */
    public static function uuid(): UuidInterface
    {
        return Uuid::uuid4();
    }

    /**
     * Strip all html tags includind scripts
     * And also strip all wordpress shortcodes
     *
     * @param $content
     *
     * @return string
     */
    public static function getRawContent($content): string
    {
        return strip_shortcodes(wp_strip_all_tags($content));
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
}
