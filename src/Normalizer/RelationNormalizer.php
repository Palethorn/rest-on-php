<?php
namespace RestOnPhp\Normalizer;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RelationNormalizer {
    private $metadata;
    private $entityManager;

    public function __construct(XmlMetadata $metadata, EntityManager $entityManager) {
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function normalize($object, $value) {
        $entityClass = str_replace('Proxy\\__CG__\\', '', get_class($value));
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $getter = 'get' . ucfirst($id_field);
        return $object->$getter();
    }

    public function denormalize($data, string $type) {
        if(!$data) {
            return null;
        }

        $matches = array();
        preg_match('/.*\/(.*)$/', $data, $matches);
        $id = $matches[1];

        $repository = $this->entityManager->getRepository($type);
        $id_field = $this->metadata->getIdFieldNameFor($type);
        $object = $repository->findOneBy(array(
            $id_field => $id
        ));

        return $object;

    }
}
