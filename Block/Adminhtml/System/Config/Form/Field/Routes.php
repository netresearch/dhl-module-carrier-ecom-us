<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\View\Element\Html\Select;

/**
 * The routes dropdown.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class Routes extends Select
{
    /**
     * @param string $value
     *
     * @return self
     */
    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->addOption('0', __('Select Route'));
            $this->addOption('US-US', __('US Domestic'));
            $this->addOption('US-INTL', __('US Cross-Border'));
            $this->addOption('CA-INTL', __('CA Cross-Border'));
        }

        return parent::_toHtml();
    }
}
