<?php
namespace RestOnPhp\Event;

class PostNormalizeEvent {
    public const NAME = 'api.event.data.post_normalize';

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