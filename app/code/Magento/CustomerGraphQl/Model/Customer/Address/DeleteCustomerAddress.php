<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CustomerGraphQl\Model\Customer\Address;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * Delete customer address
 */
class DeleteCustomerAddress
{
    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var CustomerResourceModel
     */
    private $customerResourceModel;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param CustomerResourceModel $customerResourceModel
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        AddressRepositoryInterface $addressRepository,
        CustomerResourceModel $customerResourceModel,
        CustomerFactory $customerFactory
    ) {
        $this->addressRepository = $addressRepository;
        $this->customerResourceModel = $customerResourceModel;
        $this->customerFactory = $customerFactory;
    }

    /**
     * Delete customer address
     *
     * @param AddressInterface $address
     * @return void
     * @throws GraphQlInputException
     */
    public function execute(AddressInterface $address): void
    {
        // Check against customer's actual default addresses, not just the address flags
        $customerModel = $this->customerFactory->create();
        $this->customerResourceModel->load($customerModel, $address->getCustomerId());

        $isDefaultBilling = $customerModel->getDefaultBillingAddress()
            && $address->getId() == $customerModel->getDefaultBillingAddress()->getId();
        $isDefaultShipping = $customerModel->getDefaultShippingAddress()
            && $address->getId() == $customerModel->getDefaultShippingAddress()->getId();

        if ($isDefaultBilling) {
            throw new GraphQlInputException(
                __('Customer Address %1 is set as default billing address and can not be deleted', [$address->getId()])
            );
        }
        if ($isDefaultShipping) {
            throw new GraphQlInputException(
                __('Customer Address %1 is set as default shipping address and can not be deleted', [$address->getId()])
            );
        }

        try {
            $this->addressRepository->delete($address);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }
    }
}
