<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\BulkDispatch\DispatchManagementInterface;
use Dhl\Dispatches\Api\Data\DispatchInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\CancellationErrorResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\CancellationErrorResponseInterfaceFactory;
use Dhl\Dispatches\Api\Data\DispatchResponse\CancellationResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\CancellationSuccessResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\CancellationSuccessResponseInterfaceFactory;
use Dhl\Dispatches\Api\Data\DispatchResponse\DispatchErrorResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\DispatchErrorResponseInterfaceFactory;
use Dhl\Dispatches\Api\Data\DispatchResponse\DispatchResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\DispatchSuccessResponseInterface;
use Dhl\Dispatches\Api\Data\DispatchResponse\DispatchSuccessResponseInterfaceFactory;
use Dhl\Dispatches\Model\Dispatch;
use Dhl\EcomUs\Model\ResourceModel\Package\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;

class DispatchManagement implements DispatchManagementInterface
{
    /**
     * @var CollectionFactory
     */
    private $packageCollectionFactory;

    /**
     * @var DispatchSuccessResponseInterfaceFactory
     */
    private $dispatchSuccessResponseFactory;

    /**
     * @var DispatchErrorResponseInterfaceFactory
     */
    private $dispatchErrorResponseFactory;

    /**
     * @var CancellationSuccessResponseInterfaceFactory
     */
    private $cancellationSuccessResponseFactory;

    /**
     * @var CancellationErrorResponseInterfaceFactory
     */
    private $cancellationErrorResponseFactory;

    /**
     * @var DateTimeFactory
     */
    private $dateFactory;

    /**
     * DispatchManagement constructor.
     * @param CollectionFactory $packageCollectionFactory
     * @param DispatchSuccessResponseInterfaceFactory $dispatchSuccessResponseFactory
     * @param DispatchErrorResponseInterfaceFactory $dispatchErrorResponseFactory
     * @param CancellationSuccessResponseInterfaceFactory $cancellationSuccessResponseFactory
     * @param CancellationErrorResponseInterfaceFactory $cancellationErrorResponseFactory
     * @param DateTimeFactory $dateFactory
     */
    public function __construct(
        CollectionFactory $packageCollectionFactory,
        DispatchSuccessResponseInterfaceFactory $dispatchSuccessResponseFactory,
        DispatchErrorResponseInterfaceFactory $dispatchErrorResponseFactory,
        CancellationSuccessResponseInterfaceFactory $cancellationSuccessResponseFactory,
        CancellationErrorResponseInterfaceFactory $cancellationErrorResponseFactory,
        DateTimeFactory $dateFactory
    ) {
        $this->packageCollectionFactory = $packageCollectionFactory;
        $this->dispatchSuccessResponseFactory = $dispatchSuccessResponseFactory;
        $this->dispatchErrorResponseFactory = $dispatchErrorResponseFactory;
        $this->cancellationSuccessResponseFactory = $cancellationSuccessResponseFactory;
        $this->cancellationErrorResponseFactory = $cancellationErrorResponseFactory;
        $this->dateFactory = $dateFactory;
    }

    /**
     * @param DispatchInterface[]|Dispatch[] $dispatches
     * @return DispatchResponseInterface[]
     */
    public function dispatch(array $dispatches): array
    {
        $responses = [];
        $dispatchTracks = $this->getAssociatedTracks($dispatches);

        foreach ($dispatches as $index => $dispatch) {
            if (empty($dispatchTracks[$dispatch->getId()])) {
                // dispatch has no tracks, nothing to manifest, create a (fake) negative response
                $responses[] = $this->dispatchErrorResponseFactory->create(['data' => [
                    DispatchErrorResponseInterface::REQUEST_INDEX => $index,
                    DispatchErrorResponseInterface::PACKAGE_NUMBERS => [],
                    DispatchErrorResponseInterface::DISPATCH => $dispatch,
                    DispatchErrorResponseInterface::ERRORS => [
                        __('Foo Error'),
                        __('Bar Error'),
                    ]
                ]]);
            } else {
                // dispatch has tracks, create a (fake) positive response
                try {
                    $dispatchNumber = 'FOO' . random_int(100, 999);
                } catch (\Exception $exception) {
                    $dispatchNumber = 'FOO123';
                }

                $responses[] = $this->dispatchSuccessResponseFactory->create(['data' => [
                    DispatchSuccessResponseInterface::REQUEST_INDEX => $index,
                    DispatchSuccessResponseInterface::PACKAGE_NUMBERS => $dispatchTracks[$dispatch->getId()],
                    DispatchSuccessResponseInterface::DISPATCH => $dispatch,
                    DispatchSuccessResponseInterface::DISPATCH_NUMBER => $dispatchNumber,
                    DispatchSuccessResponseInterface::DISPATCH_DATE => $this->dateFactory->create()->gmtDate(),
                    DispatchSuccessResponseInterface::DISPATCH_DOCUMENTS => [],
                ]]);
            }
        }

        return $responses;
    }

    /**
     * @param DispatchInterface[]|Dispatch[] $dispatches
     * @return CancellationResponseInterface[]
     */
    public function cancel(array $dispatches): array
    {
        $responses = [];
        $dispatchTracks = $this->getAssociatedTracks($dispatches);

        foreach ($dispatches as $index => $dispatch) {
            $dispatchDate = $this->dateFactory->create()->gmtDate('Y-m-d', $dispatch->getDispatchDate());
            $currentDate = $this->dateFactory->create()->gmtDate('Y-m-d');
            if ($dispatchDate < $currentDate) {
                // too old, cannot cancel, create a (fake) negative response
                $responses[] = $this->cancellationErrorResponseFactory->create(['data' => [
                    CancellationErrorResponseInterface::REQUEST_INDEX => $index,
                    CancellationErrorResponseInterface::PACKAGE_NUMBERS => $dispatchTracks[$dispatch->getId()],
                    CancellationErrorResponseInterface::DISPATCH => $dispatch,
                    CancellationErrorResponseInterface::ERRORS => [
                        __('Foo Error'),
                        __('Bar Error'),
                    ]
                ]]);
            } else {
                // all good, create a (fake) positive response
                $responses[] = $this->cancellationSuccessResponseFactory->create(['data' => [
                    CancellationSuccessResponseInterface::REQUEST_INDEX => $index,
                    CancellationSuccessResponseInterface::PACKAGE_NUMBERS => $dispatchTracks[$dispatch->getId()],
                    CancellationSuccessResponseInterface::DISPATCH => $dispatch,
                ]]);
            }
        }

        return $responses;
    }

    /**
     * Obtain "DHL Package ID" per dispatch.
     *
     * @param DispatchInterface[] $dispatches
     * @return string[][]
     */
    private function getAssociatedTracks(array $dispatches): array
    {
        $dispatchIds = array_map(
            function (Dispatch $dispatch) {
                return $dispatch->getId();
            },
            $dispatches
        );

        $collection = $this->packageCollectionFactory->create();
        $collection->addFieldToFilter('dispatch_id', ['in' => $dispatchIds]);

        $packageNumbers = [];
        foreach ($collection->getItems() as $item) {
            $packageNumbers[$item->getData('dispatch_id')][] = $item->getData('dhl_package_id');
        }

        return $packageNumbers;
    }
}
