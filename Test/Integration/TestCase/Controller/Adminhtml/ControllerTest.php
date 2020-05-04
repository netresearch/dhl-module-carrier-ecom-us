<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml;

use Dhl\EcomUs\Test\Integration\TestDouble\Webservice\LabelServiceStub;
use Dhl\EcomUs\Model\Webservice\LabelService;
use Magento\Framework\Exception\AuthenticationException;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Class ControllerTest
 *
 * Base controller test for all actions which trigger label api calls for order fixtures:
 * - Create shipment and label for single order
 *
 */
abstract class ControllerTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $httpMethod = 'POST';

    /**
     * Set up the label service stub to suppress actual api calls.
     *
     * @throws AuthenticationException
     */
    protected function setUp()
    {
        parent::setUp();

        $this->_objectManager->configure(['preferences' => [LabelService::class => LabelServiceStub::class]]);
    }
}
