<?php
/**
 * Build the JSON request & send on the following trigger:
 *  - ArticleCreated
 *  - ArticleUpdated
 *  - ArticleDeleted
 *
 * Uses 100% native WordPress APIs (wp_remote_post, transients).
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
    private BusTokenManager $tokenManager;
    private string $eventType;
    private string $endpointUrl;

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

    public function __construct(BusTokenManager $tokenManager, string $endpointUrl)
    {
        $this->tokenManager = $tokenManager;
        $this->endpointUrl = rtrim($endpointUrl, '/');
        $this->eventType = Enum::EVENT_ARTICLE_CREATED;
        $this->brandSettings = null;
    }

    public function setEventType(string $type): void
    {
        $this->eventType = $type;
    }

    /**
     * This for the JSON: "status": "" - enum: online, offline, deleted
     * The value for status will be set based on the status of the Article being created/edited
     *
     * @return string
     */
    private function getFieldStatus(): string
    {
        return match ($this->eventType) {
            Enum::EVENT_ARTICLE_DELETED => Enum::JSON_FIELD_STATUS_DELETED,
            default => Enum::JSON_FIELD_STATUS_ONLINE,
        };
    }

    public function sendToBus(int $post_ID, \WP_Post $post): void
    {
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY] ?? 'defaultBlogKey';

        try {
            $authToken = $this->tokenManager->getToken();

            if (!$authToken) {
                $error_msg = 'ArticleEvent: Failed to retrieve authentication token.';
                ringier_errorlogthis($error_msg);
                Utils::slackthat($error_msg, Enum::LOG_ERROR);

                return;
            }

            // Build payload
            $payloadData = [
                $this->buildMainRequestBody($post_ID, $post),
            ];
            $jsonBody = wp_json_encode($payloadData);

            $requestBody = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'charset' => 'utf-8',
                    'x-api-key' => $authToken,
                ],
                'body' => $jsonBody,
                'timeout' => 15,
            ];

            $response = wp_remote_post(
                trailingslashit($this->endpointUrl) . 'events',
                $requestBody
            );

            // Handle WP Errors (Network issues, DNS, etc)
            if (is_wp_error($response)) {
                $error_msg = 'ArticleEvent: Could not send request to BUS: ' . $response->get_error_message();
                ringier_errorlogthis($error_msg);
                Utils::slackthat($error_msg, Enum::LOG_ERROR);

                return;
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            // Handle API Errors (4xx, 5xx)
            if (!in_array($responseCode, [200, 201], true)) {
                $error_msg = "(API|ArticleEvent) Invalid response from BUS ($responseCode): " . $responseBody;
                ringier_errorlogthis($error_msg);
                Utils::slackthat($error_msg, Enum::LOG_ERROR);

                // If 401/403, flush token
                if ($responseCode === 401 || $responseCode === 403) {
                    $this->tokenManager->flushToken();
                }

                return;
            }

            // Success
            $message = <<<EOF
                // START OF MESSAGE //
                $blogKey: [INFO] The Article (ID: $post_ID) was successfully delivered to the BUS..
                .
                .
                Payload sent was:
                $responseBody
                .
                .
                // END OF MESSAGE //
                .
                .
            EOF;

            Utils::slackthat($message);

        } catch (\Exception $exception) {
            $message = <<<EOF
                $blogKey: [ALERT] ArticleEvent: An error occurred for article (ID: $post_ID)
                Error message below:
            EOF;

            ringier_errorlogthis('(api) ArticleEvent Exception: ' . $exception->getMessage());
            Utils::slackthat($message . $exception->getMessage(), Enum::LOG_ERROR);

            // Force token refresh on next run if something went wrong
            $this->tokenManager->flushToken();
        }
    }

    private function buildMainRequestBody(int $post_ID, \WP_Post $post): array
    {
        return [
            'events' => [
                $this->eventType,
            ],
            'from' => $this->tokenManager->getVentureId(),
            'reference' => (string) $post_ID,
            'created_at' => date('Y-m-d\TH:i:s.vP'), //NOTE: \DateTime::RFC3339_EXTENDED has been deprecated
            'version' => Enum::BUS_API_VERSION,
            'payload' => [
                'article' => $this->buildArticlePayloadData($post_ID, $post),
            ],
        ];
    }

    private function buildArticlePayloadData(int $post_ID, \WP_Post $post): array
    {
        // Cache values used multiple times
        $articleContent = $this->fetchArticleContent($post_ID);
        $rawContent = Utils::getRawContent($articleContent);
        $publishedDate = $this->getOgArticlePublishedDate($post_ID, $post);
        $isCustomTopLevel = $this->isCustomTopLevelCategoryEnabled();

        $payload_array = [
            'reference' => (string) $post_ID,
            'status' => $this->getFieldStatus(),
            'created_at' => $publishedDate,
            'published_at' => $publishedDate,
            'updated_at' => $this->getOgArticleModifiedDate($post_ID, $post),
            'source_type' => 'original',
            'source_detail' => $this->getAuthorName($post_ID),
            'url' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::get_reliable_permalink($post_ID),
                ],
            ],
            'canonical' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::get_canonical_url($post_ID),
                ],
            ],
            'title' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::truncate(Utils::getDecodedContent($post->post_title), 255),
                ],
            ],
            'og_title' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::truncate(Utils::getDecodedContent($this->getOgArticleOgTitle($post_ID, $post)), 255),
                ],
            ],
            'description' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::truncate(Utils::getDecodedContent(get_the_excerpt($post_ID)), 1000),
                ],
            ],
            'og_description' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::truncate(Utils::getDecodedContent($this->getOgArticleOgDescription($post_ID, $post)), 1000),
                ],
            ],
            'teaser' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => Utils::truncate(Utils::getDecodedContent(get_the_excerpt($post_ID)), 300),
                ],
            ],
            'wordcount' => Utils::getContentWordCount($rawContent),
            'images' => $this->getImages($post_ID),
            'parent_category' => $this->getParentCategoryArray($post_ID),
            'categories' => $this->getAllCategoryListArray($post_ID),
            'taxon_tags' => $this->getTaxonTags($post_ID),
            'sailthru_tags' => $this->getSailthruTags($post_ID),
            'sailthru_vars' => $this->getSailthruVars($post_ID),
            'lifetime' => Utils::getArticleLifetime($post_ID),
            'publication_reason' => Utils::getPublicationReason($post_ID),
            'primary_media_type' => $this->getPrimaryMediaType($post),
            'body' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => $rawContent,
                ],
            ],
        ];

        // Custom Top Level Category Logic
        if ($isCustomTopLevel) {
            $primary_parent_category = Utils::getPrimaryCategoryProperty($post_ID, 'term_id');
            if (!empty($primary_parent_category)) {
                $payload_array['child_category'] = $this->getBlogParentCategory($post_ID);
            }
        }

        // Handle YouTube Videos
        $video_id_list = Utils::extract_youtube_video_ids($rawContent);
        $youtube_api_key = $_ENV[Enum::ENV_GOOGLE_YOUTUBE_API_KEY] ?? '';

        if (!empty($video_id_list) && !empty($youtube_api_key)) {
            $video_data_list = [];
            foreach ($video_id_list as $video_id) {
                $video_data_list[] = Utils::fetch_youtube_video_details($video_id, $youtube_api_key);
            }
            if (!empty($video_data_list)) {
                $payload_array['videos'] = $video_data_list;
            }
        }

        /**
         * Builds the payload data for an article.
         *
         * @hook ringier_bus_build_article_payload
         *
         * @param array $payload_array The payload data array.
         * @param int $post_ID The ID of the post.
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
     * @param int $attachmentId
     *
     * @return array
     */
    private function transformImageFieldsIntoExpectedFormat(bool|string $imageUrl, string $size, mixed $image_alt, bool $isHero = false, int $attachmentId = 0): array
    {
        return [
            'url' => Utils::returnEmptyOnNullorFalse($imageUrl),
            'size' => $size,
            'alt_text' => Utils::returnEmptyOnNullorFalse($image_alt),
            'hero' => $isHero,
            'content_hash' => Utils::returnEmptyOnNullorFalse(Utils::hashImage($attachmentId)),
        ];
    }

    /**
     * @param int $post_ID
     *
     * @return array
     */
    private function fetchFeaturedImage(int $post_ID): array
    {
        $imageId = get_post_thumbnail_id($post_ID);
        if (!$imageId) {
            return [];
        }

        $imageList = [];
        $imageAlt = get_post_meta($imageId, '_wp_attachment_image_alt', true);

        foreach ($this->imageSizeList() as $size) {
            $imageUrl = get_the_post_thumbnail_url($post_ID, $size);

            if ($imageUrl) {
                $imageList[] = $this->transformImageFieldsIntoExpectedFormat($imageUrl, $size, $imageAlt, true, (int) $imageId);
            }
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
        if (!empty($imageList) && isset($imageList[$featuredImageId])) {
            unset($imageList[$featuredImageId]);
        }

        $articleContent = $this->fetchArticleContent($post_ID);

        foreach ($imageList as $image) {
            $primaryImageSlug = sanitize_title($image->post_name);
            /**
             * There is an anomaly in WordPress, when an image is "removed" from a post,
             * it is not updated in an unattached state automatically.
             * ref: https://core.trac.wordpress.org/ticket/30691#comment:12
             *
             * So I am having to check if the post content actually has that image
             * (Wasseem)
             */
            if (!$this->isImageAttachedAndStillUsed($primaryImageSlug, $articleContent)) {
                continue;
            }

            $imageId = $image->ID;
            $imageAlt = get_post_meta($imageId, '_wp_attachment_image_alt', true);

            foreach ($imageSizes as $size) {
                $imageUrl = wp_get_attachment_image_url($imageId, $size);

                if ($imageUrl) {
                    $finalImageList[] = $this->transformImageFieldsIntoExpectedFormat($imageUrl, $size, $imageAlt, false, (int) $imageId);
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
        $content = $post->post_content;

        if ($this->hasVideo($content)) {
            return 'video';
        }
        if ($this->hasGallery($content)) {
            return 'gallery';
        }
        if ($this->hasAudio($content)) {
            return 'audio';
        }

        return 'text';
    }

    /**
     * Check if article content has the specified image url
     *
     * @param string $post_name the main slug part of the image
     * @param string $content
     *
     * @return bool
     */
    private function isImageAttachedAndStillUsed(string $post_name, string $content): bool
    {
        return str_contains($content, $post_name);
    }

    /**
     * Check if the WordPress content `$post->post_content` has gallery
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasGallery(string $content): bool
    {
        return str_contains($content, 'wp-block-gallery') || str_contains($content, 'wp:gallery');
    }

    /**
     * Check if the WordPress content `$post->post_content` has a youtube video
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasVideo(string $content): bool
    {
        return str_contains($content, 'https://www.youtube.com/') || str_contains($content, 'https://youtu.be/');
    }

    /**
     * Check if the WordPress content `$post->post_content` has an audio file
     *
     * @param string $content
     *
     * @return bool
     */
    private function hasAudio(string $content): bool
    {
        return str_contains($content, '.mp3');
    }

    private function getSailthruTags(int $post_ID): array
    {
        if ($this->brandSettings === null) {
            return [];
        } elseif (isset($this->brandSettings->sailthru) && $this->brandSettings->sailthru->enable === false) {
            return [];
        }

        $vertical_type = (int) $this->brandSettings->sailthru->vertical;
        if ($vertical_type === 1) { // jobs
            $functions_terms_object = get_the_terms($post_ID, 'sailthru_functions');
            $functions_list = (!empty($functions_terms_object) && !is_wp_error($functions_terms_object)) ? wp_list_pluck($functions_terms_object, 'slug') : [];

            $experience_level_terms_object = get_the_terms($post_ID, 'sailthru_experience_level');
            $experience_level_list = (!empty($experience_level_terms_object) && !is_wp_error($experience_level_terms_object)) ? wp_list_pluck($experience_level_terms_object, 'slug') : [];

            return array_merge($functions_list, $experience_level_list);
        } elseif ($vertical_type === 3) { // property
            $meta_type_terms_object = get_the_terms($post_ID, 'sailthru_property_type');
            if (empty($meta_type_terms_object) || is_wp_error($meta_type_terms_object)) {
                return [];
            }

            return wp_list_pluck($meta_type_terms_object, 'slug');
        }

        return [];
    }

    private function getSailthruVars(int $post_ID): array
    {
        if ($this->brandSettings === null) {
            return [];
        } elseif (isset($this->brandSettings->sailthru) && $this->brandSettings->sailthru->enable === false) {
            return [];
        }

        $user_type_terms_object = get_the_terms($post_ID, 'sailthru_user_type');
        $user_type_list = (!empty($user_type_terms_object) && !is_wp_error($user_type_terms_object)) ? wp_list_pluck($user_type_terms_object, 'slug') : [];

        $user_status_terms_object = get_the_terms($post_ID, 'sailthru_user_status');
        $user_status_list = (!empty($user_status_terms_object) && !is_wp_error($user_status_terms_object)) ? wp_list_pluck($user_status_terms_object, 'slug') : [];

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
     * NOTE: parent_category should be a TranslationObject, not a list of TranslationObjects
     *
     * @param int $post_ID
     *
     * @return array
     */
    private function getParentCategoryArray(int $post_ID): array
    {
        $category = [];

        // Check if custom top-level category is enabled
        if ($this->isCustomTopLevelCategoryEnabled()) {
            $category = $this->getCustomTopLevelCategory();
        } else {
            // Fetch default blog parent category
            $primary_parent_category = Utils::getPrimaryCategoryProperty($post_ID, 'term_id');
            if (!empty($primary_parent_category)) {
                $category = $this->getBlogParentCategory($post_ID);
            } else {
                $category = $this->getAllHierarchicalTaxonomiesForThePostType($post_ID);
            }
        }

        return $category;
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

        return ($options[Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY] ?? '') === 'on';
    }

    /**
     * This is the array for the custom top level category
     *
     * @return array
     */
    private function getCustomTopLevelCategory(): array
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        $field_alt_category = $options[Enum::FIELD_TEXT_ALTERNATE_PRIMARY_CATEGORY] ?? '';

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

    private function getBlogParentCategory(int $post_ID): array
    {
        $term_id = Utils::getPrimaryCategoryProperty($post_ID, 'term_id');
        $term = !empty($term_id) ? get_term($term_id) : null;

        return [
            'id' => Utils::returnEmptyOnNullorFalse($term->term_id ?? null, true),
            'title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::returnEmptyOnNullorFalse($term->name ?? null),
                ],
            ],
            'slug' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::returnEmptyOnNullorFalse($term->slug ?? null),
                ],
            ],
        ];
    }

    private function getAllCategoryListArray(int $post_ID): array
    {
        $categories = [];

        // Include custom top-level category if enabled
        if ($this->isCustomTopLevelCategoryEnabled()) {
            $categories[] = $this->getCustomTopLevelCategory();
        }

        // Fetch all default categories associated with the post
        $defaultCategories = get_the_category($post_ID);
        if (!empty($defaultCategories)) {
            foreach ($defaultCategories as $category) {
                $categories[] = [
                    'id' => $category->term_id,
                    'title' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $category->name,
                        ],
                    ],
                    'slug' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $category->slug,
                        ],
                    ],
                ];
            }
        }

        // Fetch custom taxonomy categories
        $custom_taxo_list = $this->getAllHierarchicalTaxonomiesForThePostType($post_ID);
        if (!empty($custom_taxo_list)) {
            $categories = array_merge($categories, $custom_taxo_list);
            // Remove duplicates by ID
            $categories = array_values(array_reduce($categories, function ($carry, $item) {
                if (!isset($carry[$item['id']])) {
                    $carry[$item['id']] = $item;
                }

                return $carry;
            }, []));
        }

        return $categories;
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
    private function getOgArticleModifiedDate(int $post_ID, \WP_Post $post): string
    {
        // Ensure we have a valid GMT modified date; fallback to local modified if GMT is empty
        $date = !empty($post->post_modified_gmt) && $post->post_modified_gmt !== '0000-00-00 00:00:00'
            ? $post->post_modified_gmt
            : $post->post_modified;

        return Utils::formatDate($date);
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
    private function getOgArticlePublishedDate(int $post_ID, \WP_Post $post): string
    {
        // Ensure we have a valid GMT date; fallback to local date if GMT is empty
        $date = !empty($post->post_date_gmt) && $post->post_date_gmt !== '0000-00-00 00:00:00'
            ? $post->post_date_gmt
            : $post->post_date;

        return Utils::formatDate($date);
    }

    /**
     * Get Og Title of post
     *
     * Uses the native WP_Post title as the source of truth (not Yoast indexables,
     * which can be stale if reindexing hasn't run after a post edit).
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticleOgTitle(int $post_ID, \WP_Post $post): string
    {
        return $post->post_title;
    }

    /**
     * Get Og Description of post
     *
     * Uses the native WP excerpt as the source of truth (not Yoast indexables,
     * which can be stale if reindexing hasn't run after a post edit).
     *
     * @param int $post_ID
     * @param \WP_Post $post
     *
     * @return string
     */
    private function getOgArticleOgDescription(int $post_ID, \WP_Post $post): string
    {
        return get_the_excerpt($post_ID);
    }

    /**
     * Get all tags associated with a post as TranslationObjects.
     *
     * For standard posts this includes the built-in `post_tag` taxonomy.
     * For custom post types it includes any non-hierarchical (tag-like) custom
     * taxonomy registered for that post type, excluding irrelevant ones.
     *
     * @param int $post_ID
     *
     * @return array
     */
    private function getTaxonTags(int $post_ID): array
    {
        $tags = [];
        $post_type = get_post_type($post_ID);
        $taxonomies = get_object_taxonomies($post_type, 'objects');

        if (empty($taxonomies)) {
            return $tags;
        }

        foreach ($taxonomies as $taxonomy => $taxonomy_obj) {
            // Only process flat (non-hierarchical) taxonomies — i.e. tag-like ones
            if ($taxonomy_obj->hierarchical || in_array($taxonomy, Enum::TAXONOMY_BLACKLIST, true)) {
                continue;
            }

            $terms = get_the_terms($post_ID, $taxonomy);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $tags[] = [
                    'id' => $term->term_id,
                    'title' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $term->name,
                        ],
                    ],
                    'slug' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $term->slug,
                        ],
                    ],
                ];
            }
        }

        return $tags;
    }

    /**
     * Get all hierarchical taxonomies for the post type
     * This will be used to fetch all terms for the post type
     *
     * @param int $post_ID
     *
     * @return array
     */
    private function getAllHierarchicalTaxonomiesForThePostType(int $post_ID): array
    {
        $categories = [];
        $post_type = get_post_type($post_ID);
        $taxonomies = get_object_taxonomies($post_type, 'objects');

        if (empty($taxonomies)) {
            return $categories;
        }

        // For hierarchical taxonomies, also exclude post_tag (flat taxonomy)
        $blacklist = array_merge(Enum::TAXONOMY_BLACKLIST, ['post_tag']);

        foreach ($taxonomies as $taxonomy => $taxonomy_obj) {
            if (!$taxonomy_obj->hierarchical || in_array($taxonomy, $blacklist, true)) {
                continue;
            }

            $terms = get_the_terms($post_ID, $taxonomy);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $categories[] = [
                    'id' => $term->term_id,
                    'title' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $term->name,
                        ],
                    ],
                    'slug' => [
                        [
                            'culture' => ringier_getLocale(),
                            'value' => $term->slug,
                        ],
                    ],
                ];
            }
        }

        return $categories;
    }
}
