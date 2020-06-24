<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestCase\Model\Util;

use Dhl\ShippingCore\Model\Util\ApiLogAnonymizer;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ApiLogAnonymizerTest extends TestCase
{
    /**
     * @return string[][][]
     */
    public function getLogs(): array
    {
        return [
            'auth' => [
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/auth_log_orig.txt')],
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/auth_log_anon.txt')],
            ],
            'label' => [
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/label_log_orig.txt')],
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/label_log_anon.txt')],
            ],
            'manifest' => [
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/manifest_log_orig.txt')],
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/manifest_log_anon.txt')],
            ],
            'download' => [
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/download_log_orig.txt')],
                ['message' => file_get_contents(__DIR__ . '/../../../Provider/_files/download_log_anon.txt')],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLogs
     *
     * @param string[] $originalRecord
     * @param string[] $expectedRecord
     */
    public function stripSensitiveData(array $originalRecord, array $expectedRecord)
    {
        /** @var ApiLogAnonymizer $anonymizer */
        $anonymizer = Bootstrap::getObjectManager()->create(ApiLogAnonymizer::class, ['replacement' => '[test]']);
        $actualRecord = $anonymizer($originalRecord);
        self::assertSame($expectedRecord, $actualRecord);
    }
}