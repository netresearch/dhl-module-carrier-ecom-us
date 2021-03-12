<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Netresearch\ShippingDispatch\Api\BulkDispatch\ConfigurationInterface;
use Netresearch\ShippingDispatch\Api\BulkDispatch\DispatchManagementInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var DispatchManagement
     */
    private $dispatchManagement;

    public function __construct(DispatchManagement $dispatchManagement)
    {
        $this->dispatchManagement = $dispatchManagement;
    }

    public function getCarrierCode(): string
    {
        return EcomUs::CARRIER_CODE;
    }

    public function getDispatchManagement(): DispatchManagementInterface
    {
        return $this->dispatchManagement;
    }
}
