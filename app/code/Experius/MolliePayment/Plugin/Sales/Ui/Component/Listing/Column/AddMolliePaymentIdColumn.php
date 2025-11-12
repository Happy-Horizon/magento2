<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Experius\MolliePayment\Plugin\Sales\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

/**
 * Plugin to add Mollie Payment ID column to order grid
 */
class AddMolliePaymentIdColumn
{
    /**
     * Add Mollie payment ID to order grid collection
     *
     * @param CollectionFactory $subject
     * @param \Magento\Framework\Data\Collection $result
     * @param string $requestName
     * @return \Magento\Framework\Data\Collection
     */
    public function afterGetReport(
        CollectionFactory $subject,
        $result,
        $requestName
    ) {
        if ($requestName === 'sales_order_grid_data_source') {
            if ($result instanceof Collection) {
                $result->getSelect()->joinLeft(
                    ['payment_table' => $result->getTable('sales_order_payment')],
                    'main_table.entity_id = payment_table.parent_id',
                    []
                );
                $result->getSelect()->joinLeft(
                    ['payment_info' => $result->getConnection()->getTableName('sales_order_payment')],
                    'main_table.entity_id = payment_info.parent_id AND payment_info.method = "molliepayment"',
                    ['mollie_payment_id' => new \Zend_Db_Expr('NULLIF(payment_info.additional_information, "")')]
                );
            }
        }

        return $result;
    }
}
