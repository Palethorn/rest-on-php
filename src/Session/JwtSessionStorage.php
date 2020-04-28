<?php
namespace RestOnPhp\Session;

class JwtSessionStorage {
    private $user;

    public function getUser() {
        return $this->user;
    }

    public function setUser($user) {
        $this->user = $user;
    }
}
