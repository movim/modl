<?php
/**
 * @file Modl.php
 *
 * @brief The main file of Modl
 *
 * Copyright © 2013 Timothée Jaussoin
 *
 * This file is part of Modl.
 *
 * Moxl is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * Moxl is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Datajar.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace modl;

class Modl {
    protected $_db;
    
    protected $_dbtype;
    
    protected $_username;
    protected $_password;
    protected $_host;
    protected $_port;
    protected $_database;
    
    public $_error;
    
    // Boolean to know if we are currently connected
    public $_connected;
    
    // List of Models loaded
    protected $_models = array();
    public $modelspath;
    
    // Keep prepared request to handle transactions
    protected $_keep = array();

    protected $_user;
    
    protected static $_instance;
    
    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new Modl();
        }

        return self::$_instance;
    } 

    function __construct() {
        if(self::$_instance) {
            $inst = self::$_instance;
            $this->_db          = $inst->_db;
            $this->_dbtype      = $inst->_dbtype;
            $this->_username    = $inst->_username;
            $this->_password    = $inst->_password;
            $this->_host        = $inst->_host;
            $this->_port        = $inst->_port;
            $this->_database    = $inst->_database;
            $this->_user        = $inst->_user;
            $this->_keep        = $inst->_keep;
            $this->_error       = $inst->_error;
        }
    }
    
    public function setConnection($connstring) {
        $connection = $this->parseConnectionString($connstring);
        $this->_dbtype      = $connection['dbtype'];
        $this->_username    = $connection['username'];
        $this->_password    = $connection['password'];
        $this->_host        = $connection['host'];
        $this->_port        = $connection['port'];
        $this->_database    = $connection['database'];        
    }
    
    public function setConnectionArray($connection)
    {
        $this->_dbtype      = $connection['dbType'];
        $this->_username    = $connection['dbUsername'];
        $this->_password    = $connection['dbPassword'];
        $this->_host        = $connection['dbHost'];
        $this->_port        = $connection['dbPort'];
        $this->_database    = $connection['dbName'];  
    }

    public function setUser($user) {
        $this->_user = $user;
    }
    
    public function addModel($name) {
        array_push($this->_models, $name);
    }
    
    public function setModelsPath($path) {
        $this->modelspath = $path;
    }
    
    public function check($apply = false) {
        $msdb = new ModlSmartDB();
        return $msdb->check($apply);
    }
    
    public function connect() {
        try {
            $this->_db = new \PDO(
                $this->_dbtype.':host='.$this->_host.';dbname='.$this->_database.';port='.$this->_port, 
                $this->_username, 
                $this->_password, 
                array(
                    \PDO::ATTR_PERSISTENT => true
                )
            );
            
            $this->_connected = true;
        } catch (PDOException $e) {
            $this->_connected = false;
            ModlLogger::log($e->getMessage());
            die();
        }
    }
    
    protected function inject($instance) {
        foreach(get_object_vars(self::$_instance) as $key => $value) {
            if(property_exists($this, $key))
                $this->$key = $value;
        }
    }
    
    public function getSupportedDatabases() {
        return getDBList();
    }
    
    /**
     * Parses a connection string
     */
    protected function parseConnectionString($string)
    {
        // preg_match('%^([^/]+?)://(?:([^/@:]*?)(?::([^/@:]+?))?@([^/@:]+?)(?::([^/@:]+?))?)?/(.+)$%'
        $matches = array();
        preg_match('%^([^/]+?)://(?:([^/@]*?)(?::([^/@:]+?)@)?([^/@:]+?)(?::([^/@:]+?))?)?/(.+)$%',
                   $string, $matches);
        return array('dbtype'   => $matches[1],
                     'username' => $matches[2],
                     'password' => $matches[3],
                     'host'     => $matches[4],
                     'port'     => $matches[5],
                     'database' => $matches[6]);
    }
}
