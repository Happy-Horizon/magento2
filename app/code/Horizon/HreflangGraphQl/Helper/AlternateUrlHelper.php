<?php
/**
 * Copyright © Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace Horizon\HreflangGraphQl\Helper;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper class for generating alternate URLs across stores
 */
class AlternateUrlHelper
{
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StoreRepositoryInterface $storeRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Get alternate URLs for all stores where the entity is available
     *
     * @param callable $urlGenerator Callback function that generates URL for a given store
     * @param int|null $currentStoreId Current store ID
     * @return array Array of alternate URLs with hreflang tags
     */
    public function getAlternateUrls(callable $urlGenerator, ?int $currentStoreId = null): array
    {
        $alternateUrls = [];
        
        try {
            $stores = $this->storeRepository->getList();
            $currentStoreId = $currentStoreId ?? $this->storeManager->getStore()->getId();
            
            foreach ($stores as $store) {
                /** @var StoreInterface $store */
                if (!$store->isActive()) {
                    continue;
                }
                
                try {
                    // Generate URL for this store
                    $url = $urlGenerator($store);
                    
                    if (empty($url)) {
                        continue;
                    }
                    
                    // Get locale code for hreflang
                    $locale = $this->getLocaleCode($store);
                    
                    $alternateUrls[] = [
                        'hreflang' => $locale,
                        'url' => $url
                    ];
                } catch (\Exception $e) {
                    // Log but continue with other stores
                    $this->logger->warning(
                        sprintf(
                            'Failed to generate alternate URL for store %s: %s',
                            $store->getCode(),
                            $e->getMessage()
                        )
                    );
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to retrieve stores for alternate URLs: %s', $e->getMessage())
            );
        }
        
        return $alternateUrls;
    }

    /**
     * Get locale code for hreflang tag
     *
     * @param StoreInterface $store
     * @return string
     */
    private function getLocaleCode(StoreInterface $store): string
    {
        try {
            $locale = $store->getLocaleCode();
            // Convert locale format if needed (e.g., 'en_US' to 'en-US')
            return str_replace('_', '-', $locale);
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('Failed to get locale for store %s: %s', $store->getCode(), $e->getMessage())
            );
            // Fallback to store code if locale is not available
            return $store->getCode();
        }
    }

    /**
     * Generate relative URL for a product in a specific store
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param StoreInterface $store
     * @return string|null
     */
    public function getProductUrl(\Magento\Catalog\Model\Product $product, StoreInterface $store): ?string
    {
        try {
            // Check if product is available in this store
            $websiteIds = $product->getWebsiteIds();
            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                return null;
            }
            
            $originalStoreId = $this->storeManager->getStore()->getId();
            $this->storeManager->setCurrentStore($store->getId());
            
            // Set store context and generate URL
            $product->setStoreId($store->getId());
            $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
            $url = $product->getRequestPath();
            
            $this->storeManager->setCurrentStore($originalStoreId);
            
            return $url;
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf(
                    'Failed to generate product URL for store %s: %s',
                    $store->getCode(),
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Generate relative URL for a category in a specific store
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param StoreInterface $store
     * @return string|null
     */
    public function getCategoryUrl(\Magento\Catalog\Model\Category $category, StoreInterface $store): ?string
    {
        try {
            // Check if category is available in this store
            $storeIds = $category->getStoreIds();
            if (!in_array($store->getId(), $storeIds) && !in_array(0, $storeIds)) {
                return null;
            }
            
            $originalStoreId = $this->storeManager->getStore()->getId();
            $this->storeManager->setCurrentStore($store->getId());
            
            // Set store context and generate URL
            $category->setStoreId($store->getId());
            $baseUrl = $category->getUrlInstance()->getBaseUrl();
            $fullUrl = $category->getUrl();
            
            $this->storeManager->setCurrentStore($originalStoreId);
            
            if ($fullUrl !== null) {
                return str_replace($baseUrl, '', $fullUrl);
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf(
                    'Failed to generate category URL for store %s: %s',
                    $store->getCode(),
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Generate relative URL for a CMS page in a specific store
     *
     * @param \Magento\Cms\Api\Data\PageInterface $page
     * @param StoreInterface $store
     * @return string|null
     */
    public function getCmsPageUrl(\Magento\Cms\Api\Data\PageInterface $page, StoreInterface $store): ?string
    {
        try {
            $identifier = $page->getIdentifier();
            if (empty($identifier)) {
                return null;
            }
            
            // Check if page is available in this store
            $storeIds = $page->getStoreId();
            if ($storeIds !== null && $storeIds !== '0') {
                $storeIdsArray = is_array($storeIds) ? $storeIds : explode(',', (string)$storeIds);
                if (!in_array($store->getId(), $storeIdsArray) && !in_array('0', $storeIdsArray)) {
                    return null;
                }
            }
            
            // CMS pages typically use their identifier as URL key
            // Return relative URL path
            return '/' . $identifier;
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf(
                    'Failed to generate CMS page URL for store %s: %s',
                    $store->getCode(),
                    $e->getMessage()
                )
            );
            return null;
        }
    }
}
