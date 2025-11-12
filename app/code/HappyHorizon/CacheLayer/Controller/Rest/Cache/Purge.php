<?php
/**
 * Copyright © HappyHorizon, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace HappyHorizon\CacheLayer\Controller\Rest\Cache;

use Magento\Framework\App\Action\HttpPostInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use HappyHorizon\CacheLayer\Model\CachePurge;
use Psr\Log\LoggerInterface;

/**
 * REST API controller for cache purging
 */
class Purge implements HttpPostInterface, CsrfAwareActionInterface
{
    /**
     * Configuration path for webhook secret
     */
    const XML_PATH_WEBHOOK_SECRET = 'system/cache_layer/webhook_secret';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CachePurge
     */
    private $cachePurge;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param CachePurge $cachePurge
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        CachePurge $cachePurge,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->cachePurge = $cachePurge;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Verify webhook secret
        if (!$this->verifyWebhookSecret()) {
            $result->setHttpResponseCode(401);
            $result->setData(['error' => 'Unauthorized']);
            return $result;
        }

        $params = json_decode($this->request->getContent(), true) ?: [];

        try {
            if (isset($params['type']) && isset($params['id'])) {
                // Purge by type and ID
                $success = $this->cachePurge->purgeByTypeAndId(
                    $params['type'],
                    $params['id'],
                    $params['store_id'] ?? null
                );
            } elseif (isset($params['keys']) && is_array($params['keys'])) {
                // Purge by keys
                $success = $this->cachePurge->purgeByKeys($params['keys']);
            } elseif (isset($params['purge_all']) && $params['purge_all'] === true) {
                // Purge all
                $success = $this->cachePurge->purgeAll();
            } else {
                $result->setHttpResponseCode(400);
                $result->setData(['error' => 'Invalid request parameters']);
                return $result;
            }

            if ($success) {
                $result->setHttpResponseCode(200);
                $result->setData(['success' => true, 'message' => 'Cache purged successfully']);
            } else {
                $result->setHttpResponseCode(500);
                $result->setData(['error' => 'Cache purge failed']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Cache purge error: ' . $e->getMessage());
            $result->setHttpResponseCode(500);
            $result->setData(['error' => 'Internal server error']);
        }

        return $result;
    }

    /**
     * Verify webhook secret
     *
     * @return bool
     */
    private function verifyWebhookSecret()
    {
        $secret = $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_SECRET,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($secret)) {
            return false;
        }

        $providedSecret = $this->request->getHeader('X-Webhook-Secret') ?: 
                         $this->request->getParam('secret');

        return hash_equals($secret, $providedSecret);
    }

    /**
     * Create exception for invalid request
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate request
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
