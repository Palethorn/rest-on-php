<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Doctrine\ORM\EntityManager;
use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Repository\DefaultRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CollectionHandler {
    private $autofilters;
    private $request;
    private $metadata;
    private $entityManager;
    private $requestStack;

    public function __construct(EntityManager $entityManager, XmlMetadata $metadata, $default_autofilters = [], RequestStack $requestStack) {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
        $this->autofilters = [];

        foreach($default_autofilters as $autofilter) {
            $this->autofilters[get_class($autofilter)] = $autofilter;
        }

        $this->requestStack = $requestStack;
    }

    public function handle($resource_name) {
        $this->request = $this->requestStack->getCurrentRequest();

        $metadata = $this->metadata->getMetadataFor($resource_name);
        $id_field = $this->metadata->getIdFieldNameFor($resource_name);
        $filterMetadata = $this->metadata->getAutofilterMetadataFor($resource_name);
        $default_autofilters = [];

        foreach ($filterMetadata as $filterClass) {
            $default_autofilters[] = $this->autofilters[$filterClass];
        }

        $f = $this->request->query->get('filter') ? $this->request->query->get('filter') : [];

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
            if(in_array($field, ['gt', 'lt', 'gte', 'lte', 'default'])) {
                $filters[$field] = $filter;
                continue;
            }

            $field_metadata = $this->metadata->getFieldMetadataFor($resource_name, $field);
            $filter_type = isset($field_metadata['filter-type']) ? $field_metadata['filter-type'] : 'exact';
            $filters[$filter_type][$field] = $filter;
        }

        $pagination_parameters = $this->request->query->get('pagination');
        
        if($pagination_parameters == null) {
            $pagination_parameters = [
                'page' => 1,
                'per_page' => 10
            ];
        } else {
            if(!isset($pagination_parameters['page'])) {
                $pagination_parameters['page'] = 1;
            }

            if(!isset($pagination_parameters['per_page'])) {
                $pagination_parameters['per_page'] = 10;
            }
        }

        if($pagination_parameters == '0' || $pagination_parameters == 'false') {
            $pagination_parameters = false;
        }

        $order = $this->request->query->get('order') ? $this->request->query->get('order') : [
            $id_field => 'ASC'
        ];

        $entityMetadata = $this->entityManager->getClassMetadata($metadata['entity']);

        if(!$entityMetadata->customRepositoryClassName) {
            $entityMetadata->setCustomRepositoryClass(DefaultRepository::class);
        }

        /**
         * @var \Doctrine\ORM\EntityRepository $entityRepository
         */
        $entityRepository = $this->entityManager->getRepository($metadata['entity']);
        $paginator = $entityRepository->get($filters, $pagination_parameters, $order);
        $pagination = $this->pagination($resource_name, $paginator, $pagination_parameters);
        return new HandlerResponse(HandlerResponse::CARDINALITY_COLLECTION, $paginator, $pagination);
    }

    private function pagination($resource_name, $paginator, $pagination_parameters) {

        if($pagination_parameters == false) {
            return [];
        }

        $total_items = count($paginator);
        $route = $this->metadata->getRouteMetadataFor($resource_name, 'getCollection');
        $params = $this->request->query->all();
        $params['pagination']['page'] = $pagination_parameters['page'];
        $params['pagination']['per_page'] = $pagination_parameters['per_page'];

        $current_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination_parameters['page'] - 1;
        $previous_page = $route['path'] . '?' . http_build_query($params);
        $params['pagination']['page'] = $pagination_parameters['page'] + 1;
        $next_page = $route['path'] . '?' . http_build_query($params);

        $total_pages = ceil($total_items / $pagination_parameters['per_page']);

        $pagination = [
        ];

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
