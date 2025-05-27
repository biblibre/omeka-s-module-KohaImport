<?php
namespace KohaImport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;
use Omeka\Entity\User;

/**
 * @Entity
 * @Table(
 *     name="koha_import_import"
 *  )
 */
class KohaImportImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var Job
     *
     * @OneToOne(
     *     targetEntity=\Omeka\Entity\Job::class
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $job;

    /**
     * @Column(type="string")
     */
    protected $name;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\User")
     * @JoinColumn(nullable=false)
     */
    protected $owner;

    /**
     * @Column(type="json", nullable=true)
     */
    protected $sites;

    /**
     * @Column(type="json")
     */
    protected $config;

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;

        return $this;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner(User $user)
    {
        $this->owner = $user;

        return $this;
    }

    public function getSites()
    {
        return $this->sites;
    }

    public function setSites(?array $sites)
    {
        if (empty($sites)) {
            $this->sites = null;
        } else {
            $this->sites = $sites;
        }

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
