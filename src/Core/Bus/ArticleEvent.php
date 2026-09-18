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

    public function sendToBus(int $post_ID, \WP_Post $post): bool
    {
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY] ?? 'defaultBlogKey';

        try {
            $authToken = $this->tokenManager->getToken();

            if (!$authToken) {
                $error_msg = 'ArticleEvent: Failed to retrieve authentication token.';
                ringier_errorlogthis($error_msg);
                Utils::slackthat($error_msg, Enum::LOG_ERROR);

                return false;
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

                return false;
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

                return false;
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

            return true;

        } catch (\Exception $exception) {
            $message = <<<EOF
                $blogKey: [ALERT] ArticleEvent: An error occurred for article (ID: $post_ID)
                Error message below:
            EOF;

            ringier_errorlogthis('(api) ArticleEvent Exception: ' . $exception->getMessage());
            Utils::slackthat($message . $exception->getMessage(), Enum::LOG_ERROR);

            // Force token refresh on next run if something went wrong
            $this->tokenManager->flushToken();

            return false;
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
     * The stored article body, used to work out which images the article contains.
     *
     * Deliberately not `get_the_content()`: that returns the teaser only when the
     * body carries a `<!--more-->` tag, the first page only when it carries
     * `<!--nextpage-->`, and the password form for a protected post. Those are
     * reasonable for rendering, but images are resolved from this string alone, so
     * a truncated view silently drops every image below the cut.
     *
     * @param int $post_ID
     *
     * @return string
     */
    private function fetchImageResolutionContent(int $post_ID): string
    {
        $post = get_post($post_ID);

        return $post instanceof \WP_Post ? (string) $post->post_content : '';
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
     * The non-hero images of an article.
     *
     * The list is derived from the article content itself (block attributes,
     * `wp-image-<id>` classes, and — as a last resort — upload URLs), never from
     * the attachment/post relationship.
     *
     * @param int $post_ID
     *
     * @return array
     */
    private function fetchPostImages(int $post_ID): array
    {
        $finalImageList = [];
        $imageSizes = $this->imageSizeList();
        $imageIdList = $this->resolveContentImageIds($post_ID);

        foreach ($imageIdList as $imageId) {
            $imageAlt = get_post_meta($imageId, '_wp_attachment_image_alt', true);

            foreach ($imageSizes as $size) {
                $imageUrl = wp_get_attachment_image_url($imageId, $size);

                if ($imageUrl) {
                    $finalImageList[] = $this->transformImageFieldsIntoExpectedFormat($imageUrl, $size, $imageAlt, false, $imageId);
                }
            }
        }

        return $finalImageList;
    }

    /**
     * Resolve the attachment IDs of every image genuinely used in the article body.
     *
     * Historically this list came from `get_attached_media()`, i.e. from *ownership*
     * (the attachment's `post_parent`) rather than *usage*. WordPress never resets
     * `post_parent` when an editor removes an image from an article
     * (ref: https://core.trac.wordpress.org/ticket/30691#comment:12), so the list had
     * to be filtered by a substring check of the attachment slug against the content.
     * That check failed in both directions, because WordPress appends a collision
     * suffix to the slug and to the filename independently:
     *
     *  - false positive: a removed image slugged `uzucapiune` is a substring of its
     *    replacement `uzucapiunea`, so the dead image was dispatched forever;
     *  - false negative: a live image slugged `bloc-2` is stored as `bloc.jpg`, the
     *    slug appears nowhere in the content, so the image was never dispatched.
     *
     * Resolving by attachment ID removes both failure modes: an ID in the body means
     * the image is in the article, and nothing else does.
     *
     * An ID is only believed when it agrees with the `<img src>` it sits on. Content
     * migrated from another property keeps the *source* site's `wp-image-<id>` class,
     * which locally points at an unrelated picture; trusting it would dispatch an
     * image the article has never shown. Where they disagree the URL wins, because
     * the URL is what the reader sees.
     *
     * The featured image is excluded — it is dispatched separately as the hero.
     *
     * @param int $post_ID
     *
     * @return int[] unique attachment IDs
     */
    private function resolveContentImageIds(int $post_ID): array
    {
        $content = $this->fetchImageResolutionContent($post_ID);
        $featuredImageId = (int) get_post_thumbnail_id($post_ID);

        /*
         * Markup the editor commented out is not in the article, so it must not
         * contribute images. Block delimiters are HTML comments too, and
         * `parse_blocks()` needs them, so it reads the untouched content.
         */
        $visibleContent = $this->stripNonBlockHtmlComments($content);

        //Reconciles each `wp-image-<id>` class against the `src` of its own tag
        $tagResolution = $this->collectImgTagImageIds($visibleContent);
        $blockResolution = $this->collectBlockImageIds(parse_blocks($content));

        $candidateIdList = array_merge($tagResolution['ids'], $blockResolution['ids']);

        /*
         * A rejection only clears when some tag or block positively confirmed that ID
         * against a URL — the same stale class is routinely copied across several tags
         * in one article, one of which may be the tag it is actually right for. An ID
         * merely accepted for want of anything to check it against does not count:
         * that is the unvalidated case, not a confirmation.
         */
        $rejectedIdList = array_diff(
            array_merge($tagResolution['rejected'], $blockResolution['rejected']),
            array_merge($tagResolution['confirmed'], $blockResolution['confirmed'])
        );

        $imageIdList = [];
        foreach ($candidateIdList as $candidateId) {
            if ($candidateId <= 0 || $candidateId === $featuredImageId) {
                continue;
            }
            //Contradicted by the URL of every tag or block that named it
            if (in_array($candidateId, $rejectedIdList, true)) {
                continue;
            }
            if (isset($imageIdList[$candidateId]) || !$this->isImageAttachment($candidateId)) {
                continue;
            }
            $imageIdList[$candidateId] = $candidateId;
        }

        $imageIdList = array_values($imageIdList);

        /**
         * The attachment IDs of the non-hero images dispatched for an article.
         *
         * @hook ringier_bus_article_image_ids
         *
         * @param int[] $imageIdList The resolved attachment IDs.
         * @param int $post_ID The ID of the post.
         * @param string $content The raw article content the IDs were resolved from.
         *
         * @return int[] The attachment IDs to dispatch.
         */
        $imageIdList = apply_filters('ringier_bus_article_image_ids', $imageIdList, $post_ID, $content);

        /*
         * The filter is free to add or reorder IDs, so re-sanitise whatever comes back.
         * The featured image is deliberately not re-excluded: adding an image the body
         * does not reference is a documented use of this hook.
         */
        $imageIdList = array_unique(array_filter(array_map('absint', (array) $imageIdList)));

        return array_values(array_filter($imageIdList, [$this, 'isImageAttachment']));
    }

    /**
     * Walk the block tree and collect the attachment IDs carried in block attributes,
     * reconciled against the URL the same block carries.
     *
     * Covers `core/image` and `core/cover` (`id` + `url`), `core/media-text`
     * (`mediaId` + `mediaLink`), and legacy `core/gallery` (`ids`). Modern galleries
     * nest `core/image` blocks, which are picked up through `innerBlocks`.
     *
     * Most of these render an `<img>`, so the tag pass already covers them — this
     * exists for the ones that do not, such as a `core/cover` drawing its image as a
     * CSS background. Those would otherwise reach the payload with no check at all,
     * which is the migrated-stale-ID failure this class is written to prevent.
     *
     * @param array $blockList
     *
     * @return array{ids: int[], rejected: int[]}
     */
    private function collectBlockImageIds(array $blockList): array
    {
        $imageIdList = [];
        $rejectedIdList = [];
        $confirmedIdList = [];

        foreach ($blockList as $block) {
            $attributes = $block['attrs'] ?? [];

            $blockImageId = 0;
            foreach (['id', 'mediaId'] as $attributeName) {
                if (isset($attributes[$attributeName]) && is_numeric($attributes[$attributeName])) {
                    $blockImageId = (int) $attributes[$attributeName];
                    break;
                }
            }

            if ($blockImageId > 0) {
                $blockImageUrl = '';
                foreach (['url', 'mediaLink', 'mediaUrl'] as $urlAttribute) {
                    if (!empty($attributes[$urlAttribute]) && is_string($attributes[$urlAttribute])) {
                        $blockImageUrl = $attributes[$urlAttribute];
                        break;
                    }
                }

                $urlImageId = $this->resolveAttachmentFromUrl($blockImageUrl);
                if ($urlImageId > 0) {
                    $imageIdList[] = $urlImageId;
                    $confirmedIdList[] = $urlImageId;
                    if ($urlImageId !== $blockImageId) {
                        $rejectedIdList[] = $blockImageId;
                    }
                } elseif ($blockImageUrl === '') {
                    //No URL on the block — accepted, but it confirms nothing
                    $imageIdList[] = $blockImageId;
                } elseif ($this->attachmentMatchesUrl($blockImageId, $blockImageUrl)) {
                    $imageIdList[] = $blockImageId;
                    $confirmedIdList[] = $blockImageId;
                } else {
                    $rejectedIdList[] = $blockImageId;
                }
            }

            //A legacy gallery carries bare IDs with no URL to check them against
            if (!empty($attributes['ids']) && is_array($attributes['ids'])) {
                foreach ($attributes['ids'] as $galleryImageId) {
                    if (is_numeric($galleryImageId)) {
                        $imageIdList[] = (int) $galleryImageId;
                    }
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $inner = $this->collectBlockImageIds($block['innerBlocks']);
                $imageIdList = array_merge($imageIdList, $inner['ids']);
                $rejectedIdList = array_merge($rejectedIdList, $inner['rejected']);
                $confirmedIdList = array_merge($confirmedIdList, $inner['confirmed']);
            }
        }

        return ['ids' => $imageIdList, 'rejected' => $rejectedIdList, 'confirmed' => $confirmedIdList];
    }

    /**
     * Remove HTML comments, except the `<!-- wp:… -->` delimiters that carry the
     * block structure and the `<!--more-->` / `<!--nextpage-->` markers.
     *
     * Anything an editor commented out is not part of the article and must not
     * contribute an image.
     *
     * @param string $content
     *
     * @return string
     */
    private function stripNonBlockHtmlComments(string $content): string
    {
        return (string) preg_replace_callback(
            '/<!--(.*?)-->/s',
            static function (array $match): string {
                $commentBody = ltrim($match[1]);

                //`<!--more Custom teaser-->` is valid; `<!-- moreover ... -->` is not a marker
                if (preg_match('#^(?:/?wp:|more(?:\s|$)|nextpage\s*$|noteaser\s*$)#i', $commentBody)) {
                    return $match[0];
                }

                return '';
            },
            $content
        );
    }

    /**
     * Resolve one attachment ID per `<img>` tag, reconciling the `wp-image-<id>`
     * class against the `src` of the same tag.
     *
     * Reading the class is how WordPress itself identifies an image in content
     * (`wp_filter_content_tags()` in wp-includes/media.php), and it covers the
     * classic editor, freeform blocks and `[caption]` shortcodes. Core only uses
     * the ID to decorate the tag it found it on, though — it never swaps the URL.
     * This payload does swap it (`wp_get_attachment_image_url()`), so the ID has to
     * be shown to describe that `src` before it can be believed.
     *
     * Content migrated between properties keeps the source site's IDs, where the
     * same number means a different picture. Such an ID is returned under
     * `rejected` so that the block-attribute pass cannot reinstate it, and the
     * `src` is resolved against this site's own media instead.
     *
     * The class name is anchored because this does not parse HTML — without it a
     * longer class such as `not-a-wp-image-12` reads as attachment 12.
     *
     * @param string $content
     *
     * @return array{ids: int[], rejected: int[]}
     */
    private function collectImgTagImageIds(string $content): array
    {
        if (!preg_match_all('/<img[^>]*>/i', $content, $tagMatches)) {
            return ['ids' => [], 'rejected' => [], 'confirmed' => []];
        }

        $imageIdList = [];
        $rejectedIdList = [];
        $confirmedIdList = [];

        foreach ($tagMatches[0] as $tag) {
            $classImageId = preg_match('/(?<![\w-])wp-image-([0-9]+)/i', $tag, $classMatch)
                ? absint($classMatch[1])
                : 0;
            $imageUrl = $this->extractTagSrc($tag);

            /*
             * The URL is what the reader sees, so an attachment holding that file
             * outranks the class outright. Comparing names instead would accept a
             * stale ID whose file merely reduces to the same name — WordPress'
             * `-1` dedup suffix lands after the size, so `x-300x211.jpg` and
             * `x-300x2111.jpg` are two different pictures with one stem.
             */
            $urlImageId = $this->resolveAttachmentFromUrl($imageUrl);
            if ($urlImageId > 0) {
                $imageIdList[] = $urlImageId;
                $confirmedIdList[] = $urlImageId;
                if ($classImageId > 0 && $classImageId !== $urlImageId) {
                    $rejectedIdList[] = $classImageId;
                }
                continue;
            }

            if ($classImageId <= 0) {
                continue;
            }

            /*
             * No attachment holds that path — the image is hosted elsewhere, or the
             * tag carries no usable `src`. Fall back to the class, checked by file
             * name where there is a URL to check it against.
             */
            if ($imageUrl === '') {
                //Nothing to check it against — accepted, but it confirms nothing
                $imageIdList[] = $classImageId;
                continue;
            }

            if ($this->attachmentMatchesUrl($classImageId, $imageUrl)) {
                $imageIdList[] = $classImageId;
                $confirmedIdList[] = $classImageId;
                continue;
            }

            $rejectedIdList[] = $classImageId;
        }

        return ['ids' => $imageIdList, 'rejected' => $rejectedIdList, 'confirmed' => $confirmedIdList];
    }

    /**
     * The `src` of an `<img>` tag, quoted or not.
     *
     * @param string $tag
     *
     * @return string
     */
    private function extractTagSrc(string $tag): string
    {
        if (!preg_match('/[\s"\']src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))/i', $tag, $match)) {
            return '';
        }

        foreach ([1, 2, 3] as $group) {
            if (isset($match[$group]) && $match[$group] !== '') {
                return $match[$group];
            }
        }

        return '';
    }

    /**
     * The part of a URL below the uploads directory, whatever host it carries.
     *
     * @param string $imageUrl
     *
     * @return string relative path, or '' when the URL is not an upload path
     */
    private function uploadRelativePath(string $imageUrl): string
    {
        if ($imageUrl === '') {
            return '';
        }

        $uploadDir = wp_get_upload_dir();
        $uploadBaseUrl = (string) ($uploadDir['baseurl'] ?? '');
        if ($uploadBaseUrl === '') {
            return '';
        }

        $uploadPath = (string) parse_url($uploadBaseUrl, PHP_URL_PATH);
        $imagePath = (string) parse_url($imageUrl, PHP_URL_PATH);
        if ($uploadPath === '' || $imagePath === '') {
            return '';
        }

        $marker = rtrim($uploadPath, '/') . '/';
        $markerPosition = strpos($imagePath, $marker);
        if ($markerPosition === false) {
            return '';
        }

        return substr($imagePath, $markerPosition + strlen($marker));
    }

    /**
     * Does this attachment hold a file of the same name as the given URL?
     *
     * Compares names only, ignoring the upload folder, so it is the weaker of the
     * two tests — used when no attachment holds the URL's exact path.
     *
     * @param int $attachmentId
     * @param string $imageUrl
     *
     * @return bool
     */
    private function attachmentMatchesUrl(int $attachmentId, string $imageUrl): bool
    {
        $attachedFile = (string) get_post_meta($attachmentId, '_wp_attached_file', true);
        if ($attachedFile === '') {
            return false;
        }

        $attachmentName = $this->normaliseImageFileName(basename($attachedFile));
        $urlName = $this->normaliseImageFileName(basename((string) parse_url($imageUrl, PHP_URL_PATH)));

        return $attachmentName !== '' && $attachmentName === $urlName;
    }

    /**
     * Find the attachment holding the file an `<img src>` points at.
     *
     * The host is ignored: migrated content routinely references this site's own
     * uploads through the domain it came from, and only the path below
     * `wp-content/uploads/` identifies the file. A URL that is not an upload of
     * ours at all resolves to nothing, which is the intended outcome — an image
     * hosted elsewhere is not in our media library and has no attachment to send.
     *
     * @param string $imageUrl
     *
     * @return int attachment ID, or 0
     */
    private function resolveAttachmentFromUrl(string $imageUrl): int
    {
        $relativePath = $this->uploadRelativePath($imageUrl);
        if ($relativePath === '') {
            return 0;
        }

        $uploadBaseUrl = (string) (wp_get_upload_dir()['baseurl'] ?? '');
        if ($uploadBaseUrl === '') {
            return 0;
        }

        /*
         * `attachment_url_to_postid()` matches `_wp_attached_file` exactly, so a
         * sub-size URL has to be reduced to the name of the original first.
         */
        $candidateList = [$relativePath];
        $originalPath = preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $relativePath);
        if ($originalPath !== null && $originalPath !== $relativePath) {
            $candidateList[] = $originalPath;
        }

        /*
         * A large upload is stored as `name-scaled.ext`, but WordPress names its
         * sub-sizes — and keeps the untouched original — off the unscaled stem, so
         * neither spelling above finds it.
         */
        foreach ($candidateList as $candidatePath) {
            $scaledPath = preg_replace('/(\.[A-Za-z0-9]+)$/', '-scaled$1', $candidatePath);
            if ($scaledPath !== null && $scaledPath !== $candidatePath) {
                $candidateList[] = $scaledPath;
            }
        }

        foreach ($candidateList as $candidatePath) {
            $attachmentId = (int) attachment_url_to_postid(rtrim($uploadBaseUrl, '/') . '/' . $candidatePath);
            if ($attachmentId > 0) {
                return $attachmentId;
            }
        }

        return 0;
    }

    /**
     * Reduce an image file name to the original upload it belongs to, dropping the
     * sub-size (`-1024x768`), large-image (`-scaled`) and edited (`-e1699999999`)
     * suffixes WordPress appends.
     *
     * @param string $fileName
     *
     * @return string
     */
    private function normaliseImageFileName(string $fileName): string
    {
        $fileName = strtolower($fileName);
        $extension = (string) pathinfo($fileName, PATHINFO_EXTENSION);
        $name = (string) pathinfo($fileName, PATHINFO_FILENAME);

        do {
            $previousName = $name;
            $name = (string) preg_replace('/-(?:\d+x\d+|scaled|e\d+)$/', '', $name);
        } while ($name !== $previousName);

        return $extension === '' ? $name : $name . '.' . $extension;
    }

    /**
     * Guard against IDs that no longer resolve to an image — deleted attachments,
     * or non-image media referenced by a block.
     *
     * @param int $attachmentId
     *
     * @return bool
     */
    private function isImageAttachment(int $attachmentId): bool
    {
        if (get_post_type($attachmentId) !== 'attachment') {
            return false;
        }

        return str_starts_with((string) get_post_mime_type($attachmentId), 'image/');
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
