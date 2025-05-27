<?php

namespace KohaImport\Job;

use DateTime;
use Laminas\Http\Request;
use Omeka\Api\Request as ApiRequest;
use Omeka\Job\AbstractJob;
use Omeka\Entity\Media;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Site;
use Omeka\Entity\User;
use Omeka\Entity\ResourceTemplate;
use Omeka\Entity\ResourceClass;
use Omeka\Stdlib\ErrorStore;
use KohaImport\Entity\KohaImportRecord;
use KohaImport\Entity\KohaImportImport;
use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

class ImportJob extends AbstractJob
{
    protected $properties;
    protected $clientId;
    protected $clientSecret;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $logger = $services->get('Omeka\Logger');
        $em = $services->get('Omeka\EntityManager');
        $fulltext = $services->get('Omeka\FulltextSearch');
        $apiAdapters = $services->get('Omeka\ApiAdapterManager');
        $mappingManager = $services->get('KohaImport\MappingManager');

        $mediaIngesters = $services->get('Omeka\Media\Ingester\Manager');
        $localMediaIngesterState = $this->getModuleState('LocalMediaIngester');
        if (isset($localMediaIngesterState)) {
            $ingester = $mediaIngesters->get('local');
        } else {
            $logger->warn('Media handling disabled due to missing LocalMediaIngester module');
        }

        $import = new KohaImportImport();

        $itemSetsConfig = $this->getArg('resource_fieldset_item-sets');
        $itemsConfig = $this->getArg('resource_fieldset_items');
        if (isset($itemSetsConfig) || isset($itemsConfig)) {
            $resourceClassRepository = $em->getRepository(ResourceClass::class);
            $resourceTemplateRepository = $em->getRepository(ResourceTemplate::class);
            if (isset($itemSetsConfig['resource_class'])) {
                $itemSetsRessourceClassEntity = $resourceClassRepository->find($itemSetsConfig['resource_class']);
            }
            if (isset($itemSetsConfig['resource_template'])) {
                $itemSetsRessourceTemplateEntity = $resourceTemplateRepository->find($itemSetsConfig['resource_template']);
            }
            if (isset($itemsConfig['resource_class'])) {
                $itemsRessourceClassEntity = $resourceClassRepository->find($itemsConfig['resource_class']);
            }
            if (isset($itemsConfig['resource_template'])) {
                $itemsRessourceTemplateEntity = $resourceTemplateRepository->find($itemsConfig['resource_template']);
            }
        }

        $siteRepository = $em->getRepository(Site::class);
        $itemSetsRepository = $em->getRepository(ItemSet::class);
        $ownerRepository = $em->getRepository(User::class);

        $owner = $ownerRepository->find($this->getArg('owner'));
        $mediaIngesterOption = $this->getArg('medias_ingester');
        $mediasPath = $this->getArg('medias_path');

        $since = $this->getArg('since');
        $force = $this->getArg('force', false);
        $delete = $this->getArg('delete', false);

        $mappingConfig['item-set'] = $itemSetsConfig;
        $mappingConfig['item'] = $itemsConfig;
        $mappingConfig['since'] = $since;
        $mappingConfig['force'] = $force;
        $mappingConfig['delete'] = $delete;

        $import->setName($this->getArg('import_name'));
        $import->setJob($this->job);
        $import->setOwner($owner);
        $import->setConfig($mappingConfig);

        $sitesArray = [];
        $sites = explode(',', $this->getArg('sites'));
        foreach ($sites as $site) {
            $siteEntity = $siteRepository->find($site);
            if ($siteEntity) {
                if (!isset($sitesArray[$siteEntity->getId()])) {
                    $sitesArray[$siteEntity->getId()] = $siteEntity->getSlug();
                }
            }
        }
        $import->setSites($sitesArray);

        $em->persist($import);
        $em->flush();

        $logger->info('Job started');

        $url = rtrim($config['koha-import']['url'], '/');
        $client_id = $config['koha-import']['client_id'];
        $client_secret = $config['koha-import']['client_secret'];

        if (!$url || !$client_id || !$client_secret) {
            throw new \Exception('KohaImport is not correctly configured');
        }

