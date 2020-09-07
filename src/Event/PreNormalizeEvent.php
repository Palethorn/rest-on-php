<?php
namespace RestOnPhp\Event;

class PreNormalizeEvent {
    public const NAME = 'api.event.data.pre_normalize';

    private $entityClass, $data;

    public function __construct($entityClass, $data) {
        $this->entityClass = $entityClass;
        $this->data = $data;
    }

    public function getEntityClass() {
        return $this->entityClass;
    }

    public function getData() {
        return $this->data;
    }
}