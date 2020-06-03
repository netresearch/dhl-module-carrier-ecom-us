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
use Dhl\Dispatches\Api\Data\DispatchResponse\DocumentInterfaceFactory;
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
     * @var DocumentInterfaceFactory
     */
    private $documentFactory;

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
     * @param DocumentInterfaceFactory $documentFactory
     * @param DispatchSuccessResponseInterfaceFactory $dispatchSuccessResponseFactory
     * @param DispatchErrorResponseInterfaceFactory $dispatchErrorResponseFactory
     * @param CancellationSuccessResponseInterfaceFactory $cancellationSuccessResponseFactory
     * @param CancellationErrorResponseInterfaceFactory $cancellationErrorResponseFactory
     * @param DateTimeFactory $dateFactory
     */
    public function __construct(
        CollectionFactory $packageCollectionFactory,
        DocumentInterfaceFactory $documentFactory,
        DispatchSuccessResponseInterfaceFactory $dispatchSuccessResponseFactory,
        DispatchErrorResponseInterfaceFactory $dispatchErrorResponseFactory,
        CancellationSuccessResponseInterfaceFactory $cancellationSuccessResponseFactory,
        CancellationErrorResponseInterfaceFactory $cancellationErrorResponseFactory,
        DateTimeFactory $dateFactory
    ) {
        $this->packageCollectionFactory = $packageCollectionFactory;
        $this->documentFactory = $documentFactory;
        $this->dispatchSuccessResponseFactory = $dispatchSuccessResponseFactory;
        $this->dispatchErrorResponseFactory = $dispatchErrorResponseFactory;
        $this->cancellationSuccessResponseFactory = $cancellationSuccessResponseFactory;
        $this->cancellationErrorResponseFactory = $cancellationErrorResponseFactory;
        $this->dateFactory = $dateFactory;
    }

    private function getDispatchDocuments()
    {
        $png = <<<'PNG'
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
PNG;

        $pdf = <<<'PDF'
JVBERi0xLjUKJbXtrvsKMyAwIG9iago8PCAvTGVuZ3RoIDQgMCBSCiAgIC9GaWx0ZXIgL0ZsYXRl
RGVjb2RlCj4+CnN0cmVhbQp4nCvkCuQCAAKSANcKZW5kc3RyZWFtCmVuZG9iago0IDAgb2JqCiAg
IDEyCmVuZG9iagoyIDAgb2JqCjw8Cj4+CmVuZG9iago1IDAgb2JqCjw8IC9UeXBlIC9QYWdlCiAg
IC9QYXJlbnQgMSAwIFIKICAgL01lZGlhQm94IFsgMCAwIDEwNCAxNDcgXQogICAvQ29udGVudHMg
MyAwIFIKICAgL0dyb3VwIDw8CiAgICAgIC9UeXBlIC9Hcm91cAogICAgICAvUyAvVHJhbnNwYXJl
bmN5CiAgICAgIC9DUyAvRGV2aWNlUkdCCiAgID4+CiAgIC9SZXNvdXJjZXMgMiAwIFIKPj4KZW5k
b2JqCjEgMCBvYmoKPDwgL1R5cGUgL1BhZ2VzCiAgIC9LaWRzIFsgNSAwIFIgXQogICAvQ291bnQg
MQo+PgplbmRvYmoKNiAwIG9iago8PCAvQ3JlYXRvciAoY2Fpcm8gMS45LjUgKGh0dHA6Ly9jYWly
b2dyYXBoaWNzLm9yZykpCiAgIC9Qcm9kdWNlciAoY2Fpcm8gMS45LjUgKGh0dHA6Ly9jYWlyb2dy
YXBoaWNzLm9yZykpCj4+CmVuZG9iago3IDAgb2JqCjw8IC9UeXBlIC9DYXRhbG9nCiAgIC9QYWdl
cyAxIDAgUgo+PgplbmRvYmoKeHJlZgowIDgKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMzQ2
IDAwMDAwIG4gCjAwMDAwMDAxMjUgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAw
MTA0IDAwMDAwIG4gCjAwMDAwMDAxNDYgMDAwMDAgbiAKMDAwMDAwMDQxMSAwMDAwMCBuIAowMDAw
MDAwNTM2IDAwMDAwIG4gCnRyYWlsZXIKPDwgL1NpemUgOAogICAvUm9vdCA3IDAgUgogICAvSW5m
byA2IDAgUgo+PgpzdGFydHhyZWYKNTg4CiUlRU9GCg==
B64;
PDF;

        $pngDoc = $this->documentFactory->create([
            'name' => 'An image manifestation document.',
            'content' => base64_decode($png),
            'format' => 'PNG',
        ]);

        $pdfDoc = $this->documentFactory->create([
            'name' => 'An pdf manifestation document.',
            'content' => base64_decode($pdf),
            'format' => 'PDF',
        ]);

        return [$pngDoc, $pdfDoc];
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
                $responses[] = $this->dispatchErrorResponseFactory->create([
                    'requestIndex' => $index,
                    'packageNumbers' => [],
                    'errors' => [
                        __('Foo Error'),
                        __('Bar Error'),
                    ],
                    'dispatch' => $dispatch,
                ]);
            } else {
                // dispatch has tracks, create a (fake) positive response
                try {
                    $dispatchNumber = 'FOO' . random_int(100, 999);
                } catch (\Exception $exception) {
                    $dispatchNumber = 'FOO123';
                }

                $responses[] = $this->dispatchSuccessResponseFactory->create([
                    'requestIndex' => $index,
                    'dispatchNumber' => $dispatchNumber,
                    'dispatchDate' => $this->dateFactory->create()->gmtDate(),
                    'dispatchDocuments' => $this->getDispatchDocuments(),
                    'packageNumbers' => $dispatchTracks[$dispatch->getId()],
                    'dispatch' => $dispatch
                ]);
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
            $dispatchDate = $this->dateFactory->create()->gmtDate('Y-m-d', $dispatch->getDispatchDate() ?: null);
            $currentDate = $this->dateFactory->create()->gmtDate('Y-m-d');
            if ($dispatchDate < $currentDate) {
                // too old, cannot cancel, create a (fake) negative response
                $responses[] = $this->cancellationErrorResponseFactory->create([
                    'requestIndex' => $index,
                    'packageNumbers' => $dispatchTracks[$dispatch->getId()] ?? [],
                    'errors' => [
                        __('Foo Error'),
                        __('Bar Error'),
                    ],
                    'dispatch' => $dispatch,
                ]);
            } else {
                // all good, create a (fake) positive response
                $responses[] = $this->cancellationSuccessResponseFactory->create([
                    'requestIndex' => $index,
                    'packageNumbers' => $dispatchTracks[$dispatch->getId()] ?? [],
                    'dispatch' => $dispatch,
                ]);
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
