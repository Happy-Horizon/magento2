<?php
/**
 * Copyright © Happy Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace HappyHorizon\HreflangGraphQl\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use HappyHorizon\HreflangGraphQl\Model\HreflangUrlGenerator;

/**
 * Resolve hreflang tag for product
 */
class Hreflang implements ResolverInterface
{
    /**
     * @var HreflangUrlGenerator
     */
    private HreflangUrlGenerator $hreflangUrlGenerator;

    /**
     * @param HreflangUrlGenerator $hreflangUrlGenerator
     */
    public function __construct(
        HreflangUrlGenerator $hreflangUrlGenerator
    ) {
        $this->hreflangUrlGenerator = $hreflangUrlGenerator;
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

        /** @var Product $product */
        $product = $value['model'];
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        return $this->hreflangUrlGenerator->normalizeLocale($store);
    }
}
