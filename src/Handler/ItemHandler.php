<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ItemHandler {
    private $serializer;
    private $entityManager;
    private $validator;
    private $metadata;
    private $repository;

    public function __construct(Serializer $serializer, EntityManager $entityManager, ValidatorInterface $validator, XmlMetadata $metadata) {
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->metadata = $metadata;
    }

    public function handle($entityClass, Request $request, $id) {
        $method = $request->getMethod();
        $requestBody = $request->getContent();
        $method = strtolower($method);
        $this->repository = $this->entityManager->getRepository($entityClass);
        return $this->$method($entityClass, $id, $requestBody);
    }

    public function get($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            return new Response(
                $this->serializer->serialize(array('message' => 'Not found'), 'json'), 
                404, 
                ['Content-Type' => 'application/json']
            );
        }

        $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
        $normalized = $this->serializer->normalize($data, null, [AbstractNormalizer::ATTRIBUTES => $fields]);

        return new Response(
            $this->serializer->serialize($normalized, 'json'), 200, ['Content-Type' => 'application/json']
        );
    }

    public function post($entityClass, $id, $requestBody) {
        $data = $this->serializer->deserialize($requestBody, $entityClass, 'json');
        $errors = $this->validator->validate($data);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            return new Response($this->serializer->serialize(array('message' => $message), 'json'), 400);
        }

        try {
            $this->entityManager->persist($data);
            $this->entityManager->flush();
        } catch(UniqueConstraintViolationException $exception) {
            return new Response(
                $this->serializer->serialize(array('message' => 'Already exists'), 'json'), 409, ['Content-Type' => 'application/json']
            );
        }

        $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
        $normalized = $this->serializer->normalize($data, null, [AbstractNormalizer::ATTRIBUTES => $fields]);

        return new Response(
            $this->serializer->serialize($normalized, 'json'), 200, ['Content-Type' => 'application/json']
        );
    }

    public function put($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);
        
        if(!$data) {
            return new Response(
                $this->serializer->serialize(array('message' => 'Not found'), 'json'), 404, ['Content-Type' => 'application/json']
            );
        }

        $this->serializer->deserialize($requestBody, $entityClass, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $data]);
        $errors = $this->validator->validate($data);

        if(count($errors) > 0) {
            $message = '';

            foreach($errors as $error) {
                $message .= $error->getMessage();
            }

            return new Response($this->serializer->serialize(array('message' => $message), 'json'), 400);
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
        $normalized = $this->serializer->normalize($data, null, [AbstractNormalizer::ATTRIBUTES => $fields]);

        return new Response(
            $this->serializer->serialize($normalized, 'json'), 200, ['Content-Type' => 'application/json']
        );
    }

    public function delete($entityClass, $id, $requestBody) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $data = $this->repository->get([ 'partial' => [], 'exact' => [ $id_field => $id ] ], [
            'page' => 1,
            'per_page' => 1
        ], [], true);

        if(!$data) {
            return new Response(
                $this->serializer->serialize(array('message' => 'Not found'), 'json'), 404, ['Content-Type' => 'application/json']
            );
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return new Response('', 204);
    }

    public function patch($entityClass, $id, $requestBody) {
        return $this->put($entityClass, $id, $requestBody);
    }
}
