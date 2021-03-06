<?php
namespace RestOnPhp\Metadata;

use Symfony\Component\Config\Util\Exception\InvalidXmlException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class XmlMetadata {
    private $metadata;
    private $xpath;

    public function __construct(string $metadata_file, string $cache_dir = null) {
        if(!file_exists($cache_dir . '/resources.xml.php')) {
            $this->metadata = $this->load($metadata_file);

            if($cache_dir) {
                file_put_contents($cache_dir . '/resources.xml.php', sprintf("<?php\nreturn %s;\n", var_export($this->metadata, true)));
            }
        } else {
            $this->metadata = require_once($cache_dir . '/resources.xml.php');
        }
    }

    private function load($metadata_file) {
        $this->metadata = new \DOMDocument();
        $this->metadata->preserveWhiteSpace = false;
        $this->metadata->load($metadata_file);
        
        if(!@$this->metadata->schemaValidate(__DIR__ . '/../../config/api-resource.xsd')) {
            throw new InvalidXmlException('resources.xml is not a valid XML document.');
        }

        $this->xpath = new \DOMXPath($this->metadata);
        $this->xpath->registerNamespace('ns', 'urn:mapping');

        $parsed = [];
        $resources = $this->xpath->query('//ns:mapping/ns:resource');
        
        foreach($resources as $resource) {
            $name = $resource->getAttribute('name');
            $entity = $resource->getAttribute('entity');
            $id = $resource->getAttribute('id');
            $secure = $resource->getAttribute('secure');
            $roles = $resource->getAttribute('roles');
            $roles = $roles ? explode('|', $roles) : array();
            $secure = $secure == 'true' ? true : false;

            $routes = [];
            $fields = [];
            $autofilters = [];
            $autofillers = [];

            foreach($resource->getElementsByTagName('route') as $route_element) {
                $route_name = $route_element->getAttribute('name');
                $route_method = $route_element->getAttribute('method');
                $route_path = $route_element->getAttribute('path');

                $routes[$route_name] = array(
                    'name' => $route_name,
                    'method' => $route_method,
                    'path' => $route_path
                );
            }

            foreach($resource->getElementsByTagName('field') as $field_element) {
                $field_name = $field_element->getAttribute('name');
                $field_type = $field_element->getAttribute('type');
                $field_id = $field_element->getAttribute('id');
                $field_normalizer = $field_element->getAttribute('normalizer');
                $field_id = $field_id && $field_id == 'true' ? true : false;
                $field_filter_type = $field_element->getAttribute('filter-type');
                $field_filter_type = $field_filter_type ? $field_filter_type : 'exact';

                $fields[$field_name] = array(
                    'name' => $field_name,
                    'type' => $field_type,
                    'filter-type' => $field_filter_type,
                    'normalizer' => $field_normalizer
                );
            }

            foreach($resource->getElementsByTagName('autofilter') as $field_element) {
                $autofilters[] = $field_element->getAttribute('class');
            }

            foreach($resource->getElementsByTagName('autofiller') as $field_element) {
                $autofillers[] = $field_element->getAttribute('class');
            }

            $parsed[$entity] = array(
                'name' => $name,
                'entity' => $entity,
                'id' => $id,
                'secure' => $secure,
                'roles' => $roles,
                'routes' => $routes,
                'fields' => $fields,
                'autofilters' => $autofilters,
                'autofillers' => $autofillers
            );
        }

        return $parsed;
    }

    public function getNormalizerFieldsFor(string $entityClass) {
        $resource = null;
        $fields = array();

        $resource = $this->getMetadataFor($entityClass);

        foreach($resource['fields'] as $field) {
            $fields[] = $field['name'];
        }

        return $fields;
    }

    public function getMetadataForDocs() {
        $docs = [];

        foreach($this->metadata as $resource) {
            $routes = [];
            $fields = [];

            foreach($resource['routes'] as $route) {
                $routes[] = $route;
            }

            foreach($resource['fields'] as $field) {
                $fields[] = $field;
            }

            $docs[] = array(
                'name' => $resource['name'],
                'entity' => $resource['entity'],
                'id' => $resource['id'],
                'fields' => $fields,
                'routes' => $routes
            );
        }

        return $docs;
    }

    public function getMetadataFor($entityClass) {
        $entityClass = str_replace('Proxy\\__CG__\\', '', $entityClass);

        if(!isset($this->metadata[$entityClass])) {
            throw new ResourceNotFoundException(sprintf('Resource of type %s doesn\'t exist.', $entityClass));
        }

        return $this->metadata[$entityClass];
    }

    public function getFieldMetadataFor($entityClass, $field_name) {
        $resource = $this->getMetadataFor($entityClass);
        return $resource['fields'][$field_name];
    }

    public function getRouteMetadataFor($entityClass, $route_name) {
        $resource = $this->getMetadataFor($entityClass);
        return $resource['routes'][$route_name];
    }

    public function getIdFieldNameFor($entityClass) {
        $resource = $this->getMetadataFor($entityClass);
        return $resource['id'];
    }

    public function getAutofilterMetadataFor($entityClass) {
        $resource = $this->getMetadataFor($entityClass);
        return $resource['autofilters'];
    }

    public function getAutofillerMetadataFor($entityClass) {
        $resource = $this->getMetadataFor($entityClass);
        return $resource['autofillers'];
    }
}
