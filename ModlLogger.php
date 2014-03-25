<?php

namespace modl;

class ModlLogger {
    static $_logs = array();
    
    public static function log($message, $priority = '') 
    {
        array_push(self::$_logs, $message);
        \system\Logs\Logger::log($message);
    }
}
