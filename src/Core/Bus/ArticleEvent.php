<?php
/**
 * Build the JSON request & send on the following trigger
 *  - ArticleCreated
 *  - ArticleUpdated
 *  - ArticleDeleted
 *
 * USAGE example:
 * ///
 * $authClient = new Auth();
 * $authClient->setParameters($_ENV['BUS_ENDPOINT'], $_ENV['VENTURE_CONFIG'], $_ENV['BUS_API_USERNAME'], $_ENV['BUS_API_PASSWORD']);
 *
 * $result = $authClient->acquireToken();
 * if ($result === true) {
 * $articleEvent = new ArticleEvent($authClient);
 * $articleEvent->setEventType($articleTriggerMode);
 * $articleEvent->sendToBus($post_ID, $post);
 * } else {
 * wp_die('could not get token');
 * }
 * ///
 *
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class ArticleEvent
{
    /** @var AuthenticationInterface */
    private AuthenticationInterface $authClient;
    private string $eventType;

    /**
     * @var \Brand_settings
     * This class is specific to Ringier Blog platforms
     * For others not using this, the object would be null
     * Used mainly for retrieving custom meta data for Sailthru:
     *  E.g:
     *      "sailthru_tags": ["apartments-for-sale", "apartments-for-rent"],
     *      "sailthru_vars": {
     *          "page_type" : "article",
     *          "user_type": ["seeker"],
     *          "user_status": ["active", "passive"]
     *      },
     */
    public mixed $brandSettings;

    public function __construct(AuthenticationInterface $authClient)
    {
        $this->authClient = $authClient; //Let's get an auth token early
        $this->eventType = Enum::EVENT_ARTICLE_CREATED;
        $this->brandSettings = null; //initially null until set by respective brands' blog
    }

    /**
     * We will need to be able to set the type individually in the scenario for ArticleDeleted
     *
     * @param string $type
     */
    public function setEventType(string $type)
    {
        $this->eventType = $type;
    }

    /**
     * This for the JSON: "status": "" - enum: online, offline, deleted
     * The value for status will be set based on the status of the Article being created/edited
     *
     * @return string
     */
    private function getFieldStatus()
    {
        switch ($this->eventType) {
            case Enum::EVENT_ARTICLE_CREATED:
                return Enum::JSON_FIELD_STATUS_ONLINE;
                break;
            case Enum::EVENT_ARTICLE_DELETED:
                return Enum::JSON_FIELD_STATUS_DELETED;
                break;
            default:
                return Enum::JSON_FIELD_STATUS_ONLINE;
        }
    }

    /**
     * Will reuse $authClient object to send the Article Payload
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @throws \Exception
     */
    public function sendToBus(int $post_ID, \WP_Post $post): void
    {
        /*
         * TODO: As of this coding (Apr 2021) there was no use-case for Callback yet
         *
         * for now, we go the simple route as below.
         */
        try {
            $requestBody = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-type' => 'application/json',
                    'charset' => 'utf-8',
                    'x-api-key' => $this->authClient->getToken(),
                ],

                'json' => [
                    $this->buildMainRequestBody($post_ID, $post),
                ],
            ];
            //            ringier_errorlogthis(json_encode($this->buildMainRequestBody($post_ID, $post)));

            $response = $this->authClient->getHttpClient()->request(
                'POST',
                'events',
                $requestBody
            );
            $raw_response = (string) $response->getBody();
            $bodyArray = json_decode($raw_response, true);

            // To compress string so it is not truncated on Slack
            if (json_last_error() !== JSON_ERROR_NONE) {
                Utils::l('JSON encoding error: ' . json_last_error_msg());

                return;
            }
            $raw_response = json_encode($raw_response);

            if (extension_loaded('zlib')) {
                $raw_response = gzcompress($raw_response);
            }

            $message2 = <<<EOF
            The payload seems to have been successfully delivered.
            And the FULL json compressed with gzcompress() (to prevent truncation) was:
            
            EOF;
            Utils::l($message2 . base64_encode($raw_response)); //push to SLACK

            //            ringier_errorlogthis($bodyArray);
        } catch (\Exception $exception) {
            $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
            $message = <<<EOF
                            $blogKey: [ALERT] an error occurred for article (ID: $post_ID)
                            [This job should be (re)queued in the next few seconds..]
                            
                            Error message below:
                            
                        EOF;

            //log error to our custom log file - viewable via Admin UI
            ringier_errorlogthis('[api] ERROR occurred, below error thrown:');
            ringier_errorlogthis($exception->getMessage()); //push to SLACK

            //send to slack
            Utils::l($message . $exception->getMessage()); //push to SLACK

            //clear Auth token on any error
            $this->authClient->flushToken();

            //Queuing - done by outer call, hence rethrow error back
            throw $exception;
        }
    }

    /**
     * Main JSON structure is created here
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @throws \Exception
     *
     * @return array
     */
    public function buildMainRequestBody(int $post_ID, \WP_Post $post): array
    {
        return [
            'events' => [
                $this->eventType,
            ],
            'from' => $this->authClient->getVentureId(),
            'reference' => "$post_ID",
            'created_at' => date('Y-m-d\TH:i:s.vP'), //NOTE: \DateTime::RFC3339_EXTENDED has been deprecated
            'version' => Enum::BUS_API_VERSION,
            'payload' => [
                'article' => $this->buildArticlePayloadData($post_ID, $post),
            ],
        ];
    }

    /**
     * Sub JSON structure
     * Here we create the inner Article Payload
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @throws \Exception
     *
     * @return array
     */
    public function buildArticlePayloadData(int $post_ID, \WP_Post $post): array
    {
        $payload_array = [
            'reference' => "$post_ID",
            'status' => $this->getFieldStatus(),
            'created_at' => $this->getOgArticlePublishedDate($post_ID, $post),
            'published_at' => $this->getOgArticlePublishedDate($post_ID, $post),
            'updated_at' => $this->getOgArticleModifiedDate($post_ID, $post),
            'source_type' => 'original',
            'source_detail' => $this->getAuthorName($post_ID),
            'url' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => wp_get_canonical_url($post_ID),
                ],
            ],
            'title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::truncate($post->post_title, 255),
                ],
            ],
            'og_title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::truncate($this->getOgArticleOgTitle($post_ID, $post), 255),
                ],
            ],
            'description' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::truncate(get_the_excerpt($post_ID), 1000),
                ],
            ],
            'og_description' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::truncate($this->getOgArticleOgDescription($post_ID, $post), 1000),
                ],
            ],
            'teaser' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::truncate(get_the_excerpt($post_ID), 300),
                ],
            ],
            'wordcount' => Utils::getContentWordCount($this->fetchArticleContent($post_ID)),
            'images' => $this->getImages($post_ID),
            'parent_category' => $this->getParentCategoryArray($post_ID),
            /*
             * NOTE:
             *  For now 'categories' will have only one time similar to 'parent_category
             *  As per MKT-1639, until further priority comes, we agreed on the following:
             *      - Currently on the blog, there is only one level of categories
             *      - add categories and keep parent_category for now, as the CDE
             *        and the Sailthru publishing services will need some time to switch over
             *
             * (wasseem | 9th Dec 2022)
             */
            'categories' => $this->getAllCategoryListArray($post_ID),
            'sailthru_tags' => $this->getSailthruTags($post_ID),
            'sailthru_vars' => $this->getSailthruVars($post_ID),
            'lifetime' => Utils::getArticleLifetime($post_ID),
            'publication_reason' => Utils::getPublicationReason($post_ID),
            'primary_media_type' => $this->getPrimaryMediaType($post),
            'body' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::getRawContent($this->fetchArticleContent($post_ID)),
                ],
            ],
        ];

        if ($this->isCustomTopLevelCategoryEnabled() === true) {
            $payload_array['child_category'] = $this->getBlogParentCategory($post_ID);
        }

        /**
         * Builds the payload data for an article.
         *
         * @hook ringier_bus_build_article_payload
         *
         * @param array $payload_array The payload data array.
         * @param int $post_ID The ID of the post.s
         * @param \WP_Post $post The post object.
         *
         * @return array The payload data array.
         */
        return apply_filters('ringier_bus_build_article_payload', $payload_array, $post_ID, $post);
    }

    /**
     * Fetches the main content of the post, stripping out tags that WordPress adds
     *
     * @param int $post_ID
     *
     * @return string
     */
    private function fetchArticleContent(int $post_ID): string
    {
        return get_the_content(null, false, get_post($post_ID));
    }

    /**
     * Reconcile featured image list with the rest of the images in the article (post)
     *
     * @param int $post_ID
     *
     * @return array
     */
    private function getImages(int $post_ID): array
    {
        return array_merge(
            $this->fetchFeaturedImage($post_ID),
            $this->fetchPostImages($post_ID)
        );
    }

    /**
     * List of image sizes the event is expecting
     *
     * @return string[]
     */
    private function imageSizeList(): array
    {
        return [
            'small_rectangle',
            'small_square',
            'large_rectangle',
            'large_square',
        ];
    }

    /**
     * The key/value pairs as laid down by the BUS specs
     *
     * @param bool|string $imageUrl
     * @param string $size
     * @param mixed $image_alt
     * @param bool $isHero
     *
     * @return array
     */
    private function transformImageFieldsIntoExpectedFormat(bool|string $imageUrl, string $size, mixed $image_alt, bool $isHero = false): array
    {
        return [
            'url' => Utils::returnEmptyOnNullorFalse($imageUrl),
            'size' => $size,
            'alt_text' => Utils::returnEmptyOnNullorFalse($image_alt),
            'hero' => $isHero,
            'content_hash' => Utils::returnEmptyOnNullorFalse(Utils::hashImage($imageUrl)),
        ];
    }

    /**
     * @param int $post_ID
     *
     * @return array
     */
    private function fetchFeaturedImage(int $post_ID): array
    {
        $imageList = [];
        $imageSizes = $this->imageSizeList();

        foreach ($imageSizes as $size) {
            $imageId = get_post_thumbnail_id($post_ID);
            $imageAlt = get_post_meta($imageId, '_wp_attachment_image_alt', true);
            $imageUrl = get_the_post_thumbnail_url(get_post($post_ID), $size);
            //$image_title = get_the_title($image_id);
            $imageList[] = $this->transformImageFieldsIntoExpectedFormat($imageUrl, $size, $imageAlt, true);
        }

        return $imageList;
    }

    /**
     * @param int $post_ID
     *
     * @return array
     */
    private function fetchPostImages(int $post_ID): array
    {
        $finalImageList = [];
        $featuredImageId = get_post_thumbnail_id($post_ID);
        $imageList = get_attached_media('image', $post_ID);
        $imageSizes = $this->imageSizeList();

        //Remove the featured image in the list since we are already catering for it prior to this
        if (!empty($imageList) && (isset($imageList[$featuredImageId]))) {
            unset($imageList[$featuredImageId]);
        }

        //now deal with the rest
        if (count($imageList) > 0) {
            foreach ($imageList as $image) {
                foreach ($imageSizes as $size) {
                    $primaryImageSlug = sanitize_title($image->post_name);
                    /**
                     * There is an anomaly in WordPress, when an image is "removed" from a post,
                     * it is not updated in an unattached state automatically.
                     * ref: https://core.trac.wordpress.org/ticket/30691#comment:12
                     *
                     * So I am having to check if the post content actually has that image
                     * (Wasseem)
                     */
                    if ($this->isImageAttachedAndStillUsed($primaryImageSlug, $this->fetchArticleContent($post_ID))) {
                        $imageId = trim($image->ID);
                        $imageAlt = get_post_meta($imageId, '_wp_attachment_image_alt', true);
                        $imageUrl = wp_get_attachment_image_url($imageId, $size);
                        $finalImageList[] = $this->transformImageFieldsIntoExpectedFormat($imageUrl, $size, $imageAlt);
                    }
                }
            }
        }

        return $finalImageList;
    }

    /**
     * @param int $post_id
     *
     * @return string|null
     */
    private function getAuthorName(int $post_id): ?string
    {
        $author_id = get_post_field('post_author', $post_id);

        return get_the_author_meta('display_name', $author_id);
    }

    /**
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getPrimaryMediaType(\WP_Post $post): string
    {
        //default value
        $media_type = 'text';

        if (isset($post->post_content)) {
            $content = $post->post_content;

            if ($this->hasVideo($content)) {
                $media_type = 'video';
            } elseif ($this->hasGallery($content) === true) {
                $media_type = 'gallery';
            } elseif ($this->hasAudio($content)) {
                $media_type = 'audio';
            }
        }

        return $media_type;
    }

    /**
     * Check if article content has the specified image url
     *
     * @param string $post_name the main slug part of the image
     * @param string $content
     *
     * @return bool
     */
    private function isImageAttachedAndStillUsed(string $post_name, string $content)
    {
        if ((mb_strpos($content, $post_name) !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the WordPress content `$post->post_content` has gallery
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasGallery(string $content)
    {
        //we are using `strpos` instead of `str_contains` to be php7.4 compatible
        if ((mb_strpos($content, 'wp-block-gallery') !== false) ||
            (mb_strpos($content, 'wp:gallery') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the WordPress content `$post->post_content` has a youtube video
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasVideo(string $content)
    {
        if ((mb_strpos($content, 'https://www.youtube.com/') !== false) ||
            (mb_strpos($content, 'https://youtu.be/') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the WordPress content `$post->post_content` has an audio file
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasAudio(string $content)
    {
        if ((mb_strpos($content, '.mp3') !== false)) {
            return true;
        }

        return false;
    }

    private function getSailthruTags(int $post_ID)
    {
        if ($this->brandSettings == null) {
            return [];
        } elseif (isset($this->brandSettings->sailthru) && $this->brandSettings->sailthru->enable === false) {
            return [];
        }

        //else proceed further
        $vertical_type = (int) $this->brandSettings->sailthru->vertical;
        if ($vertical_type == 1) { //jobs
            $functions_terms_object = get_the_terms($post_ID, 'sailthru_functions');
            if (($functions_terms_object === false) || is_wp_error($functions_terms_object)) {
                $functions_list = [];
            } else {
                $functions_list = wp_list_pluck($functions_terms_object, 'slug');
            }

            $experience_level_terms_object = get_the_terms($post_ID, 'sailthru_experience_level');
            if (($experience_level_terms_object === false) || is_wp_error($experience_level_terms_object)) {
                $experience_level_list = [];
            } else {
                $experience_level_list = wp_list_pluck($experience_level_terms_object, 'slug');
            }

            return array_merge($functions_list, $experience_level_list);
        } elseif ($vertical_type == 3) { //property
            $meta_type_terms_object = get_the_terms($post_ID, 'sailthru_property_type');
            if (($meta_type_terms_object === false) || (is_wp_error($meta_type_terms_object))) {
                return [];
            }
            $meta_type_list = wp_list_pluck($meta_type_terms_object, 'slug');

            return $meta_type_list;
        }

        return [];
    }

    private function getSailthruVars(int $post_ID)
    {
        if ($this->brandSettings == null) {
            return [];
        } elseif ($this->brandSettings->sailthru->enable === false) {
            return [];
        }
        //get user_type
        $user_type_terms_object = get_the_terms($post_ID, 'sailthru_user_type');
        if (($user_type_terms_object === false) || is_wp_error($user_type_terms_object)) {
            $user_type_list = [];
        } else {
            $user_type_list = wp_list_pluck($user_type_terms_object, 'slug');
        }

        //get user_status
        $user_status_terms_object = get_the_terms($post_ID, 'sailthru_user_status');
        if (($user_status_terms_object === false) || is_wp_error($user_status_terms_object)) {
            $user_status_list = [];
        } else {
            $user_status_list = wp_list_pluck($user_status_terms_object, 'slug');
        }

        return [
            'content_type' => 'article',
            'locale' => ringier_getLocale(),
            'user_type' => $user_type_list,
            'user_status' => $user_status_list,
        ];
    }

    /**
     * Will return the primary category array depending on whether any user defined Top level category was ENABLEBD
     *
     * @param int $post_ID
     *
     * @throws \Monolog\Handler\MissingExtensionException
     *
     * @return array
     */
    private function getParentCategoryArray(int $post_ID): array
    {
        if ($this->isCustomTopLevelCategoryEnabled() === true) {
            return $this->getCustomTopLevelCategory();
        } else {
            return $this->getBlogParentCategory($post_ID);
        }
    }

    /**
     * To check if the custom Top Level category is enabled
     * This is done on the Settings page on the admin UI
     *
     * @return bool
     */
    private function isCustomTopLevelCategoryEnabled(): bool
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        // Check if the key exists before accessing its value.
        if (isset($options[Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY])) {
            $field_status_alt_category = $options[Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY];
            if (strcmp($field_status_alt_category, 'on') == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * This is the array for the custom top level category
     *
     * @return array
     */
    private function getCustomTopLevelCategory(): array
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        $field_alt_category = $options[Enum::FIELD_TEXT_ALTERNATE_PRIMARY_CATEGORY];

        return [
            'id' => 0,
            'title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::returnEmptyOnNullorFalse($field_alt_category),
                ],
            ],
            'slug' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => sanitize_title($field_alt_category),
                ],
            ],
        ];
    }

    /**
     * This is the actual primary category set within WordPress for this blog instance
     *
     * @param int $post_ID
     *
     * @throws \Monolog\Handler\MissingExtensionException
     *
     * @return array
     */
    private function getBlogParentCategory(int $post_ID): array
    {
        return [
            'id' => Utils::returnEmptyOnNullorFalse(Utils::getPrimaryCategoryProperty($post_ID, 'term_id'), true),
            'title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::returnEmptyOnNullorFalse(Utils::getPrimaryCategoryProperty($post_ID, 'name')),
                ],
            ],
            'slug' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::returnEmptyOnNullorFalse(Utils::getPrimaryCategoryProperty($post_ID, 'slug')),
                ],
            ],
        ];
    }

    /**
     * To get list of all categories
     *
     *
     *
     * @param int $post_ID
     *
     * @throws \Monolog\Handler\MissingExtensionException
     *
     * @return array
     */
    private function getAllCategoryListArray(int $post_ID): array
    {
        if ($this->isCustomTopLevelCategoryEnabled() === true) {
            $data_array[] = $this->getCustomTopLevelCategory();
        }
        $data_array[] = $this->getBlogParentCategory($post_ID);

        return $data_array;
    }

    /**
     * Get Modified Date for post
     * in the format RFC3339 (ISO8601)
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticleModifiedDate(int $post_ID, \WP_Post $post)
    {
        if (class_exists('YoastSEO') && (is_object(YoastSEO()))) {
            return YoastSEO()->meta->for_post($post_ID)->open_graph_article_modified_time;
        }

        return Utils::formatDate($post->post_modified_gmt);
    }

    /**
     * Get Published Date for post
     * in the format RFC3339 (ISO8601)
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticlePublishedDate(int $post_ID, \WP_Post $post)
    {
        if (class_exists('YoastSEO') && (is_object(YoastSEO()))) {
            return YoastSEO()->meta->for_post($post_ID)->open_graph_article_published_time;
        }

        return Utils::formatDate($post->post_date_gmt);
    }

    /**
     * Get Og Title of post
     * We use the Yoast wrapper if possible, else return normal title
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticleOgTitle(int $post_ID, \WP_Post $post)
    {
        if (class_exists('YoastSEO') && (is_object(YoastSEO()))) {
            return YoastSEO()->meta->for_post($post_ID)->open_graph_title;
        }

        return $post->post_title;
    }

    /**
     * Get Og Description of post
     * We use the Yoast wrapper if possible, else return normal Description
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticleOgDescription(int $post_ID, \WP_Post $post)
    {
        if (class_exists('YoastSEO') && (is_object(YoastSEO()))) {
            return YoastSEO()->meta->for_post($post_ID)->open_graph_description;
        }

        return get_the_excerpt($post_ID);
    }
}
