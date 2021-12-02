<?php
namespace RestOnPhp\Factory;

use Doctrine\ORM\EntityManager;

/**
 * @property EntityManager $entityManager
 */
class PdoConnectionFactory {
    private $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager) {
        $this->entityManager = $entityManager;    
    }
    
    /**
     * @return PDO
     */
    public function getConnection() {
        return $this->entityManager->getConnection()->getWrappedConnection();
    }
}
