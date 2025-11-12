<?php
/**
 * Copyright © Experius, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Experius\HttpOptimization\Model;

use Magento\Framework\HTTP\AsyncClientInterface;
use Magento\Framework\HTTP\AsyncClient\HttpResponseDeferredInterface;
use Magento\Framework\HTTP\AsyncClient\Request;
use Magento\Framework\HTTP\AsyncClient\GuzzleAsyncClient;

/**
 * Wrapper for AsyncClientInterface that injects optimized Guzzle clients
 * into GuzzleAsyncClient via reflection for DNS caching and connection pooling.
 */
class GuzzleAsyncClientPooled implements AsyncClientInterface
{
    /**
     * @var GuzzleClientFactory
     */
    private $clientFactory;

    /**
     * @var GuzzleAsyncClient|null
     */
    private $wrappedClient = null;

    /**
     * @param GuzzleClientFactory $clientFactory
     */
    public function __construct(GuzzleClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get or create the wrapped GuzzleAsyncClient with optimized client injected.
     *
     * @return GuzzleAsyncClient
     */
    private function getWrappedClient(): GuzzleAsyncClient
    {
        if ($this->wrappedClient === null) {
            $optimizedClient = $this->clientFactory->create();
            
            // Create GuzzleAsyncClient instance with optimized client
            $this->wrappedClient = new GuzzleAsyncClient($optimizedClient);
        }

        return $this->wrappedClient;
    }

    /**
     * @inheritDoc
     */
    public function request(Request $request): HttpResponseDeferredInterface
    {
        return $this->getWrappedClient()->request($request);
    }
}
