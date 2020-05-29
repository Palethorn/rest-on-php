<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class CollectionHandler {
    private $entityManager;
    private $metadata;

    public function __construct(EntityManager $entityManager, XmlMetadata $metadata) {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
    }

    public function handle($entityClass, Request $request) {
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

        $pagination_parameters = $request->query->get('pagination') ? $request->query->get('pagination') : array(
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
        $paginator = $entityRepository->get($filters, $pagination_parameters, $order);
        $pagination = $this->pagination($entityClass, $paginator, $pagination_parameters, $request);
        return [ 'collection', $paginator, $pagination ];
    }

    private function pagination($entityClass, $paginator, $pagination_parameters, $request) {

        $total_items = count($paginator);
        $route = $this->metadata->getRouteMetadataFor($entityClass, 'getCollection');
        $params = $request->query->all();
        $params['pagination']['page'] = $pagination_parameters['page'];
        $params['pagination']['per_page'] = $pagination_parameters['per_page'];

        $current_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination_parameters['page'] - 1;
        $previous_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination_parameters['page'] + 1;
        $next_page = $route['path'] . '?' . http_build_query($params);

        $total_pages = ceil($total_items / $pagination_parameters['per_page']);

        $pagination = array(
        );

        if($pagination_parameters['page'] - 1 > 0) {
            $pagination['previous_page'] = $previous_page;
        }

        $pagination['current_page'] = $current_page;

        if($pagination_parameters['page'] + 1 <= $total_pages) {
            $pagination['next_page'] = $next_page;
        }

        
        $pagination['total_pages'] = $total_pages;
        $pagination['total_items'] = $total_items;
        return $pagination;
    }
}
