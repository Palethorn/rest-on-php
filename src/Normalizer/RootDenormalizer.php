<?php
namespace RestOnPhp\Normalizer;

use Doctrine\ORM\EntityManager;
use RestOnPhp\Metadata\XmlMetadata;

class RootDenormalizer {
    private $xmlMetadata, $denormalizers;

    public function __construct(XmlMetadata $xmlMetadata, $denormalizers = []) {
        $this->denormalizers = [];
        $this->xmlMetadata = $xmlMetadata;

        foreach($denormalizers as $denormalizer) {
            $this->denormalizers[get_class($denormalizer)] = $denormalizer;
        }
        
    }
    public function denormalizeItem($data, $resource_metadata, $object = null) {
        if(!$object) {
            $object = new $resource_metadata['entity']();
        }

        foreach($resource_metadata['fields'] as $field) {
            if(!isset($data[$field['name']])) {
                continue;
            }

            $value = $data[$field['name']];
            $entity_metadata = null;

            if(!in_array($field['type'], [ 'integer', 'string', 'boolean', 'text', 'object' ])) {
                $entity_metadata = $this->xmlMetadata->getMetadataForEntity($field['type']);
            }

            if(isset($field['denormalizer'])) {
                $value = $this->denormalizers[$field['denormalizer']]->denormalizeItem(
                    $field,
                    $value, 
                    $entity_metadata
                );
            }

            $setter = 'set' . ucfirst($field['name']);
            $object->$setter($value);
        }
        
        return $object;
    }
}