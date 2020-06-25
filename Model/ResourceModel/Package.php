<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ResourceModel;

use Dhl\EcomUs\Model\Package as PackageModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

/**
 * Package resource model
 *
 * @author Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class Package extends AbstractDb
{
    /**
     * Init main table and primary key.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('dhlecomus_package', 'entity_id');
    }

    /**
     * @param PackageModel $object
     * @param string $trackNumber
     * @return Package
     * @throws NotFoundException
     * @throws LocalizedException
     */
    public function loadByTrackNumber(PackageModel $object, string $trackNumber): self
    {
        $object->beforeLoad($trackNumber, PackageModel::TRACKING_ID);

        $connection = $this->getConnection();
        if ($connection) {
            $trackingId = $this->getConnection()->quoteIdentifier(
                sprintf('%s.%s', $this->getMainTable(), PackageModel::TRACKING_ID)
            );
            $packageId = $this->getConnection()->quoteIdentifier(
                sprintf('%s.%s', $this->getMainTable(), PackageModel::PACKAGE_ID)
            );
            $trackId = $this->getConnection()->quoteIdentifier(
                sprintf('%s.%s', $this->getMainTable(), PackageModel::TRACK_ID)
            );

            $select = $this->getConnection()
                ->select()
                ->from($this->getMainTable())
                ->where($trackId . 'IS NULL')
                ->where($trackingId . '=?', $trackNumber)
                ->orWhere($packageId . '=?', $trackNumber);

            $data = $connection->fetchRow($select);
            if ($data) {
                $object->setData($data);
            } else {
                throw new NotFoundException(__('Package not found'));
            }
        }

        $this->unserializeFields($object);
        $this->_afterLoad($object);
        $object->afterLoad();
        $object->setOrigData();
        $object->setHasDataChanges(false);

        return $this;
    }
}
