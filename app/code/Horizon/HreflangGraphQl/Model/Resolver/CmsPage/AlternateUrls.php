<?php
/**
 * Copyright © Horizon. All rights reserved.
 */
declare(strict_types=1);

namespace Horizon\HreflangGraphQl\Model\Resolver\CmsPage;

use Horizon\HreflangGraphQl\Helper\AlternateUrlHelper;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolver for CMS page alternate URLs
 */
class AlternateUrls implements ResolverInterface
{
    /**
     * @var AlternateUrlHelper
     */
    private $alternateUrlHelper;

    /**
     * @var GetPageByIdentifierInterface
     */
    private $getPageByIdentifier;

    /**
     * @param AlternateUrlHelper $alternateUrlHelper
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     */
    public function __construct(
        AlternateUrlHelper $alternateUrlHelper,
        GetPageByIdentifierInterface $getPageByIdentifier
    ) {
        $this->alternateUrlHelper = $alternateUrlHelper;
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
            // If identifier is not available, return empty array
            return [];
        }

        /** @var StoreInterface $currentStore */
        $currentStore = $context->getExtensionAttributes()->getStore();
        $identifier = $value['identifier'] ?? $value[PageInterface::IDENTIFIER] ?? null;

        if (empty($identifier)) {
            return [];
        }

        return $this->alternateUrlHelper->getAlternateUrls(
            function (StoreInterface $store) use ($identifier) {
                try {
                    // Try to get page for this store
                    $page = $this->getPageByIdentifier->execute($identifier, (int)$store->getId());
                    if (!$page->isActive()) {
                        return null;
                    }
                    return $this->alternateUrlHelper->getCmsPageUrl($page, $store);
                } catch (NoSuchEntityException $e) {
                    // Page doesn't exist in this store
                    return null;
                } catch (\Exception $e) {
                    return null;
                }
            },
            (int)$currentStore->getId()
        );
    }
}
