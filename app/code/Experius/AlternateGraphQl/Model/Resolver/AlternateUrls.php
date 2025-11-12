<?php
/**
 * Copyright © Experius, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\AlternateGraphQl\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Resolve alternate URLs for hreflang tags
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var GetPageByIdentifierInterface
     */
    private $getPageByIdentifier;

    /**
     * Locale to hreflang mapping
     * Maps Magento locale codes to ISO 639-1 language codes
     *
     * @var array
     */
    private $localeToHreflangMap = [
        'nl_NL' => 'nl',
        'nl_BE' => 'nl-BE',
        'en_US' => 'en',
        'en_GB' => 'en-GB',
        'de_DE' => 'de',
        'de_AT' => 'de-AT',
        'fr_FR' => 'fr',
        'fr_BE' => 'fr-BE',
        'es_ES' => 'es',
        'it_IT' => 'it',
        'pt_PT' => 'pt',
        'pl_PL' => 'pl',
        'cs_CZ' => 'cs',
        'sk_SK' => 'sk',
        'hu_HU' => 'hu',
        'ro_RO' => 'ro',
        'bg_BG' => 'bg',
        'hr_HR' => 'hr',
        'sl_SI' => 'sl',
        'et_EE' => 'et',
        'lv_LV' => 'lv',
        'lt_LT' => 'lt',
        'fi_FI' => 'fi',
        'sv_SE' => 'sv',
        'da_DK' => 'da',
        'no_NO' => 'no',
        'is_IS' => 'is',
        'ga_IE' => 'ga',
        'mt_MT' => 'mt',
        'el_GR' => 'el',
        'ru_RU' => 'ru',
        'uk_UA' => 'uk',
        'tr_TR' => 'tr',
        'ar_SA' => 'ar',
        'he_IL' => 'he',
        'ja_JP' => 'ja',
        'ko_KR' => 'ko',
        'zh_CN' => 'zh-CN',
        'zh_TW' => 'zh-TW',
        'th_TH' => 'th',
        'vi_VN' => 'vi',
        'id_ID' => 'id',
        'ms_MY' => 'ms',
        'hi_IN' => 'hi',
        'bn_BD' => 'bn',
        'ta_IN' => 'ta',
        'te_IN' => 'te',
        'mr_IN' => 'mr',
        'gu_IN' => 'gu',
        'kn_IN' => 'kn',
        'ml_IN' => 'ml',
        'pa_IN' => 'pa',
        'or_IN' => 'or',
        'as_IN' => 'as',
        'ne_NP' => 'ne',
        'si_LK' => 'si',
        'my_MM' => 'my',
        'km_KH' => 'km',
        'lo_LA' => 'lo',
        'ka_GE' => 'ka',
        'hy_AM' => 'hy',
        'az_AZ' => 'az',
        'kk_KZ' => 'kk',
        'ky_KG' => 'ky',
        'uz_UZ' => 'uz',
        'mn_MN' => 'mn',
        'be_BY' => 'be',
        'mk_MK' => 'mk',
        'sq_AL' => 'sq',
        'sr_RS' => 'sr',
        'bs_BA' => 'bs',
        'me_ME' => 'me',
    ];

    /**
     * @param StoreRepositoryInterface $storeRepository
     * @param UrlInterface $urlBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param PageRepositoryInterface $pageRepository
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     */
    public function __construct(
        StoreRepositoryInterface $storeRepository,
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        PageRepositoryInterface $pageRepository,
        GetPageByIdentifierInterface $getPageByIdentifier
    ) {
        $this->storeRepository = $storeRepository;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->pageRepository = $pageRepository;
        $this->getPageByIdentifier = $getPageByIdentifier;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if ($value === null) {
            return [];
        }

        /** @var StoreInterface $currentStore */
        $currentStore = $context->getExtensionAttributes()->getStore();
        $currentStoreId = (int)$currentStore->getId();

        // Determine entity type and get model
        $entity = $this->getEntityFromValue($value);
        
        // For CMS pages, we can still generate URLs even if entity is null initially
        $isCmsPage = isset($value[PageInterface::IDENTIFIER]) || isset($value[PageInterface::PAGE_ID]);
        
        if ($entity === null && !$isCmsPage) {
            return [];
        }

        $alternateUrls = [];
        $stores = $this->storeRepository->getList();

        foreach ($stores as $store) {
            /** @var StoreInterface $store */
            if (!$store->isActive()) {
                continue;
            }

            try {
                $url = $this->generateUrlForStore($entity, $store, $value);
                if ($url === null || empty($url)) {
                    continue;
                }

                $hreflang = $this->getHreflangFromStore($store);
                if ($hreflang === null) {
                    continue;
                }

                // Validate URL format
                if (!$this->isValidUrl($url)) {
                    continue;
                }

                $alternateUrls[] = [
                    'href' => $url,
                    'hreflang' => $hreflang
                ];
            } catch (\Exception $e) {
                // Skip stores where entity is not available
                continue;
            }
        }

        return $alternateUrls;
    }

    /**
     * Get entity model from resolver value
     *
     * @param array $value
     * @return Product|Category|PageInterface|null
     */
    private function getEntityFromValue(array $value)
    {
        // Check if model is directly available (products/categories)
        if (isset($value['model'])) {
            $model = $value['model'];
            if ($model instanceof Product || $model instanceof Category || $model instanceof PageInterface) {
                return $model;
            }
        }

        // For CMS pages, try to load from identifier
        if (isset($value[PageInterface::IDENTIFIER]) || isset($value[PageInterface::PAGE_ID])) {
            try {
                if (isset($value[PageInterface::PAGE_ID])) {
                    $page = $this->pageRepository->getById((int)$value[PageInterface::PAGE_ID]);
                } elseif (isset($value[PageInterface::IDENTIFIER])) {
                    // Need store ID - try to get from current context
                    // For now, return null and let the URL generation handle it per store
                    return null;
                }
                if (isset($page) && $page instanceof PageInterface) {
                    return $page;
                }
            } catch (NoSuchEntityException $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Generate URL for entity in specific store
     *
     * @param Product|Category|PageInterface $entity
     * @param StoreInterface $store
     * @param array $value
     * @return string|null
     */
    private function generateUrlForStore($entity, StoreInterface $store, array $value): ?string
    {
        try {
            $storeId = (int)$store->getId();

            if ($entity instanceof Product) {
                return $this->generateProductUrl($entity, $store, $storeId);
            } elseif ($entity instanceof Category) {
                return $this->generateCategoryUrl($entity, $store, $storeId);
            } elseif ($entity instanceof PageInterface) {
                return $this->generateCmsPageUrl($entity, $store, $storeId, $value);
            } else {
                // Try CMS page from value data if entity is null
                if (isset($value[PageInterface::IDENTIFIER]) || isset($value[PageInterface::PAGE_ID])) {
                    return $this->generateCmsPageUrl($value, $store, $storeId, $value);
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Generate product URL for store
     *
     * @param Product $product
     * @param StoreInterface $store
     * @param int $storeId
     * @return string|null
     */
    private function generateProductUrl(Product $product, StoreInterface $store, int $storeId): ?string
    {
        try {
            // Check if product is available in this store
            $product->setStoreId($storeId);
            if (!$product->getId() || !$product->isAvailable()) {
                return null;
            }

            // Generate URL
            $urlModel = $product->getUrlModel();
            $url = $urlModel->getUrl($product, ['_ignore_category' => true, '_store' => $storeId]);
            
            return $this->normalizeUrl($url, $store);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate category URL for store
     *
     * @param Category $category
     * @param StoreInterface $store
     * @param int $storeId
     * @return string|null
     */
    private function generateCategoryUrl(Category $category, StoreInterface $store, int $storeId): ?string
    {
        try {
            // Check if category is available in this store
            $category->setStoreId($storeId);
            if (!$category->getId() || !$category->isActive()) {
                return null;
            }

            // Generate URL
            $url = $category->getUrl();
            if ($url === null) {
                return null;
            }

            return $this->normalizeUrl($url, $store);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate CMS page URL for store
     *
     * @param PageInterface|array $pageOrValue
     * @param StoreInterface $store
     * @param int $storeId
     * @param array|null $value
     * @return string|null
     */
    private function generateCmsPageUrl($pageOrValue, StoreInterface $store, int $storeId, ?array $value = null): ?string
    {
        try {
            $page = null;
            $identifier = null;

            if ($pageOrValue instanceof PageInterface) {
                $page = $pageOrValue;
            } elseif (is_array($value) && isset($value[PageInterface::IDENTIFIER])) {
                $identifier = $value[PageInterface::IDENTIFIER];
            } elseif (is_array($pageOrValue) && isset($pageOrValue[PageInterface::IDENTIFIER])) {
                $identifier = $pageOrValue[PageInterface::IDENTIFIER];
            }

            // Try to load page for this store if we have identifier
            if ($identifier && !$page) {
                try {
                    $page = $this->getPageByIdentifier->execute($identifier, $storeId);
                } catch (NoSuchEntityException $e) {
                    return null;
                }
            }

            if (!$page instanceof PageInterface) {
                return null;
            }

            // Check if page is active
            if (!$page->getId() || !$page->isActive()) {
                return null;
            }

            // Generate URL using identifier
            $pageIdentifier = $page->getIdentifier();
            if (empty($pageIdentifier)) {
                return null;
            }

            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
            $url = rtrim($baseUrl, '/') . '/' . ltrim($pageIdentifier, '/');

            return $this->normalizeUrl($url, $store);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalize URL to absolute format
     *
     * @param string $url
     * @param StoreInterface $store
     * @return string
     */
    private function normalizeUrl(string $url, StoreInterface $store): string
    {
        // If URL is already absolute, return as is
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        // Convert relative URL to absolute
        $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        if (strpos($url, '/') === 0) {
            // Absolute path
            $baseUrl = rtrim($baseUrl, '/');
        } else {
            // Relative path
            $baseUrl = rtrim($baseUrl, '/') . '/';
        }

        return $baseUrl . ltrim($url, '/');
    }

    /**
     * Get hreflang code from store locale
     *
     * @param StoreInterface $store
     * @return string|null
     */
    private function getHreflangFromStore(StoreInterface $store): ?string
    {
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $store->getCode()
        );

        if (empty($locale)) {
            return null;
        }

        // Check if we have a direct mapping
        if (isset($this->localeToHreflangMap[$locale])) {
            return $this->localeToHreflangMap[$locale];
        }

        // Fallback: extract language code from locale (e.g., 'nl_NL' -> 'nl')
        $parts = explode('_', $locale);
        if (isset($parts[0]) && strlen($parts[0]) === 2) {
            return strtolower($parts[0]);
        }

        return null;
    }

    /**
     * Validate URL format
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Basic URL validation
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
