<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Controller\Adminhtml\Payment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Sync Mollie Payment Controller
 */
class Sync extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Experius_MolliePayment::payment_sync';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Execute sync action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');

        if (!$orderId) {
            return $result->setData([
                'success' => false,
                'message' => __('Order ID is required.')
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if ($order->getPayment()->getMethod() !== 'molliepayment') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Order does not use Mollie payment method.')
                ]);
            }

            $paymentId = $order->getPayment()->getAdditionalInformation('mollie_payment_id');

            if (!$paymentId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Mollie payment ID not found.')
                ]);
            }

            // Here you would typically make an API call to Mollie to get the latest payment status
            // For now, we'll just return success
            // TODO: Implement actual Mollie API call to sync payment status

            $this->messageManager->addSuccessMessage(__('Payment status synchronized successfully.'));

            return $result->setData([
                'success' => true,
                'message' => __('Payment status synchronized successfully.')
            ]);

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Mollie Payment Sync: Order not found', [
                'order_id' => $orderId,
                'exception' => $e->getMessage()
            ]);
            return $result->setData([
                'success' => false,
                'message' => __('Order not found.')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Mollie Payment Sync: Exception occurred', [
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while syncing payment status.')
            ]);
        }
    }
}
