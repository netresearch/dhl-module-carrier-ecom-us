<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestDouble\Webservice;

use Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\Shipment\Stage\SendRequestStageStub;
use Dhl\Sdk\EcomUs\Api\Data\LabelInterface;
use Dhl\Sdk\EcomUs\Api\LabelServiceInterface;
use Dhl\Sdk\EcomUs\Exception\ServiceException;

/**
 * Class LabelServiceStub
 *
 * Return responses on webservice calls which can be predefined via artifacts containers.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class LabelServiceStub implements LabelServiceInterface
{
    /**
     * @var int Internal counter for label responses
     */
    private $requestIndex = 0;

    /**
     * @var SendRequestStageStub
     */
    private $sendRequestStage;

    /**
     * LabelServiceStub constructor.
     *
     * @param SendRequestStageStub $sendRequestStage
     */
    public function __construct(
        SendRequestStageStub $sendRequestStage
    ) {
        $this->sendRequestStage = $sendRequestStage;
    }

    public function createLabel(
        \JsonSerializable $labelRequest,
        string $format = self::LABEL_FORMAT_PNG
    ): LabelInterface {
        // obtain the current api response as prepared in the stage
        $response = $this->sendRequestStage->apiResponses[$this->requestIndex++];
        if ($response instanceof ServiceException) {
            throw $response;
        }

        return $response;
    }
}
