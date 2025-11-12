<?php
/**
 * Copyright © Experius, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Experius\HttpOptimization\Model;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\RequestOptions;

/**
 * Factory for creating optimized Guzzle HTTP clients with DNS caching,
 * connection pooling, and TCP keep-alive.
 */
class GuzzleClientFactory
{
    /**
     * DNS cache TTL in seconds
     */
    private const DNS_CACHE_TTL = 300;

    /**
     * @var Client|null
     */
    private static $clientInstance = null;

    /**
     * Get or create a shared singleton Guzzle Client instance with optimizations.
     *
     * @return Client
     */
    public function create(): Client
    {
        if (self::$clientInstance === null) {
            // Use CurlMultiHandler for connection pooling
            $handler = new CurlMultiHandler();

            $stack = HandlerStack::create($handler);

            // Configure DNS caching and connection pooling
            $config = [
                'handler' => $stack,
                RequestOptions::TIMEOUT => 30,
                RequestOptions::CONNECT_TIMEOUT => 10,
                'curl' => [
                    CURLOPT_DNS_CACHE_TIMEOUT => self::DNS_CACHE_TTL,
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_TCP_KEEPIDLE => 60,
                    CURLOPT_TCP_KEEPINTVL => 10,
                ],
            ];

            self::$clientInstance = new Client($config);
        }

        return self::$clientInstance;
    }
}
