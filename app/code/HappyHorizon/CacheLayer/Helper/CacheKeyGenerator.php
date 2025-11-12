<?php
/**
 * Copyright © HappyHorizon, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace HappyHorizon\CacheLayer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Helper class for generating Surrogate-Key cache keys
 */
class CacheKeyGenerator extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        PageRepositoryInterface $pageRepository
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->pageRepository = $pageRepository;
    }

    /**
     * Generate Surrogate-Key for a product
     *
     * @param int|string $productId
     * @param int|null $storeId
     * @return string
     */
    public function generateProductKey($productId, $storeId = null)
    {
        try {
            if ($storeId === null) {
                $storeId = $this->storeManager->getStore()->getId();
            }
            $product = $this->productRepository->getById($productId, false, $storeId);
            return sprintf('product-%d-%d', $product->getId(), $storeId);
        } catch (NoSuchEntityException $e) {
            $this->_logger->error('Product not found for cache key generation: ' . $productId);
            return sprintf('product-%s-%d', $productId, $storeId ?? 0);
        }
    }

    /**
     * Generate Surrogate-Key for a category
     *
     * @param int|string $categoryId
     * @param int|null $storeId
     * @return string
     */
    public function generateCategoryKey($categoryId, $storeId = null)
    {
        try {
            if ($storeId === null) {
                $storeId = $this->storeManager->getStore()->getId();
            }
            $category = $this->categoryRepository->get($categoryId, $storeId);
            return sprintf('category-%d-%d', $category->getId(), $storeId);
        } catch (NoSuchEntityException $e) {
            $this->_logger->error('Category not found for cache key generation: ' . $categoryId);
            return sprintf('category-%s-%d', $categoryId, $storeId ?? 0);
        }
    }

    /**
     * Generate Surrogate-Key for a CMS page
     *
     * @param int|string $pageId
     * @param int|null $storeId
     * @return string
     */
    public function generatePageKey($pageId, $storeId = null)
    {
        try {
            if ($storeId === null) {
                $storeId = $this->storeManager->getStore()->getId();
            }
            $page = $this->pageRepository->getById($pageId);
            return sprintf('page-%d-%d', $page->getId(), $storeId);
        } catch (NoSuchEntityException $e) {
            $this->_logger->error('Page not found for cache key generation: ' . $pageId);
            return sprintf('page-%s-%d', $pageId, $storeId ?? 0);
        }
    }

    /**
     * Generate Surrogate-Key for multiple entities
     *
     * @param array $keys Array of cache keys
     * @return string Comma-separated list of keys
     */
    public function combineKeys(array $keys)
    {
        return implode(' ', array_filter($keys));
    }

    /**
     * Generate store-specific key prefix
     *
     * @param int|null $storeId
     * @return string
     */
    public function generateStoreKey($storeId = null)
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        return sprintf('store-%d', $storeId);
    }
}
