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
use Horizon\Storefront\Helper\DataInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolver for CMS page canonical URL
 */
class CanonicalUrl implements ResolverInterface
{
    /**
     * @var DataInterface
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param DataInterface $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataInterface $helper,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
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
            return $this->helper->getCanonicalUrl($storeId);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error resolving CMS page canonical URL: %s', $e->getMessage()),
                ['exception' => $e]
            );
            return null;
        }
    }
}
