<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Util;

use Magento\Framework\App\ProductMetadataInterface;

/**
 * Obtain application and platform version related information.
 */
class Version
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * Version constructor.
     *
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(ProductMetadataInterface $productMetadata)
    {
        $this->productMetadata = $productMetadata;
    }

    /**
     * Get formatted User Agent String.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return sprintf(
            'Magento/%s (Language=PHP/%s.%s; Platform=%s/%s)',
            $this->productMetadata->getVersion(),
            PHP_MAJOR_VERSION,
            PHP_MINOR_VERSION,
            php_uname('s'),
            php_uname('r')
        );
    }
}
