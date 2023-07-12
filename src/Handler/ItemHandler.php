<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use RestOnPhp\Event\PostDeserializeEvent;
use RestOnPhp\Event\PreDeserializeEvent;
use RestOnPhp\Event\ResourcePostDeleteEvent;
use RestOnPhp\Event\ResourcePreDeleteEvent;
use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Normalizer\RootDenormalizer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ItemHandler implements HandlerInterface {
    private $dispatcher;
    private $request;
    private $filters;
    private $fillers;
    private $metadata;
    private $validator;
    private $repository;
    private $entityManager;
    private $denormalizer;

    public function __construct(
        EventDispatcher $dispatcher, 
        EntityManager $entityManager, 
        ValidatorInterface $validator, 
        XmlMetadata $metadata,
        RequestStack $requestStack,
        RootDenormalizer $denormalizer
    ) {
        $this->filters = [];
        $this->fillers = [];
        $this->metadata = $metadata;
        $this->validator = $validator;
        $this->dispatcher = $dispatcher;
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->denormalizer = $denormalizer;
    }

    public function handle($resource_name, $id = null) {
        $resource_metadata = $this->metadata->getMetadataFor($resource_name);
        $entityClass = $resource_metadata['entity'];
        $entityMetadata = $this->entityManager->getClassMetadata($entityClass);

        if(!$entityMetadata->customRepositoryClassName) {
            $entityMetadata->setCustomRepositoryClass('RestOnPhp\Repository\DefaultRepository');
        }

        $method = $this->request->getMethod();
        $method = strtolower($method);
        $this->repository = $this->entityManager->getRepository($entityClass);
        $result = $this->$method($resource_name, $id);

        if($result instanceof Response) {
            return new HandlerResponse(HandlerResponse::CARDINALITY_NONE, $result, null);
        }

        return new HandlerResponse(HandlerResponse::CARDINALITY_SINGLE, $result, null);
    }

    public function get($resource_name, $id) {
        $id_field = $this->metadata->getIdFieldNameFor($resource_name);
        $data = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ], 
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $this->filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            throw new ResourceNotFoundException("Item not found");
        }

        return $data;
    }

    public function post($resource_name, $id) {
        $resource_metadata = $this->metadata->getMetadataFor($resource_name);
        $this->dispatcher->dispatch(new PreDeserializeEvent($resource_name, $this->request->getContent()), PreDeserializeEvent::NAME);
        $data = json_decode($this->request->getContent(), true);
        $this->dispatcher->dispatch(new PostDeserializeEvent($resource_name, $this->request->getContent(), $data), PostDeserializeEvent::NAME);
        $object = $this->denormalizer->denormalizeItem($data, $resource_metadata);
        $errors = $this->validator->validate($object);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            throw new ValidatorException($message);
        }

        foreach($this->fillers as $filler) {
            $filler->fill($object);
        }

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        return $object;
    }

    public function put($resource_name, $id) {
        $resource_metadata = $this->metadata->getMetadataFor($resource_name);
        $id_field = $this->metadata->getIdFieldNameFor($resource_name);
        $object = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ],
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $this->filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);
        
        if(!$object) {
            throw new ResourceNotFoundException("Item not found");
        }

        $this->dispatcher->dispatch(new PreDeserializeEvent($resource_name, $this->request->getContent()), PreDeserializeEvent::NAME);
        $data = json_decode($this->request->getContent(), true);
        $this->dispatcher->dispatch(new PostDeserializeEvent($resource_name, $this->request->getContent(), $data), PostDeserializeEvent::NAME);
        $this->denormalizer->denormalizeItem($data, $resource_metadata, $object);
        $errors = $this->validator->validate($object);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            throw new ValidatorException($message);
        }

        foreach($this->fillers as $filler) {
            $filler->fill($object);
        }

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        return $object;
    }

    public function delete($resource_name, $id) {
        $id_field = $this->metadata->getIdFieldNameFor($resource_name);
        $data = $this->repository->get([ 
            'partial' => [], 
            'exact' => [ $id_field => $id ],
            'lte' => [],
            'gte' => [],
            'lt' => [],
            'gt' => [],
            'default' => $this->filters
        ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            throw new ResourceNotFoundException("Item not found");
        }

        $this->dispatcher->dispatch(new ResourcePreDeleteEvent($data), ResourcePreDeleteEvent::NAME);
        $this->entityManager->remove($data);
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new ResourcePostDeleteEvent($data), ResourcePostDeleteEvent::NAME);

        return new Response('', 204, []);
    }

    public function patch($resource_name, $id) {
        return $this->put($resource_name, $id);
    }

    public function setFilters($filters) {
        $this->filters = $filters;
    }

    public function setFillers($fillers) {
        $this->fillers = $fillers;
    }
}
