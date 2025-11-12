<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Block;

use Magento\Payment\Block\Info as PaymentInfo;

/**
 * Mollie Payment Info Block (Frontend)
 */
class Info extends PaymentInfo
{
    /**
     * @var string
     */
    protected $_template = 'Experius_MolliePayment::info/default.phtml';

    /**
     * Get Mollie payment ID
     *
     * @return string|null
     */
    public function getMolliePaymentId()
    {
        return $this->getInfo()->getAdditionalInformation('mollie_payment_id');
    }

    /**
     * Get Mollie payment status
     *
     * @return string|null
     */
    public function getMollieStatus()
    {
        return $this->getInfo()->getAdditionalInformation('mollie_status');
    }
}
