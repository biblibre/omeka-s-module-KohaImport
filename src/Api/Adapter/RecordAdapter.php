<?php
namespace KohaImport\Api\Adapter;

use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use KohaImport\Api\Representation\RecordRepresentation;
use KohaImport\Entity\KohaImportRecord;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;

class RecordAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'koha_import_record';
    }

    public function getRepresentationClass()
    {
        return RecordRepresentation::class;
    }

    public function getEntityClass()
    {
        return KohaImportRecord::class;
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (isset($data['biblionumber'])) {
            $entity->setBiblionumber($data['biblionumber']);
        }

        if (isset($data['o:resource']['o:id'])) {
            $resource = $this->getAdapter('resources')->findEntity($data['o:resource']['o:id']);
            $entity->setResource($resource);
        }

        if (isset($data['type'])) {
            $entity->setType($data['type']);
        }

        if (isset($data['o:import']['o:id'])) {
            $import = $this->getAdapter('koha_import_import')->findEntity($data['o:import']['o:id']);
            $entity->setImport($import);
        }

        if (isset($data['jobs'])) {
            foreach ($data['jobs'] as $jobId) {
                $job = $this->getAdapter('jobs')->findEntity($jobId);
                if ($job) {
                    $entity->addJob($job);
                }
            }
        }

        if (isset($data['updatedAt'])) {
            $updatedAt = new \DateTime($data['updatedAt']);
            $entity->setUpdatedAt($updatedAt);
        }

        if (isset($data['importedAt'])) {
            $importedAt = new \DateTime($data['importedAt']);
            $entity->setImportedAt($importedAt);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['import_id'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.import',
                $this->createNamedParameter($qb, $query['import_id'])
            ));
        }

        if (isset($query['type'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.type',
                $this->createNamedParameter($qb, $query['type'])
            ));
        }
    }
}
