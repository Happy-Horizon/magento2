<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Plugin\QuoteGraphQl\Model\Resolver;

use Experius\CustomerDataValidationGraphQl\Model\Service\CustomerDataValidator;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForCheckout;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder;

/**
 * Plugin to validate cart ownership before placing order
 */
class PlaceOrderPlugin
{
    /**
     * @var CustomerDataValidator
     */
    private $customerDataValidator;

    /**
     * @var GetCartForCheckout
     */
    private $getCartForCheckout;

    /**
     * @param CustomerDataValidator $customerDataValidator
     * @param GetCartForCheckout $getCartForCheckout
     */
    public function __construct(
        CustomerDataValidator $customerDataValidator,
        GetCartForCheckout $getCartForCheckout
    ) {
        $this->customerDataValidator = $customerDataValidator;
        $this->getCartForCheckout = $getCartForCheckout;
    }

    /**
     * Validate cart ownership before placing order
     *
     * @param PlaceOrder $subject
     * @param callable $proceed
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundResolve(
        PlaceOrder $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!empty($args['input']['cart_id'])) {
            $maskedCartId = $args['input']['cart_id'];
            $userId = (int)$context->getUserId();
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

            try {
                $cart = $this->getCartForCheckout->execute($maskedCartId, $userId, $storeId);
                $this->customerDataValidator->validateCartOwnership(
                    $cart,
                    $userId,
                    sprintf('PlaceOrder::resolve - cart_id: %s', $maskedCartId)
                );
            } catch (\Exception $e) {
                // Let the original resolver handle the exception
            }
        }

        return $proceed($field, $context, $info, $value, $args);
    }
}
