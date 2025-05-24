<?php
/**
 * Will be responsible for:
 *      - authentication with the BUS + Retrieving access token
 *      - cache the access token
 *      - delete token from cache when needed + refetch new token
 *
 * USAGE example:
 * ///
 * $authClient = new BusTokenManager();
 * $authClient->setParameters($_ENV['BUS_ENDPOINT'], $_ENV['VENTURE_CONFIG'], $_ENV['BUS_API_USERNAME'], $_ENV['BUS_API_PASSWORD']);
 *
 * $result = $authClient->acquireToken();
 * if ($result === true) {
 * //another should the be able to use $authClient by implementing its interface
 * } else {
 * wp_die('could not get token');
 * }
 * ///
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;

class BusTokenManager
{
    private string $endpoint;
    private string $ventureConfig;
    private string $username;
    private string $password;
    private mixed $authToken;

    public function __construct()
    {
        $this->authToken = null;
    }

    public function setParameters(string $endpointUrl, string $ventureConfig, string $username, string $password): void
    {
        $this->endpoint = rtrim($endpointUrl, '/');
        $this->ventureConfig = $ventureConfig;
        $this->username = $username;
        $this->password = $password;
    }

    public function getToken(mixed $regenerate = false): mixed
    {
        if ($regenerate !== false) {
            $this->flushToken();
            $this->acquireToken();
        }

        return $this->authToken;
    }

    public function flushToken(): void
    {
        $this->authToken = null;
        delete_transient(Enum::CACHE_KEY);
    }

    public function acquireToken(): mixed
    {
        $this->authToken = get_transient(Enum::CACHE_KEY);

        if ($this->authToken === false) {
            $response = wp_remote_post(
                trailingslashit($this->endpoint) . 'login',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'username' => $this->username,
                        'password' => $this->password,
                        'node_id' => $this->ventureConfig,
                    ]),
                    'timeout' => 15,
                ]
            );

            if (is_wp_error($response)) {
                $this->flushToken();
                ringier_errorlogthis('[auth_api] could not get a token from BUS Login Endpoint: ');
                ringier_errorlogthis($response->get_error_message());

                return false;
            }

            $code = wp_remote_retrieve_response_code($response);
            $bodyArray = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($bodyArray['token'])) {
                $this->authToken = $bodyArray['token'];
                set_transient(Enum::CACHE_KEY, $this->authToken, DAY_IN_SECONDS);

                return true;
            }

            return false;
        }

        return true;
    }

    public function getVentureId(): string
    {
        return $this->ventureConfig;
    }
}
