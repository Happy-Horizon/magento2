<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;
use Magento\Framework\AuthorizationInterface;

/**
 * Mollie Payment Tab Block
 */
class Payment extends AbstractOrder implements TabInterface
{
    /**
     * @var AuthorizationInterface
     */
    protected $authorization;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param AuthorizationInterface $authorization
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        AuthorizationInterface $authorization,
        array $data = []
    ) {
        $this->authorization = $authorization;
        parent::__construct($context, $registry, $adminHelper, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('Mollie Payment');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('Mollie Payment Information');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        $order = $this->getOrder();
        return $order && $order->getPayment() && 
               $order->getPayment()->getMethod() === 'molliepayment' &&
               $this->authorization->isAllowed('Experius_MolliePayment::payment_info');
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return !$this->canShowTab();
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
     * Get sync URL
     *
     * @return string
     */
    public function getSyncUrl()
    {
        $order = $this->getOrder();
        return $this->getUrl('molliepayment/payment/sync', ['order_id' => $order->getId()]);
    }
}
