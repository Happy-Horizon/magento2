<?php
/**
 * Copyright © HK. All rights reserved.
 */
declare(strict_types=1);

namespace HK\PreventOrderEmail\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

/**
 * Observer to prevent emails for initially canceled/declined orders
 * Handles sales_order_place_after event
 */
class PreventOrderEmail implements ObserverInterface
{
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

        $status = $order->getStatus();
        $state = $order->getState();
        
        // Check if order status is canceled or declined
        if ($status === 'canceled' || $status === 'declined' || 
            $state === Order::STATE_CANCELED || $state === Order::STATE_CLOSED) {
            $order->setCanSendNewEmailFlag(false);
            return;
        }

        // Check payment additional info for cancellation indicators
        $payment = $order->getPayment();
        if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
            $additionalInfo = $payment->getAdditionalInformation();
            
            // Check for Mollie cancellation statuses
            if (isset($additionalInfo['status'])) {
                $mollieStatus = strtolower((string)$additionalInfo['status']);
                if (in_array($mollieStatus, ['canceled', 'expired', 'failed'], true)) {
                    $order->setCanSendNewEmailFlag(false);
                    return;
                }
            }
            
            // Check for other cancellation indicators
            if (isset($additionalInfo['cancelled']) && $additionalInfo['cancelled'] === true) {
                $order->setCanSendNewEmailFlag(false);
                return;
            }
            
            if (isset($additionalInfo['is_cancelled']) && $additionalInfo['is_cancelled'] === true) {
                $order->setCanSendNewEmailFlag(false);
                return;
            }
        }
    }
}
