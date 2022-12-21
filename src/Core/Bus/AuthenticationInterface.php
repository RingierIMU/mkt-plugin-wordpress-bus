<?php
/**
 * Define a contract with the aim of fulfilling the following responsibility:
 *      - authentication with the BUS + Retrieving access token
 *      - cache the access token
 *      - delete token from cache when needed + refetch new token
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use GuzzleHttp\Client;

interface AuthenticationInterface
{
    /**
     * Exposes the httpClient object
     *
     * @return Client
     */
    public function getHttpClient(): Client;

    /**
     * Exposes the venture_config_id to be reused
     *
     * @return string
     */
    public function getVentureId(): string;

    /**
     * Set all major parameters.
     * We'll use $_ENV from WordPress to pass the parameters
     * Going this way since we are not using any DIC Containers or parameter bags
     *
     * @param string $endpointUrl
     * @param string $ventureConfig
     * @param string $username
     * @param string $password
     */
    public function setParameters(string $endpointUrl, string $ventureConfig, string $username, string $password);

    /**
     * Idea here is for this function fetch the token and save in the cache
     * If the token is not in the cache, it will fetch by contacting the Login Endpoint
     * This methode should return TRUE on success
     *
     * @return mixed
     */
    public function acquireToken(): mixed;

    /**
     * Fetch the actual token
     * with the possibility of telling the system to delete old key and fetch a new (current) one
     *
     * @param mixed $regenerate
     *
     * @return mixed
     */
    public function getToken(mixed $regenerate = false);

    /**
     * Should unset token everywhere - cache + in object
     *
     * @return mixed
     */
    public function flushToken();
}
