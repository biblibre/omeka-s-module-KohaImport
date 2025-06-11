<?php

namespace KohaImport\Form;

use Laminas\Form\Form;
use Laminas\Form\Element as LaminasElement;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init()
    {
        $this->setAttribute('action', 'koha-import/map');

        $this->add([
            'name' => 'import_name',
            'type' => LaminasElement\Text::class,
            'options' => [
                'label' => 'Import name', //@translate
            ],
            'attributes' => [
                'id' => 'koha-import-import-name',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'medias_ingester',
            'type' => LaminasElement\Radio::class,
            'options' => [
                'label' => 'Medias source', // @translate
                'info' => 'Choose between local or S3 bucket server', // @translate
                'value_options' => [
                    'local' => 'Local server', // @translate
                    'bucket' => 'Bucket S3', // @translate
                ],
            ],
            'attributes' => [
                'value' => 'local',
                ],
        ]);

        $this->add([
            'name' => 'medias_path',
            'type' => LaminasElement\Text::class,
            'options' => [
                'label' => 'Medias path', //@translate
                'info' => 'Only used for local server option', // @translate
            ],
            'attributes' => [
                'id' => 'koha-import-medias-path',
            ],
        ]);

        $this->add([
            'type' => LaminasElement\MultiCheckbox::class,
            'name' => 'resource-type',
            'options' => [
                'label' => 'Select resource type to import', //@translate
                'value_options' => [
                    'item-sets' => 'Item sets', //@translate
                    'items' => 'Items', //@translate
                ],
            ],
        ]);

        $this->add([
            'name' => 'sites',
            'type' => OmekaElement\SiteSelect::class,
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select sites', // @translate
                'multiple' => true,
                'id' => 'koha-import-sites',
            ],
            'options' => [
                'label' => 'Sites for resources', // @translate
                'empty_option' => '',
            ],
        ]);

        $this->add([
            'name' => 'owner',
            'type' => OmekaElement\UserSelect::class,
            'attributes' => [
                'id' => 'koha-import-owner',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select owner', // @translate
                'required' => true,
            ],
            'options' => [
                'label' => 'Owner', // @translate
                'empty_option' => '',
            ],
        ]);

        $this->add([
            'name' => 'since',
            'type' => LaminasElement\Text::class,
            'attributes' => [
                'required' => false,
                'id' => 'koha-import-since',
            ],
            'options' => [
                'label' => 'Since', // @translate
                'info' => 'Should be a datetime formatted like this: YYYY-mm-dd HH:MM:SS', // @translate
            ],
        ]);

        $this->add([
            'name' => 'force',
            'type' => LaminasElement\Checkbox::class,
            'attributes' => [
                'required' => false,
                'id' => 'koha-import-force',
            ],
            'options' => [
                'label' => 'Force', // @translate
                'info' => 'Force update even when it seems the resource is already up-to-date', // @translate
            ],
        ]);

        $this->add([
            'name' => 'delete',
            'type' => LaminasElement\Checkbox::class,
            'attributes' => [
                'required' => false,
                'id' => 'koha-import-delete',
            ],
            'options' => [
                'label' => 'Delete', // @translate
                'info' => 'Delete Omeka S resources that are not exported by Koha anymore', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'sites',
            'required' => false,
        ]);
    }
}
