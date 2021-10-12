<?php
/**
 * Will be responsible for:
 *      - authentication with the BUS + Retrieving access token
 *      - cache the access token
 *      - delete token from cache when needed + refetch new token
 *
 * USAGE example:
 * ///
 * $authClient = new Auth();
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
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Auth implements AuthenticationInterface
{
    private $endpoint;
    private $ventureConfig;
    private $username;
    private $password;
    private $authToken;

    /** @var Client */
    private $httpClient;

    /** @var @var FilesystemAdapter */
    private $cache;

    public function __construct()
    {
        $this->authToken = null;
        $this->cache = new FilesystemAdapter(Enum::CACHE_NAMESPACE, 0, WP_BUS_RINGIER_PLUGIN_CACHE_DIR);
    }

    public function setParameters($endpointUrl, $ventureConfig, $username, $password)
    {
        $this->endpoint = $endpointUrl;
        $this->ventureConfig = $ventureConfig;
        $this->username = $username;
        $this->password = $password;
    }

    public function getToken($regenerate = false)
    {
        if ($regenerate !== false) {
            //regenerate
            $this->flushToken();
            $this->acquireToken();
        }

        return $this->authToken;
    }

    public function flushToken()
    {
        ringier_infologthis('[auth_api] Clearing Token: ' . $this->authToken);
        $this->authToken = null;
        $this->cache->delete(Enum::CACHE_KEY);
        ringier_infologthis('[auth_api] token cleared done!');
    }

    /**
     * Idea here is for this function fetch the token and save in the cache
     * If the token is not in the cache, it will fetch by contacting the Login Endpoint
     * This methode should return TRUE on success
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return bool
     */
    public function acquireToken(): bool
    {
        $this->authToken = $this->cache->get(Enum::CACHE_KEY, function (ItemInterface $item) {
            $this->httpClient = new Client(['base_uri' => $this->endpoint]);
            try {
                $response = $this->httpClient->request(
                    'POST',
                    'login',
                    [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-type' => 'application/json',
                    ],

                    'json' => [
                        'username' => $this->username,
                        'password' => $this->password,
                        'venture_config_id' => $this->ventureConfig,
                    ],
                ]
                );
                $bodyArray = json_decode((string) $response->getBody(), true);

                if (array_key_exists('token', $bodyArray)) {
                    return $bodyArray['token'];
                }

                return null;
            } catch (RequestException $exception) {
                $this->flushToken();

                ringier_infologthis('--- --- ---');
                ringier_infologthis('[auth_api] ERROR - could not get a token from BUS Login Endpoint');
                ringier_infologthis('--- --- ---');

                ringier_errorlogthis('--- --- ---');
                ringier_errorlogthis('--- --- ---');
                ringier_errorlogthis('[auth_api] ERROR - could not get a token from BUS Login Endpoint');
                ringier_errorlogthis('[auth_api] error thrown below:');
                ringier_errorlogthis($exception->getMessage());
                ringier_errorlogthis('--- --- ---');
                ringier_errorlogthis('--- --- ---');

                throw $exception; //will be catched by our outer call to re-schedule this action
            }
        });

        if ($this->authToken !== null) {
            return true;
        }

        return false;
    }

    /**
     * Exposes the httpClient object
     *
     * @return Client|mixed
     */
    public function getHttpClient()
    {
        if (!is_object($this->httpClient)) {
            return new Client(['base_uri' => $this->endpoint]);
        }

        return $this->httpClient;
    }

    public function getVentureId()
    {
        return $this->ventureConfig;
    }
}
