<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\CatalogGraphQl\Model\Resolver\Hreflang\HreflangUrlGenerator;

/**
 * Resolve alternate URLs for product hreflang tags
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * @var HreflangUrlGenerator
     */
    private $hreflangUrlGenerator;

    /**
     * @var Emulation
     */
    private $appEmulation;

    /**
     * @param HreflangUrlGenerator $hreflangUrlGenerator
     * @param Emulation $appEmulation
     */
    public function __construct(
        HreflangUrlGenerator $hreflangUrlGenerator,
        Emulation $appEmulation
    ) {
        $this->hreflangUrlGenerator = $hreflangUrlGenerator;
        $this->appEmulation = $appEmulation;
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

        /* @var Product $product */
        $product = $value['model'];
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        // Generate alternate URLs for all stores
        $alternateUrls = $this->hreflangUrlGenerator->generateAlternateUrls(
            function (StoreInterface $targetStore) use ($product) {
                try {
                    // Emulate store environment for URL generation
                    $this->appEmulation->startEnvironmentEmulation(
                        (int)$targetStore->getId(),
                        \Magento\Framework\App\Area::AREA_FRONTEND,
                        true
                    );
                    
                    // Generate URL for this store
                    $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
                    $requestPath = $product->getRequestPath();
                    
                    if (!$requestPath) {
                        return null;
                    }

                    return $requestPath;
                } catch (\Exception $e) {
                    return null;
                } finally {
                    // Stop store emulation
                    $this->appEmulation->stopEnvironmentEmulation();
                }
            },
            $store
        );

        // Return as JSON string for frontend consumption
        return json_encode($alternateUrls);
    }
}
