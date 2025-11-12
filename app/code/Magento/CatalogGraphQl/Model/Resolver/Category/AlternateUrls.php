<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\CatalogGraphQl\Model\Resolver\Hreflang\HreflangUrlGenerator;

/**
 * Resolve alternate URLs for category hreflang tags
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

        /* @var Category $category */
        $category = $value['model'];
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        // Generate alternate URLs for all stores
        $alternateUrls = $this->hreflangUrlGenerator->generateAlternateUrls(
            function (StoreInterface $targetStore) use ($category) {
                try {
                    // Emulate store environment for URL generation
                    $this->appEmulation->startEnvironmentEmulation(
                        (int)$targetStore->getId(),
                        \Magento\Framework\App\Area::AREA_FRONTEND,
                        true
                    );
                    
                    // Generate URL for this store
                    $baseUrl = $category->getUrlInstance()->getBaseUrl();
                    $categoryUrl = $category->getUrl();
                    
                    if (!$categoryUrl) {
                        return null;
                    }

                    // Return relative URL
                    return str_replace($baseUrl, '', $categoryUrl);
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
