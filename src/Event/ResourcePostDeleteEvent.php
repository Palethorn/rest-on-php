<?php
namespace RestOnPhp\Event;

class ResourcePostDeleteEvent {
    public const NAME = 'api.event.resource.post_delete';
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