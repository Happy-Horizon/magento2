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
 * Get Mollie Payment Info Controller
 */
class Info extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Experius_MolliePayment::payment_info';

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
     * Execute info action
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

            $payment = $order->getPayment();
            $paymentId = $payment->getAdditionalInformation('mollie_payment_id');
            $status = $payment->getAdditionalInformation('mollie_status');

            // Here you would typically make an API call to Mollie to get detailed payment information
            // For now, we'll return the stored information
            // TODO: Implement actual Mollie API call to get payment details

            return $result->setData([
                'success' => true,
                'data' => [
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'order_id' => $order->getIncrementId(),
                    'amount' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode()
                ]
            ]);

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Mollie Payment Info: Order not found', [
                'order_id' => $orderId,
                'exception' => $e->getMessage()
            ]);
            return $result->setData([
                'success' => false,
                'message' => __('Order not found.')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Mollie Payment Info: Exception occurred', [
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while retrieving payment information.')
            ]);
        }
    }
}
