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
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class ArticleEvent
{
    /** @var AuthenticationInterface */
    private $authClient;
    private $eventType;

    public function __construct(AuthenticationInterface $authClient)
    {
        $this->authClient = $authClient; //Let's get an auth token early
        $this->eventType = Enum::EVENT_ARTICLE_CREATED;
    }

    /**
     * We will need to be able to set the type individually in the scenario for ArticleDeleted
     *
     * @param $type
     */
    public function setEventType($type)
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
    public function sendToBus(int $post_ID, \WP_Post $post)
    {
        /*
         * TODO: As of this coding (Apr 2021) there was no use-case for Callback yet
         *       So, in future if we need to handle call back, we could probably use Guzzle Promises
         *          e.g: https://programming.vip/docs/asynchronous-requests-in-guzzle.html
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
//            ringier_infologthis(json_encode($this->buildMainRequestBody($post_ID, $post)));
            $response = $this->authClient->getHttpClient()->request(
                'POST',
                'events',
                $requestBody
            );
            ringier_infologthis('[api] attempting to push to bus');
            $bodyArray = json_decode((string) $response->getBody(), true);
            ringier_infologthis('[api] the push have probably succeeded at this point');
//            errorlogthis($bodyArray);
        } catch (\Exception $exception) {
            ringier_infologthis('[api] ERROR - could not push to BUS');

            $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];
            $message = <<<EOF
                            $blogKey: [ALERT] an error occurred for article (ID: $post_ID)
                            [This job should be (re)queued in the next few seconds..]
                            
                            Error message below:
                            
                        EOF;

            //log error to our custom log file - viewable via Admin UI
            ringier_errorlogthis('[api] ERROR occurred, below error thrown:');
            ringier_errorlogthis($exception->getMessage()); //push to SLACK
//            ringier_errorlogthis('[api] ERROR occurred, below json response');
//            ringier_errorlogthis($bodyArray);

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
     * @return array
     */
    public function buildMainRequestBody(int $post_ID, \WP_Post $post)
    {
        return [
            'events' => [
                $this->eventType,
            ],
            'venture_config_id' => $this->authClient->getVentureId(),
            'venture_reference' => "$post_ID",
            'created_at' => date('Y-m-d\TH:i:s.vP'), //NOTE: \DateTime::RFC3339_EXTENDED has been deprecated
            'culture' => ringier_getLocale(),
            'action_type' => 'user',
            'action_reference' => Utils::uuid(),
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
     * @return array
     */
    public function buildArticlePayloadData(int $post_ID, \WP_Post $post)
    {
        return [
            'reference' => "$post_ID",
            'status' => $this->getFieldStatus(),
            'created_at' => $this->getOgArticlePublishedDate($post_ID, $post),
            'published_at' => $this->getOgArticlePublishedDate($post_ID, $post),
            'updated_at' => $this->getOgArticleModifiedDate($post_ID, $post),
            'source_type' => 'original',
            'url' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => wp_get_canonical_url($post_ID),
                ],
            ],
            'title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => $post->post_title,
                ],
            ],
            'og_title' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => $this->getOgArticleOgTitle($post_ID, $post),
                ],
            ],
            'description' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => get_the_excerpt($post_ID),
                ],
            ],
            'og_description' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => $this->getOgArticleOgDescription($post_ID, $post),
                ],
            ],
            'body' => [
                [
                    'culture' => ringier_getLocale(),
                    'value' => Utils::getRawContent(get_the_content(null, false, get_post($post_ID))),
                ],
            ],
            'wordcount' => Utils::getContentWordCount(get_the_content(null, false, get_post($post_ID))),
            'images' => [
                BusHelper::getImageArrayForApi($post_ID, 'small_rectangle'),
                BusHelper::getImageArrayForApi($post_ID, 'small_square'),
                BusHelper::getImageArrayForApi($post_ID),//'large_rectangle'
                BusHelper::getImageArrayForApi($post_ID, 'large_square'),
            ],
            'parent_category' => $this->getParentCategoryArray($post_ID),
            'sailthru_tags' => [

            ],
            'lifetime' => Utils::getArticleLifetime($post_ID),
        ];
    }

    private function getParentCategoryArray($post_ID)
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

    /**
     * NOT USED for now
     *
     * @param object $sailthruTaxonomy
     * @param \WP_Post $post
     *
     * @return array|mixed
     */
    public function getSailthruMeta($sailthruTaxonomy, \WP_Post $post)
    {
        //Code block reuse from Sailthru_taxonomy class
        $tags = [];
        foreach ($sailthruTaxonomy->settings->get_meta() as $value) {
            $input_name = $value['name'];
            // Check if the there's a meta field for the current type
            if (isset($meta[$input_name])) {
                if ($value['tag']) {
                    $tags = $meta[$input_name];
                    continue;
                }
            }
        }

        return $tags;
    }
}
