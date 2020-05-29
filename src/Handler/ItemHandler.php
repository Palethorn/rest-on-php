<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ItemHandler {
    private $serializer;
    private $entityManager;
    private $validator;
    private $metadata;
    private $repository;

    public function __construct(EntityManager $entityManager, ValidatorInterface $validator, XmlMetadata $metadata) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->metadata = $metadata;
    }

    public function handle($entityClass, Request $request, $id) {
        $method = $request->getMethod();
        $requestBody = $request->getContent();
        $method = strtolower($method);
        $this->repository = $this->entityManager->getRepository($entityClass);
        
        return [ 'item', $this->$method($entityClass, $id, $requestBody) ];
    }

    public function get($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
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

    public function put($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
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

    public function delete($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
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

    public function patch($entityClass, $id, $requestBody) {
        return $this->put($entityClass, $id, $requestBody);
    }
}
