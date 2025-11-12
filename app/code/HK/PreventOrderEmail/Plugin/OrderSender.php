<?php
/**
 * Copyright © HK. All rights reserved.
 */
declare(strict_types=1);

namespace HK\PreventOrderEmail\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;

/**
 * Plugin to prevent order confirmation emails for canceled/declined orders
 */
class OrderSender
{
    /**
     * Block email sending if order is canceled/declined or payment indicates cancellation
     *
     * @param OrderSender $subject
     * @param callable $proceed
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     */
    public function aroundSend(
        OrderSender $subject,
        callable $proceed,
        Order $order,
        $forceSyncMode = false
    ): bool {
        // Check if order status is canceled or declined
        $status = $order->getStatus();
        $state = $order->getState();
        
        if ($status === 'canceled' || $status === 'declined' || 
            $state === Order::STATE_CANCELED || $state === Order::STATE_CLOSED) {
            return false;
        }

        // Check payment additional info for cancellation indicators
        $payment = $order->getPayment();
        if ($payment instanceof Payment) {
            $additionalInfo = $payment->getAdditionalInformation();
            
            // Check for Mollie cancellation statuses
            if (isset($additionalInfo['status'])) {
                $mollieStatus = strtolower((string)$additionalInfo['status']);
                if (in_array($mollieStatus, ['canceled', 'expired', 'failed'], true)) {
                    return false;
                }
            }
            
            // Check for other cancellation indicators in additional info
            if (isset($additionalInfo['cancelled']) && $additionalInfo['cancelled'] === true) {
                return false;
            }
            
            if (isset($additionalInfo['is_cancelled']) && $additionalInfo['is_cancelled'] === true) {
                return false;
            }
        }

        return $proceed($order, $forceSyncMode);
    }
}
