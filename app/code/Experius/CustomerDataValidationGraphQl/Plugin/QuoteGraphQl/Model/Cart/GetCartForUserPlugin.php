<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CustomerDataValidationGraphQl\Plugin\QuoteGraphQl\Model\Cart;

use Experius\CustomerDataValidationGraphQl\Model\Service\CustomerDataValidator;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Quote\Model\Quote;

/**
 * Plugin to validate cart ownership in GetCartForUser
 */
class GetCartForUserPlugin
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
     * Validate cart ownership after cart is retrieved
     *
     * @param GetCartForUser $subject
     * @param Quote $result
     * @param string $cartHash
     * @param int|null $customerId
     * @param int $storeId
     * @return Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterExecute(
        GetCartForUser $subject,
        Quote $result,
        string $cartHash,
        ?int $customerId,
        int $storeId
    ): Quote {
        $this->customerDataValidator->validateCartOwnership(
            $result,
            $customerId,
            sprintf('GetCartForUser::execute - cart_hash: %s', $cartHash)
        );

        return $result;
    }
}
