<?php

//http://www.leaseweblabs.com/2014/04/psr-0-psr-4-autoloading-classes-php/
class Loader
{
    protected static $parentPath = null;
    protected static $paths = null;
    protected static $files = null;
    protected static $nsChar = '\\';
    protected static $initialized = false;
    
    protected static function initialize()
    {
        if (static::$initialized) return;
        static::$initialized = true;
        static::$parentPath = __FILE__;
        for ($i=substr_count(get_class(), static::$nsChar);$i>=0;$i--) {
            static::$parentPath = dirname(static::$parentPath);
        }
        static::$paths = array();
        static::$files = array(__FILE__);
    }
    
    public static function register($path,$namespace) {
        if (!static::$initialized) static::initialize();
        static::$paths[$namespace] = trim($path,DIRECTORY_SEPARATOR);
    }
    
    public static function load($class) {
        if (class_exists($class,false)) return;
        if (!static::$initialized) static::initialize();
        
        foreach (static::$paths as $namespace => $path) {
            if (!$namespace || $namespace.static::$nsChar === substr($class, 0, strlen($namespace.static::$nsChar))) {
                
                $fileName = substr($class,strlen($namespace.static::$nsChar)-1);
                $fileName = str_replace(static::$nsChar, DIRECTORY_SEPARATOR, ltrim($fileName,static::$nsChar));
                $fileName = static::$parentPath.DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.$fileName.'.php';
                
                if (file_exists($fileName)) {
                    include $fileName;
                    return true;
                }
            }
        }
        return false;
    }
}

spl_autoload_register(array('Loader', 'load'));