<?php
namespace RestOnPhp\Security;

interface SecureUser {
    /**
     * @param string $role
     * @return bool
     */
    function hasRole($role);

    /**
     * @return bool
     */
    function isSuperAdmin();

    /**
     * @param string $token
     */
    function setToken($token);

    /**
     * @return string
     */
    function getToken();
}