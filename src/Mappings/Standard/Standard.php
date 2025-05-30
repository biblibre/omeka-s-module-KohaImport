<?php

namespace KohaImport\Mappings\Standard;

use DateTime;
use Omeka\Entity\Value;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use KohaImport\Mappings\AbstractMapping;
use KohaImport\Entity\KohaImportRecord;
use CustomVocab\Entity\CustomVocab;

class Standard extends AbstractMapping
{
    protected $services;
    protected $properties;

    public function __construct($services)
    {
        $this->services = $services;
    }

    public function getLabel()
    {
        return 'standard';
    }

    public function transform(KohaImportRecord $record, $resourceType, $fields, $medias, $bucketFiles = null)
    {
        $logger = $this->services->get('Omeka\Logger');
        $em = $this->services->get('Omeka\EntityManager');
        $valueSuggestState = $this->getModuleState('ValueSuggest');
        $customVocabState = $this->getModuleState('CustomVocab');
        if (isset($customVocabState) && $customVocabState == 'active') {
            $customVocabRepository = $em->getRepository(CustomVocab::class);
        }

        $resource = $this->findOrNew($record, $resourceType);
        $biblionumber = $record->getBiblionumber();

        if ($resourceType == 'item-set' && $resource->getResourceName() != 'item_sets') {
            $logger->warn(sprintf('Biblionumber %d already exists as %s', $biblionumber, $resource->getResourceName()));
        }

        if ($resourceType == 'item' && $resource->getResourceName() != 'items') {
            $logger->warn(sprintf('Biblionumber %d already exists as %s', $biblionumber, $resource->getResourceName()));
        }

        $mediasRelated = [];
        $resourcesRelations = [];
        $values = $resource->getValues();
        $values->clear();
        $values->add($this->newLiteralValue($resource, 'koha:biblionumber', $biblionumber));
        $mapping = $this->getMapping();

        foreach ($fields as $field) {
            $tag = (string) array_key_first($field);
            if (!is_array($field[$tag]) || !array_key_exists('subfields', $field[$tag])) {
                continue;
            }
            $subfields = $field[$tag]['subfields'];

            if (isset($mapping[$tag])) {
                foreach ($mapping[$tag] as $dataType => $dataMapping) {
                    $customMapping = $this->isCustomMapping($mapping[$tag]);
                    if (is_array($dataMapping) && !$customMapping) {
                        foreach ($dataMapping as $omekaProperty => $subfieldTags) {
                            foreach ($subfields as $subfield) {
                                foreach ($subfieldTags as $subfieldTag) {
                                    if (isset($subfield[$subfieldTag])) {
                                        $value = trim($subfield[$subfieldTag]);
                                        if (strlen($value) > 0) {
                                            try {
                                                $values->add($this->newLiteralValue($resource, $omekaProperty, $value));
                                            } catch (\Exception $e) {
                                                $logger->warn($e);
                                            }
                                        }
                                        if (isset($dataMapping['dcterms:title']) && !$resource->getTitle()) {
                                            $resource->setTitle($value);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $valuesArray = [];
                        foreach ($subfields as $subfield) {
                            foreach ($subfield as $key => $value) {
                                $valuesArray[$key][] = trim($value);
                            }
                        }
                        switch ($dataType) {
                            case 'uri':
                                if (isset($valuesArray['u'])) {
                                    foreach ($valuesArray['u'] as $value) {
                                        if (strlen($value) > 0) {
                                            try {
                                                $values->add($this->newUriValue($resource, $valuesArray));
                                            } catch (\Exception $e) {
                                                $logger->warn($e);
                                            }
                                        }
                                    }
                                }
                            break;
                            case 'value_suggest':
                                $omekaProperty = array_key_first($mapping[$tag]['literal']);
                                if (isset($valueSuggestState) && $valueSuggestState == 'active' && isset($valuesArray['3'])) {
                                    foreach ($valuesArray['3'] as $index => $value) {
                                        $label = '';
                                        if (isset($valuesArray['a'][$index])) {
                                            $label .= sprintf('%s', $valuesArray['a'][$index]);
                                        }
                                        if (isset($valuesArray['b'][$index])) {
                                            if (!empty($label)) {
                                                $label .= ', ';
                                            }
                                            $label .= sprintf('%s', $valuesArray['b'][$index]);
                                        }
                                        if (isset($valuesArray['f'][$index])) {
                                            if (!empty($label)) {
                                                $label .= ' ';
                                            }
                                            $label .= sprintf('(%s)', $valuesArray['f'][$index]);
                                        }
                                        $label = $label ? trim($label) : $value;
                                        if (strlen($value) > 0) {
                                            try {
                                                $values->add($this->newValueSuggestValue($resource, $omekaProperty, $value, $label));
                                            } catch (\Exception $e) {
                                                $logger->warn($e);
                                            }
                                        }
                                    }
                                } else {
                                    $value = '';
                                    if (isset($valuesArray['a'])) {
                                        $value .= implode(', ', $valuesArray['a']);
                                    }
                                    if (isset($valuesArray['b'])) {
                                        if (!empty($value)) {
                                            $value .= ', ';
                                        }
                                        $value .= implode(', ', $valuesArray['b']);
                                    }
                                    if (isset($valuesArray['f'])) {
                                        if (!empty($value)) {
                                            $value .= ' ';
                                        }
                                        $value .= '(' . implode(', ', $valuesArray['f']) . ')';
                                    }
                                    $value = trim($value);
                                    if (strlen($value) > 0) {
                                        try {
                                            $values->add($this->newLiteralValue($resource, $omekaProperty, $value));
                                        } catch (\Exception $e) {
                                            $logger->warn($e);
                                        }
                                    }
                                }
                            break;
                            case 'koha_av':
                                $omekaProperty = array_key_first($mapping[$tag]['literal']);
                                $subfieldTags = $mapping[$tag]['literal'][$omekaProperty];
                                foreach ($subfieldTags as $subfieldTag) {
                                    if (isset($valuesArray[$subfieldTag])) {
                                        foreach ($valuesArray[$subfieldTag] as $value) {
                                            $value = trim($value);
                                            if (isset($customVocabState) && $customVocabState == 'active') {
                                                $avLabel = sprintf('Koha AV - %s$%s', $tag, $subfieldTag);
                                                $customVocab = $customVocabRepository->findOneBy(['label' => $avLabel]);
                                                if (strlen($value) > 0) {
                                                    if ($customVocab) {
                                                        try {
                                                            $values->add($this->newCustomVocabValue($customVocab->getId(), $resource, $omekaProperty, $value));
                                                        } catch (\Exception $e) {
                                                            $logger->warn($e);
                                                        }
                                                    }
                                                }
                                            } else {
                                                try {
                                                    $values->add($this->newLiteralValue($resource, $omekaProperty, $value));
                                                } catch (\Exception $e) {
                                                    $logger->warn($e);
                                                }
                                            }
                                        }
                                    }
                                }
                            break;
                            case 'medias':
                                if (!empty($medias['path']) || $medias['ingester_option'] === 'bucket') {
                                    $subfieldTags = $mapping[$tag]['medias'];
                                    foreach ($subfieldTags as $subfieldTag) {
                                        if (isset($valuesArray[$subfieldTag])) {
                                            foreach ($valuesArray[$subfieldTag] as $value) {
                                                $value = trim($value);
                                                $targetMedia = null;
                                                if (!empty($value)) {
                                                    $value = basename($value);

                                                    if ($medias['ingester_option'] === 'local') {
                                                        $targetMedia = $medias['path'] . $value;
                                                        if (isset($targetMedia)) {
                                                            $mediasRelated['local'][] = $targetMedia;
                                                        }
                                                    } else {
                                                        if (isset($bucketFiles)) {
                                                            foreach ($bucketFiles as $filePath) {
                                                                if (strpos($filePath, $value) !== false) {
                                                                    $targetMedia = $filePath;
                                                                    break;
                                                                }
                                                            }
                                                            if (isset($targetMedia)) {
                                                                $mediasRelated['bucket'][] = $targetMedia;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            break;
                            case 'relation_field':
                                $omekaProperty = array_key_first($mapping[$tag]['literal']);
                                if (isset($valuesArray['9'])) {
                                    foreach ($valuesArray['9'] as $value) {
                                        $resourcesRelations[$biblionumber][$tag]['resource_to_link'][] = trim($value);
                                    }
                                } else {
                                    if (isset($valuesArray['t'])) {
                                        foreach ($valuesArray['t'] as $value) {
                                            $value = trim($value);
                                            if (strlen($value) > 0) {
                                                try {
                                                    $values->add($this->newLiteralValue($resource, $omekaProperty, $value));
                                                } catch (\Exception $e) {
                                                    $logger->warn($e);
                                                }
                                            }
                                        }
                                    }
                                }
                            break;
                        }
                    }
                }
            }
        }
        $resourceBuilt['resource'] = $resource;
        $resourceBuilt['medias'] = $mediasRelated;
        $resourceBuilt['relations'] = $resourcesRelations;

        return $resourceBuilt;
    }
    protected function newLiteralValue($resource, $term, $value)
    {
        $v = new Value();
        $v->setResource($resource);
        $v->setProperty($this->getProperty($term));
        $v->setType('literal');
        $v->setValue($value);
        if ($term == 'koha:biblionumber') {
            $v->setIsPublic(false);
        }

        return $v;
    }

    protected function newUriValue($resource, $subfields)
    {
        $value = $subfields['u'];
        $label = $subfields['a'] ?? $value;

        $v = new Value();
        $v->setResource($resource);
        $v->setProperty($this->getProperty('bibo:uri'));
        $v->setType('uri');
        $v->setUri($value);
        $v->setValue($label);
        return $v;
    }

    protected function newValueSuggestValue($resource, $omekaProperty, $value, $label)
    {
        $v = new Value();
        $v->setResource($resource);
        $v->setProperty($this->getProperty($omekaProperty));
        $v->setType('valuesuggest:idref:ppn');
        $v->setUri("https://www.idref.fr/$value");
        $v->setValue($label);
        return $v;
    }

    protected function newCustomVocabValue($avId, $resource, $omekaProperty, $value)
    {
        $v = new Value();
        $v->setResource($resource);
        $v->setProperty($this->getProperty($omekaProperty));
        $v->setType("customvocab:$avId");
        $v->setValue($value);
        return $v;
    }

    protected function newResourceValue($resource, $omekaProperty, $resourceToLink)
    {
        $mapTypes = [
            'items' => 'item',
            'item_sets' => 'itemset',
        ];
        $type = $mapTypes[$resourceToLink->getResourceName()];

        $v = new Value();
        $v->setResource($resource);
        $v->setProperty($this->getProperty($omekaProperty));
        $v->setType("resource:$type");
        $v->setValueResource($resourceToLink);

        return $v;
    }

    protected function findOrNew(KohaImportRecord $record, $resourceType)
    {
        $resource = $record->getResource();
        if (!$resource) {
            if ($resourceType == 'item-set') {
                $resource = new ItemSet();
                $resource->setCreated(new DateTime());

                $record->setResource($resource);
                $record->setType('item-set');
            } else {
                $resource = new Item();
                $resource->setCreated(new DateTime());

                $record->setResource($resource);
                $record->setType('item');
            }
        }

        return $resource;
    }

    protected function isCustomMapping($mappingTag)
    {
        $hasValueSuggest = isset($mappingTag['value_suggest']) && $mappingTag['value_suggest'] === true;
        $mediasHandling = isset($mappingTag['medias']) && is_array($mappingTag['medias']);
        $hasRelationField = isset($mappingTag['relation_field']) && $mappingTag['relation_field'] === true;

        return $hasValueSuggest || $mediasHandling || $hasRelationField;
    }

    protected function buildPropertiesMap()
    {
        $em = $this->services->get('Omeka\EntityManager');

        $this->properties = [];

        $properties = $em->getRepository('Omeka\Entity\Property')->findAll();
        foreach ($properties as $property) {
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $this->properties[$term] = $property;
        }
    }

    protected function getProperty(string $term)
    {
        $properties = $this->getProperties();
        if (!isset($properties[$term])) {
            throw new \Exception("Property '$term' does not exist");
        }

        return $properties[$term] ?? null;
    }

    protected function getProperties()
    {
        if (!isset($this->properties)) {
            $this->buildPropertiesMap();
        }

        return $this->properties;
    }

    protected function getModuleState($name)
    {
        $moduleManager = $this->services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($name);

        if ($module) {
            return $module->getState();
        } else {
            return false;
        }
    }

    public function getMapping()
    {
        return [
            '001' => ['literal' => ['koha:biblionumber']],
            '009' => ['literal' => ['dcterms:identifier']],
            '010' => ['literal' => ['bibo:isbn']],
            '011' => ['literal' => ['bibo:issn']],
            '073' => ['literal' => ['bibo:eanucc13']],
            '101' => ['literal' => ['dcterms:language' => ['a']]],
            '200' => ['literal' => ['dcterms:title' => ['a'], 'dcterms:alternative' => ['e']]],
            '210' => ['literal' => ['dcterms:publisher' => ['c'], 'dcterms:date' => ['d']]],
            '214' => ['literal' => ['dcterms:publisher' => ['c'], 'dcterms:date' => ['d']]],
            '215' => ['literal' => ['dcterms:extent' => ['a'], 'dcterms:format' => ['d']]],
            '300' => ['literal' => ['dcterms:description' => ['a']]],
            '303' => ['literal' => ['bibo:annotates' => ['a']]],
            '305' => ['literal' => ['bibo:annotates' => ['a']]],
            '310' => ['literal' => ['dcterms:description' => ['a']]],
            '317' => ['literal' => ['dcterms:provenance' => ['a']]],
            '318' => ['literal' => ['dcterms:description' => ['a']]],
            '319' => ['literal' => ['dcterms:accessRights' => ['a']]],
            '327' => ['literal' => ['dcterms:description' => ['a']]],
            '328' => ['literal' => ['bibo:annotates' => ['a']]],
            '330' => ['literal' => ['dcterms:abstract' => ['a']]],
            '335' => ['literal' => ['dcterms:source' => ['a']]],
            '345' => ['literal' => ['dcterms:provenance' => ['a']]],
            '359' => ['literal' => ['dcterms:tableOfContents' => ['b','c','d','e','f','g','h','i','j','p']]],
            '371' => ['literal' => ['dcterms:accessRights' => ['a']]],
            '461' => ['literal' => ['dcterms:isPartOf' => ['t']], 'relation_field' => true],
            '462' => ['literal' => ['dcterms:isPartOf' => ['t']], 'relation_field' => true],
            '463' => ['literal' => ['dcterms:hasPart' => ['t']], 'relation_field' => true],
            '464' => ['literal' => ['dcterms:hasPart' => ['t']], 'relation_field' => true],
            '488' => ['literal' => ['dcterms:relation' => ['t']], 'uri' => true],
            '600' => ['literal' => ['dcterms:subject' => ['a']], 'value_suggest' => true],
            '601' => ['literal' => ['dcterms:subject' => ['a']], 'value_suggest' => true],
            '606' => ['literal' => ['dcterms:subject' => ['a']], 'value_suggest' => true],
            '607' => ['literal' => ['dcterms:spatial' => ['a']], 'value_suggest' => true],
            '610' => ['literal' => ['dcterms:subject' => ['a']], 'value_suggest' => true],
            '615' => ['literal' => ['dcterms:subject' => ['a']], 'value_suggest' => true, 'koha_av' => true],
            '700' => ['literal' => ['dcterms:creator' => ['a']], 'value_suggest' => true],
            '701' => ['literal' => ['dcterms:contributor' => ['a']],'value_suggest' => true],
            '702' => ['literal' => ['dcterms:contributor' => ['a']], 'value_suggest' => true],
            '703' => ['literal' => ['dcterms:provenance' => ['a']], 'value_suggest' => true],
            '710' => ['literal' => ['dcterms:creator' => ['a']], 'value_suggest' => true],
            '711' => ['literal' => ['dcterms:contributor' => ['a']], 'value_suggest' => true],
            '712' => ['literal' => ['dcterms:contributor' => ['a']], 'value_suggest' => true],
            '713' => ['literal' => ['dcterms:provenance' => ['a']], 'value_suggest' => true],
            '856' => ['medias' => ['d']],
            '995' => ['literal' => ['dcterms:source' => ['a'], 'dcterms:identifier' => ['f'], 'bibo:locator' => ['k']]],
        ];
    }
}
