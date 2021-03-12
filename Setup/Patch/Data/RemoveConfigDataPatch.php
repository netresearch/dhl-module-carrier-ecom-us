<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Setup;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class RemoveConfigDataPatch implements PatchRevertableInterface, DataPatchInterface
{
    /**
     * @var SchemaSetupInterface|Setup
     */
    private $schemaSetup;

    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply()
    {
        return $this;
    }

    /**
     * Remove any configuration that is managed by this extension
     */
    public function revert()
    {
        $defaultConnection = $this->schemaSetup->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $configTable = $this->schemaSetup->getTable('core_config_data', ResourceConnection::DEFAULT_CONNECTION);
        $defaultConnection->delete($configTable, "`path` LIKE 'carriers/dhlecomus%'");
    }
}
