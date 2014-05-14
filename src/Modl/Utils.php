<?php

namespace Modl;

use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\NormalizerFormatter;

class Utils {

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

    public static function log($message, $arr = false, $arr2 = false) 
    {
        if(LOG_LEVEL != null && LOG_LEVEL > 0) {
            $log = new Logger('modl');
            $log->pushHandler(new SyslogHandler('modl'));

            if(LOG_LEVEL > 1)
                $log->pushHandler(new StreamHandler(LOG_PATH.'/sql.log', Logger::DEBUG));
                
            if(is_array($arr) && is_array($arr2))
                $log->addInfo($message, $arr, $arr2);
            elseif(is_array($arr))
                $log->addInfo($message);
            else
                $log->addInfo(serialize($message));
        }
    }
}
