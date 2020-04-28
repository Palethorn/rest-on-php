<?php
namespace RestOnPhp\Doctrine\Behaviour;

trait Timestampable {
    public function updatedAt() {
        $this->setUpdatedAt(new \DateTime());
    }

    public function createdAt() {
        $this->setCreatedAt(new \DateTime());
    }
}
