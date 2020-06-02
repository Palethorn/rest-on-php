<?php
namespace RestOnPhp\Security;

interface SecureUser {
    function hasRole($role);
    function isSuperAdmin();
}