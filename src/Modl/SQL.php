<?php
/**
 * @file ModlSQL.php
 *
 * @brief The SQL connector of Modl
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

namespace Modl;

class SQL extends Modl {
    protected $_sql = '';
    private $_resultset;
    private $_params;
    private $_warnings = array();
    protected $_effective;
    
    function __construct() {
        parent::inject($this);
    }

    protected function transaction() {
        $this->_db->beginTransaction();
    }

    protected function commit() {
        $this->_db->commit();
    }
    
    public function prepare($classname = null, $params = false) {
        if($this->_connected) {
            $this->_resultset = $this->_db->prepare($this->_sql);
            
            if(isset($classname) && class_exists('modl\\'.$classname)) {
                $classname = 'modl\\'.$classname;
                $class = new $classname;
                
                if(isset($params)&& is_array($params)) {
                    $this->_params = $params;
                    
                    $struct = $class->_struct;

                    if(isset($struct))
                        foreach($params as $key => $value) {
                            if(isset($struct->$key)) {
                                $caract = $struct->$key;
                                
                                if(isset($caract->mandatory) 
                                && $caract->mandatory == true 
                                && !isset($value))
                                    array_push($this->_warnings, $key.' is not set');

                                switch($caract->type) {
                                    case 'string' :
                                        $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_STR);    
                                    break;
                                    case 'date' :
                                        $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_STR);      
                                    break;
                                    case 'int' :
                                        $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_INT);    
                                    break;
                                    case 'bool' :
                                        $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_BOOL);    
                                    break;
                                    default :
                                        $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_STR);  
                                    break;
                                }
                            } else {
                                // Call the logger here
                                array_push($this->_warnings, $classname.' attribute '.$key.' not found');
                            }
                        }
                }
            }
        } else {
            array_push($this->_warnings, 'Database not ready');
        }
    }
    
    public function run($classname = null, $type = 'list') {
        if(empty($this->_warnings))
            $this->_resultset->execute();
        else {
            Utils::log($this->_warnings);
        }

        $this->_warnings = array();

        if($this->_resultset != null) {
            $errors = $this->_resultset->errorInfo();
            if($errors[0] != '000000') {
                Utils::log(trim($this->_sql), $this->_params, $errors);

                Utils::log($errors[1]);
                Utils::log($errors[2]);
            }
            
            if($this->_resultset->rowCount() == 0)
                $this->_effective = false;
            else
                $this->_effective = true;
                        
            $ns_classname = 'modl\\'.$classname;
                        
            if(isset($classname) && class_exists($ns_classname) && $this->_resultset != null) {            
                $results = array();
                
                while($row = $this->_resultset->fetch(\PDO::FETCH_NAMED)) {

                    $obj = new $ns_classname;
                    foreach($row as $key => $value) {
                        if(isset($value)) {
                            if(is_array($value)) {
                                $value = $value[0];
                            }
                            
                            if(property_exists($obj, $key))
                                $obj->$key = $value;
                        }
                    }
                    
                    array_push($results, $obj);
                }
                
                $i = 0;
                $empty = new $ns_classname;
                foreach($results as $obj) {
                    if($obj == $empty)
                        unset($results[$i]);
                    $i++;
                }
                
                if(empty($results))
                    return null;
                else {
                    foreach($results as $obj)
                        $obj->clean();
                    if($type == 'list')    
                        return $results;
                    elseif($type == 'item')
                        return $results[0];
                }
            } elseif($type = 'array' && $this->_resultset != null) {
                $results = $this->_resultset->fetchAll(\PDO::FETCH_ASSOC);
                return $results;
            } else 
                return null;
        } else
            return null;
    }
}
