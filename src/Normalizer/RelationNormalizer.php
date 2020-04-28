<?php
namespace RestOnPhp\Normalizer;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RelationNormalizer implements NormalizerInterface, DenormalizerInterface {
    private $metadata;
    private $entityManager;

    public function __construct(XmlMetadata $metadata, EntityManager $entityManager) {
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function normalize($object, string $format = null, array $context = []) {
        $entityClass = str_replace('Proxy\\__CG__\\', '', get_class($object));
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $route = $this->metadata->getRouteMetadataFor($entityClass, 'getItem');
        $getter = 'get' . ucfirst($id_field);
        return str_replace('{id}', $object->$getter(), $route['path']);
    }

    public function supportsNormalization($data, string $format = null) {
        return true;
    }

    public function denormalize($data, string $type, string $format = null, array $context = []) {
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

    public function supportsDenormalization($data, string $type, string $format = null) {
        return true;
    }
}
