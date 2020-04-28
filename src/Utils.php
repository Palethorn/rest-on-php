<?php
namespace RestOnPhp;

class Utils {
    public static function camelize($string) {
        return preg_replace_callback('/_([a-z])/', 
            function($matches) {
                return strtoupper($matches[1]);
            }, $string);
    }

    public static function snake_case($string) {
        $string = lcfirst($string);
        return preg_replace_callback('/[A-Z]/', 
            function($matches) {
                return '_' . strtolower($matches[1]);
            }, $string);
    }

    public static function scandir($dir, $callback, $recursive = false) {
        $files = scandir($dir);
        
        foreach($files as $file) {
            if($file == '.' || $file == '..') {
                continue;
            }

            if(is_dir($dir . DIRECTORY_SEPARATOR . $file) && $recursive) {
                self::scandir($dir . DIRECTORY_SEPARATOR . $file, $callback, $recursive);
            }

            $callback($dir, $file);
        }
    }
}