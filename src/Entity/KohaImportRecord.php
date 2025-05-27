<?php

namespace KohaImport\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\Job;

/**
 * @Entity
 * @Table(
 *     name="koha_import_record",
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             name="kohaimport_record_biblionumber_idx",
 *             columns={"biblionumber"},
 *         ),
 *     },
 *  )
 */
class KohaImportRecord extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="KohaImportImport", inversedBy="records")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $import;

    /**
     * @Column(type="integer")
     */
    protected $biblionumber;

    /**
     * @OneToOne(
     *     targetEntity="Omeka\Entity\Resource",
     * )
     * @JoinColumn(unique=true, nullable=false, onDelete="CASCADE")
     */
    protected $resource;

    /**
     * @Column(type="string")
     */
    protected $type;

    /**
     * @ManyToMany(targetEntity="Omeka\Entity\Job")
     * @JoinTable(name="koha_import_record_job",
     *      joinColumns={@JoinColumn(name="record_id", referencedColumnName="id", onDelete="CASCADE")},
     *      inverseJoinColumns={@JoinColumn(name="job_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    protected $jobs;

    /**
     * @Column(type="datetime")
     */
    protected $updatedAt;

    /**
     * @Column(type="datetime")
     */
    protected $importedAt;

    public function __construct()
    {
        $this->jobs = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setImport(KohaImportImport $import)
    {
        $this->import = $import;
    }

    public function getImport()
    {
        return $this->import;
    }

    public function getBiblionumber()
    {
        return $this->biblionumber;
    }

    public function setBiblionumber($biblionumber)
    {
        $this->biblionumber = $biblionumber;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function addJob(Job $job)
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs[] = $job;
        }
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    public function getImportedAt()
    {
        return $this->importedAt;
    }

    public function setImportedAt(DateTime $importedAt)
    {
        $this->importedAt = $importedAt;
    }
}
