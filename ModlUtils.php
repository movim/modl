<?php

namespace modl;

function getDBList() {
    $dblist = array(
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL'
        );
    return $dblist;
}

function loadModel($name) {
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
