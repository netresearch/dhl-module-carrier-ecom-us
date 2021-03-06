<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Logger\Monolog;

/**
 * Provide options for the "log level" module configuration setting.
 */
class LogLevel implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return string[][]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => (string) Monolog::ERROR, 'label' => __('Errors')],
            ['value' => (string) Monolog::INFO, 'label' => __('Info (All API Activities)')],
        ];
    }
}
