<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Model\Service;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;

/**
 * Customer Data Validator Service
 *
 * Validates customer IDs, customer objects, cart ownership, and address ownership
 * against the authenticated user to prevent cross-customer data exposure.
 */
class CustomerDataValidator
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Validate customer ID against authenticated user
     *
     * @param int|null $customerId Customer ID to validate
     * @param int|null $authenticatedCustomerId Authenticated customer ID
     * @param string $context Context description for logging
     * @return bool
     * @throws LocalizedException
     */
    public function validateCustomerId(
        ?int $customerId,
        ?int $authenticatedCustomerId,
        string $context = ''
    ): bool {
        if ($customerId === null || $authenticatedCustomerId === null) {
            return true; // Allow null values, let authorization handle it
        }

        if ($customerId !== $authenticatedCustomerId) {
            $this->logMismatch(
                'Customer ID mismatch',
                [
                    'expected_customer_id' => $authenticatedCustomerId,
                    'actual_customer_id' => $customerId,
                    'context' => $context
                ]
            );
            throw new LocalizedException(
                __('Customer ID mismatch detected. Expected: %1, Actual: %2', $authenticatedCustomerId, $customerId)
            );
        }

        return true;
    }

    /**
     * Validate customer object against authenticated user
     *
     * @param CustomerInterface|null $customer Customer object to validate
     * @param int|null $authenticatedCustomerId Authenticated customer ID
     * @param string $context Context description for logging
     * @return bool
     * @throws LocalizedException
     */
    public function validateCustomer(
        ?CustomerInterface $customer,
        ?int $authenticatedCustomerId,
        string $context = ''
    ): bool {
        if ($customer === null || $authenticatedCustomerId === null) {
            return true; // Allow null values, let authorization handle it
        }

        $customerId = (int)$customer->getId();
        return $this->validateCustomerId($customerId, $authenticatedCustomerId, $context);
    }

    /**
     * Validate cart ownership
     *
     * @param CartInterface $cart Cart to validate
     * @param int|null $authenticatedCustomerId Authenticated customer ID
     * @param string $context Context description for logging
     * @return bool
     * @throws LocalizedException
     */
    public function validateCartOwnership(
        CartInterface $cart,
        ?int $authenticatedCustomerId,
        string $context = ''
    ): bool {
        $cartCustomerId = (int)$cart->getCustomerId();

        // Guest cart (customer_id = 0 or null) - allow if authenticated user is also guest
        if ($cartCustomerId === 0 && ($authenticatedCustomerId === null || $authenticatedCustomerId === 0)) {
            return true;
        }

        // Customer cart - must match authenticated user
        if ($cartCustomerId !== 0 && $authenticatedCustomerId !== null) {
            if ($cartCustomerId !== $authenticatedCustomerId) {
                $this->logMismatch(
                    'Cart ownership mismatch',
                    [
                        'expected_customer_id' => $authenticatedCustomerId,
                        'cart_customer_id' => $cartCustomerId,
                        'cart_id' => $cart->getId(),
                        'context' => $context
                    ]
                );
                throw new LocalizedException(
                    __('Cart ownership mismatch detected. Cart belongs to customer %1, but authenticated user is %2', 
                        $cartCustomerId, $authenticatedCustomerId)
                );
            }
        }

        return true;
    }

    /**
     * Validate address ownership
     *
     * @param AddressInterface $address Address to validate
     * @param int|null $authenticatedCustomerId Authenticated customer ID
     * @param string $context Context description for logging
     * @return bool
     * @throws LocalizedException
     */
    public function validateAddressOwnership(
        AddressInterface $address,
        ?int $authenticatedCustomerId,
        string $context = ''
    ): bool {
        if ($authenticatedCustomerId === null) {
            return true; // Let authorization handle guest access
        }

        $addressCustomerId = (int)$address->getCustomerId();

        if ($addressCustomerId !== $authenticatedCustomerId) {
            $this->logMismatch(
                'Address ownership mismatch',
                [
                    'expected_customer_id' => $authenticatedCustomerId,
                    'address_customer_id' => $addressCustomerId,
                    'address_id' => $address->getId(),
                    'context' => $context
                ]
            );
            throw new LocalizedException(
                __('Address ownership mismatch detected. Address belongs to customer %1, but authenticated user is %2',
                    $addressCustomerId, $authenticatedCustomerId)
            );
        }

        return true;
    }

    /**
     * Log mismatch for security monitoring
     *
     * @param string $type Type of mismatch
     * @param array $data Additional data to log
     * @return void
     */
    private function logMismatch(string $type, array $data): void
    {
        $this->logger->warning(
            sprintf('Customer Data Validation: %s', $type),
            array_merge(
                [
                    'type' => $type,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
                $data
            )
        );
    }
}
