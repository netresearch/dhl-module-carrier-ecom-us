<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\Data\DispatchInterface;
use Dhl\Dispatches\Model\Dispatch;
use Dhl\EcomUs\Model\ResourceModel\Package\CollectionFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterface;

/**
 * Load track entities for dispatches.
 */
class DispatchTracksLoader
{
    /**
     * @var CollectionFactory
     */
    private $packageCollectionFactory;

    /**
     * DispatchRequestBuilder constructor.
     *
     * @param CollectionFactory $packageCollectionFactory
     */
    public function __construct(CollectionFactory $packageCollectionFactory)
    {
        $this->packageCollectionFactory = $packageCollectionFactory;
    }

    /**
     * Obtain tracks, indexed by dispatch id
     *
     * @param DispatchInterface[] $dispatches
     * @return ShipmentTrackInterface[][]
     */
    public function getTracks(array $dispatches): array
    {
        $dispatchIds = array_map(
            function (Dispatch $dispatch) {
                return $dispatch->getId();
            },
            $dispatches
        );

        $collection = $this->packageCollectionFactory->create();
        $collection->addFieldToFilter('dispatch_id', ['in' => $dispatchIds]);

        $dispatchTracks = [];
        foreach ($collection->getItems() as $item) {
            $dispatchTracks[$item->getData('dispatch_id')][] = $item;
        }

        return $dispatchTracks;
    }
}
