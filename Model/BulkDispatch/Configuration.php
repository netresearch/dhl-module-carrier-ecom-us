<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\BulkDispatch\ConfigurationInterface;
use Dhl\Dispatches\Api\BulkDispatch\DispatchManagementInterface;
use Dhl\EcomUs\Model\Carrier\EcomUs;

class Configuration implements ConfigurationInterface
{
    /**
     * @var DispatchManagement
     */
    private $dispatchManagement;

    /**
     * Configuration constructor.
     * @param DispatchManagement $dispatchManagement
     */
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
