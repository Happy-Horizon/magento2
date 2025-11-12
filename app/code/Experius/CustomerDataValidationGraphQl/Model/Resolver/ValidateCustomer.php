<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Model\Resolver;

use Experius\CustomerDataValidationGraphQl\Model\Service\CustomerDataValidator;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Resolver for validateCustomer GraphQL query
 */
class ValidateCustomer implements ResolverInterface
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
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $authenticatedCustomerId = (int)$context->getUserId();
        $customerIdToValidate = isset($args['customer_id']) ? (int)$args['customer_id'] : null;

        if ($customerIdToValidate === null) {
            throw new GraphQlInputException(__('Required parameter "customer_id" is missing'));
        }

        try {
            $this->customerDataValidator->validateCustomerId(
                $customerIdToValidate,
                $authenticatedCustomerId,
                'ValidateCustomer::resolve'
            );

            return [
                'is_valid' => true,
                'message' => __('Customer ID is valid')
            ];
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return [
                'is_valid' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
