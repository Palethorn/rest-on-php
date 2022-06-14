<?php
namespace RestOnPhp\Handler\Response;

interface HandlerResponseInterface {
    const CARDINALITY_COLLECTION = 0;
    const CARDINALITY_SINGLE = 1;
    const CARDINALITY_NONE = 2;

    function __construct(int $cardinality, $data, $pagination = null);
    function getCardinality();
    /**
     * @return Response
     */
    function getData();
    function getPagination();
}