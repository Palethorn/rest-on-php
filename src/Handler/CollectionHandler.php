<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RequestStack;

class CollectionHandler {
    private $autofilters;
    private $request;
    private $metadata;
    private $entityManager;

    public function __construct(EntityManager $entityManager, XmlMetadata $metadata, $default_autofilters = [], RequestStack $requestStack) {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
        $this->autofilters = [];

        foreach($default_autofilters as $autofilter) {
            $this->autofilters[get_class($autofilter)] = $autofilter;
        }

        $this->request = $requestStack->getCurrentRequest();
    }

    public function handle($entityClass) {
        $id_field = $this->metadata->getIdFieldNameFor($entityClass);
        $filterMetadata = $this->metadata->getAutofilterMetadataFor($entityClass);
        $default_autofilters = [];

        foreach ($filterMetadata as $filterClass) {
            $default_autofilters[] = $this->autofilters[$filterClass];
        }

        $f = $this->request->query->get('filter') ? $this->request->query->get('filter') : array();

        $filters = [
            'exact' => [],
            'partial' => [],
            'lt' => [],
            'gt' => [],
            'lte' => [],
            'gte' => [],
            'default' => $default_autofilters
        ];

        foreach($f as $field => $filter) {
            if(in_array($field, array('gt', 'lt', 'gte', 'lte', 'default'))) {
                $filters[$field] = $filter;
                continue;
            }

            $field_metadata = $this->metadata->getFieldMetadataFor($entityClass, $field);
            $filter_type = isset($field_metadata['filter-type']) ? $field_metadata['filter-type'] : 'exact';
            $filters[$filter_type][$field] = $filter;
        }

        $pagination_parameters = $this->request->query->get('pagination');
        
        if($pagination_parameters == null) {
            $pagination_parameters = [
                'page' => 1,
                'per_page' => 10
            ];
        }

        if($pagination_parameters == '0' || $pagination_parameters == 'false') {
            $pagination_parameters = false;
        }

        $order = $this->request->query->get('order') ? $this->request->query->get('order') : array(
            $id_field => 'ASC'
        );

        /**
         * @var \Doctrine\ORM\EntityRepository $entityRepository
         */
        $entityRepository = $this->entityManager->getRepository($entityClass);
        $paginator = $entityRepository->get($filters, $pagination_parameters, $order);
        $pagination = $this->pagination($entityClass, $paginator, $pagination_parameters);
        return [ 'collection', $paginator, $pagination ];
    }

    private function pagination($entityClass, $paginator, $pagination_parameters) {

        if($pagination_parameters == false) {
            return [];
        }

        $total_items = count($paginator);
        $route = $this->metadata->getRouteMetadataFor($entityClass, 'getCollection');
        $params = $this->request->query->all();
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
