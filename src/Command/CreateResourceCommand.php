<?php
namespace RestOnPhp\Command;

use Doctrine\ORM\EntityManager;
use Exception;
use RuntimeException;
use Symfony\Component\Config\Util\Exception\InvalidXmlException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateResourceCommand extends Command {
    protected static $defaultName = 'api:create-resource';
    private $config_dir, $entityManager;
    private $route_definitions = [
            [
                'name' => 'getCollection',
                'method' => 'GET',
                'path' => '/%s'
            ],
            [
                'name' => 'getItem',
                'method' => 'GET',
                'path' => '/%s/{id}'
            ],
            [
                'name' => 'create',
                'method' => 'POST',
                'path' => '/%s'
            ],
            [
                'name' => 'update',
                'method' => 'PATCH',
                'path' => '/%s/{id}'
            ],
            [
                'name' => 'replace',
                'method' => 'PUT',
                'path' => '/%s/{id}'
            ],
            [
                'name' => 'delete',
                'method' => 'DELETE',
                'path' => '/%s/{id}'
            ]
        ];

    protected function configure() {
        $this->addArgument('entity', InputArgument::REQUIRED, 'Entity from which to create a resource');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $entity = $input->getArgument('entity');
        $metadata = $this->entityManager->getClassMetadata($entity);
        $resources = new \DOMDocument();
        $resources->preserveWhiteSpace = false;
        $resources->formatOutput = true;
        $resources->load($this->config_dir . '/resources.xml');

        if(!@$resources->schemaValidate(__DIR__ . '/../../config/api-resource.xsd')) {
            throw new InvalidXmlException('resources.xml is not a valid XML document.');
        }

        $this->xpath = new \DOMXPath($resources);
        $this->xpath->registerNamespace('ns', 'urn:mapping');
        $resource = $this->xpath->query('//ns:mapping/ns:resource[@entity="' . $entity . '"]');

        if(isset($resource[0])) {
            throw new Exception(sprintf('Resource %s already exists!', $entity));
            return 1;
        }

        $resource_name = $metadata->getTableName() . 's';
        $resource = $resources->createElement('resource');
        $resource->setAttribute('secure', 'false');
        $resource->setAttribute('id', $metadata->getIdentifier()[0]);
        $resource->setAttribute('entity', $entity);
        $resource->setAttribute('name', $resource_name);

        foreach($this->route_definitions as $route_definition) {
            $route_element = $resources->createElement('route');
            $route_element->setAttribute('name', $route_definition['name']);
            $route_element->setAttribute('method', $route_definition['method']);
            $route_element->setAttribute('path', sprintf($route_definition['path'], $resource_name));
            $resource->appendChild($route_element);
        }

        foreach($metadata->fieldMappings as $fieldMapping) {
            $field = $resources->createElement('field');
            $field->setAttribute('name', $fieldMapping['fieldName']);
            $field->setAttribute('type', $fieldMapping['type']);
            $resource->appendChild($field);
        }

        $resources->firstChild->appendChild($resource);
        $resources->save($this->config_dir . '/resources.xml');
        return 0;
    }

    public function setConfigDir($config_dir) {
        $this->config_dir = $config_dir;
    }

    public function setEntityManager(EntityManager $entityManager) {
        $this->entityManager = $entityManager;
    }
}