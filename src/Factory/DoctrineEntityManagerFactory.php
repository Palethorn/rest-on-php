<?php
namespace RestOnPhp\Factory;

use Doctrine;
use Doctrine\ORM\EntityManager;

class DoctrineEntityManagerFactory {
    public function create(
        $database_host,
        $database_port,
        $database_name,
        $database_user,
        $database_password,
        $config_dir,
        $cache_dir,
        $namespace
    ) {
        $config = new \Doctrine\ORM\Configuration();
        $config->setProxyDir($cache_dir . '/doctrine_proxies');
        $config->setProxyNamespace('Proxy');

        // TODO: Entity namespace as parameter
        $namespaces = array(
            $config_dir . '/doctrine_mapping' => $namespace
        );
        $driver = new \Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver($namespaces);
        $config->setMetadataDriverImpl($driver);

        if(!is_dir($cache_dir . '/doctrine_metadata')) {
            mkdir($cache_dir . '/doctrine_metadata', 0777, true);
        }

        $config->setMetadataCacheImpl(new Doctrine\Common\Cache\FilesystemCache($cache_dir . '/doctrine_metadata'));

        // database configuration parameters
        $conn = array(
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'dbname' => $database_name,
            'user' => $database_user,
            'password' => $database_password,
            'host' => $database_host,
            'port' => $database_port,
        );

        // obtaining the entity manager
        return EntityManager::create($conn, $config);
    }
}