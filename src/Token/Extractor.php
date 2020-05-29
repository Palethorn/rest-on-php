<?php
namespace RestOnPhp\Token;

use Symfony\Component\HttpFoundation\Request;

class Extractor {
    private $bearer;
    private $name;

    public function __construct(string $bearer, string $name) {
        $this->bearer = $bearer;
        $this->name = $name;
    }

    public function extract(Request $request) {
        if($this->bearer == 'cookie') {
            return $request->cookies->get($this->name);
        }

        if($this->bearer == 'query_parameter') {
            return $request->query->get($this->name);
        }

        if($this->bearer == 'header') {
            return $request->headers->get($this->name);
        }

        return null;
    }
}
