<?php

namespace KohaImport;

use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        require __DIR__ . '/vendor/autoload.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');

        $sql = <<<'SQL'
            CREATE TABLE koha_import_import (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, sites LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)', config LONGTEXT NOT NULL COMMENT '(DC2Type:json)', UNIQUE INDEX UNIQ_E663D187BE04EA9 (job_id), INDEX IDX_E663D1877E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        CREATE TABLE koha_import_record (id INT AUTO_INCREMENT NOT NULL, import_id INT NOT NULL, resource_id INT NOT NULL, biblionumber INT NOT NULL, type VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL, imported_at DATETIME NOT NULL, INDEX IDX_E019800BB6A263D9 (import_id), UNIQUE INDEX UNIQ_E019800B89329D25 (resource_id), UNIQUE INDEX kohaimport_record_biblionumber_idx (biblionumber), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        CREATE TABLE koha_import_record_job (record_id INT NOT NULL, job_id INT NOT NULL, INDEX IDX_BB8D2EE34DFD750C (record_id), INDEX IDX_BB8D2EE3BE04EA9 (job_id), PRIMARY KEY(record_id, job_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        ALTER TABLE koha_import_import ADD CONSTRAINT FK_E663D187BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE;
        ALTER TABLE koha_import_import ADD CONSTRAINT FK_E663D1877E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id);
        ALTER TABLE koha_import_record ADD CONSTRAINT FK_E019800BB6A263D9 FOREIGN KEY (import_id) REFERENCES koha_import_import (id) ON DELETE CASCADE;
        ALTER TABLE koha_import_record ADD CONSTRAINT FK_E019800B89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
        ALTER TABLE koha_import_record_job ADD CONSTRAINT FK_BB8D2EE34DFD750C FOREIGN KEY (record_id) REFERENCES koha_import_record (id) ON DELETE CASCADE;
        ALTER TABLE koha_import_record_job ADD CONSTRAINT FK_BB8D2EE3BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE;
        SQL;

        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = <<<'SQL'
            DROP TABLE IF EXISTS koha_import_record_job;
            DROP TABLE IF EXISTS koha_import_record;
            DROP TABLE IF EXISTS koha_import_import;
        SQL;

        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }

    public function getConfig()
    {
        return require __DIR__ . '/config/module.config.php';
    }
}
