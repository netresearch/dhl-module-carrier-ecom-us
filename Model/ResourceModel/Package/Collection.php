<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ResourceModel\Package;

use Netresearch\ShippingDispatch\Model\ResourceModel\Package\Collection as PackageCollection;

class Collection extends PackageCollection
{
    protected function _construct(): void
    {
        parent::_construct();

        $this->_map['fields']['status'] = 'dispatch.status';
    }

    /**
     * Join tables.
     *
     * @return PackageCollection
     */
    protected function _initSelect(): PackageCollection
    {
        parent::_initSelect();

        $this->joinPackageNumbers();

        return $this;
    }

    /**
     * Add the "DHL Package ID" column to the select.
     *
     * The DHL eCommerce carrier stores additional identifiers for each
     * shipping label booked at the web service. The database table where
     * these identifiers are persisted gets joined here.
     *
     * @return void
     */
    private function joinPackageNumbers(): void
    {
        $this->getSelect()
             ->join(
                 ['package' => $this->getTable('dhlecomus_package')],
                 'main_table.entity_id = package.track_id',
                 ['package.dhl_package_id']
             );
    }

    /**
     * Join dispatch table for filtering, no columns are added to the result.
     *
     * @return void
     */
    public function joinDispatch(): void
    {
        $this->getSelect()
            ->joinLeft(
                ['dispatch' => $this->getTable('dhlgw_dispatch')],
                'assoc.dispatch_id = dispatch.entity_id',
                []
            );
    }
}
