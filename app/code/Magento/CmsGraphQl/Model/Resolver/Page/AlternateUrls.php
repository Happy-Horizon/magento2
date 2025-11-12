<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CmsGraphQl\Model\Resolver\Page;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\CmsUrlRewrite\Model\CmsPageUrlPathGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

/**
 * Resolver for alternate URLs field to provide hreflang tag data
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * Locale config path
     */
    private const XML_PATH_DEFAULT_LOCALE = 'general/locale/code';

    /**
     * CMS page entity type
     */
    private const ENTITY_TYPE = 'cms-page';

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CmsPageUrlPathGenerator
     */
    private $urlPathGenerator;

    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param StoreManagerInterface $storeManager
     * @param CmsPageUrlPathGenerator $urlPathGenerator
     * @param UrlFinderInterface $urlFinder
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageRepositoryInterface $pageRepository,
        StoreManagerInterface $storeManager,
        CmsPageUrlPathGenerator $urlPathGenerator,
        UrlFinderInterface $urlFinder,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->pageRepository = $pageRepository;
        $this->storeManager = $storeManager;
        $this->urlPathGenerator = $urlPathGenerator;
        $this->urlFinder = $urlFinder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
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
        if (!isset($value[PageInterface::PAGE_ID])) {
            $this->logger->warning('CMS Page alternate_urls resolver: page_id not found in value array');
            return json_encode([]);
        }

        $pageId = (int)$value[PageInterface::PAGE_ID];

        try {
            $page = $this->pageRepository->getById($pageId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('CMS Page alternate_urls resolver: Page not found', ['page_id' => $pageId]);
            return json_encode([]);
        }

        $alternateUrls = [];
        $stores = $page->getStores();

        // If stores array contains 0, it means the page is available for all stores
        if (in_array('0', $stores, true) || in_array(0, $stores, true)) {
            $stores = array_keys($this->storeManager->getStores());
        }

        foreach ($stores as $storeId) {
            try {
                $store = $this->storeManager->getStore((int)$storeId);
                
                // Get locale code for hreflang tag
                $localeCode = $this->scopeConfig->getValue(
                    self::XML_PATH_DEFAULT_LOCALE,
                    ScopeInterface::SCOPE_STORE,
                    $store->getCode()
                );
                
                // Find URL rewrite for this page in this store to get the correct request path
                $urlRewrite = $this->urlFinder->findOneByData([
                    UrlRewrite::ENTITY_TYPE => self::ENTITY_TYPE,
                    UrlRewrite::ENTITY_ID => $pageId,
                    UrlRewrite::STORE_ID => $store->getId(),
                    UrlRewrite::REDIRECT_TYPE => 0
                ]);
                
                // Use request path from URL rewrite if available, otherwise fall back to identifier
                $requestPath = $urlRewrite ? $urlRewrite->getRequestPath() : $this->urlPathGenerator->getUrlPath($page);
                
                // Generate absolute URL for this store
                $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
                
                // Ensure URL is absolute and complete
                $absoluteUrl = rtrim($baseUrl, '/') . '/' . ltrim($requestPath, '/');
                
                // Use locale code as key (e.g., "en_US", "nl_NL")
                $alternateUrls[$localeCode] = $absoluteUrl;
            } catch (NoSuchEntityException $e) {
                $this->logger->warning(
                    'CMS Page alternate_urls resolver: Store not found',
                    ['store_id' => $storeId, 'page_id' => $pageId]
                );
                continue;
            } catch (\Exception $e) {
                $this->logger->error(
                    'CMS Page alternate_urls resolver: Error generating URL for store',
                    ['store_id' => $storeId, 'page_id' => $pageId, 'error' => $e->getMessage()]
                );
                continue;
            }
        }

        return json_encode($alternateUrls, JSON_UNESCAPED_SLASHES);
    }
}
