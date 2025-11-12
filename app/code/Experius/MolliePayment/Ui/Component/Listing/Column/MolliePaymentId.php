<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Mollie Payment ID Column
 */
class MolliePaymentId extends Column
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->orderRepository = $orderRepository;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $molliePaymentId = null;
                
                if (isset($item['entity_id'])) {
                    try {
                        $order = $this->orderRepository->get($item['entity_id']);
                        $payment = $order->getPayment();
                        
                        if ($payment && $payment->getMethod() === 'molliepayment') {
                            $molliePaymentId = $payment->getAdditionalInformation('mollie_payment_id');
                        }
                    } catch (NoSuchEntityException $e) {
                        // Order not found, skip
                    } catch (\Exception $e) {
                        // Error loading order, skip
                    }
                }
                
                $item[$this->getData('name')] = $molliePaymentId ?: '';
            }
        }

        return $dataSource;
    }
}
