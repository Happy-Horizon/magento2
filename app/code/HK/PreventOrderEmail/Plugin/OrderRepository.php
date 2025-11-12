<?php
/**
 * Copyright © HK. All rights reserved.
 */
declare(strict_types=1);

namespace HK\PreventOrderEmail\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Plugin to set canSendNewEmailFlag to false for canceled/declined orders
 */
class OrderRepository
{
    /**
     * Set canSendNewEmailFlag to false before saving canceled/declined orders
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $entity
     * @return array
     */
    public function beforeSave(
        OrderRepositoryInterface $subject,
        OrderInterface $entity
    ): array {
        if ($entity instanceof Order) {
            $status = $entity->getStatus();
            $state = $entity->getState();
            
            // Check if order status is canceled or declined
            if ($status === 'canceled' || $status === 'declined' || 
                $state === Order::STATE_CANCELED || $state === Order::STATE_CLOSED) {
                $entity->setCanSendNewEmailFlag(false);
            } else {
                // Check payment additional info for cancellation indicators
                $payment = $entity->getPayment();
                if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
                    $additionalInfo = $payment->getAdditionalInformation();
                    
                    // Check for Mollie cancellation statuses
                    if (isset($additionalInfo['status'])) {
                        $mollieStatus = strtolower((string)$additionalInfo['status']);
                        if (in_array($mollieStatus, ['canceled', 'expired', 'failed'], true)) {
                            $entity->setCanSendNewEmailFlag(false);
                        }
                    }
                    
                    // Check for other cancellation indicators
                    if (isset($additionalInfo['cancelled']) && $additionalInfo['cancelled'] === true) {
                        $entity->setCanSendNewEmailFlag(false);
                    }
                    
                    if (isset($additionalInfo['is_cancelled']) && $additionalInfo['is_cancelled'] === true) {
                        $entity->setCanSendNewEmailFlag(false);
                    }
                }
            }
        }
        
        return [$entity];
    }
}
