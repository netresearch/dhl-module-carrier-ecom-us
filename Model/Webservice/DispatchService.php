<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Webservice;

use Dhl\EcomUs\Model\Config\ModuleConfig;
use Dhl\EcomUs\Model\Util\Version;
use Dhl\Sdk\EcomUs\Api\AuthenticationStorageInterfaceFactory;
use Dhl\Sdk\EcomUs\Api\Data\ManifestInterface;
use Dhl\Sdk\EcomUs\Api\ManifestServiceInterface;
use Dhl\Sdk\EcomUs\Api\ServiceFactoryInterfaceFactory;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Psr\Log\LoggerInterface;

/**
 * Class DispatchService
 *
 * Wrapper around the SDK's manifestation service to set authentication data from module config.
 */
class DispatchService implements ManifestServiceInterface
{
    /**
     * @var AuthenticationStorageInterfaceFactory
     */
    private $authStorageFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var ServiceFactoryInterfaceFactory
     */
    private $serviceFactoryFactory;

    /**
     * @var Version
     */
    private $version;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var ManifestServiceInterface|null
     */
    private $dispatchService;

    public function __construct(
        AuthenticationStorageInterfaceFactory $authStorageFactory,
        ModuleConfig $moduleConfig,
        ServiceFactoryInterfaceFactory $serviceFactoryFactory,
        Version $version,
        LoggerInterface $logger,
        int $storeId
    ) {
        $this->authStorageFactory = $authStorageFactory;
        $this->moduleConfig = $moduleConfig;
        $this->serviceFactoryFactory = $serviceFactoryFactory;
        $this->version = $version;
        $this->logger = $logger;
        $this->storeId = $storeId;
    }

    /**
     * Create dispatch service.
     *
     * @return ManifestServiceInterface
     * @throws ServiceException
     */
    private function getService(): ManifestServiceInterface
    {
        if ($this->dispatchService === null) {
            $authStorage = $this->authStorageFactory->create(
                [
                    'storeId' => $this->storeId,
                    'username' => $this->moduleConfig->getApiUser($this->storeId),
                    'password' => $this->moduleConfig->getApiPassword($this->storeId),
                ]
            );

            $serviceFactory = $this->serviceFactoryFactory->create(['userAgent' => $this->version->getUserAgent()]);
            $this->dispatchService = $serviceFactory->createManifestationService(
                $authStorage,
                $this->logger,
                $this->moduleConfig->isSandboxMode($this->storeId)
            );
        }

        return $this->dispatchService;
    }

    public function createManifest(string $pickupAccountNumber): ManifestInterface
    {
        return $this->getService()->createManifest($pickupAccountNumber);
    }

    public function createPackageManifest(
        string $pickupAccountNumber,
        array $packageIds = [],
        array $dhlPackageIds = []
    ): ManifestInterface {
        return $this->getService()->createPackageManifest($pickupAccountNumber, $packageIds, $dhlPackageIds);
    }

    public function getManifest(string $pickupAccountNumber, string $requestId): ManifestInterface
    {
        return $this->getService()->getManifest($pickupAccountNumber, $requestId);
    }
}
