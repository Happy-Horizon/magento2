<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Experius\HeadlessCheckout\Model;

use Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface;
use Experius\HeadlessCheckout\Api\GuestHeadlessCheckoutManagementInterface;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\GuestShippingInformationManagementInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;

/**
 * Guest headless checkout management service
 */
class GuestHeadlessCheckoutManagement implements GuestHeadlessCheckoutManagementInterface
{
    /**
     * @var GuestShippingInformationManagementInterface
     */
    protected $guestShippingInformationManagement;

    /**
     * @var GuestPaymentInformationManagementInterface
     */
    protected $guestPaymentInformationManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    protected $guestCartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param GuestShippingInformationManagementInterface $guestShippingInformationManagement
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        GuestShippingInformationManagementInterface $guestShippingInformationManagement,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        LoggerInterface $logger
    ) {
        $this->guestShippingInformationManagement = $guestShippingInformationManagement;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->guestCartRepository = $guestCartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function updateCheckout($cartId, CheckoutStateInterface $checkoutState)
    {
        try {
            // Verify cart exists
            $this->guestCartRepository->get($cartId);

            // Update shipping information if provided
            if ($checkoutState->getShippingAddress() || $checkoutState->getShippingMethod()) {
                $shippingInformation = $this->createShippingInformation($checkoutState);
                $this->guestShippingInformationManagement->saveAddressInformation($cartId, $shippingInformation);
            }

            // Update payment information if provided
            if ($checkoutState->getPaymentMethod()) {
                $email = $checkoutState->getEmail();
                if (!$email) {
                    // Try to get email from billing address
                    $billingAddress = $checkoutState->getBillingAddress();
                    if ($billingAddress && $billingAddress->getEmail()) {
                        $email = $billingAddress->getEmail();
                    }
                }
                
                if ($email) {
                    $billingAddress = $checkoutState->getBillingAddress();
                    $this->guestPaymentInformationManagement->savePaymentInformation(
                        $cartId,
                        $email,
                        $checkoutState->getPaymentMethod(),
                        $billingAddress
                    );
                }
            }

            return $checkoutState;
        } catch (\Exception $e) {
            $this->logger->error('Error updating guest checkout: ' . $e->getMessage(), [
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
            $email = $checkoutState ? $checkoutState->getEmail() : null;
            $paymentMethod = $checkoutState ? $checkoutState->getPaymentMethod() : null;
            $billingAddress = $checkoutState ? $checkoutState->getBillingAddress() : null;

            if ($paymentMethod && $email) {
                return $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $email,
                    $paymentMethod,
                    $billingAddress
                );
            } else {
                // If no payment method provided, try to get from cart
                $quote = $this->guestCartRepository->get($cartId);
                $paymentMethod = $quote->getPayment();
                
                if (!$email && $billingAddress) {
                    $email = $billingAddress->getEmail();
                }
                
                if (!$email) {
                    throw new CouldNotSaveException(__('Email is required for guest checkout.'));
                }

                return $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $email,
                    $paymentMethod,
                    $billingAddress
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Error placing guest order: ' . $e->getMessage(), [
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
