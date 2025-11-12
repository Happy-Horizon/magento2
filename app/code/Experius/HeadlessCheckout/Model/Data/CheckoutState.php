<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Experius\HeadlessCheckout\Model\Data;

use Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Checkout state data model
 */
class CheckoutState extends AbstractExtensibleModel implements CheckoutStateInterface
{
    /**
     * @inheritdoc
     */
    public function getShippingAddress()
    {
        return $this->getData(self::SHIPPING_ADDRESS);
    }

    /**
     * @inheritdoc
     */
    public function setShippingAddress($address)
    {
        return $this->setData(self::SHIPPING_ADDRESS, $address);
    }

    /**
     * @inheritdoc
     */
    public function getBillingAddress()
    {
        return $this->getData(self::BILLING_ADDRESS);
    }

    /**
     * @inheritdoc
     */
    public function setBillingAddress($address)
    {
        return $this->setData(self::BILLING_ADDRESS, $address);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentMethod()
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @inheritdoc
     */
    public function setPaymentMethod($paymentMethod)
    {
        return $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    /**
     * @inheritdoc
     */
    public function getShippingMethod()
    {
        return $this->getData(self::SHIPPING_METHOD);
    }

    /**
     * @inheritdoc
     */
    public function setShippingMethod($shippingMethod)
    {
        return $this->setData(self::SHIPPING_METHOD, $shippingMethod);
    }

    /**
     * @inheritdoc
     */
    public function getEmail()
    {
        return $this->getData(self::EMAIL);
    }

    /**
     * @inheritdoc
     */
    public function setEmail($email)
    {
        return $this->setData(self::EMAIL, $email);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(
        \Experius\HeadlessCheckout\Api\Data\CheckoutStateExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
