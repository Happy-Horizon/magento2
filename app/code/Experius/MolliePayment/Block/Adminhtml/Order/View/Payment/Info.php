<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Block\Adminhtml\Order\View\Payment;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

/**
 * Mollie Payment Info Block
 */
class Info extends Template
{
    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @param Template\Context $context
     * @param Registry $coreRegistry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->coreRegistry = $coreRegistry;
    }

    /**
     * Get order
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    /**
     * Get Mollie payment ID
     *
     * @return string|null
     */
    public function getMolliePaymentId()
    {
        $order = $this->getOrder();
        if ($order && $order->getPayment()) {
            return $order->getPayment()->getAdditionalInformation('mollie_payment_id');
        }
        return null;
    }

    /**
     * Get Mollie payment status
     *
     * @return string|null
     */
    public function getMollieStatus()
    {
        $order = $this->getOrder();
        if ($order && $order->getPayment()) {
            return $order->getPayment()->getAdditionalInformation('mollie_status');
        }
        return null;
    }

    /**
     * Check if order uses Mollie payment
     *
     * @return bool
     */
    public function isMolliePayment()
    {
        $order = $this->getOrder();
        return $order && $order->getPayment() && 
               $order->getPayment()->getMethod() === 'molliepayment';
    }
}
