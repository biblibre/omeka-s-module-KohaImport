<?php
namespace KohaImport\Service\Form;

use KohaImport\Form\MappingForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MappingFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $mappingManager = $services->get('KohaImport\MappingManager');

        $form = new MappingForm;
        $form->setOption('config-data', $options['config-data']);
        $form->setMappingManager($mappingManager);
        $form->setServiceLocator($services);

        return $form;
    }
}
