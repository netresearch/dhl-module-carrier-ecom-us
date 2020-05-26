<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\BulkDispatch\DispatchManagementInterface;

class DispatchManagement implements DispatchManagementInterface
{
    public function dispatch(array $dispatches): array
    {
        return [];
    }

    public function cancel(array $dispatches): array
    {
        return [];
    }
}
