<?php

namespace KohaImport\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use KohaImport\Mappings\Standard\Standard;

class StandardMappingFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Standard($services);
    }
}
