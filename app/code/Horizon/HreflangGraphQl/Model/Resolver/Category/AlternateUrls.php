<?php
/**
 * Copyright © Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace Horizon\HreflangGraphQl\Model\Resolver\Category;

use Horizon\HreflangGraphQl\Helper\AlternateUrlHelper;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolver for category alternate URLs
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * @var AlternateUrlHelper
     */
    private $alternateUrlHelper;

    /**
     * @param AlternateUrlHelper $alternateUrlHelper
     */
    public function __construct(
        AlternateUrlHelper $alternateUrlHelper
    ) {
        $this->alternateUrlHelper = $alternateUrlHelper;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Category $category */
        $category = $value['model'];
        /** @var StoreInterface $currentStore */
        $currentStore = $context->getExtensionAttributes()->getStore();

        return $this->alternateUrlHelper->getAlternateUrls(
            function (StoreInterface $store) use ($category) {
                return $this->alternateUrlHelper->getCategoryUrl($category, $store);
            },
            (int)$currentStore->getId()
        );
    }
}
