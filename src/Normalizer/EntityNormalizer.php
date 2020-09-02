<?php

namespace RestOnPhp\Normalizer;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use RestOnPhp\Utils;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class EntityNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface {
    private $defaultContext = [];
    private $metadata;
    private $normalizers = [];

    public function __construct(array $defaultContext = [], XmlMetadata $metadata, EntityManager $entityManager, $normalizers = []) {
        foreach($normalizers as $normalizer) {
            $this->normalizers[get_class($normalizer)] = $normalizer;
        }
        
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
        $this->relationNormalizer = new RelationNormalizer($metadata, $entityManager);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, string $format = null, array $context = []) {
        $normalized = array();
        $metadata = $this->metadata->getMetadataFor(get_class($object));

        foreach($metadata['fields'] as $field) {
            $getter = 'get' . ucfirst(Utils::camelize($field['name']));
            $value = $object->$getter();

            if(isset($field['normalizer']) && $field['normalizer'] != '') {
                $normalized[$field['name']] = $this->normalizers[$field['normalizer']]->normalize($object, $value);
            } else {
                $normalized[$field['name']] = $value;
            }
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null) {
        if(is_object($data)) {
            return $data instanceof Normalizable;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, string $format = null, array $context = []) {
        if(isset($context['object_to_populate'])) {
            $object = $context['object_to_populate'];
        } else {
            $reflectionClass = new \ReflectionClass($type);
            $object = $reflectionClass->newInstance();
        }

        foreach($data as $key => $value) {
            $field = $this->metadata->getFieldMetadataFor($type, $key);
            $setter = 'set' . ucfirst($key);

            if(isset($field['normalizer']) && $field['normalizer'] != '') {
                $object->$setter(
                    $this->normalizers[$field['normalizer']]->denormalize(
                        $value, 
                        $field['type'],
                        $format, 
                        $context
                    ));
            } else if($field['type'] == 'datetime') {
                $object->$setter(\DateTime::createFromFormat('Y-m-d H:i:s O', $value));
            } else {
                $object->$setter($value);
            }
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null) {
        $reflectionClass = new \ReflectionClass($type);
        $interfaceNames = $reflectionClass->getInterfaceNames();
        
        if(in_array('RestOnPhp\Normalizer\Normalizable', $interfaceNames)) {
            return true;
        }

        return $type instanceof Denormalizable;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool {
        return __CLASS__ === static::class;
    }
}
