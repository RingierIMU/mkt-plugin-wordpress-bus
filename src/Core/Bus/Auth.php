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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RingierBusPlugin\Enum;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Auth implements AuthenticationInterface
{
    private string $endpoint;
    private string $ventureConfig;
    private string $username;
    private string $password;
    private mixed $authToken;
    private ?Client $httpClient;
    private FilesystemAdapter $cache;

    public function __construct()
    {
        $this->authToken = null;
        $this->httpClient = null;
        $this->cache = new FilesystemAdapter(Enum::CACHE_NAMESPACE, 0, RINGIER_BUS_PLUGIN_CACHE_DIR);
    }

    public function setParameters(string $endpointUrl, string $ventureConfig, string $username, string $password): void
    {
        $this->endpoint = $endpointUrl;
        $this->ventureConfig = $ventureConfig;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param mixed $regenerate
     *
     * @throws GuzzleException
     */
    public function getToken(mixed $regenerate = false): mixed
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
        $this->authToken = null;
        $this->cache->delete(Enum::CACHE_KEY);
    }

    /**
     * Idea here is for this function fetch the token and save in the cache
     * If the token is not in the cache, it will fetch by contacting the Login Endpoint
     * This methode should return TRUE on success
     *
     * @throws GuzzleException|\Psr\Cache\InvalidArgumentException
     *
     * @return mixed
     */
    public function acquireToken(): mixed
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
                        'node_id' => $this->ventureConfig,
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
                ringier_errorlogthis('[auth_api] ERROR - could not get a token from BUS Login Endpoint');
                ringier_errorlogthis('[auth_api] error thrown below:');
                ringier_errorlogthis($exception->getMessage());

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
     * @return Client
     */
    public function getHttpClient(): Client
    {
        if (!is_object($this->httpClient)) {
            return new Client(['base_uri' => $this->endpoint]);
        }

        return $this->httpClient;
    }

    public function getVentureId(): string
    {
        return $this->ventureConfig;
    }
}
