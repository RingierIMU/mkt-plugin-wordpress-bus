<?php

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class AuthorEvent
{
    private BusTokenManager $authClient;
    private string $eventType;
    private string $endpointUrl;

    public function __construct(BusTokenManager $authClient, string $endpointUrl)
    {
        $this->authClient = $authClient;
        $this->eventType = Enum::EVENT_AUTHOR_CREATED;
        $this->endpointUrl = rtrim($endpointUrl, '/');
    }

    public function setEventType(string $type): void
    {
        $this->eventType = $type;
    }

    public function sendToBus(array $author_data): void
    {
        $author_ID = $author_data['id'];
        $blogKey = $_ENV[Enum::ENV_BUS_APP_KEY];

        try {
            $authToken = $this->authClient->getToken();
            if (!$authToken) {
                ringier_errorlogthis('AuthorEvent: Failed to retrieve authentication token.');
            }

            $jsonBody = wp_json_encode($this->buildMainRequestBody($author_data));
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
                ringier_errorlogthis('AuthorEvent: Could not send request to BUS: ' . $response->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if (!in_array($responseCode, [200, 201], true)) {
                $error_msg = '(API|AuthorEvent) Invalid response from BUS: ' . $responseBody;
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
                $blogKey: [ALERT] AuthorEvent: An error occurred for author (ID: $author_ID)

                Error message below:
            EOF;

            ringier_errorlogthis('(api) the following error was thrown:');
            ringier_errorlogthis($exception->getMessage());
            Utils::slackthat($message . $exception->getMessage(), Enum::LOG_ERROR);

            $this->authClient->flushToken();
        }
    }

    private function buildMainRequestBody(array $author_data): array
    {
        $author_ID = $author_data['id'];

        return [[
            'events' => [
                $this->eventType,
            ],
            'from' => $this->authClient->getVentureId(),
            'reference' => "$author_ID",
            'created_at' => date('Y-m-d\TH:i:s.vP'), //NOTE: \DateTime::RFC3339_EXTENDED has been deprecated
            'version' => Enum::BUS_API_VERSION,
            'payload' => [
                'author' => $this->buildAuthorPayloadData($author_data),
            ],
        ]];
    }

    private function buildAuthorPayloadData(array $author_data): array
    {
        $author_page_status = $author_data['status'];
        if ($this->eventType === Enum::EVENT_AUTHOR_DELETED) {
            $author_page_status = Enum::JSON_FIELD_STATUS_OFFLINE;
        }

        return [
            'reference' => $author_data['reference'],
            'url' => $author_data['url'],
            'name' => $author_data['name'],
            'writer_type' => $author_data['writer_type'],
            'status' => $author_page_status,
            'created_at' => $author_data['created_at'],
            'updated_at' => $author_data['updated_at'],
            'image' => $author_data['image'],
        ];
    }
}
