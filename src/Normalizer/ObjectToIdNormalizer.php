<?php
namespace RestOnPhp\Normalizer;

class ObjectToIdNormalizer {
    public function normalizeItem($data, $resource_metadata) {
        $id = $resource_metadata['id'];
        $getter = 'get' . ucfirst($id);
        return $data->$getter();
    }
}