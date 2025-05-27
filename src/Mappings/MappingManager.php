<?php

namespace KohaImport\Mappings;

use Omeka\ServiceManager\AbstractPluginManager;

class MappingManager extends AbstractPluginManager
{
    protected $instanceOf = MappingInterface::class;
}
