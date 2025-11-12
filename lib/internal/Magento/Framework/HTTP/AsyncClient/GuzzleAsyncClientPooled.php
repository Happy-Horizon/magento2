<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Framework\HTTP\AsyncClient;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Magento\Framework\HTTP\AsyncClientInterface;

/**
 * Client based on Guzzle HTTP client with connection pooling optimization.
 * This class extends GuzzleAsyncClient to use an optimized Client instance
 * with connection pooling and DNS caching to reduce DNS lookup overhead.
 */
class GuzzleAsyncClientPooled implements AsyncClientInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var GuzzleClientFactory
     */
    private $clientFactory;

    /**
     * @param GuzzleClientFactory $clientFactory
     */
    public function __construct(GuzzleClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get or create optimized Guzzle Client instance.
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create();
        }

        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function request(Request $request): HttpResponseDeferredInterface
    {
        $options = [];
        $options[RequestOptions::HEADERS] = $request->getHeaders();
        if ($request->getBody() !== null) {
            $options[RequestOptions::BODY] = $request->getBody();
        }

        return new GuzzleWrapDeferred(
            $this->getClient()->requestAsync(
                $request->getMethod(),
                $request->getUrl(),
                $options
            )
        );
    }
}
