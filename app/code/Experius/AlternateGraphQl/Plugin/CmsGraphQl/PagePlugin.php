<?php
/**
 * Copyright © Experius, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\AlternateGraphQl\Plugin\CmsGraphQl;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\CmsGraphQl\Model\Resolver\Page;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Plugin to inject page model into resolver value for alternate URLs
 */
class PagePlugin
{
    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var GetPageByIdentifierInterface
     */
    private $getPageByIdentifier;

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     */
    public function __construct(
        PageRepositoryInterface $pageRepository,
        GetPageByIdentifierInterface $getPageByIdentifier
    ) {
        $this->pageRepository = $pageRepository;
        $this->getPageByIdentifier = $getPageByIdentifier;
    }

    /**
     * Add model to resolver value after resolve
     *
     * @param Page $subject
     * @param array $result
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function afterResolve(
        Page $subject,
        array $result,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        if (empty($result)) {
            return $result;
        }

        // Add model to result for alternate URLs resolver
        try {
            if (isset($result[PageInterface::PAGE_ID])) {
                $page = $this->pageRepository->getById((int)$result[PageInterface::PAGE_ID]);
                $result['model'] = $page;
            } elseif (isset($result[PageInterface::IDENTIFIER])) {
                $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
                $page = $this->getPageByIdentifier->execute(
                    (string)$result[PageInterface::IDENTIFIER],
                    $storeId
                );
                $result['model'] = $page;
            }
        } catch (NoSuchEntityException $e) {
            // Page not found, skip adding model
        }

        return $result;
    }
}
