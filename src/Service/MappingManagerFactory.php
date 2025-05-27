<?php
namespace KohaImport\Service;

use KohaImport\Mappings\MappingManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Omeka\Service\Exception;

class MappingManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $config = $serviceLocator->get('Config');
        if (!isset($config['koha-import_mapping'])) {
            throw new Exception\ConfigException('Missing koha-import_mapping configuration');
        }

        return new MappingManager($serviceLocator, $config['koha-import_mapping']);
    }
}
