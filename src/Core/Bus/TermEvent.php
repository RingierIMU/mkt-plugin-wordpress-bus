<?php

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class TermEvent
{
    private BusTokenManager $authClient;
    private string $eventType;
    private string $endpointUrl;
    private string $termType;

    public function __construct(BusTokenManager $authClient, string $endpointUrl, string $termType = Enum::TERM_TYPE_CATEGORY)
    {
        $this->authClient = $authClient;
        $this->eventType = Enum::EVENT_AUTHOR_CREATED;
        $this->endpointUrl = rtrim($endpointUrl, '/');
        $this->termType = $termType;
    }

    public function setEventType(string $type): void
    {
        $this->eventType = $type;
    }

    public function sendToBus(array $topic_data): void
    {
        $topic_id = $topic_data['id'];
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];

        try {
            $authToken = $this->authClient->getToken();
            if (!$authToken) {
                ringier_errorlogthis('TopicEvent | ' . $this->termType . ': Failed to retrieve authentication token.');
            }

            $jsonBody = wp_json_encode($this->buildMainRequestBody($topic_data));
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

            if (is_wp_error($response)) {
                ringier_errorlogthis('TopicEvent | ' . $this->termType . ': Could not send request to BUS: ' . $response->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if (!in_array($responseCode, [200, 201], true)) {
                $error_msg = '(API|TopicEvent | ' . $this->termType . ') Invalid response from BUS: ' . $responseBody;
                ringier_errorlogthis($error_msg);
                Utils::slackthat($error_msg, Enum::LOG_ERROR);

                return;
            }

            $message = <<<EOF
                $blogKey: The event was successfully delivered to the BUS.

                Payload details:
                
                
            EOF;
            Utils::slackthat($message . $jsonBody, Enum::LOG_INFO);
        } catch (\Exception $exception) {

            $message = <<<EOF
                $blogKey: [ALERT] TopicEvent|$this->termType: An error occurred for term (ID: $topic_id)

                Error message below:
            EOF;

            ringier_errorlogthis('(api) the following error was thrown:');
            ringier_errorlogthis($exception->getMessage());
            Utils::slackthat($message . $exception->getMessage(), Enum::LOG_ERROR);

            $this->authClient->flushToken();
        }
    }

    private function buildMainRequestBody(array $topic_data): array
    {
        $topic_id = $topic_data['id'];

        return [[
            'events' => [
                $this->eventType,
            ],
            'from' => $this->authClient->getVentureId(),
            'reference' => wp_generate_uuid4(),
            'created_at' => date('Y-m-d\TH:i:s.vP'), //NOTE: \DateTime::RFC3339_EXTENDED has been deprecated
            'version' => Enum::BUS_API_VERSION,
            'payload' => [
                'topic' => $this->buildTopicPayloadData($topic_data),
            ],
        ]];
    }

    private function buildTopicPayloadData(array $topic_data): array
    {
        $date_created = Utils::formatDate($topic_data['created_at']);
        if (empty($date_created)) {
            $date_created = Utils::formatDate($topic_data['updated_at']);
        }

        return [
            'reference' => (string) $topic_data['id'],
            'status' => (string) $topic_data['status'],
            'created_at' => $date_created,
            'updated_at' => Utils::formatDate($topic_data['updated_at']),
            'url' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => (string) $topic_data['url'],
                ],
            ],
            'title' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => (string) $topic_data['title'],
                ],
            ],
            'slug' => [
                [
                    'culture' => (string) ringier_getLocale(),
                    'value' => (string) $topic_data['slug'],
                ],
            ],
            'page_type' => (string) $topic_data['page_type'],
        ];
    }

}
