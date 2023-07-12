<?php
declare(strict_types=1);
namespace RestOnPhp\Handler;


interface HandlerInterface {
    function setFilters($filters);
    function setFillers($fillers);
}