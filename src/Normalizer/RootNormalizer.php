<?php
namespace RestOnPhp\Normalizer;

use DateTime;
use RestOnPhp\Metadata\XmlMetadata;

class RootNormalizer {
    private $normalizers, $xmlMetadata;

    public function __construct(XmlMetadata $xmlMetadata, $normalizers = []) {
        $this->normalizers = [];
        $this->xmlMetadata = $xmlMetadata;

        foreach($normalizers as $normalizer) {
            $this->normalizers[get_class($normalizer)] = $normalizer;
        }
    }

    public function normalizeCollection($data, $resource_metadata) {
        $normalized = [];

        foreach($data as $item) {
            $normalized[] = $this->normalizeItem($item, $resource_metadata);
        }

        return $normalized;
    }

    public function normalizeItem($data, $resource_metadata) {
        $normalized = [];

        foreach($resource_metadata['fields'] as $field) {
            $getter = 'get' . ucfirst($field['name']);
            $value = $data->$getter();


            if($value instanceof DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            } else if(isset($field['normalizer']) && 'string' == $field['type']) {
                $normalizer = $this->normalizers[$field['normalizer']];
                $value = $normalizer->normalizeItem($value, [], $data);
            } else if(isset($field['normalizer'])) {
                $entity = $field['type'];
                $normalizer = $this->normalizers[$field['normalizer']];
                $value = $normalizer->normalizeItem($value, $this->xmlMetadata->getMetadataForEntity($entity), $data);
            } else if(is_object($value)) {
                $entity = $field['type'];
                $value = $this->normalizeItem($value, $this->xmlMetadata->getMetadataForEntity($entity), $data);
            }
            
            $normalized[$field['name']] = $value;
        }

        return $normalized;
    }
}