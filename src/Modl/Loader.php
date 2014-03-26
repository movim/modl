<?php

namespace Modl;

/**
 * Modl loader
 * 
 * Use example:
 * 
 *    <?php
 *    require '/path/to/src/Modl/Loader.php';
 *    \Modl\Loader::register();
 *    
 *    use Modl\Modl;
 *    
 *    $db = Modl::getInstance();
 */
class Loader
{
    private static $namespace = 'Modl\\';
    
    /**
     * Registers loader as an SPL autoloader.
     *
     * @param boolean $prepend
     */
    public static function register($prepend = false)
    {
        // Handle new function since PHP 5.3.0
        if (version_compare(phpversion(), '5.3.0', '>=')) {
            spl_autoload_register(array(__CLASS__, 'autoload'), true, $prepend);
        } else {
            spl_autoload_register(array(__CLASS__, 'autoload'));
        }
    }

    /**
     * PSR-4 & PSR-0 autoload method.
     *
     * @param string $className
     *
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
     */
    public static function autoload($className)
    {
        // Not in Modl namespace, go to the next autoloader.
        if (strncmp(self::$namespace, $className, strlen(self::$namespace)) !== 0) {
            return;
        }

        // Remove root namespace.
        $rootLess = substr($className, strlen(self::$namespace));

        // Get the absolute path to the file.
        $file = __DIR__ . DIRECTORY_SEPARATOR;
        $file .= str_replace('\\', DIRECTORY_SEPARATOR, $rootLess) . '.php';

        // Require file if exist, else go to the next autoloader.
        if (file_exists($file)) {
            require $file;
        }
    }
}
