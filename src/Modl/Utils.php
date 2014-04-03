<?php

namespace Modl;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Utils {

    /**
     * @var \Monolog\Logger
     */
    private static $logger;

    static function getDBList() {
        $dblist = array(
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL'
            );
        return $dblist;
    }

    static function loadModel($name) {
        try {
            $db = Modl::getInstance();
            $base = $db->modelspath.'/';
            
            $datafolder = $base.strtolower($name).'/';
            require_once($datafolder.$name.'.php');
            require_once($datafolder.$name.'DAO.php');

            $db->addModel($name);
        } catch(Exception $e) {
            echo 'Error importing new data : '.$e->getMessage();
        }
    }

    /**
     * Add a log message to logger, with a level. You can had some data context like vars value, etc...
     *
     * @param $message string
     * @param $level integer
     * @param $context array 
     */
    public static function log($message, $level = null, array $context = array()) 
    {
        // Init logger if no one set.
        if (! self::$logger) {
            self::initLogger();
        }

        // Set default level if needed.
        if (! $level) {
            $level = Logger::INFO;
        }

        // Add a new log record
        self::$logger->addRecord($level, $message, $context);
    }

    /**
     * Init logger with some handlers.
     * Have to be call before use modl.
     *
     * @param $handlers array An array of \Monolog\Handler\HandlerInterface.
     * @throw \Exception if logger already init.
     */
    public static function initLogger(array $handlers = array())
    {
        if (! self::$logger) {
            self::$logger = new Logger('modl');

            // If no handler given, create a default one.
            if (empty($handlers)) {
                // If log_path is defined, use it, else try to write in a default file in root app directory.
                if (defined('LOG_PATH')) {
                    $file = LOG_PATH;
                } else {
                    $file = __DIR__ . '/../../../modl.log';
                }

                // Set default log file in vendor's parent directory.
                self::$logger->pushHandler(new StreamHandler($file, Logger::INFO));
            } else {
                foreach ($handlers as $handler) {
                    self::$logger->pushHandler(handler);
                }
            }
        } else {
            throw new \Exception("Modl logger already init.");
        }
    }
}
