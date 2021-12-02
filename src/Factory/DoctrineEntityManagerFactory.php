<?php
namespace RestOnPhp\Factory;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * @property EntityManager $entityManager
 */
class DoctrineEntityManagerFactory {
    private $entityManager;

    public function create(
        $database_driver,
        // $database_path,
        $database_host,
        $database_port,
        $database_name,
        $database_user,
        $database_password,
        $config_dir,
        $cache_dir,
        $namespace
    ) {
        if($this->entityManager) {
            return $this->entityManager;
        }

        $config = new \Doctrine\ORM\Configuration();
        $config->setProxyDir($cache_dir . '/doctrine_proxies');
        $config->setProxyNamespace('Proxy');

        $namespaces = [
            $config_dir . '/doctrine_mapping' => $namespace
        ];
        $driver = new \Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver($namespaces);
        $config->setMetadataDriverImpl($driver);

        if(!is_dir($cache_dir . '/doctrine_metadata')) {
            mkdir($cache_dir . '/doctrine_metadata', 0777, true);
        }

        $metadataCache = new FilesystemAdapter('', 0, $cache_dir . '/doctrine_metadata');
        $config->setMetadataCache($metadataCache);

        // database configuration parameters
        $conn = [
            'charset' => 'utf8mb4',
            'driver' => $database_driver,
            // 'path' => $database_path,
            'dbname' => $database_name,
            'user' => $database_user,
            'password' => $database_password,
            'host' => $database_host,
            'port' => $database_port,
        ];

        // obtaining the entity manager
        return $this->entityManager = EntityManager::create($conn, $config);
    }
}