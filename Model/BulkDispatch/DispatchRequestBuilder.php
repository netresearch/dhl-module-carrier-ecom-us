<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\Data\DispatchInterface;
use Dhl\Dispatches\Model\Dispatch;
use Dhl\EcomUs\Model\Config\ModuleConfig;
use Magento\Store\Api\StoreWebsiteRelationInterface;

/**
 * Prepare data for SDK.
 */
class DispatchRequestBuilder
{
    /**
     * @var StoreWebsiteRelationInterface
     */
    private $storeRelation;

    /**
     * @var ModuleConfig
     */
    private $config;

    /**
     * @var DispatchTracksLoader
     */
    private $trackLoader;

    /**
     * @var DispatchRequestFactory
     */
    private $dispatchRequestFactory;

    /**
     * @var int[]
     */
    private $storeIds = [];

    /**
     * @var DispatchInterface[]
     */
    private $dispatches = [];

    /**
     * DispatchRequestBuilder constructor.
     *
     * @param StoreWebsiteRelationInterface $storeRelation
     * @param ModuleConfig $config
     * @param DispatchTracksLoader $trackLoader
     * @param DispatchRequestFactory $dispatchRequestFactory
     */
    public function __construct(
        StoreWebsiteRelationInterface $storeRelation,
        ModuleConfig $config,
        DispatchTracksLoader $trackLoader,
        DispatchRequestFactory $dispatchRequestFactory
    ) {
        $this->storeRelation = $storeRelation;
        $this->config = $config;
        $this->trackLoader = $trackLoader;
        $this->dispatchRequestFactory = $dispatchRequestFactory;
    }

    /**
     * Obtain any (the first) store ID assigned to the given website to be used for config access.
     *
     * @param int $websiteId
     * @return int
     */
    private function getStoreId(int $websiteId): int
    {
        if (!isset($this->storeIds[$websiteId])) {
            $storeIds = $this->storeRelation->getStoreByWebsiteId($websiteId);
            $this->storeIds[$websiteId] = (int) array_shift($storeIds);
        }

        return $this->storeIds[$websiteId];
    }

    /**
     * @param DispatchInterface[]|Dispatch[] $dispatches
     * @return $this
     */
    public function setDispatches(array $dispatches): self
    {
        $this->dispatches = $dispatches;
        return $this;
    }

    /**
     * @return DispatchRequest[]
     */
    public function create(): array
    {
        $dispatchRequests = [];
        $tracks = $this->trackLoader->getTracks($this->dispatches);

        foreach ($this->dispatches as $dispatch) {
            $storeId = $this->getStoreId($dispatch->getWebsiteId());

            $dispatchRequests[] = $this->dispatchRequestFactory->create([
                'storeId' => $storeId,
                'pickupAccountNumber' => $this->config->getPickupAccountNumber($storeId),
                'dispatch' => $dispatch,
                'tracks' => $tracks[$dispatch->getEntityId()] ?? [],
            ]);
        }

        $this->dispatches = [];

        return $dispatchRequests;
    }
}
