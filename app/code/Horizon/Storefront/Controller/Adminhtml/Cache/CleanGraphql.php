<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Controller\Adminhtml\Cache;

use Magento\Backend\Controller\Adminhtml\Cache;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Clean GraphQL cache controller
 */
class CleanGraphql extends Cache implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Backend::cache';

    /**
     * @var FrontendPool
     */
    private $cacheFrontendPool;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var StateInterface
     */
    private $cacheState;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Cache\StateInterface $cacheState
     * @param \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\StateInterface $cacheState,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context, $cacheTypeList, $cacheState, $cacheFrontendPool, $resultPageFactory);
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheState = $cacheState;
        $this->logger = $logger;
    }

    /**
     * Clean GraphQL cache
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        try {
            // Clean GraphQL schema cache
            $graphqlCache = $this->cacheFrontendPool->get('graphql_schema');
            if ($graphqlCache) {
                $graphqlCache->clean();
                $this->logger->info('GraphQL schema cache cleaned successfully');
            }

            // Clean GraphQL resolver cache if available
            $resolverCache = $this->cacheFrontendPool->get('graphql_resolver');
            if ($resolverCache) {
                $resolverCache->clean();
                $this->logger->info('GraphQL resolver cache cleaned successfully');
            }

            // Invalidate GraphQL cache types
            $cacheTypes = ['graphql_schema', 'graphql_resolver'];
            foreach ($cacheTypes as $cacheType) {
                if ($this->cacheState->isEnabled($cacheType)) {
                    $this->cacheTypeList->invalidate($cacheType);
                }
            }

            $this->messageManager->addSuccessMessage(
                __('The GraphQL cache has been cleaned successfully.')
            );

            $this->logger->info('GraphQL cache cleaning completed');
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error cleaning GraphQL cache: %s', $e->getMessage()),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('An error occurred while cleaning the GraphQL cache: %1', $e->getMessage())
            );
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('adminhtml/*');
    }
}
