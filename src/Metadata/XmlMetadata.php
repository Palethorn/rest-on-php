<?php
namespace RestOnPhp\Metadata;

use Exception;
use RuntimeException;
use Symfony\Component\Config\Util\Exception\InvalidXmlException;
use Symfony\Component\Finder\Finder;
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

    private function loadResource($metadata_file) {
        $this->metadata = new \DOMDocument();
        $this->metadata->preserveWhiteSpace = false;
        $this->metadata->load($metadata_file);
        
        if(!@$this->metadata->schemaValidate(__DIR__ . '/../../config/api-resource.xsd')) {
            throw new InvalidXmlException($metadata_file . ' is not a valid XML document.');
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
            $roles = $roles ? explode('|', $roles) : [];
            $secure = $secure == 'true' ? true : false;
            $normalizer = $resource->getAttribute('normalizer') ? $resource->getAttribute('normalizer') : null;
            $denormalizer = $resource->getAttribute('denormalizer') ? $resource->getAttribute('denormalizer') : null;
            $routes = [];
            $fields = [];
            $filters = [];
            $fillers = [];

            foreach($resource->getElementsByTagName('route') as $route_element) {
                $route_name = $route_element->getAttribute('name');
                $route_http_method = $route_element->getAttribute('http_method');
                $route_handler = $route_element->getAttribute('handler');
                $route_method = $route_element->getAttribute('method');
                $route_path = $route_element->getAttribute('path');

                $routes[$route_name] = [
                    'name' => $route_name,
                    'http_method' => $route_http_method,
                    'handler' => $route_handler,
                    'method' => $route_method,
                    'path' => $route_path,
                ];
            }

            foreach($resource->getElementsByTagName('field') as $field_element) {
                $field_name = $field_element->getAttribute('name');
                $field_type = $field_element->getAttribute('type');
                $field_id = $field_element->getAttribute('id');
                $field_normalizer = $field_element->getAttribute('normalizer') ? $field_element->getAttribute('normalizer') : null;
                $field_denormalizer = $field_element->getAttribute('denormalizer') ? $field_element->getAttribute('denormalizer') : null;
                $field_id = $field_id && $field_id == 'true' ? true : false;
                $field_filter_type = $field_element->getAttribute('filter-type');
                $field_filter_type = $field_filter_type ? $field_filter_type : 'exact';

                $fields[$field_name] = [
                    'name' => $field_name,
                    'type' => $field_type,
                    'filter-type' => $field_filter_type,
                    'normalizer' => $field_normalizer,
                    'denormalizer' => $field_denormalizer
                ];
            }

            foreach($resource->getElementsByTagName('filter') as $field_element) {
                $filters[] = $field_element->getAttribute('id');
            }

            foreach($resource->getElementsByTagName('filler') as $field_element) {
                $fillers[] = $field_element->getAttribute('id');
            }

            $parsed[$name] = [
                'name' => $name,
                'entity' => $entity,
                'id' => $id,
                'secure' => $secure,
                'roles' => $roles,
                'normalizer' => $normalizer,
                'denormalizer' => $denormalizer,
                'routes' => $routes,
                'fields' => $fields,
                'filters' => $filters,
                'fillers' => $fillers
            ];
        }

        return $parsed;
    }

    private function load($metadata_file) {
        $parsed = [];

        if(is_dir($metadata_file)) {
            $finder = new Finder();

            $finder->in($metadata_file)
                ->name('*.xml')
                ->files();

            foreach($finder as $resource_file) {
                $p = $this->loadResource($resource_file->getRealPath());
                $parsed = array_merge($parsed, $p);
            }
        } else {
            $parsed = $this->loadResource($metadata_file);
        }

        return $parsed;
    }

    public function getNormalizerFieldsFor(string $name) {
        $resource = null;
        $fields = [];

        $resource = $this->getMetadataFor($name);

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

            $docs[] = [
                'name' => $resource['name'],
                'entity' => $resource['entity'],
                'id' => $resource['id'],
                'fields' => $fields,
                'routes' => $routes
            ];
        }

        return $docs;
    }

    public function getMetadataFor($name) {
        if(!isset($this->metadata[$name])) {
            throw new ResourceNotFoundException(sprintf('Resource %s doesn\'t exist.', $name));
        }

        return $this->metadata[$name];
    }

    public function getMetadataForEntity($entityClass) {
        foreach($this->metadata as $resource_metadata) {
            if($entityClass == $resource_metadata['entity']) {
                return $resource_metadata;
            }
        }
        
        throw new ResourceNotFoundException(sprintf('Resource entity class %s doesn\'t exist.', $entityClass));
    }

    public function getFieldMetadataFor($name, $field_name) {
        $resource = $this->getMetadataFor($name);
        return isset($resource['fields'][$field_name]) ? $resource['fields'][$field_name] : null;
    }

    public function getFieldMetadataForEntity($entity_class, $field_name) {
        $resource = $this->getMetadataForEntity($entity_class);

        if(!isset($resource['fields'][$field_name])) {
            throw new RuntimeException(sprintf('Field metadata for %s does not exist.', $field_name));
        }

        return isset($resource['fields'][$field_name]) ? $resource['fields'][$field_name] : null;
    }

    public function getRouteMetadataFor($name, $route_name) {
        $resource = $this->getMetadataFor($name);
        return $resource['routes'][$route_name];
    }

    public function getRouteMetadata() {
        $routes = [];

        foreach($this->metadata as $resource) {
            foreach($resource['routes'] as $route) {
                $routes[] = [
                    'resource' => $resource['name'],
                    'name' => $resource['name'] . '_' . $route['name'],
                    'path' => $route['path'],
                    'method' => $route['method'],
                    'http_method' => $route['http_method'],
                    'handler' => isset($route['handler']) ? $route['handler'] : null,
                ];
            }
        }

        return $routes;
    }

    public function getIdFieldNameFor($name) {
        $resource = $this->getMetadataFor($name);
        return $resource['id'];
    }

    public function getIdFieldNameForEntity($entityClass) {
        $resource = $this->getMetadataForEntity($entityClass);
        return $resource['id'];
    }

    public function getFilterMetadataFor($name) {
        $resource = $this->getMetadataFor($name);
        return $resource['filters'];
    }

    public function getFillerMetadataFor($name) {
        $resource = $this->getMetadataFor($name);
        return $resource['fillers'];
    }
}
