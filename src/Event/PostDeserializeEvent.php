<?php
namespace RestOnPhp\Event;

class PostDeserializeEvent {
    public const NAME = 'api.event.data.post_deserialize';
    private $entityClass, $data, $object;

    public function __construct($entityClass, $data, $object) {
        $this->data = $data;
        $this->object = $object;
        $this->entityClass = $entityClass;
    }

    public function getEntityClass() {
        return $this->entityClass;
    }

    public function getData() {
        return $this->data;
    }

    public function getObject() {
        return $this->object;
    }
}