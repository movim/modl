<?php
/**
 * @file ModlModel.php
 *
 * @brief The generic Model of Modl
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

class ModlModel extends Modl {
    protected $_struct;
    private $_decoded_struct;
    
    public function __construct() {
        $this->_decoded_struct = json_decode($this->_struct);
    }
    
    public function __get($name) {
        if($name == '_struct')
            return $this->_decoded_struct;
        elseif(property_exists($this, $name)) {
            //$attr = array();
            
            //$struct = json_decode($this->_struct);
            
            /*$attr['value'] = $this->$name;

            if(isset($struct->$name->type))
                $attr['type'] = $struct->$name->type;
            else
                $attr['type'] = null;
            
            if(isset($struct->$name->size))
                $attr['size'] = $struct->$name->size;
            else
                $attr['size'] = null;

            if(isset($struct->$name->mandatory) 
            && $struct->$name->mandatory == true)
                $attr['mandatory'] = $struct->$name->mandatory;
            else
                $attr['mandatory'] = false;
                
            return (object)$attr;*/
            return $this->$name;
        }
    }
    
    public function __set($name, $value) {
        $struct = $this->_decoded_struct;

        if(isset($struct->$name->type)) {
            $type = $struct->$name->type;
            switch($type) {
                case 'int' :
                    $this->$name = (int)$value;
                break;
                case 'string' :
                    $this->$name = (string)$value;
                break;
                case 'date' :
                    $date = new \DateTime((string)$value);
                    $now = new \DateTime();

                    if(
                        $date->format('Y-m-d H:i:s') != '0000-00-00 00:00:00'
                    &&  $date->format('Y-m-d H:i:s') != $now->format('Y-m-d H:i:s')
                    )
                        $this->$name = $date->format('Y-m-d H:i:s');
                break;
                default :
                    $this->$name = (string)$value;
                break;
            }
        } elseif(property_exists($this, $name))
            $this->$name = (string)$value;
    }

    public function toJSON() {
        $this->clean();
        return json_encode(get_object_vars($this));
    }
    
    public function toArray() {
        $this->clean();
        return get_object_vars($this);
    }

    public function clean() {
        /*
        $struct = $this->_decoded_struct;

        if(isset($struct)) {
            foreach($struct as $key => $value) {
                if(isset($value->protect) && $value->protect) {
                    unset($this->$key);
                }
            }
        }
        */
        unset($this->_struct);
        unset($this->_db);
        unset($this->_dbtype);
        unset($this->_username);
        unset($this->_password);
        unset($this->_host);
        unset($this->_port);
        unset($this->_database);
        unset($this->_error);
        unset($this->_keep);
        unset($this->_user);
        unset($this->_models);
        unset($this->_connected);
        unset($this->_decoded_struct);
        unset($this->modelspath);
    }
}
