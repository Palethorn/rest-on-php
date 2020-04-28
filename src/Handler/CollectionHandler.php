<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;

class CollectionHandler {
    private $serializer;
    private $entityManager;
    private $metadata;

    public function __construct(Serializer $serializer, EntityManager $entityManager, XmlMetadata $metadata) {
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
    }

    public function handle($entityClass, Request $request) {
        $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);

        $f = $request->query->get('filter') ? $request->query->get('filter') : array();
        $filters = [
            'exact' => [],
            'partial' => []
        ];

        foreach($f as $field => $filter) {
            $field_metadata = $this->metadata->getFieldMetadataFor($entityClass, $field);
            $filter_type = isset($field_metadata['filter-type']) ? $field_metadata['filter-type'] : 'exact';
            $filters[$filter_type][$field] = $filter;
        }

        $pagination = $request->query->get('pagination') ? $request->query->get('pagination') : array(
            'page' => 1,
            'per_page' => 10
        );
        
        $order = $request->query->get('order') ? $request->query->get('order') : array(
            $id_field => 'ASC'
        );

        /**
         * @var \Doctrine\ORM\EntityRepository $entityRepository
         */
        $entityRepository = $this->entityManager->getRepository($entityClass);
        $paginator = $entityRepository->get($filters, $pagination, $order);
        $total_items = count($paginator);
        $normalized = array('items' => $this->serializer->normalize(
            $paginator, 
            null,
            [AbstractNormalizer::ATTRIBUTES => $fields]
        ));

        $route = $this->metadata->getRouteMetadataFor($entityClass, 'getCollection');
        $params = $request->query->all();
        $params['pagination']['page'] = $pagination['page'];
        $params['pagination']['per_page'] = $pagination['per_page'];

        $current_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination['page'] - 1;
        $previous_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination['page'] + 1;
        $next_page = $route['path'] . '?' . http_build_query($params);

        $total_pages = ceil($total_items / $pagination['per_page']);

        $normalized['pagination'] = array(
        );

        if($pagination['page'] - 1 > 0) {
            $normalized['pagination']['previous_page'] = $previous_page;
        }

        $normalized['pagination']['current_page'] = $current_page;

        if($pagination['page'] + 1 <= $total_pages) {
            $normalized['pagination']['next_page'] = $next_page;
        }

        
        $normalized['pagination']['total_pages'] = $total_pages;
        $normalized['pagination']['total_items'] = $total_items;


        return new Response($this->serializer->serialize($normalized, 'json'), 200, ['Content-Type' => 'application/json']);
    }
}
