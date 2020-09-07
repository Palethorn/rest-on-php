<?php
namespace RestOnPhp\Event;

class PreSerializeEvent {
    public const NAME = 'api.event.data.pre_serialize';
    private $entityClass, $data, $normalized;

    public function __construct($entityClass, $data, $normalized) {
        $this->entityClass = $entityClass;
        $this->data = $data;
        $this->normalized = $normalized;
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
}