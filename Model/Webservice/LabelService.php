<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\Webservice;

use Dhl\EcomUs\Model\Config\ModuleConfig;
use Dhl\EcomUs\Util\Version;
use Dhl\Sdk\EcomUs\Api\AuthenticationStorageInterfaceFactory;
use Dhl\Sdk\EcomUs\Api\Data\LabelInterface;
use Dhl\Sdk\EcomUs\Api\LabelServiceInterface;
use Dhl\Sdk\EcomUs\Api\ServiceFactoryInterfaceFactory;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Psr\Log\LoggerInterface;

/**
 * Class LabelService
 *
 * Wrapper around the SDK's label service to set authentication data from module config.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class LabelService implements LabelServiceInterface
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
     * @var LabelServiceInterface|null
     */
    private $labelService;

    /**
     * ShipmentService constructor.
     *
     * @param AuthenticationStorageInterfaceFactory $authStorageFactory
     * @param ModuleConfig $moduleConfig
     * @param ServiceFactoryInterfaceFactory $serviceFactoryFactory
     * @param Version $version
     * @param LoggerInterface $logger
     * @param \int $storeId
     */
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
     * Create shipment service.
     *
     * @return LabelServiceInterface
     * @throws ServiceException
     */
    private function getService(): LabelServiceInterface
    {
        if ($this->labelService === null) {
            $authStorage = $this->authStorageFactory->create(
                [
                    'storeId' => $this->storeId,
                    'username' => $this->moduleConfig->getApiUser($this->storeId),
                    'password' => $this->moduleConfig->getApiPassword($this->storeId),
                ]
            );

            $serviceFactory = $this->serviceFactoryFactory->create(['userAgent' => $this->version->getUserAgent()]);
            $this->labelService = $serviceFactory->createLabelService(
                $authStorage,
                $this->logger,
                $this->moduleConfig->isSandboxMode($this->storeId)
            );
        }

        return $this->labelService;
    }

    public function createLabel(
        \JsonSerializable $labelRequest,
        string $format = self::LABEL_FORMAT_PNG
    ): LabelInterface {
        return $this->getService()->createLabel($labelRequest, $format);
    }
}
