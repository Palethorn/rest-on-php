<?php
namespace RestOnPhp\Session;
use RestOnPhp\Security\SecureUser;

class JwtSessionStorage {
    private $user;

    /**
     * @return SecureUser
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @param SecureUser
     */
    public function setUser(SecureUser $user) {
        $this->user = $user;
    }
}
