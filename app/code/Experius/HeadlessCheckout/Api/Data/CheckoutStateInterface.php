<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Experius\HeadlessCheckout\Api\Data;

/**
 * Interface CheckoutStateInterface
 * @api
 */
interface CheckoutStateInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    /**#@+
     * Constants defined for keys of array, makes typos less likely
     */
    const SHIPPING_ADDRESS = 'shipping_address';
    const BILLING_ADDRESS = 'billing_address';
    const PAYMENT_METHOD = 'payment_method';
    const SHIPPING_METHOD = 'shipping_method';
    const EMAIL = 'email';
    /**#@-*/

    /**
     * Get shipping address
     *
     * @return \Magento\Quote\Api\Data\AddressInterface|null
     */
    public function getShippingAddress();

    /**
     * Set shipping address
     *
     * @param \Magento\Quote\Api\Data\AddressInterface|null $address
     * @return $this
     */
    public function setShippingAddress($address);

    /**
     * Get billing address
     *
     * @return \Magento\Quote\Api\Data\AddressInterface|null
     */
    public function getBillingAddress();

    /**
     * Set billing address
     *
     * @param \Magento\Quote\Api\Data\AddressInterface|null $address
     * @return $this
     */
    public function setBillingAddress($address);

    /**
     * Get payment method
     *
     * @return \Magento\Quote\Api\Data\PaymentInterface|null
     */
    public function getPaymentMethod();

    /**
     * Set payment method
     *
     * @param \Magento\Quote\Api\Data\PaymentInterface|null $paymentMethod
     * @return $this
     */
    public function setPaymentMethod($paymentMethod);

    /**
     * Get shipping method code
     *
     * @return string|null
     */
    public function getShippingMethod();

    /**
     * Set shipping method code
     *
     * @param string|null $shippingMethod
     * @return $this
     */
    public function setShippingMethod($shippingMethod);

    /**
     * Get email (for guest checkout)
     *
     * @return string|null
     */
    public function getEmail();

    /**
     * Set email (for guest checkout)
     *
     * @param string|null $email
     * @return $this
     */
    public function setEmail($email);

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Experius\HeadlessCheckout\Api\Data\CheckoutStateExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Experius\HeadlessCheckout\Api\Data\CheckoutStateExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Experius\HeadlessCheckout\Api\Data\CheckoutStateExtensionInterface $extensionAttributes
    );
}
