<?php
namespace RestOnPhp\Event;

class ResourcePreDeleteEvent {
    public const NAME = 'api.event.resource.pre_delete';
    private $object;

    public function __construct($object) {
        $this->object = $object;
    }

    public function getEntityClass() {
        return $this->entityClass;
    }

    public function getObject() {
        return $this->object;
    }
}