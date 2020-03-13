<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Block\Adminhtml\System\Config\Form\Field;

use Dhl\EcomUs\Util\ShippingProducts;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * The products dropdown.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class Products extends Select
{
    /**
     * @var ShippingProducts
     */
    private $shippingProducts;

    public function __construct(Context $context, ShippingProducts $shippingProducts, array $data = [])
    {
        $this->shippingProducts = $shippingProducts;

        parent::__construct($context, $data);
    }

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
            $this->addOption('0', __('Select Product'));

            $products = array_merge(
                $this->shippingProducts->getApplicableProducts('US'),
                $this->shippingProducts->getApplicableProducts('CA')
            );

            $options = [];
            foreach ($products as $areaProducts) {
                foreach ($areaProducts as $destination => $product) {
                    $options[$product] = $product;
                }
            }

            foreach ($options as $option) {
                $label = sprintf('%s (%s)', $this->shippingProducts->getProductName($option), $option);
                $this->addOption($option, $label);
            }
        }

        return parent::_toHtml();
    }
}
