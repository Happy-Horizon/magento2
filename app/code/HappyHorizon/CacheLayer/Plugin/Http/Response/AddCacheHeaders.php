<?php
/**
 * Copyright © HappyHorizon, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace HappyHorizon\CacheLayer\Plugin\Http\Response;

use Magento\Framework\App\Response\Http;
use HappyHorizon\CacheLayer\Helper\CacheKeyGenerator;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cms\Api\PageRepositoryInterface;

/**
 * Plugin to add Surrogate-Key and Cache-Control headers to HTTP responses
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
     * Add cache headers to HTTP response
     *
     * @param Http $subject
     * @return Http
     */
    public function afterSendResponse(Http $subject)
    {
        // Skip for admin and API requests
        if ($this->isAdminRequest() || $this->isApiRequest()) {
            return $subject;
        }

        $keys = [];
        $storeId = $this->storeManager->getStore()->getId();
        $keys[] = $this->cacheKeyGenerator->generateStoreKey($storeId);

        // Extract entity information from request
        $pathInfo = $this->request->getPathInfo();
        
        // Product page
        if (preg_match('#/catalog/product/view/id/(\d+)#', $pathInfo, $matches)) {
            $keys[] = $this->cacheKeyGenerator->generateProductKey($matches[1], $storeId);
        }
        
        // Category page
        if (preg_match('#/catalog/category/view/id/(\d+)#', $pathInfo, $matches)) {
            $keys[] = $this->cacheKeyGenerator->generateCategoryKey($matches[1], $storeId);
        }
        
        // CMS page
        if (preg_match('#/([^/]+)\.html#', $pathInfo, $matches)) {
            try {
                $page = $this->pageRepository->getByIdentifier($matches[1], $storeId);
                $keys[] = $this->cacheKeyGenerator->generatePageKey($page->getId(), $storeId);
            } catch (\Exception $e) {
                // Page not found, skip
            }
        }

        if (!empty($keys)) {
            $surrogateKey = $this->cacheKeyGenerator->combineKeys($keys);
            $subject->setHeader('Surrogate-Key', $surrogateKey, true);
            $subject->setHeader('Cache-Control', 'public, max-age=3600', true);
        }

        return $subject;
    }

    /**
     * Check if current request is admin request
     *
     * @return bool
     */
    private function isAdminRequest()
    {
        return strpos($this->request->getPathInfo(), '/admin/') === 0;
    }

    /**
     * Check if current request is API request
     *
     * @return bool
     */
    private function isApiRequest()
    {
        return strpos($this->request->getPathInfo(), '/rest/') === 0 ||
               strpos($this->request->getPathInfo(), '/graphql') === 0;
    }
}
