<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ResourceModel\Package;

use Dhl\Dispatches\Model\ResourceModel\Package\Collection as PackageCollection;

class Collection extends PackageCollection
{
    /**
     * Join tables.
     *
     * @return PackageCollection
     */
    protected function _initSelect()
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
     */
    private function joinPackageNumbers()
    {
        $this->getSelect()
             ->join(
                 ['package' => $this->getTable('dhlecomus_package')],
                 'main_table.entity_id = package.track_id',
                 ['package.dhl_package_id']
             );
    }
}
