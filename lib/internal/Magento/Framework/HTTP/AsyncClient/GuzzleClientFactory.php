<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Framework\HTTP\AsyncClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;

/**
 * Factory for creating Guzzle HTTP Client with connection pooling and DNS optimization.
 * This factory configures the client to reuse connections and cache DNS lookups,
 * reducing DNS lookup overhead and improving performance.
 */
class GuzzleClientFactory
{
    /**
     * @var HandlerStack
     */
    private $handlerStack;

    /**
     * @var Client
     */
    private static $sharedClient;

    /**
     * Create Guzzle HTTP Client with optimized connection pooling configuration.
     * Returns a shared singleton instance to maximize connection reuse.
     *
     * @return Client
     */
    public function create(): Client
    {
        // Return shared instance to maximize connection reuse
        if (self::$sharedClient === null) {
            $handlerStack = $this->getHandlerStack();

            self::$sharedClient = new Client([
                'handler' => $handlerStack,
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::TIMEOUT => 30,
                RequestOptions::HTTP_ERRORS => true,
                // Enable connection pooling and DNS caching
                'curl' => [
                    // Enable DNS caching (300 seconds = 5 minutes)
                    CURLOPT_DNS_CACHE_TIMEOUT => 300,
                    // Enable connection reuse (don't force fresh connections)
                    CURLOPT_FRESH_CONNECT => false,
                    CURLOPT_FORBID_REUSE => false,
                    // Enable TCP keep-alive for persistent connections
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_TCP_KEEPIDLE => 60,
                    CURLOPT_TCP_KEEPINTVL => 10,
                ],
            ]);
        }

        return self::$sharedClient;
    }

    /**
     * Get or create handler stack with connection pooling middleware.
     * Uses CurlMultiHandler for better async connection pooling support.
     *
     * @return HandlerStack
     */
    private function getHandlerStack(): HandlerStack
    {
        if ($this->handlerStack === null) {
            // Use CurlMultiHandler for better connection pooling with async requests
            // CurlMultiHandler automatically handles connection pooling
            $multiHandler = new CurlMultiHandler();
            
            // Create handler stack with CurlMultiHandler
            $this->handlerStack = HandlerStack::create($multiHandler);
            
            // Add middleware to ensure connection reuse options are always set
            $this->handlerStack->push(function (callable $handler) {
                return function (RequestInterface $request, array $options) use ($handler) {
                    // Ensure connection reuse is enabled for every request
                    if (!isset($options['curl'])) {
                        $options['curl'] = [];
                    }
                    
                    // Merge with default connection pooling options
                    // These ensure DNS caching and connection reuse
                    // Using integer values for CURLOPT constants:
                    // CURLOPT_DNS_CACHE_TIMEOUT = 78
                    // CURLOPT_FRESH_CONNECT = 74
                    // CURLOPT_FORBID_REUSE = 75
                    // CURLOPT_TCP_KEEPALIVE = 213
                    // CURLOPT_TCP_KEEPIDLE = 214
                    // CURLOPT_TCP_KEEPINTVL = 215
                    $options['curl'] = array_merge([
                        78 => 300,  // CURLOPT_DNS_CACHE_TIMEOUT
                        74 => false, // CURLOPT_FRESH_CONNECT
                        75 => false, // CURLOPT_FORBID_REUSE
                        213 => 1,    // CURLOPT_TCP_KEEPALIVE
                        214 => 60,   // CURLOPT_TCP_KEEPIDLE
                        215 => 10,   // CURLOPT_TCP_KEEPINTVL
                    ], $options['curl']);
                    
                    return $handler($request, $options);
                };
            }, 'connection_pooling');
        }

        return $this->handlerStack;
    }
}
