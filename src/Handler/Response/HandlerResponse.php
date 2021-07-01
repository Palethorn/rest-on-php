<?php

namespace RestOnPhp\Handler\Response;

class HandlerResponse implements HandlerResponseInterface {

    private $cardinality;
    private $data;
    private $pagination;

    public function __construct(int $cardinality, $data, $pagination = null) {
        $this->cardinality = $cardinality;
        $this->data = $data;
        $this->pagination = $pagination;
    }

    function getCardinality() {
        return $this->cardinality;
    }

    function getData() {
        return $this->data;
    }

    function getPagination() {
        return $this->pagination;
    }   
}