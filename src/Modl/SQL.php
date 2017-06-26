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

class SQL extends Modl
{
    const SQL_DATE = 'Y-m-d H:i:s';

    protected $_sql = '';
    private $_resultset;
    private $_params;
    private $_warnings = [];
    protected $_effective;

    function __construct()
    {
        parent::inject();
    }

    protected function transaction()
    {
        $this->_db->beginTransaction();
    }

    protected function commit()
    {
        $this->_db->commit();
    }

    public function prepare($mainclassname = null, $params = false)
    {
        if($this->_connected) {
            $this->_resultset = $this->_db->prepare($this->_sql);

            if(!$params) return;

            $this->_params = $params;

            // No mainclassname defined, try the default one
            if($mainclassname == null) {
                if(substr(get_class($this), -3, 3) == 'DAO') {
                    // We strip Modl/ and DAO from the classname
                    $mainclassname = substr(get_class($this), 0, -3);
                } else {
                    array_push($this->_warnings, 'A model needs to be set');
                    return;
                }
            } else {
                $mainclassname = 'Modl\\'.$mainclassname;
            }

            if(class_exists($mainclassname)) {
                $class = new $mainclassname;
                $mainstruct = $class->_struct;
            } else {
                array_push($this->_warnings, 'The defined model '.$mainclassname.' doesn\'t exists');
                return;
            }

            foreach($this->_params as $key => $value) {
                $a = explode('_', $key);
                $ckey = reset($a);

                $a = explode('.', $key);

                // We have an attribute from another model
                if(count($a) > 1
                && class_exists('Modl\\'.$a[0])) {
                    $subclassname = 'Modl\\'.$a[0];
                    $class = new $subclassname;

                    $classname = $subclassname;
                    $struct = $class->_struct;

                    $ckey = $key = $a[1];
                } else {
                    $classname = $mainclassname;
                    $struct = $mainstruct;
                }

                if(isset($struct[$ckey])) {
                    $caract = $struct[$ckey];

                    if(
                    ((isset($caract['key']) && $caract['key'] == true)
                        ||
                    (isset($caract['mandatory']) && $caract['mandatory'] == true))
                    && !isset($value) && !empty($value)) {
                        array_push($this->_warnings, $key.' is not set');
                        return;
                    }

                    switch($caract['type']) {
                        case 'bool' :
                        case 'int' :
                            $this->_resultset->bindValue(':'.$key, (int)$value, \PDO::PARAM_INT);
                        break;
                        // Seems buggy on MySQL
                        /*case 'bool' :
                            $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_BOOL);
                        break;*/
                        case 'date' :
                            if(!empty($value)) {
                                $date = new \DateTime((string)$value);
                                $this->_resultset->bindValue(':'.$key, $date->format(self::SQL_DATE), \PDO::PARAM_STR);
                            } else {
                                $this->_resultset->bindValue(':'.$key, null, \PDO::PARAM_STR);
                            }
                        break;
                        case 'serialized' :
                            if(!empty($value)) {
                                $this->_resultset->bindValue(':'.$key, serialize($value), \PDO::PARAM_STR);
                            } else {
                                $this->_resultset->bindValue(':'.$key, null, \PDO::PARAM_STR);
                            }
                        break;
                        case 'text' :
                        case 'string' :
                        default :
                            $this->_resultset->bindValue(':'.$key, $value, \PDO::PARAM_STR);
                        break;
                    }
                } else {
                    // Call the logger here
                    array_push($this->_warnings, $classname.' attribute '.$key.' not found');
                }
            }
        } else {
            array_push($this->_warnings, 'Database not ready');
        }
    }

    public function run($classname = null, $type = 'list')
    {
        if(empty($this->_warnings)) {
            $this->_resultset->execute();
        } else {
            Utils::log($this->_warnings);
        }

        if($classname == null
        && substr(get_class($this), -3, 3) == 'DAO') {
            // We strip Modl/ and DAO from the classname
            $classname = substr(get_class($this), 5, -3);
        }

        $this->_warnings = [];

        if($this->_resultset != null) {
            $errors = $this->_resultset->errorInfo();
            if($errors[0] != '000000') {
                Utils::log($errors[1].' : '.$errors[2]);
                Utils::log(trim(preg_replace('/\s+/', ' ',$this->_sql)), $this->_params, $errors);
            }

            if($this->_resultset->rowCount() == 0) {
                $this->_effective = false;
            } else {
                $this->_effective = true;
            }

            $ns_classname = 'Modl\\'.$classname;

            if($type == 'count' && $this->_resultset != null) {
                $results = $this->_resultset->fetchAll(\PDO::FETCH_ASSOC);
                if(is_array($results) && isset($results[0])) {
                    $arr = array_values($results[0]);
                    return (int)$arr[0];
                }
            }

            if(isset($classname)
            && class_exists($ns_classname)
            && $this->_resultset != null
            && $type != 'array') {
                $results = [];

                while($row = $this->_resultset->fetch(\PDO::FETCH_NAMED)) {
                    $obj = new $ns_classname;

                    foreach($row as $key => $value) {
                        if(isset($value)) {
                            if(is_array($value)) {
                                $value = current(array_filter($value));
                            }

                            if(property_exists($obj, $key)
                            && property_exists($obj, '_struct')
                            && array_key_exists($key, $obj->_struct)) {
                                switch($obj->_struct[$key]['type']) {
                                    case 'int' :
                                        $obj->$key = (int)$value;
                                    break;
                                    case 'bool' :
                                        $obj->$key = (bool)$value;
                                    break;
                                    case 'serialized' :
                                        $obj->$key = unserialize($value);
                                    break;
                                    case 'date' :
                                    case 'string' :
                                    default :
                                        $obj->$key = (string)$value;
                                    break;
                                }
                            } else {
                                $obj->$key = $value;
                            }
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

                if(empty($results)) {
                    return null;
                } else {
                    foreach($results as $obj) {
                        $obj->clean();
                    }

                    if($type == 'list') {
                        return $results;
                    } elseif($type == 'item') {
                        return $results[0];
                    }
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
