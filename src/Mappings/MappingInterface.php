<?php
namespace KohaImport\Mappings;

use KohaImport\Entity\KohaImportRecord;

interface MappingInterface
{
    public function getLabel();
    public function transform(KohaImportRecord $record, $resourceType, $fields, $medias, $bucketFiles = null);
}
