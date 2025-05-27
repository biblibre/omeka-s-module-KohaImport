<?php

namespace KohaImport\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use KohaImport\Controller\Admin\ConfigController;

class ConfigControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $apiManager = $serviceLocator->get('Omeka\ApiManager');
        $configController = new ConfigController();
        $configController->setApiManager($apiManager);

        return $configController;
    }
}
