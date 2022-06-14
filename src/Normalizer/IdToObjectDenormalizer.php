<?php
namespace RestOnPhp\Normalizer;

use Doctrine\ORM\EntityManager;
use RestOnPhp\Metadata\XmlMetadata;

/**
 * @property EntityManager $entityManager
 * @property XmlMetadata $xmlMetadata
 */
class IdToObjectDenormalizer {
    private $entityManager, $xmlMetadata;

    public function __construct(EntityManager $entityManager, XmlMetadata $xmlMetadata) {
        $this->entityManager = $entityManager;
        $this->xmlMetadata = $xmlMetadata;
    }

    public function denormalizeItem($field, $value, $resource_metadata) {
        $id_field = $resource_metadata['id'];
        
        return $this->entityManager->getRepository($resource_metadata['entity'])->findOneBy([
            $id_field => $value
        ]);
    }
}