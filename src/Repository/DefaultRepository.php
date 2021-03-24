<?php
namespace RestOnPhp\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DefaultRepository extends EntityRepository {

    public function get($filters = [], $pagination = [], $order = [], $single = false) {
        $q = $this->createQueryBuilder('r');

        foreach($order as $field => $direction) {
            $q->orderBy('r.' . $field, $direction);
        }

        foreach($filters['partial'] as $field => $value) {
            $q->andWhere(sprintf('r.%s LIKE :%s', $field, $field));
            $q->setParameter($field, '%' . $value . '%');
        }

        foreach($filters['exact'] as $field => $value) {
            $q->andWhere(sprintf('r.%s = :%s', $field, $field));
            $q->setParameter($field, $value);
        }

        foreach($filters['gt'] as $field => $value) {
            $q->andWhere(sprintf('r.%s > :%s', $field, $field));
            $q->setParameter($field, $value);
        }

        foreach($filters['lt'] as $field => $value) {
            $q->andWhere(sprintf('r.%s < :%s', $field, $field));
            $q->setParameter($field, $value);
        }

        foreach($filters['gte'] as $field => $value) {
            $q->andWhere(sprintf('r.%s >= :%s', $field, $field));
            $q->setParameter($field, $value);
        }

        foreach($filters['lte'] as $field => $value) {
            $q->andWhere(sprintf('r.%s <= :%s', $field, $field));
            $q->setParameter($field, $value);
        }

        foreach($filters['default'] as $filter) {
            $q = $filter->filter($q);
        }

        if($pagination && is_array($pagination) && isset($pagination['page'], $pagination['per_page'])) {
            $q->setMaxResults($pagination['per_page']);
            $q->setFirstResult(($pagination['page'] - 1) * $pagination['per_page']);
        }

        if($single) {
            return $q->getQuery()->getOneOrNullResult();
        }

        return new Paginator($q->getQuery());
    }
}
