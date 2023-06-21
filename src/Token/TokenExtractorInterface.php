<?php
namespace RestOnPhp\Token;

use Symfony\Component\HttpFoundation\Request;

interface TokenExtractorInterface {
    function extract(Request $request);
}