        $this->setClientId($client_id);
        $this->setClientSecret($client_secret);

        $bucket_key = $config['koha-import_bucket']['key'];
        $bucket_secret = $config['koha-import_bucket']['secret'];
        $bucket_region = $config['koha-import_bucket']['region'];
        $bucket_endpoint = $config['koha-import_bucket']['endpoint'];
        $bucket_name = $config['koha-import_bucket']['name'];

        $bucketFiles = [];
        if ($mediaIngesterOption == 'bucket' && $bucket_key && $bucket_secret && $bucket_region && $bucket_endpoint && $bucket_name) {
            $s3Client = new S3Client([
                'region' => $bucket_region,
                'endpoint' => $bucket_endpoint,
                'credentials' => [
                    'key' => $bucket_key,
                    'secret' => $bucket_secret,
                ],
            ]);

            $bucket_adapter = new AwsS3V3Adapter($s3Client, $bucket_name);
            $bucket_filesystem = new Filesystem($bucket_adapter);
            try {
                $listing = $bucket_filesystem->listContents('/', true);
                /** @var \League\Flysystem\StorageAttributes $item */
                foreach ($listing as $item) {
                    $path = $item->path();
                    if (!in_array($path, $bucketFiles)) {
                        $bucketFiles[] = $path;
                    }
                }
            } catch (FilesystemException $exception) {
                throw new \Exception($exception);
            }
        }

        $this->buildPropertiesMap();
        $originalIdentityMap = $em->getUnitOfWork()->getIdentityMap();

        $this->buildRecordsFromMetadata();

