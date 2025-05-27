<?php

namespace KohaImport\Form;

use Laminas\Form\Form;
use KohaImport\Form\Element\ItemFieldset;
use KohaImport\Form\Element\ItemSetFieldset;

class MappingForm extends Form
{
    protected $mappingManager;
    protected $serviceLocator;

    public function init()
    {
        $this->setAttribute('action', 'import');
        $this->setAttribute('id', 'mapping_form');

        $configData = $this->getOption('config-data');
        $resourceTypes = $configData['resource-type'];

        unset($configData['resource-type']);

        foreach ($configData as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if (!isset($value)) {
                continue;
            }
            $this->add([
                'name' => $key,
                'type' => 'hidden',
                'attributes' => [
                    'value' => $value,
                ],
            ]);
        }

        $fieldsetMap = [
            'items' => ItemFieldset::class,
            'item-sets' => ItemSetFieldset::class,
        ];

        foreach ($resourceTypes as $type) {
            $requiredOption = count($resourceTypes) > 1;
            if (isset($fieldsetMap[$type])) {
                $this->add([
                    'name' => "resource_fieldset_$type",
                    'type' => $fieldsetMap[$type],
                ]);

                $this->populateInputs($this->get("resource_fieldset_$type"), $requiredOption);
            }
        }
    }

    protected function populateInputs($fieldset, $requiredOption)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $isPublic = $settings->get('default_to_private') ? '0' : '1';

        $profileSelect = $fieldset->get('import_profile');
        $profileSelect->setValueOptions($this->getMappingOptions());

        $isPublicInput = $fieldset->get('is_public');
        $isPublicInput->setValue($isPublic);

        $fieldInput = $fieldset->get('field_define_type');
        $valueInput = $fieldset->get('value_define_type');

        if ($requiredOption) {
            $fieldInput->setAttribute('required', true);
            $valueInput->setAttribute('required', true);
        }
    }

    public function setServiceLocator($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    public function setMappingManager($mappingManager)
    {
        $this->mappingManager = $mappingManager;
    }

    public function getMappingManager()
    {
        return $this->mappingManager;
    }

    protected function getMappingOptions()
    {
        $mappingManager = $this->getMappingManager();
        $mappingNames = $mappingManager->getRegisteredNames();

        $options = [];

        foreach ($mappingNames as $name) {
            $mapping = $mappingManager->get($name);
            $options[$name] = $mapping->getLabel();
        }

        return $options;
    }
}
