<?php
/**
 * Will be responsible for:
 *      - authentication with the BUS + Retrieving access token
 *      - cache the access token
 *      - delete token from cache when needed + refetch new token
 *
 * USAGE example:
 * ///
 * $tokenManager = new BusTokenManager();
 * $tokenManager->setParameters($_ENV['BUS_ENDPOINT'], $_ENV['VENTURE_CONFIG'], $_ENV['BUS_API_USERNAME'], $_ENV['BUS_API_PASSWORD']);
 *
 * $result = $tokenManager->acquireToken();
 * if ($result === true) {
 * //another should the be able to use $tokenManager by implementing its interface
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
use RingierBusPlugin\Utils;

class BusTokenManager
{
    private string $endpoint;
    private string $ventureConfig;
    private string $username;
    private string $password;
    private ?string $authToken;

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

    public function getToken(bool $regenerate = false): ?string
    {
        if ($regenerate) {
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

    public function acquireToken(): bool
    {
        $cached = get_transient(Enum::CACHE_KEY);

        // Validate cached token is a non-empty string (guards against stale null/empty transients)
        if (is_string($cached) && $cached !== '') {
            $this->authToken = $cached;

            return true;
        }

        // No valid cached token — fetch from the BUS login endpoint
        $this->authToken = null;

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
            ringier_errorlogthis('[auth_api] could not get a token from BUS Login Endpoint: ' . $response->get_error_message());

            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $bodyArray = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($bodyArray['token']) && is_string($bodyArray['token']) && $bodyArray['token'] !== '') {
            $this->authToken = $bodyArray['token'];
            set_transient(Enum::CACHE_KEY, $this->authToken, DAY_IN_SECONDS);

            return true;
        }

        // Log failure details so we know why token acquisition failed
        $error_msg = "[auth_api] Failed to acquire BUS token (HTTP $responseCode)";
        ringier_errorlogthis($error_msg);
        Utils::slackthat($error_msg, Enum::LOG_ERROR);

        return false;
    }

    public function getVentureId(): string
    {
        return $this->ventureConfig;
    }
}
