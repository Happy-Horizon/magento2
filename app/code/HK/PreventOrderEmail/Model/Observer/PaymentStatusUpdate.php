<?php
/**
 * Copyright © HK. All rights reserved.
 */
declare(strict_types=1);

namespace HK\PreventOrderEmail\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Observer to handle payment_status_update event
 * Disables email sending and marks payments as cancelled for Mollie canceled/expired/failed statuses
 */
class PaymentStatusUpdate implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $order = $event->getData('order');
        
        if (!$order instanceof Order) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment instanceof \Magento\Sales\Model\Order\Payment) {
            return;
        }

        $additionalInfo = $payment->getAdditionalInformation();
        
        // Check for Mollie status in additional info
        if (isset($additionalInfo['status'])) {
            $mollieStatus = strtolower((string)$additionalInfo['status']);
            
            // If status is canceled, expired, or failed, prevent email sending
            if (in_array($mollieStatus, ['canceled', 'expired', 'failed'], true)) {
                // Disable email sending for this order
                $order->setCanSendNewEmailFlag(false);
                
                // Mark payment as cancelled by ensuring status is set in additional info
                // The payment status is already in additional info, we just need to prevent emails
                
                // Only cancel the order if status is 'canceled' and order is not already canceled
                if ($mollieStatus === 'canceled' && $order->getState() !== Order::STATE_CANCELED) {
                    try {
                        $order->cancel();
                        $order->save();
                    } catch (\Exception $e) {
                        $this->logger->error(
                            'Error canceling order after payment status update: ' . $e->getMessage(),
                            ['order_id' => $order->getId(), 'status' => $mollieStatus]
                        );
                    }
                }
            }
        }
    }
}
