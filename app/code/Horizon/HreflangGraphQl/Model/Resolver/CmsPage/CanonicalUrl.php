<?php
/**
 * Copyright © Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace Horizon\HreflangGraphQl\Model\Resolver\CmsPage;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolver for CMS page canonical URL
 */
class CanonicalUrl implements ResolverInterface
{
    /**
     * @var GetPageByIdentifierInterface
     */
    private $getPageByIdentifier;

    /**
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     */
    public function __construct(
        GetPageByIdentifierInterface $getPageByIdentifier
    ) {
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
        if (!isset($value['identifier']) && !isset($value[PageInterface::IDENTIFIER])) {
            return null;
        }

        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();
        $identifier = $value['identifier'] ?? $value[PageInterface::IDENTIFIER] ?? null;

        if (empty($identifier)) {
            return null;
        }

        try {
            // Get the page to verify it exists
            $page = $this->getPageByIdentifier->execute($identifier, (int)$store->getId());
            
            if (!$page->isActive()) {
                return null;
            }

            // Return relative URL (identifier)
            return '/' . $identifier;
        } catch (\Exception $e) {
            return null;
        }
    }
}
