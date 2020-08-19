<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ItemHandler {
    private $request;
    private $filters;
    private $autofillers;
    private $metadata;
    private $validator;
    private $serializer;
    private $repository;
    private $entityManager;

    public function __construct(
        Serializer $serializer, 
        EntityManager $entityManager, 
        ValidatorInterface $validator, 
        XmlMetadata $metadata, 
        $default_filters = [], 
        $autofillers = [], 
        RequestStack $requestStack
    ) {

        $this->filters = [];
        $this->autofillers = [];
        $this->metadata = $metadata;
        $this->validator = $validator;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();

        foreach($default_filters as $filter) {
            $this->filters[get_class($filter)] = $filter;
        }

        foreach($autofillers as $autofiller) {
            $this->autofillers[get_class($autofiller)] = $autofiller;
        }
    }

    public function handle($entityClass, $id = null) {
        $method = $this->request->getMethod();
        $method = strtolower($method);
        $this->repository = $this->entityManager->getRepository($entityClass);

        $filterMetadata = $this->metadata->getFilterMetadataFor($entityClass);
        $default_filters = [];

        foreach ($filterMetadata as $filterClass) {
            $default_filters[] = $this->filters[$filterClass];
        }

        $autofillerMetadata = $this->metadata->getAutofillerMetadataFor($entityClass);
        $default_autofillers = [];

        foreach ($autofillerMetadata as $autofillerClass) {
            $default_autofillers[] = $this->autofillers[$autofillerClass];
        }

        return [ 'item', $this->$method($entityClass, $id, $default_filters, $default_autofillers) ];
    }

    public function get($entityClass, $id, $default_filters) {
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

    public function post($entityClass, $id, $filters, $autofillers) {
        $data = $this->serializer->deserialize($this->request->getContent(), $entityClass, 'json');
        $errors = $this->validator->validate($data);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            throw new ValidatorException($message);
        }

        foreach($autofillers as $autofiller) {
            $autofiller->fill($data);
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function put($entityClass, $id, $default_filters) {
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

        $this->serializer->deserialize($this->request->getContent(), $entityClass, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $data]);
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

    public function delete($entityClass, $id, $default_filters) {
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

    public function patch($entityClass, $id, $default_filters) {
        return $this->put($entityClass, $id, $default_filters);
    }
}
