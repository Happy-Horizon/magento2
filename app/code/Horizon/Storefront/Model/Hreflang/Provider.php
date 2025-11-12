<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Model\Hreflang;

use Horizon\Storefront\Helper\DataInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Hreflang tag provider
 */
class Provider
{
    /**
     * @var DataInterface
     */
    private $helper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param DataInterface $helper
     * @param StoreManagerInterface $storeManager
     * @param PageRepositoryInterface $pageRepository
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataInterface $helper,
        StoreManagerInterface $storeManager,
        PageRepositoryInterface $pageRepository,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->pageRepository = $pageRepository;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
    }

    /**
     * Get href lang tags for current page
     *
     * @param string|null $currentUrl
     * @param int|null $currentStoreId
     * @return array
     */
    public function getHreflangTags(?string $currentUrl = null, ?int $currentStoreId = null): array
    {
        $hreflangTags = [];
        
        try {
            $currentStore = $this->storeManager->getStore($currentStoreId);
            $stores = $this->storeManager->getStores();
            
            foreach ($stores as $store) {
                $storeId = (int)$store->getId();
                $localeCode = $this->getLocaleCode($store);
                
                if (!$localeCode) {
                    continue;
                }
                
                // Get alternate URL for this store
                $alternateUrl = $this->getAlternateUrlForStore($store, $currentUrl, $currentStoreId);
                
                if ($alternateUrl) {
                    $hreflangTags[] = [
                        'hreflang' => $localeCode,
                        'url' => $alternateUrl,
                        'store_id' => $storeId
                    ];
                }
            }
            
            // Add x-default if applicable
            $defaultStore = $this->storeManager->getDefaultStoreView();
            if ($defaultStore) {
                $defaultUrl = $this->getAlternateUrlForStore($defaultStore, $currentUrl, $currentStoreId);
                if ($defaultUrl) {
                    $hreflangTags[] = [
                        'hreflang' => 'x-default',
                        'url' => $defaultUrl,
                        'store_id' => (int)$defaultStore->getId()
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error generating hreflang tags: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
        
        return $hreflangTags;
    }

    /**
     * Get alternate URL for store
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param string|null $currentUrl
     * @param int|null $currentStoreId
     * @return string|null
     */
    private function getAlternateUrlForStore($store, ?string $currentUrl, ?int $currentStoreId): ?string
    {
        try {
            $storeId = (int)$store->getId();
            
            // If same store, return current URL
            if ($storeId === $currentStoreId && $currentUrl) {
                return $currentUrl;
            }
            
            // Try to get store-specific URL based on current page type
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
            
            // If we have a current URL, try to map it to the alternate store
            if ($currentUrl) {
                return $this->mapUrlToStore($currentUrl, $store, $currentStoreId);
            }
            
            return $baseUrl;
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('Could not get alternate URL for store %s: %s', $store->getId(), $e->getMessage())
            );
            return null;
        }
    }

    /**
     * Map URL to alternate store
     *
     * @param string $currentUrl
     * @param \Magento\Store\Api\Data\StoreInterface $targetStore
     * @param int|null $currentStoreId
     * @return string
     */
    private function mapUrlToStore(string $currentUrl, $targetStore, ?int $currentStoreId): string
    {
        try {
            $currentStore = $this->storeManager->getStore($currentStoreId);
            $targetStoreId = (int)$targetStore->getId();
            
            // Get base URLs
            $currentBaseUrl = $currentStore->getBaseUrl(UrlInterface::URL_TYPE_LINK);
            $targetBaseUrl = $targetStore->getBaseUrl(UrlInterface::URL_TYPE_LINK);
            
            // Replace base URL
            $relativePath = str_replace($currentBaseUrl, '', $currentUrl);
            $targetUrl = rtrim($targetBaseUrl, '/') . '/' . ltrim($relativePath, '/');
            
            return $targetUrl;
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('Could not map URL to store: %s', $e->getMessage())
            );
            return $targetStore->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        }
    }

    /**
     * Get locale code for store
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return string|null
     */
    private function getLocaleCode($store): ?string
    {
        try {
            $locale = $store->getConfig(\Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE);
            return str_replace('_', '-', strtolower($locale));
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('Could not determine locale for store %s: %s', $store->getId(), $e->getMessage())
            );
            return null;
        }
    }
}
