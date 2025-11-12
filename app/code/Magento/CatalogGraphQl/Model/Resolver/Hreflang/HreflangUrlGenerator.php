<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogGraphQl\Model\Resolver\Hreflang;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Helper class to generate hreflang alternate URLs
 */
class HreflangUrlGenerator
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LocaleResolverInterface
     */
    private $localeResolver;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param StoreManagerInterface $storeManager
     * @param LocaleResolverInterface $localeResolver
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        LocaleResolverInterface $localeResolver,
        UrlInterface $urlBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Generate alternate URLs for all stores
     *
     * @param callable $urlGenerator Callback that generates URL for a given store
     * @param StoreInterface $currentStore Current store
     * @return array Array of alternate URLs with structure: [['hreflang' => 'en', 'url' => '/path'], ...]
     */
    public function generateAlternateUrls(callable $urlGenerator, StoreInterface $currentStore): array
    {
        $alternateUrls = [];
        $stores = $this->storeManager->getStores(true);

        foreach ($stores as $store) {
            if (!$store->isActive()) {
                continue;
            }

            try {
                $storeId = (int)$store->getId();
                $currentStoreId = (int)$currentStore->getId();

                // Generate URL for this store
                $url = $urlGenerator($store);
                if (!$url) {
                    continue;
                }

                // Get locale code for this store
                $locale = $this->getLocaleForStore($store);
                $hreflang = $this->normalizeLocale($locale);

                // Ensure URL is absolute
                $absoluteUrl = $this->makeAbsoluteUrl($url, $store);

                $alternateUrls[] = [
                    'hreflang' => $hreflang,
                    'url' => $absoluteUrl
                ];
            } catch (\Exception $e) {
                // Skip stores that fail to generate URLs
                continue;
            }
        }

        return $alternateUrls;
    }

    /**
     * Get current page hreflang value
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getCurrentHreflang(StoreInterface $store): string
    {
        $locale = $this->getLocaleForStore($store);
        return $this->normalizeLocale($locale);
    }

    /**
     * Get locale for a store
     *
     * @param StoreInterface $store
     * @return string
     */
    private function getLocaleForStore(StoreInterface $store): string
    {
        // Try to get locale from store config
        $locale = $store->getConfig('general/locale/code');
        if (!$locale) {
            // Fallback to store locale code if method exists
            if (method_exists($store, 'getLocaleCode')) {
                $locale = $store->getLocaleCode();
            }
        }
        if (!$locale) {
            // Final fallback
            $locale = 'en_US';
        }
        return $locale;
    }

    /**
     * Normalize locale to hreflang format (e.g., 'en_US' -> 'en', 'nl_NL' -> 'nl')
     *
     * @param string $locale
     * @return string
     */
    private function normalizeLocale(string $locale): string
    {
        // Extract language code from locale (e.g., 'en_US' -> 'en')
        $parts = explode('_', $locale);
        return strtolower($parts[0] ?? 'en');
    }

    /**
     * Make URL absolute if it's relative
     *
     * @param string $url
     * @param StoreInterface $store
     * @return string
     */
    private function makeAbsoluteUrl(string $url, StoreInterface $store): string
    {
        // If URL is already absolute, return as is
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        // Get base URL for the store
        $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        
        // Remove trailing slash from base URL and leading slash from path
        $baseUrl = rtrim($baseUrl, '/');
        $url = ltrim($url, '/');

        return $baseUrl . '/' . $url;
    }
}
