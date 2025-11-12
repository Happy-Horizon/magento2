<?php
/**
 * Copyright © Happy Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace HappyHorizon\HreflangGraphQl\Model;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Generates absolute alternate URLs and normalizes locales for hreflang tags
 */
class HreflangUrlGenerator
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * Generate absolute URL for a product in a specific store
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return string|null
     */
    public function getProductUrl(ProductInterface $product, StoreInterface $store): ?string
    {
        try {
            $currentStore = $this->storeManager->getStore();
            $this->storeManager->setCurrentStore($store->getId());
            
            if (!$product instanceof \Magento\Catalog\Model\Product) {
                $this->storeManager->setCurrentStore($currentStore->getId());
                return null;
            }
            
            // Set store context for the product
            $product->setStoreId($store->getId());
            
            // Generate URL using product's URL model
            $url = $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
            
            // Ensure absolute URL
            if ($url && !preg_match('/^https?:\/\//', $url)) {
                $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
                $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            }
            
            $this->storeManager->setCurrentStore($currentStore->getId());
            
            return $url ?: null;
        } catch (\Exception $e) {
            try {
                $this->storeManager->setCurrentStore($currentStore->getId());
            } catch (\Exception $ex) {
                // Ignore restore errors
            }
            return null;
        }
    }

    /**
     * Generate absolute URL for a category in a specific store
     *
     * @param CategoryInterface $category
     * @param StoreInterface $store
     * @return string|null
     */
    public function getCategoryUrl(CategoryInterface $category, StoreInterface $store): ?string
    {
        try {
            $currentStore = $this->storeManager->getStore();
            $this->storeManager->setCurrentStore($store->getId());
            
            if (!$category instanceof \Magento\Catalog\Model\Category) {
                $this->storeManager->setCurrentStore($currentStore->getId());
                return null;
            }
            
            // Set store context for the category
            $category->setStoreId($store->getId());
            
            // Generate URL
            $url = $category->getUrl();
            
            // Ensure absolute URL
            if ($url && !preg_match('/^https?:\/\//', $url)) {
                $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
                $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            }
            
            $this->storeManager->setCurrentStore($currentStore->getId());
            
            return $url ?: null;
        } catch (\Exception $e) {
            try {
                $this->storeManager->setCurrentStore($currentStore->getId());
            } catch (\Exception $ex) {
                // Ignore restore errors
            }
            return null;
        }
    }

    /**
     * Normalize locale code for hreflang attribute
     *
     * @param StoreInterface $store
     * @return string
     */
    public function normalizeLocale(StoreInterface $store): string
    {
        $localeCode = $store->getLocaleCode();
        
        if (!$localeCode) {
            // Fallback to store code if locale is not set
            return strtolower(str_replace('_', '-', $store->getCode()));
        }
        
        // Convert locale format (e.g., en_US) to hreflang format (e.g., en-us)
        $locale = str_replace('_', '-', strtolower($localeCode));
        
        // Handle language-region format (e.g., en-US, nl-NL)
        return $locale;
    }

    /**
     * Get all stores that have the same product/category
     *
     * @param ProductInterface|null $product
     * @param CategoryInterface|null $category
     * @return StoreInterface[]
     */
    public function getAlternateStores(?ProductInterface $product = null, ?CategoryInterface $category = null): array
    {
        $alternateStores = [];
        $currentStore = null;
        
        try {
            $stores = $this->storeManager->getStores();
            $currentStore = $this->storeManager->getStore();
            
            foreach ($stores as $store) {
                if (!$store->isActive()) {
                    continue;
                }
                
                $isValid = true;
                
                // Check if product exists in this store
                if ($product) {
                    try {
                        if ($product instanceof \Magento\Catalog\Model\Product) {
                            // Check if product is assigned to this store's website
                            $websiteIds = $product->getWebsiteIds();
                            if (empty($websiteIds) || !in_array($store->getWebsiteId(), $websiteIds)) {
                                $isValid = false;
                            } else {
                                // Temporarily set store to check status
                                $originalStoreId = $product->getStoreId();
                                $product->setStoreId($store->getId());
                                
                                // Check product status in this store
                                $status = $product->getStatus();
                                if ($status != \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
                                    $isValid = false;
                                }
                                
                                // Restore original store ID
                                $product->setStoreId($originalStoreId);
                            }
                        } else {
                            $isValid = false;
                        }
                    } catch (\Exception $e) {
                        $isValid = false;
                    }
                }
                
                // Check if category exists in this store
                if ($category && $isValid) {
                    try {
                        if ($category instanceof \Magento\Catalog\Model\Category) {
                            // Temporarily set store to check category status
                            $originalStoreId = $category->getStoreId();
                            $category->setStoreId($store->getId());
                            
                            // Check if category is active in this store
                            if (!$category->getId() || !$category->getIsActive()) {
                                $isValid = false;
                            }
                            
                            // Restore original store ID
                            $category->setStoreId($originalStoreId);
                        } else {
                            $isValid = false;
                        }
                    } catch (\Exception $e) {
                        $isValid = false;
                    }
                }
                
                if ($isValid) {
                    $alternateStores[] = $store;
                }
            }
        } catch (\Exception $e) {
            // Return empty array on error
        } finally {
            // Always restore original store context
            if ($currentStore) {
                try {
                    $this->storeManager->setCurrentStore($currentStore->getId());
                } catch (\Exception $e) {
                    // Ignore restore errors
                }
            }
        }
        
        return $alternateStores;
    }
}
