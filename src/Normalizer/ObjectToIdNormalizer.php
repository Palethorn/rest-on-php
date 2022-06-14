<?php
namespace RestOnPhp\Normalizer;

class ObjectToIdNormalizer {
    public function normalizeItem($field, $data, $resource_metadata) {
        if(!$data) {
            return null;
        }

        $id = $resource_metadata['id'];
        $getter = 'get' . ucfirst($id);
        return $data->$getter();
    }
}