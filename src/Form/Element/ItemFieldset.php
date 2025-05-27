<?php

namespace KohaImport\Form\Element;

use Laminas\Form\Element as LaminasElement;
use Omeka\Form\Element as OmekaElement;
use Laminas\Form\Fieldset;

class ItemFieldset extends Fieldset
{
    public function init()
    {
        $this->setAttribute('class', 'resource_fieldsets');

        $this->add([
            'name' => 'field_define_type',
            'type' => LaminasElement\Text::class,
            'options' => [
                'label' => 'Mapped field with resource type', //@translate
                'info' => 'e.g. "214$a"', //@translate
            ],
            'attributes' => [
                'id' => 'koha-import-mapped-field-resource',
            ],
        ]);

        $this->add([
            'name' => 'value_define_type',
            'type' => LaminasElement\Text::class,
            'options' => [
                'label' => 'Mapped value with resource type', //@translate
                'info' => 'e.g. "foo"', //@translate
            ],
            'attributes' => [
                'id' => 'koha-import-mapped-value-resource',
            ],
        ]);

        $this->add([
            'name' => 'import_profile',
            'type' => LaminasElement\Select::class,
            'options' => [
                'label' => 'Select an import profile', //@translate
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'resource_class',
            'type' => OmekaElement\ResourceClassSelect::class,
            'attributes' => [
                'id' => 'koha-import-resource-class',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a class', // @translate
            ],
            'options' => [
                'label' => 'Class', // @translate
                'empty_option' => '',
            ],
        ]);

        $this->add([
            'name' => 'resource_template',
            'type' => OmekaElement\ResourceTemplateSelect::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'koha-import-resource-template',
                'class' => 'chosen-select',
                'multiple' => false,
                'data-placeholder' => 'Select resource template…', // @translate
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'add_to_item_sets',
            'type' => OmekaElement\ItemSetSelect::class,
            'options' => [
                'label' => 'Add to item sets', // @translate
            ],
            'attributes' => [
                'id' => 'koha-import-add-to-item-sets',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select item sets', // @translate
            ],
        ]);

        $this->add([
            'name' => 'is_public',
            'type' => LaminasElement\Radio::class,
            'options' => [
                'label' => 'Resource visibility', // @translate
                'value_options' => [
                    '0' => 'Not public', // @translate
                    '1' => 'Public', // @translate
                ],
            ],
            'attributes' => [
                'class' => 'koha-import-visibility',
                'value' => '',
                'required' => true,
            ],
        ]);
    }
}
