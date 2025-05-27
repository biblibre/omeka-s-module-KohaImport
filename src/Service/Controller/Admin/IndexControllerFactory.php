<?php

namespace KohaImport\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use KohaImport\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $config = $services->get('Config');

        $controller = new IndexController($config['koha-import']);

        return $controller;
    }
}
