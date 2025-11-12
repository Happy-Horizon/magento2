<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Experius\HeadlessCheckout\Api;

use Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface;

/**
 * Interface for managing headless checkout
 * @api
 */
interface HeadlessCheckoutManagementInterface
{
    /**
     * Update checkout state for a specified cart.
     *
     * @param int $cartId
     * @param \Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface $checkoutState
     * @return \Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateCheckout($cartId, CheckoutStateInterface $checkoutState);

    /**
     * Place order for a specified cart.
     *
     * @param int $cartId
     * @param \Experius\HeadlessCheckout\Api\Data\CheckoutStateInterface|null $checkoutState
     * @return int Order ID.
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function placeOrder($cartId, CheckoutStateInterface $checkoutState = null);
}
