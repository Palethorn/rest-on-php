<?php
namespace ChromecastManagement\Token;

use RestOnPhp\Token\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

class BasicTokenExtractor implements TokenExtractorInterface {

    public function extract(Request $request) {
        if($request->cookies->get('token')) {
            return $request->cookies->get('token');
        }

        if($request->cookies->get('TOKEN')) {
            return $request->cookies->get('TOKEN');
        }

        if($request->query->get('token')) {
            return $request->query->get('token');
        }

        if($request->headers->get('Authorization')) {

            return str_replace('Token ', '', $request->headers->get('Authorization'));
        }

        if($request->headers->get('X-Token')) {
            return $request->headers->get('X-Token');
        }

        return null;
    }
}