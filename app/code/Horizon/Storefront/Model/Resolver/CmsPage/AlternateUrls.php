<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Model\Resolver\CmsPage;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Horizon\Storefront\Model\Hreflang\Provider;
use Psr\Log\LoggerInterface;

/**
 * Resolver for CMS page alternate URLs
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * @var Provider
     */
    private $hreflangProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Provider $hreflangProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        Provider $hreflangProvider,
        LoggerInterface $logger
    ) {
        $this->hreflangProvider = $hreflangProvider;
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
        try {
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $pageUrl = $value['url_key'] ?? null;
            
            if (!$pageUrl) {
                return [];
            }

            $currentUrl = $pageUrl;
            $hreflangTags = $this->hreflangProvider->getHreflangTags($currentUrl, $storeId);
            
            return array_map(function ($tag) {
                return [
                    'hreflang' => $tag['hreflang'],
                    'url' => $tag['url']
                ];
            }, $hreflangTags);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error resolving CMS page alternate URLs: %s', $e->getMessage()),
                ['exception' => $e]
            );
            return [];
        }
    }
}
