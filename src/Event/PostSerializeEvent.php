<?php
namespace RestOnPhp\Event;

class PostSerializeEvent {
    public const NAME = 'api.event.data.post_serialize';
    private $entityClass, $data, $normalized, $serialized;

    public function __construct($entityClass, $data, $normalized, $serialized) {
        $this->entityClass = $entityClass;
        $this->data = $data;
        $this->normalized = $normalized;
        $this->serialized = $serialized;
    }

    public function getEntityClass() {
        return $this->entityClass;
    }

    public function getData() {
        return $this->data;
    }

    public function getNormalized() {
        return $this->normalized;
    }

    public function getSerialized() {
        return $this->serialized;
    }
}