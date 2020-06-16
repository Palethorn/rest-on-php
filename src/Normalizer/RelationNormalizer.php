<?php
namespace RestOnPhp\Normalizer;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;

class RelationNormalizer {
    private $metadata;
    private $entityManager;

    public function __construct(XmlMetadata $metadata, EntityManager $entityManager) {
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function normalize($object, $value) {
        if($value == null) {
            return null;
        }
        
        $entityClass = str_replace('Proxy\\__CG__\\', '', get_class($value));
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $getter = 'get' . ucfirst($id_field);
        return $value->$getter();
    }

    public function denormalize($data, string $type) {
        if(!$data) {
            return null;
        }

        $repository = $this->entityManager->getRepository($type);
        $id_field = $this->metadata->getIdFieldNameFor($type);
        $object = $repository->findOneBy(array(
            $id_field => $data
        ));

        return $object;

    }
}
