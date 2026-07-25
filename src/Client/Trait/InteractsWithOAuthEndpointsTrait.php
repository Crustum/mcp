<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Trait;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Crustum\Mcp\Client\Exception\OAuthException;

/**
 * Configures Cake HTTP client instances for OAuth discovery and token requests.
 */
trait InteractsWithOAuthEndpointsTrait
{
    /**
     * Create a configured HTTP client for OAuth endpoint requests.
     *
     * @return \Cake\Http\Client
     */
    protected function oAuthHttpClient(): Client
    {
        return new Client([
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Perform a GET request against an OAuth endpoint.
     *
     * @param string $url Request URL
     * @return \Cake\Http\Client\Response
     */
    protected function oAuthGet(string $url): Client\Response
    {
        try {
            return $this->oAuthHttpClient()->get($url, [], [
                'redirect' => 0,
            ]);
        } catch (NetworkException $networkException) {
            throw new OAuthException(
                "OAuth GET request to [{$url}] failed: {$networkException->getMessage()}",
                0,
                $networkException,
            );
        }
    }

    /**
     * Perform a JSON POST request against an OAuth endpoint.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $data Request payload
     * @return \Cake\Http\Client\Response
     */
    protected function oAuthPostJson(string $url, array $data): Client\Response
    {
        try {
            return $this->oAuthHttpClient()->post($url, $data, [
                'type' => 'json',
                'redirect' => 0,
            ]);
        } catch (NetworkException $networkException) {
            throw new OAuthException(
                "OAuth POST request to [{$url}] failed: {$networkException->getMessage()}",
                0,
                $networkException,
            );
        }
    }

    /**
     * Perform a form POST request against an OAuth endpoint.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $data Form fields
     * @param array<string, mixed> $options Additional request options
     * @return \Cake\Http\Client\Response
     */
    protected function oAuthPostForm(string $url, array $data, array $options = []): Client\Response
    {
        try {
            return $this->oAuthHttpClient()->post($url, $data, array_merge([
                'redirect' => 0,
            ], $options));
        } catch (NetworkException $networkException) {
            throw new OAuthException(
                "OAuth POST request to [{$url}] failed: {$networkException->getMessage()}",
                0,
                $networkException,
            );
        }
    }
}
