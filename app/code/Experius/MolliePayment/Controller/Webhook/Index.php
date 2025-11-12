<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Mollie Webhook Controller
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

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
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Execute webhook action
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $postData = $this->request->getPostValue();
            $paymentId = $postData['id'] ?? null;
            $orderId = $postData['metadata']['order_id'] ?? null;
            $status = $postData['status'] ?? null;

            if (!$paymentId || !$orderId) {
                $this->logger->error('Mollie Webhook: Missing payment ID or order ID', [
                    'post_data' => $postData
                ]);
                return $result->setData(['status' => 'error', 'message' => 'Missing required data']);
            }

            $order = $this->orderRepository->get($orderId);
            
            if ($order->getPayment()->getMethod() !== 'molliepayment') {
                $this->logger->warning('Mollie Webhook: Order payment method mismatch', [
                    'order_id' => $orderId,
                    'payment_method' => $order->getPayment()->getMethod()
                ]);
                return $result->setData(['status' => 'error', 'message' => 'Invalid payment method']);
            }

            // Update payment information
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('mollie_payment_id', $paymentId);
            $payment->setAdditionalInformation('mollie_status', $status);
            $payment->save();

            // Update order status based on Mollie payment status
            $this->updateOrderStatus($order, $status);

            $this->logger->info('Mollie Webhook: Payment status updated', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'status' => $status
            ]);

            return $result->setData(['status' => 'success']);

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Mollie Webhook: Order not found', [
                'exception' => $e->getMessage()
            ]);
            return $result->setData(['status' => 'error', 'message' => 'Order not found']);
        } catch (\Exception $e) {
            $this->logger->error('Mollie Webhook: Exception occurred', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $result->setData(['status' => 'error', 'message' => 'Internal error']);
        }
    }

    /**
     * Update order status based on Mollie payment status
     *
     * @param Order $order
     * @param string $status
     * @return void
     */
    protected function updateOrderStatus(Order $order, $status)
    {
        switch ($status) {
            case 'paid':
                if ($order->canInvoice()) {
                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus(Order::STATE_PROCESSING);
                }
                break;
            case 'failed':
            case 'expired':
            case 'canceled':
                if ($order->canCancel()) {
                    $order->cancel();
                }
                break;
            case 'pending':
            default:
                // Keep current status
                break;
        }

        $order->save();
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Allow webhook without CSRF validation
        return true;
    }
}
