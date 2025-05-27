<?php
namespace KohaImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ImportRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'index';
    }

    public function getJsonLd()
    {
        return [
            'o:job' => $this->job()->getReference(),
            'name' => $this->name(),
            'owner' => $this->owner(),
            'sites' => $this->sites(),
            'config' => $this->config(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:KohaimportImport';
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
}
