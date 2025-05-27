<?php
namespace KohaImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class RecordRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'index';
    }

    public function getJsonLd()
    {
        return [
            'o:import' => $this->import()->getReference(),
            'biblionumber' => $this->biblionumber(),
            'resource' => $this->resource()->getReference(),
            'type' => $this->type(),
            'jobs' => $this->jobs(),
            'updatedAt' => $this->updatedAt(),
            'importedAt' => $this->importedAt(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:KohaimportRecord';
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function name()
    {
        return $this->resource->getName();
    }

    public function owner()
    {
        return $this->resource->getOwner();
    }

    public function sites()
    {
        return $this->resource->getSites();
    }

    public function config()
    {
        return $this->resource->getConfig();
    }

    public function records()
    {
        return $this->resource->getRecords();
    }
}
