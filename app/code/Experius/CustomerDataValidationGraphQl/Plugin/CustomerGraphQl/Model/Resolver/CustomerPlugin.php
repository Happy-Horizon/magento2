<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Plugin\CustomerGraphQl\Model\Resolver;

use Experius\CustomerDataValidationGraphQl\Model\Service\CustomerDataValidator;
use Magento\CustomerGraphQl\Model\Resolver\Customer;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Plugin to validate customer data in Customer resolver
 */
class CustomerPlugin
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
     * Validate customer data after resolution
     *
     * @param Customer $subject
     * @param array $result
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterResolve(
        Customer $subject,
        array $result,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        /** @var ContextInterface $context */
        if ($context->getExtensionAttributes()->getIsCustomer()) {
            $authenticatedCustomerId = (int)$context->getUserId();
            $customerId = isset($result['id']) ? (int)$result['id'] : null;

            $this->customerDataValidator->validateCustomerId(
                $customerId,
                $authenticatedCustomerId,
                'Customer::resolve'
            );
        }

        return $result;
    }
}
