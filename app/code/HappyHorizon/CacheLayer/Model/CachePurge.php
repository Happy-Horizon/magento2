<?php
/**
 * Copyright © HappyHorizon, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace HappyHorizon\CacheLayer\Model;

use HappyHorizon\CacheLayer\Helper\CacheKeyGenerator;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Model for cache purging operations
 */
class CachePurge
{
    /**
     * Configuration path for Fastly service ID
     */
    const XML_PATH_FASTLY_SERVICE_ID = 'system/cache_layer/fastly_service_id';

    /**
     * Configuration path for Fastly API token
     */
    const XML_PATH_FASTLY_API_TOKEN = 'system/cache_layer/fastly_api_token';

    /**
     * Configuration path for webhook secret
     */
    const XML_PATH_WEBHOOK_SECRET = 'system/cache_layer/webhook_secret';

    /**
     * @var CacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CacheKeyGenerator $cacheKeyGenerator
     * @param Curl $curl
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CacheKeyGenerator $cacheKeyGenerator,
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Purge cache by type and ID
     *
     * @param string $type Type of entity (product, category, page)
     * @param string|int $id Entity ID
     * @param int|null $storeId Store ID
     * @return bool
     */
    public function purgeByTypeAndId($type, $id, $storeId = null)
    {
        $keys = [];
        
        switch ($type) {
            case 'product':
                $keys[] = $this->cacheKeyGenerator->generateProductKey($id, $storeId);
                break;
            case 'category':
                $keys[] = $this->cacheKeyGenerator->generateCategoryKey($id, $storeId);
                break;
            case 'page':
                $keys[] = $this->cacheKeyGenerator->generatePageKey($id, $storeId);
                break;
            default:
                $this->logger->error('Invalid purge type: ' . $type);
                return false;
        }

        if ($storeId !== null) {
            $keys[] = $this->cacheKeyGenerator->generateStoreKey($storeId);
        }

        return $this->purgeByKeys($keys);
    }

    /**
     * Purge cache by surrogate keys
     *
     * @param array $keys Array of surrogate keys
     * @return bool
     */
    public function purgeByKeys(array $keys)
    {
        if (empty($keys)) {
            return false;
        }

        $serviceId = $this->scopeConfig->getValue(
            self::XML_PATH_FASTLY_SERVICE_ID,
            ScopeInterface::SCOPE_STORE
        );
        $apiToken = $this->scopeConfig->getValue(
            self::XML_PATH_FASTLY_API_TOKEN,
            ScopeInterface::SCOPE_STORE
        );

        if (!$serviceId || !$apiToken) {
            $this->logger->error('Fastly configuration missing');
            return false;
        }

        $surrogateKey = $this->cacheKeyGenerator->combineKeys($keys);
        
        try {
            $url = sprintf(
                'https://api.fastly.com/service/%s/purge',
                $serviceId
            );

            $this->curl->setHeaders([
                'Fastly-Key: ' . $apiToken,
                'Surrogate-Key: ' . $surrogateKey,
                'Accept: application/json'
            ]);

            $this->curl->post($url, []);
            $statusCode = $this->curl->getStatus();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Cache purged successfully for keys: ' . $surrogateKey);
                return true;
            } else {
                $this->logger->error('Cache purge failed with status: ' . $statusCode);
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Cache purge exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Purge all cache
     *
     * @return bool
     */
    public function purgeAll()
    {
        $serviceId = $this->scopeConfig->getValue(
            self::XML_PATH_FASTLY_SERVICE_ID,
            ScopeInterface::SCOPE_STORE
        );
        $apiToken = $this->scopeConfig->getValue(
            self::XML_PATH_FASTLY_API_TOKEN,
            ScopeInterface::SCOPE_STORE
        );

        if (!$serviceId || !$apiToken) {
            $this->logger->error('Fastly configuration missing');
            return false;
        }

        try {
            $url = sprintf(
                'https://api.fastly.com/service/%s/purge_all',
                $serviceId
            );

            $this->curl->setHeaders([
                'Fastly-Key: ' . $apiToken,
                'Accept: application/json'
            ]);

            $this->curl->post($url, []);
            $statusCode = $this->curl->getStatus();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('All cache purged successfully');
                return true;
            } else {
                $this->logger->error('Cache purge all failed with status: ' . $statusCode);
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Cache purge all exception: ' . $e->getMessage());
            return false;
        }
    }
}
