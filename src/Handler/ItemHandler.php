<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ItemHandler {
    private $serializer;
    private $entityManager;
    private $validator;
    private $metadata;
    private $repository;
    private $filters;

    public function __construct(Serializer $serializer, EntityManager $entityManager, ValidatorInterface $validator, XmlMetadata $metadata, $default_filters = []) {
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->metadata = $metadata;
        $this->filters = [];

        foreach($default_filters as $filter) {
            $this->filters[get_class($filter)] = $filter;
        }
    }

    public function handle($entityClass, Request $request, $id) {
        $method = $request->getMethod();
        $requestBody = $request->getContent();
        $method = strtolower($method);
        $this->repository = $this->entityManager->getRepository($entityClass);

        $filterMetadata = $this->metadata->getFilterMetadataFor($entityClass);
        $default_filters = [];

        foreach ($filterMetadata as $filterClass) {
            $default_filters[] = $this->filters[$filterClass];
        }

        return [ 'item', $this->$method($entityClass, $id, $requestBody, $default_filters) ];
    }

    public function get($entityClass, $id, $requestBody, $default_filters) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ], 
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $default_filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            throw new ResourceNotFoundException("Item not found");
        }

        return $data;
    }

    public function post($entityClass, $id, $requestBody) {
        $data = $this->serializer->deserialize($requestBody, $entityClass, 'json');
        $errors = $this->validator->validate($data);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            throw new ValidatorException($message);
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function put($entityClass, $id, $requestBody, $default_filters) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ],
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $default_filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);
        
        if(!$data) {
            throw new ResourceNotFoundException("Item not found");
        }

        $this->serializer->deserialize($requestBody, $entityClass, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $data]);
        $errors = $this->validator->validate($data);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            throw new ValidatorException($message);
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function delete($entityClass, $id, $requestBody, $default_filters) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ],
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $default_filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            throw new ResourceNotFoundException("Item not found");
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return '';
    }

    public function patch($entityClass, $id, $requestBody, $default_filters) {
        return $this->put($entityClass, $id, $requestBody, $default_filters);
    }
}
