<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Experius\HeadlessCheckout\Model;

use Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface;
use Experius\HeadlessCheckout\Api\HeadlessCheckoutManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

/**
 * Headless checkout management service
 */
class HeadlessCheckoutManagement implements HeadlessCheckoutManagementInterface
{
    /**
     * @var ShippingInformationManagementInterface
     */
    protected $shippingInformationManagement;

    /**
     * @var PaymentInformationManagementInterface
     */
    protected $paymentInformationManagement;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ShippingInformationManagementInterface $shippingInformationManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function updateCheckout($cartId, CheckoutStateInterface $checkoutState)
    {
        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->getActive($cartId);
            
            // Update shipping information if provided
            if ($checkoutState->getShippingAddress() || $checkoutState->getShippingMethod()) {
                $shippingInformation = $this->createShippingInformation($checkoutState);
                $this->shippingInformationManagement->saveAddressInformation($cartId, $shippingInformation);
            }

            // Update payment information if provided
            if ($checkoutState->getPaymentMethod()) {
                $billingAddress = $checkoutState->getBillingAddress();
                $this->paymentInformationManagement->savePaymentInformation(
                    $cartId,
                    $checkoutState->getPaymentMethod(),
                    $billingAddress
                );
            }

            return $checkoutState;
        } catch (\Exception $e) {
            $this->logger->error('Error updating checkout: ' . $e->getMessage(), [
                'cart_id' => $cartId,
                'exception' => $e
            ]);
            throw new CouldNotSaveException(__('Unable to update checkout: %1', $e->getMessage()), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function placeOrder($cartId, CheckoutStateInterface $checkoutState = null)
    {
        try {
            // Update checkout state if provided
            if ($checkoutState !== null) {
                $this->updateCheckout($cartId, $checkoutState);
            }

            // Place the order
            $paymentMethod = $checkoutState ? $checkoutState->getPaymentMethod() : null;
            $billingAddress = $checkoutState ? $checkoutState->getBillingAddress() : null;

            if ($paymentMethod) {
                return $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $paymentMethod,
                    $billingAddress
                );
            } else {
                // If no payment method provided, use existing payment information
                /** @var Quote $quote */
                $quote = $this->cartRepository->getActive($cartId);
                $paymentMethod = $quote->getPayment();
                
                return $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $paymentMethod,
                    $billingAddress
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Error placing order: ' . $e->getMessage(), [
                'cart_id' => $cartId,
                'exception' => $e
            ]);
            throw new CouldNotSaveException(__('Unable to place order: %1', $e->getMessage()), $e);
        }
    }

    /**
     * Create shipping information from checkout state
     *
     * @param CheckoutStateInterface $checkoutState
     * @return \Magento\Checkout\Api\Data\ShippingInformationInterface
     */
    protected function createShippingInformation(CheckoutStateInterface $checkoutState)
    {
        /** @var \Magento\Checkout\Api\Data\ShippingInformationInterface $shippingInformation */
        $shippingInformation = \Magento\Framework\App\ObjectManager::getInstance()
            ->create(\Magento\Checkout\Api\Data\ShippingInformationInterface::class);

        if ($checkoutState->getShippingAddress()) {
            $shippingInformation->setShippingAddress($checkoutState->getShippingAddress());
        }

        if ($checkoutState->getBillingAddress()) {
            $shippingInformation->setBillingAddress($checkoutState->getBillingAddress());
        }

        if ($checkoutState->getShippingMethod()) {
            // Parse shipping method (format: carrier_code_method_code)
            $shippingMethodParts = explode('_', $checkoutState->getShippingMethod(), 2);
            if (count($shippingMethodParts) === 2) {
                $shippingInformation->setShippingCarrierCode($shippingMethodParts[0]);
                $shippingInformation->setShippingMethodCode($shippingMethodParts[1]);
            }
        }

        return $shippingInformation;
    }
}
