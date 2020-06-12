<?php
namespace RestOnPhp\Handler;

use Symfony\Component\HttpFoundation\RequestStack;

class ClosureHandler {
    private $closure;
    private $requestStack;
    
    public function __construct(\Closure $closure, RequestStack $requestStack) {
        $this->closure = $closure;
        $this->requestStack = $requestStack;
    }

    public function handle() {
        return ($this->closure)($this->requestStack->getCurrentRequest());
    }
}