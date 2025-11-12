<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;

/**
 * Mollie Payment Method Model
 */
class Payment extends AbstractMethod
{
    /**
     * Payment method code
     */
    const CODE = 'molliepayment';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * @var string
     */
    protected $_infoBlockType = \Experius\MolliePayment\Block\Info::class;

    /**
     * Check whether payment method is available
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Authorize payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('The authorize action is not available.'));
        }

        $order = $payment->getOrder();
        $apiKey = $this->getConfigData('api_key');
        
        if (empty($apiKey)) {
            throw new LocalizedException(__('Mollie API key is not configured.'));
        }

        // Store payment information
        $payment->setTransactionId($this->generateTransactionId());
        $payment->setIsTransactionClosed(false);
        $payment->setAdditionalInformation('mollie_payment_id', $this->generateTransactionId());
        $payment->setAdditionalInformation('mollie_status', 'pending');

        return $this;
    }

    /**
     * Capture payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new LocalizedException(__('The capture action is not available.'));
        }

        $payment->setTransactionId($this->generateTransactionId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Refund payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new LocalizedException(__('The refund action is not available.'));
        }

        $payment->setTransactionId($this->generateTransactionId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Void payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     * @throws LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$this->canVoid()) {
            throw new LocalizedException(__('The void action is not available.'));
        }

        $payment->setTransactionId($this->generateTransactionId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Generate transaction ID
     *
     * @return string
     */
    protected function generateTransactionId()
    {
        return 'mollie_' . uniqid();
    }

    /**
     * Get config payment action url
     *
     * @return string|null
     */
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('payment_action');
    }
}
