<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Horizon\Storefront\Model\Hreflang\Provider;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Block for rendering href lang tags
 */
class Hreflang extends Template
{
    /**
     * @var Provider
     */
    private $hreflangProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context
     * @param Provider $hreflangProvider
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Provider $hreflangProvider,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->hreflangProvider = $hreflangProvider;
        $this->storeManager = $storeManager;
    }

    /**
     * Get href lang tags for current page
     *
     * @return array
     */
    public function getHreflangTags(): array
    {
        try {
            $currentUrl = $this->getCurrentUrl();
            $currentStoreId = (int)$this->storeManager->getStore()->getId();
            
            return $this->hreflangProvider->getHreflangTags($currentUrl, $currentStoreId);
        } catch (\Exception $e) {
            $this->_logger->error(
                sprintf('Error getting hreflang tags: %s', $e->getMessage())
            );
            return [];
        }
    }

    /**
     * Get current URL
     *
     * @return string
     */
    private function getCurrentUrl(): string
    {
        return $this->_urlBuilder->getCurrentUrl();
    }

    /**
     * Check if href lang tags should be rendered
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        $tags = $this->getHreflangTags();
        return !empty($tags);
    }
}
