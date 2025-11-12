<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Plugin\CustomerGraphQl\Model\Customer\Address;

use Experius\CustomerDataValidationGraphQl\Model\Service\CustomerDataValidator;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\CustomerGraphQl\Model\Customer\Address\GetCustomerAddress;

/**
 * Plugin to validate address ownership in GetCustomerAddress
 */
class GetCustomerAddressPlugin
{
    /**
     * @var CustomerDataValidator
     */
    private $customerDataValidator;

    /**
     * @param CustomerDataValidator $customerDataValidator
     */
    public function __construct(
        CustomerDataValidator $customerDataValidator
    ) {
        $this->customerDataValidator = $customerDataValidator;
    }

    /**
     * Validate address ownership after address is retrieved
     *
     * @param GetCustomerAddress $subject
     * @param AddressInterface $result
     * @param int $addressId
     * @param int $customerId
     * @return AddressInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterExecute(
        GetCustomerAddress $subject,
        AddressInterface $result,
        int $addressId,
        int $customerId
    ): AddressInterface {
        $this->customerDataValidator->validateAddressOwnership(
            $result,
            $customerId,
            sprintf('GetCustomerAddress::execute - address_id: %d', $addressId)
        );

        return $result;
    }
}