        $this->detachAllNewEntities($originalIdentityMap);

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            $em->flush();
            return;
        }

        $accessToken = $this->getAccessToken($url);

        $lastBiblionumber = 0;
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $inserted = 0;
        $resourcesRelations = [];
        while ($biblios = $this->getBiblios($url, $accessToken, ['since' => $since, 'after' => $lastBiblionumber, 'include_record' => true])) {
            if ($this->shouldStop()) {
                $logger->info('Job stopped');
                $em->flush();
                return;
            }

            // Define for fulltext index
            $itemSets = [];
            $items = [];
            $resourceConfigs = [];
            $itemSetRequirements = !empty($itemSetsConfig['field_define_type']) && !empty($itemSetsConfig['value_define_type']);
            $itemRequirements = !empty($itemsConfig['field_define_type']) && !empty($itemsConfig['value_define_type']);

            if ($itemSetRequirements) {
                $resourceConfigs['item-set'] = $itemSetsConfig;
            }
            if ($itemRequirements) {
                $resourceConfigs['item'] = $itemsConfig;
            }
            foreach ($biblios as $biblio) {
                $mediasRelated = [];

                if (!empty($resourceConfigs)) {
                    $checkedResource = $this->checkResourceType($resourceConfigs, $biblio);

                    if (is_array($checkedResource)) {
                        foreach ($checkedResource as $type => $resourceConfig) {
                            $logger->warn(sprintf('%s not equal to %s for biblionumber %d and not be transformed into %s',
                                $resourceConfig['config_field'],
                                $resourceConfig['config_value'],
                                $biblio['biblionumber'],
                                $type
                            ));
                        }
                        $lastBiblionumber = $biblio['biblionumber'];
                        continue;
                    } else {
                        $resourceType = $checkedResource;
                    }
                }

                if (!$itemSetRequirements && !empty($itemSetsConfig)) {
                    $resourceType = 'item-set';
                }
                if (!$itemRequirements && !empty($itemsConfig)) {
                    $resourceType = 'item';
                }

                $lastBiblionumber = $biblionumber = $biblio['biblionumber'];
                $record = $this->recordFindOrNew($biblionumber);

                if (!$force) {
                    $updatedAt = $record->getUpdatedAt();
                    $updatedAt = $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : '';
                    if ($updatedAt === $biblio['updated_at']) {
                        // Item is already up to date
                        ++$skipped;
                        continue;
                    }
                }

                if ($resourceType == 'item-set') {
                    $mappingProfile = $mappingManager->get($itemSetsConfig['import_profile']);
                    $customMapping = $mappingProfile->getMapping();
                }
                if ($resourceType == 'item') {
                    $mappingProfile = $mappingManager->get($itemsConfig['import_profile']);
                    $customMapping = $mappingProfile->getMapping();
                }

                $resourceBuilt = $mappingProfile->transform($record, $resourceType, $biblio['record']['fields'], ['path' => $mediasPath, 'ingester_option' => $mediaIngesterOption], $bucketFiles);
                $resource = $resourceBuilt['resource'];
                $mediasRelated = $resourceBuilt['medias'];
                $resourcesRelations[$biblionumber] = $resourceBuilt['relations'][$biblionumber];

                $record->setUpdatedAt(DateTime::createFromFormat('Y-m-d H:i:s', $biblio['updated_at']));
                $record->setImportedAt(new DateTime());
                $record->setType($resourceType);
                $record->addJob($this->job);
                $record->setImport($import);

                $em->persist($record);

                $resource->setModified(new DateTime());
                if ($resource->getId()) {
                    ++$updated;
                } else {
                    ++$inserted;
                }
                $resource->setOwner($owner);

                if ($resourceType == 'item-set') {
                    $resource->setResourceClass($itemSetsRessourceClassEntity);
                    $resource->setResourceTemplate($itemSetsRessourceTemplateEntity);
                    $resource->setIsPublic($itemSetsConfig['is_public']);
                }
                if ($resourceType == 'item') {
                    $resource->setResourceClass($itemsRessourceClassEntity);
                    $resource->setResourceTemplate($itemsRessourceTemplateEntity);
                    $resource->setIsPublic($itemsConfig['is_public']);

                    if (isset($itemsConfig['add_to_item_sets']) && $resourceType == 'item') {
                        foreach ($itemsConfig['add_to_item_sets'] as $itemSetId) {
                            $itemSetEntity = $itemSetsRepository->find($itemSetId);
                            if ($itemSetEntity) {
                                if (!$resource->getItemSets()->contains($itemSetEntity)) {
                                    $resource->getItemSets()->add($itemSetEntity);
                                }
                            }
                        }
                    }

                    if (!empty($mediasRelated)) {
                        foreach ($mediasRelated as $type => $paths) {
                            foreach ($paths as $path) {
                                if ($type === 'bucket') {
                                    $remoteFileContent = $bucket_filesystem->read($path);
                                    $tempFilePath = $this->getTempFilePath($path, $remoteFileContent);
                                    $path = $tempFilePath;
                                }
                                $newMedia = new Media();
                                $newMedia->setIngester('local');
                                $newMedia->setRenderer($ingester->getRenderer());
                                $newMedia->setSource($path);
                                $newMedia->setItem($resource);
                                $newMedia->setTitle(basename($path));
                                $newMedia->setCreated(new \DateTime('now'));
                                $newMedia->setModified(new \DateTime('now'));

                                $errorStore = new ErrorStore();
                                $request = new ApiRequest(ApiRequest::CREATE, 'media');
                                $request->setContent([
                                    'ingest_filename' => $path,
                                    'original_file_action' => 'keep',
                                ]);

                                $ingester->ingest($newMedia, $request, $errorStore);
                                if ($errorStore->hasErrors()) {
                                    foreach ($errorStore->getErrors() as $key => $messages) {
                                        foreach ($messages as $message) {
                                            $logger->err(sprintf('Error while ingesting file: %s (%s)', $message, $key));
                                        }
                                    }
                                    continue;
                                }
                                $resource->getMedia()->add($newMedia);
                                if ($tempFilePath) {
                                    unlink($tempFilePath);
                                }
                            }
                        }
                    }
                }

                if (isset($sites)) {
                    if (is_array($sites)) {
                        foreach ($sites as $site) {
                            $siteEntity = $siteRepository->find($site);
                            if ($siteEntity) {
                                if (!$resource->getSites()->contains($siteEntity)) {
                                    $resource->getSites()->add($siteEntity);
                                }
                            }
                        }
                    } else {
                        $siteEntity = $siteRepository->find($sites);
                        if ($siteEntity) {
                            if (!$resource->getSites()->contains($siteEntity)) {
                                $resource->getSites()->add($siteEntity);
                            }
                        }
                    }
                }
                $em->persist($resource);
                $em->flush();

                if (isset($resourcesRelations[$biblionumber])) {
                    $resourcesRelations[$biblionumber]['omeka_id'] = $resource->getId();
                    $resourcesRelations[$biblionumber]['resource_type'] = $resourceType;
                }

                if ($resourceType == 'item-set') {
                    $itemSets[] = $resource;
                }
                if ($resourceType == 'item') {
                    $items[] = $resource;
                }
            }

            $processed += count($biblios);
            $logger->info(sprintf('Processed %d biblios', $processed));

            $itemAdapter = $apiAdapters->get('items');
            $itemSetAdapter = $apiAdapters->get('item_sets');
            foreach ($itemSets as $itemSet) {
                $fulltext->save($itemSet, $itemSetAdapter);
            }

            foreach ($items as $item) {
                $fulltext->save($item, $itemAdapter);
            }
        }
        $em->persist($import);
        $em->flush();

        $this->detachAllNewEntities($originalIdentityMap);

        $logger->info(sprintf('Inserted: %d, Updated: %d, Skipped: %d', $inserted, $updated, $skipped));

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            $em->flush();
            return;
        }

        $logger->info('Started linking resources. This can take some time.');
        foreach ($resourcesRelations as $biblionumber => $fieldRelations) {
            foreach ($fieldRelations as $tag => $relations) {
                if (is_array($relations)) {
                    $omekaProperty = array_key_first($customMapping[$tag]['literal']);
                    if (isset($relations['resource_to_link'])) {
                        $targetBiblionumbers = $relations['resource_to_link'];
                        $recordRepository = $em->getRepository(KohaImportRecord::class);
                        foreach ($targetBiblionumbers as $targetBiblionumber) {
                            $targetRecord = $recordRepository->findOneBy(['biblionumber' => $targetBiblionumber]);
                            if (isset($targetRecord)) {
                                $resourceToLink = $targetRecord->getResource();
                                $values = $resource->getValues();
                                try {
                                    $values->add($mappingProfile->newResourceValue($resource, $omekaProperty, $resourceToLink));
                                } catch (\Exception $e) {
                                    $logger->warn($e);
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            $em->flush();
            return;
        }

        $logger->info('Started retrieval of all biblionumbers in Koha and Omeka. This can take some time.');

        $biblionumbersInKoha = $this->getAllBiblionumbersInKoha($url, $accessToken);
        $biblionumbersInOmeka = $this->getAllBiblionumbersInOmeka();

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            $em->flush();
            return;
        }

        $deletedBiblionumbers = [];
        foreach ($biblionumbersInOmeka as $biblionumberInOmeka) {
            if (!array_key_exists($biblionumberInOmeka, $biblionumbersInKoha)) {
                $deletedBiblionumbers[] = $biblionumberInOmeka;
            }
        }

        if ($delete) {
            $logger->info(sprintf('Number of resources about to be deleted: %d', count($deletedBiblionumbers)));
        } else {
            $logger->info(sprintf('Number of resources that would have been deleted: %d', count($deletedBiblionumbers)));
        }

        $recordRepository = $em->getRepository(KohaImportRecord::class);
        foreach ($deletedBiblionumbers as $deletedBiblionumber) {
            $record = $recordRepository->findOneBy(['biblionumber' => $deletedBiblionumber]);
            if (!$record) {
                $logger->warn(sprintf('Biblionumber %d is marked for deletion but its corresponding item cannot be found'));
                $em->flush();
                continue;
            }

            $resource->getResource();
            $resourceTitle = $resource->getTitle();
            $resourceId = $resource->getId();

            if ($delete) {
                $em->remove($resource);
                $em->remove($record);
                $em->flush();
            }

            $action = $delete ? 'deleted' : 'would have been deleted';
            $logger->info(sprintf('%s %d %s (biblionumber: %d, title: %s)',
                $resourceType == 'item-set' ? 'Item set' : 'Item',
                $resourceId,
                $action,
                $deletedBiblionumber,
                $resourceTitle
            ));
        }

        $logger->info('Job ended');
        $em->flush();
    }

    protected function getBiblios(string $url, string $accessToken, array $options = [])
    {
        $services = $this->getServiceLocator();
        $httpClient = $services->get('Omeka\HttpClient');

        $include_record = $options['include_record'] ?? false;
        $since = $options['since'] ?? null;
        $after = $options['after'] ?? null;

        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        $request->setUri("$url/api/v1/contrib/omekaexport/biblios");
        $request->getHeaders()->addHeaders([
            'Authorization' => "Bearer $accessToken",
        ]);
        $query = $request->getQuery();
        if ($include_record) {
            $query->set('include_record', '1');
        }
        if ($since) {
            $query->set('since', $since);
        }
        if ($after) {
            $query->set('after', $after);
        }

        $response = $httpClient->send($request);
        if ($response->getStatusCode() === 401) {
            $newAccessToken = $this->getAccessToken($url);

            $request->getHeaders()->addHeaders([
            'Authorization' => "Bearer $newAccessToken",
        ]);
            $response = $httpClient->send($request);
        }
        if (!$response->isSuccess()) {
            throw new \Exception('Failed to get biblios. Response: ' . $response->renderStatusLine());
        }

        $responseBody = json_decode($response->getBody(), true);
        if (null === $responseBody) {
            throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        if (!isset($responseBody['biblios'])) {
            throw new \Exception('No biblios in response');
        }

        return $responseBody['biblios'];
    }

    protected function getAccessToken($url)
    {
        $services = $this->getServiceLocator();
        $httpClient = $services->get('Omeka\HttpClient');
        $client_id = $this->getClientId();
        $client_secret = $this->getClientSecret();

        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->setUri("$url/api/v1/oauth/token");
        $request->getHeaders()->addHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $request->getPost()
            ->set('grant_type', 'client_credentials')
            ->set('client_id', $client_id)
            ->set('client_secret', $client_secret);

        $response = $httpClient->send($request);
        if (!$response->isSuccess()) {
            throw new \Exception('Failed to get API token. Response: ' . $response->renderStatusLine());
        }

        $responseBody = json_decode($response->getBody(), true);
        if (null === $responseBody) {
            throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        $accessToken = $responseBody['access_token'];
        if (!$accessToken) {
            throw new \Exception('No access token in the response');
        }

        return $accessToken;
    }

    protected function getClientId()
    {
        return $this->clientId;
    }

    protected function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    protected function getClientSecret()
    {
        return $this->clientSecret;
    }

    protected function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    protected function recordFindOrNew(int $biblionumber)
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $repository = $em->getRepository(KohaImportRecord::class);
        $record = $repository->findOneBy(['biblionumber' => $biblionumber]);
        if (!$record) {
            $record = new KohaImportRecord();
            $record->setBiblionumber($biblionumber);
        }

        return $record;
    }

    protected function detachAllNewEntities(array $oldIdentityMap)
    {
        $em = $this->getEntityManager();
        $identityMap = $em->getUnitOfWork()->getIdentityMap();
        foreach ($identityMap as $entityClass => $entities) {
            foreach ($entities as $idHash => $entity) {
                if (!isset($oldIdentityMap[$entityClass][$idHash])) {
                    $em->detach($entity);
                }
            }
        }
    }

    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    protected function buildPropertiesMap()
    {
        $em = $this->getEntityManager();

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

    protected function buildRecordsFromMetadata()
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');
        $em = $this->getEntityManager();

        $dql = '
            SELECT r.id id, TRIM(v.value) value FROM Omeka\Entity\Resource r
            JOIN r.values v WITH v.property = :property AND v.type = :type
        ';

        $query = $em->createQuery($dql);
        $query->setParameter('property', $this->getProperty('koha:biblionumber'));
        $query->setParameter('type', 'literal');
        $results = $query->getResult();
        $chunks = array_chunk($results, 1000);
        foreach ($chunks as $results) {
            $records = [];
            foreach ($results as $r) {
                if (!is_numeric($r['value'])) {
                    continue;
                }

                $biblionumber = (int) $r['value'];

                // Check that $biblionumber is a positive integer (not a float)
                if ($biblionumber === 0 || $biblionumber != $r['value']) {
                    continue;
                }

                $record = $records[$biblionumber] ?? null;
                if ($record) {
                    $logger->warn(sprintf('Resource %1$d has value %2$d in one of its koha:biblionumber property but biblio %2$d will be imported into resource %3$d', $r['id'], $biblionumber, $record->getResource()->getId()));
                    continue;
                }

                $record = $this->recordFindOrNew($biblionumber);
                if ($record->getId()) {
                    $resource = $record->getResource();
                    if ($resource->getId() !== $r['id']) {
                        $logger->warn(sprintf('Resource %1$d has value %2$d in one of its koha:biblionumber property but biblio %2$d has been imported into resource %3$d', $r['id'], $biblionumber, $resource->getId()));
                    }
                } else {
                    $resourceRecord = $em->getRepository(KohaImportRecord::class)->findOneBy(['resource' => $r['id']]);
                    if ($resourceRecord) {
                        $logger->warn(sprintf('Resource %1$d has value %2$d in one of its koha:biblionumber property but it was created from biblio %3$d', $r['id'], $biblionumber, $resourceRecord->getBiblionumber()));
                        continue;
                    }

                    $resource = $em->find('Omeka\Entity\Resource', $r['id']);
                    $record->setResource($resource);
                    $record->setUpdatedAt($resource->getModified());
                    $record->setImportedAt($resource->getCreated());
                    $record->addJob($this->job);
                    $em->persist($record);
                }
                $records[$biblionumber] = $record;
            }

            $em->flush();
            foreach ($records as $record) {
                $em->detach($record);
            }
        }
    }

    protected function getAllBiblionumbersInKoha($url, $accessToken)
    {
        $biblionumbers = [];
        $lastBiblionumber = 0;
        while ($biblios = $this->getBiblios($url, $accessToken, ['after' => $lastBiblionumber])) {
            foreach ($biblios as $biblio) {
                $lastBiblionumber = $biblionumber = $biblio['biblionumber'];
                $biblionumbers[$biblionumber] = $biblionumber;
            }
        }

        return $biblionumbers;
    }

    protected function getAllBiblionumbersInOmeka()
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery('SELECT r.biblionumber FROM KohaImport\Entity\KohaImportRecord r');
        $results = $query->getScalarResult();
        $biblionumbers = [];
        foreach ($results as $result) {
            $biblionumber = $result['biblionumber'];
            $biblionumbers[$biblionumber] = (int) $biblionumber;
        }

        return $biblionumbers;
    }

    protected function checkResourceType($configs, $biblio)
    {
        $result = [];

        foreach ($configs as $resourceType => $config) {
            $fieldString = explode('$', $config['field_define_type']);
            $fieldMapped = $fieldString[0];
            $subfieldMapped = $fieldString[1];
            $valueMapped = $config['value_define_type'];
            $checkIsValid = false;

            foreach ($biblio['record']['fields'] as $field) {
                if (isset($field[$fieldMapped])) {
                    foreach ($field[$fieldMapped]['subfields'] as $subfield) {
                        if (isset($subfield[$subfieldMapped])) {
                            $checkIsValid = (is_array($subfield[$subfieldMapped]) && in_array($valueMapped, $subfield[$subfieldMapped])) ||
                                    ($subfield[$subfieldMapped] == $valueMapped);

                            if ($checkIsValid) {
                                return $resourceType;
                            }
                        }
                    }
                }
            }

            $result[$resourceType] = [
                'config_field' => $config['field_define_type'],
                'config_value' => $config['value_define_type'],
            ];
        }

        return $result;
    }

    protected function getModuleState($name)
    {
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($name);
        if ($module) {
            return $module->getState();
        } else {
            return false;
        }
    }

    protected function getTempFilePath($path, $remoteFileContent)
    {
        if ($remoteFileContent !== false) {
            $tempFilePath = sys_get_temp_dir() . '/' . basename($path);
            $tempFile = fopen($tempFilePath, 'wb');

            if ($tempFile) {
                fwrite($tempFile, $remoteFileContent);
                fclose($tempFile);

                return $tempFilePath;
            }
        }
    }
}
