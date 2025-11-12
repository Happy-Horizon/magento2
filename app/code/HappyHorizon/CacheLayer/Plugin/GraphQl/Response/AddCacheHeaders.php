<?php
/**
 * Copyright © HappyHorizon, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace HappyHorizon\CacheLayer\Plugin\GraphQl\Response;

use Magento\GraphQl\Controller\GraphQl;
use Magento\Framework\App\ResponseInterface;
use HappyHorizon\CacheLayer\Helper\CacheKeyGenerator;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cms\Api\PageRepositoryInterface;

/**
 * Plugin to add Surrogate-Key and Cache-Control headers to GraphQL responses
 */
class AddCacheHeaders
{
    /**
     * @var CacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @param CacheKeyGenerator $cacheKeyGenerator
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        CacheKeyGenerator $cacheKeyGenerator,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        PageRepositoryInterface $pageRepository
    ) {
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->pageRepository = $pageRepository;
    }

    /**
     * Add cache headers to GraphQL response
     *
     * @param GraphQl $subject
     * @param ResponseInterface $result
     * @return ResponseInterface
     */
    public function afterDispatch(GraphQl $subject, ResponseInterface $result)
    {
        // Only process GraphQL requests
        if (strpos($this->request->getPathInfo(), '/graphql') === false) {
            return $result;
        }

        // Extract store ID from request
        $storeId = $this->storeManager->getStore()->getId();
        $keys = [$this->cacheKeyGenerator->generateStoreKey($storeId)];

        // Parse GraphQL query to extract entity IDs
        $query = $this->request->getContent();
        if ($query) {
            $queryData = json_decode($query, true);
            if (isset($queryData['query'])) {
                $keys = array_merge($keys, $this->extractKeysFromQuery($queryData['query'], $storeId));
            }
        }

        if (!empty($keys) && $result instanceof \Magento\Framework\App\Response\Http) {
            $surrogateKey = $this->cacheKeyGenerator->combineKeys(array_unique($keys));
            $result->setHeader('Surrogate-Key', $surrogateKey, true);
            $result->setHeader('Cache-Control', 'public, max-age=3600', true);
        }

        return $result;
    }

    /**
     * Extract cache keys from GraphQL query
     *
     * @param string $query
     * @param int $storeId
     * @return array
     */
    private function extractKeysFromQuery($query, $storeId)
    {
        $keys = [];

        // Extract product IDs
        if (preg_match_all('/products?\s*\([^)]*filters:\s*\{[^}]*ids:\s*\[([^\]]+)\]/i', $query, $matches)) {
            foreach ($matches[1] as $idsString) {
                $ids = array_map('trim', explode(',', $idsString));
                foreach ($ids as $id) {
                    $id = trim($id, '"\'');
                    if (is_numeric($id)) {
                        $keys[] = $this->cacheKeyGenerator->generateProductKey($id, $storeId);
                    }
                }
            }
        }

        // Extract category IDs
        if (preg_match_all('/categories?\s*\([^)]*filters:\s*\{[^}]*ids:\s*\[([^\]]+)\]/i', $query, $matches)) {
            foreach ($matches[1] as $idsString) {
                $ids = array_map('trim', explode(',', $idsString));
                foreach ($ids as $id) {
                    $id = trim($id, '"\'');
                    if (is_numeric($id)) {
                        $keys[] = $this->cacheKeyGenerator->generateCategoryKey($id, $storeId);
                    }
                }
            }
        }

        // Extract CMS page identifiers
        if (preg_match_all('/cmsPage\s*\([^)]*identifier:\s*["\']([^"\']+)["\']/i', $query, $matches)) {
            foreach ($matches[1] as $identifier) {
                try {
                    $page = $this->pageRepository->getByIdentifier($identifier, $storeId);
                    $keys[] = $this->cacheKeyGenerator->generatePageKey($page->getId(), $storeId);
                } catch (\Exception $e) {
                    // Page not found, skip
                }
            }
        }

        return $keys;
    }
}
