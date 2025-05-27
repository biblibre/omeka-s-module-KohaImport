<?php
namespace KohaImport\Api\Adapter;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use KohaImport\Api\Representation\ImportRepresentation;
use KohaImport\Entity\KohaImportImport;

class ImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'koha_import_import';
    }

    public function getRepresentationClass()
    {
        return ImportRepresentation::class;
    }

    public function getEntityClass()
    {
        return KohaImportImport::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }

        if (isset($data['name'])) {
            $entity->setName($data['name']);
        }

        if (isset($data['owner_id'])) {
            $user = $this->getAdapter('users')->findEntity($data['owner_id']);
            $entity->setOwner($user);
        }

        if (isset($data['siteIds'])) {
            $sites = [];
            $siteIds = $data['siteIds'];
            foreach ($siteIds as $siteId) {
                if (!empty($siteId)) {
                    $siteEntity = $this->getAdapter('sites')->findEntity($siteId);
                    if (!isset($sites[$siteEntity->getId()])) {
                        $sites[$siteEntity->getId()] = $siteEntity->getSlug();
                    }
                }
            }
            $entity->setSites($sites);
        }

        if (isset($data['config'])) {
            $entity->setConfig($data['config']);
        }
    }
}
