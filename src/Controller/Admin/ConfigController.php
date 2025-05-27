<?php

namespace KohaImport\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ConfigController extends AbstractActionController
{
    protected $apiManager;

    public function showAction()
    {
        $queryParams = $this->params()->fromQuery();
        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('importName', $queryParams['import_name']);
        $view->setVariable('detailsName', $queryParams['details_name']);

        $importConfig = $queryParams['import_config'] ?? [];

        $config = $this->transformValues($importConfig);
        $view->setVariable('config', $config);

        return $view;
    }

    protected function transformValues(array $config)
    {
        $transformedConfig = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $value = $this->transformValues($value);
                if (!empty($value)) {
                    $transformedConfig[$key] = $value;
                }
            } else {
                $isBoolean = in_array($key, ['is_public', 'force', 'delete']);
                $isId = in_array($key, ['resource_class', 'resource_template', 'add_to_item_sets']);
                if (!empty($value)) {
                    if ($isBoolean) {
                        $transformedConfig[$key] = $value == 1 ? 'Yes' : 'No';
                    } elseif ($isId) {
                        $transformedConfig[$key] = $this->displayStringValue($key, $value);
                    } else {
                        $transformedConfig[$key] = $value;
                    }
                }
            }

            if ($key === 'add_to_item_sets' && is_array($value)) {
                $transformedConfig[$key] = [];
                foreach ($value as $itemSetId) {
                    $itemSetValue = $this->displayStringValue($key, $itemSetId);
                    if ($itemSetValue) {
                        $transformedConfig[$key][] = $itemSetValue;
                    }
                }
                if (empty($transformedConfig[$key])) {
                    unset($transformedConfig[$key]);
                }
            }
        }
        return $transformedConfig;
    }

    protected function displayStringValue($key, $id)
    {
        $mappedRepresentations = [
        'resource_class' => 'resource_classes',
        'resource_template' => 'resource_templates',
        'add_to_item_sets' => 'item_sets',
    ];
        $api = $this->getApiManager();
        if (!is_array($id)) {
            $response = $api->read($mappedRepresentations[$key], ['id' => $id]);
            if ($response) {
                $representation = $response->getContent();
                return $key == 'add_to_item_sets' ? $representation->title() : $representation->label();
            }
        } else {
            $itemSetTitles = [];
            foreach ($id as $itemSetId) {
                $response = $api->read($mappedRepresentations[$key], ['id' => $itemSetId]);
                if ($response) {
                    $title = $response->getContent()->title();
                    $itemSetTitles[] = $title;
                }
            }
            return !empty($itemSetTitles) ? implode(', ', $itemSetTitles) : null;
        }
        return null;
    }

    public function getApiManager()
    {
        return $this->apiManager;
    }

    public function setApiManager($apiManager)
    {
        $this->apiManager = $apiManager;

        return $this;
    }
}
