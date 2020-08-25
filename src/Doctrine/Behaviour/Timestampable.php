<?php
namespace RestOnPhp\Doctrine\Behaviour;

trait Timestampable {
    protected $createdAt, $updatedAt;
    
    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function setCreatedAt($value) {
        $this->createdAt = $value;
    }

    public function getUpdatedAt() {
        return $this->updatedAt;
    }

    public function setUpdatedAt($value) {
        $this->updatedAt = $value;
    }

    public function updatedAt() {
        $this->setUpdatedAt(new \DateTime());
    }

    public function createdAt() {
        $this->setCreatedAt(new \DateTime());
    }
}
