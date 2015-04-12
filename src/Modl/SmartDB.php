<?php
/**
 * @file ModlSQL.php
 *
 * @brief The SQL DB generator, updater of Modl
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

class SmartDB extends SQL {    
    function __construct() {
        parent::inject($this);
    }

    private function getType($ctype, $csize = false) {
        $type = $size = false;
        
        switch($ctype) {
            case 'string':
                $type = 'varchar';
                $size = '('.$csize.')'; 
            break;
            case 'int':
                $type = 'int';
                $size = '  ';
            break;
            case 'text':
                if($this->_dbtype == 'mysql')
                    $type = 'longtext';
                else
                    $type = 'text';
                $size = '  ';
            break;
            case 'date':
                if($this->_dbtype == 'mysql')
                    $type = 'datetime';
                else
                    $type = 'timestamp';
                $size = '  ';
            break;
        }

        return array($type, $size);
    }
    
    public function check($apply = false) {
        $infos = array();
                
        switch($this->_dbtype) {
            case 'mysql':
                $where = ' table_schema = :database';
            break;
            case 'pgsql':
                $where = ' table_catalog = :database and table_schema = \'public\'';
            break;
        }
        
        $sql = '
            select * from information_schema.columns where'.$where;

        if(isset($this->_db)) {
            $resultset = $this->_db->prepare($sql);
            $resultset->bindValue(':database', $this->_database, \PDO::PARAM_STR);  
            $resultset->execute();
            
            $results = $resultset->fetchAll(\PDO::FETCH_CLASS);
        } else
            $results = array();
        
        $tables  = array();
        $columns = array();
        
        $prim_keys = $this->getKeys();
        
        $table = '';
        foreach($results as $c) {
            switch($this->_dbtype) {
                case 'mysql':
                    $table_name = strtolower($c->TABLE_NAME);
                    $column_name = strtolower($c->COLUMN_NAME);
                break;
                case 'pgsql':
                    $table_name = strtolower($c->table_name);
                    $column_name = strtolower($c->column_name);
                break;
            }     
                
            if($table != $table_name) {
                $tables[$table_name] = true;
            }
            
            $columns[$table_name.'_'.$column_name] = $c;
            
            $table = $table_name;
        }

        // We create a copy to detect some extra columns in the database
        $extra_columns = $columns;
        
        // Now we get the models structs
        $modl = Modl::getInstance();
        $models = $modl->_models;
        
        foreach($models as $model) {
            $model = strtolower($model);

            // We remove the default modl column
            unset($extra_columns[$model.'_modl']);

            $classname = 'modl\\'.$model;

            $keys = array();
            $need_recreate_keys = false;

            $m = new $classname;

            if(!isset($tables[$model])) {
                if($apply == true)
                    $this->createTable($model);
                else
                    array_push($infos, $model.' table have to be created');
            }
            
            foreach((array)$m->_struct as $key => $value) {
                $name = $model.'_'.$key;
                if(!isset($columns[$name])) {
                    if($apply == true)
                        $this->createColumn($model, $key, $value);
                    else
                        array_push($infos, $name.' column have to be created');
                }

                if(isset($columns[$name])) {
                    list($type, $size) = $this->getType($value->type, $value->size);

                    switch($this->_dbtype) {
                        case 'mysql':
                            $dbtype = $columns[$name]->DATA_TYPE;
                            $dbsize = $columns[$name]->CHARACTER_MAXIMUM_LENGTH;
                        break;
                        case 'pgsql':
                            $dbtype = preg_replace('/[0-9]/','', $columns[$name]->udt_name);
                            $dbsize = $columns[$name]->character_maximum_length;
                        break;
                    }

                    $changesize = false;
                    if($type == 'varchar' && $dbsize != $value->size) {
                        $changesize = true;
                    }
                    
                    if($dbtype != $type || $changesize) {
                        if($apply == true)
                            $this->updateColumn($model, $key, $value);
                        else
                            array_push($infos, $name.' column have to be updated from '.$dbtype.'('.$dbsize.') to '.$type.'('.$value->size.')');
                    }
                }

                if(isset($value->key) && $value->key) {
                    // We push all the keys
                    array_push($keys, $key);

                    // If one of them is not in the database
                    if($prim_keys[$name] != true) {
                        // If we apply the changes we recreate all the keys
                        if($apply == true) {
                            $need_recreate_keys = true;
                        } else {
                            array_push($infos, $name.' key have to be created');
                        }
                    }
                }

                unset($extra_columns[$name]);
            }
            
            if(!empty($keys) && $need_recreate_keys) {
                $this->createKeys($model, $keys);
            }
        }

        // And we remove the extra columns
        foreach($extra_columns as $key => $value) {
            if($apply == true) {
                list($table, $column) = explode('_', $key);
                $this->deleteColumn($table, $column);
            } else
                array_push($infos, $key.' column have to be removed');
        }

        if(!empty($infos))
            return $infos;
        else
            return null;
    }
    
    private function getKeys() {
        switch($this->_dbtype) {
            case 'mysql':
                $where = ' table_schema = :database';
            break;
            case 'pgsql':
                $where = ' table_catalog = :database and table_schema = \'public\'';
            break;
        }
        
        $sql = '
            select * from information_schema.key_column_usage where'.$where.' order by table_name';

        if(isset($this->_db)) {
            $resultset = $this->_db->prepare($sql);
            $resultset->bindValue(':database', $this->_database, \PDO::PARAM_STR);  
            $resultset->execute();
            
            $results = $resultset->fetchAll(\PDO::FETCH_CLASS);
        } else
            $results = array();
            
        $arr = array();
        
        foreach($results as $row) {
            switch($this->_dbtype) {
                case 'mysql':
                    $arr[$row->TABLE_NAME.'_'.$row->COLUMN_NAME] = true;
                break;
                case 'pgsql':
                    $arr[$row->table_name.'_'.$row->column_name] = true;
                break;
            }   
        }
        
        return $arr;
    }
    
    private function createTable($name) {
        Utils::log('Creating table '.$name);
        $name = strtolower($name);
        
        $sql = '
            create table '.$name.'
            (
                modl int
            );
            ';

        switch($this->_dbtype) {
            case 'mysql':
                $sql .= ' CHARACTER SET utf8 COLLATE utf8_bin';
            break;
        }  
        
        $this->_sql = $sql;

        $this->prepare(); 
        $this->run(); 
        
    }
    
    private function createColumn($table_name, $column_name, $struct) {        
        $table_name  = strtolower($table_name);
        $column_name = strtolower($column_name);
        
        Utils::log('Creating column '.$column_name);
        
        $type = $size = false;

        list($type, $size) = $this->getType($struct->type, $struct->size);
        
        if($type != false && $size != false) {
            $this->_sql = '
                alter table '.$table_name.'
                add column '.$column_name.' '.$type.$size.'
                ';
            $this->prepare();
            $this->run();
        }
    }

    private function updateColumn($table_name, $column_name, $struct) {
        $table_name  = strtolower($table_name);
        $column_name = strtolower($column_name);
        
        Utils::log('Updating column '.$column_name);
        
        $type = $size = false;

        list($type, $size) = $this->getType($struct->type, $struct->size);
        
        if($type != false && $size != false) {
            switch($this->_dbtype) {
                case 'mysql':
                    $this->_sql = '
                        alter table '.$table_name.'
                        modify '.$column_name.' '.$type.$size.'
                        ';
                break;
                case 'pgsql':
                    $this->_sql = '
                        alter table '.$table_name.'
                        alter column '.$column_name.' type '.$type.$size.'
                        ';
                break;
            }
            
            $this->prepare();
            $this->run();
        }
    }

    private function deleteColumn($table_name, $column_name) {
        $table_name  = strtolower($table_name);
        $column_name = strtolower($column_name);
        
        Utils::log('Delete column '.$column_name);
        
        $this->_sql = '
            alter table '.$table_name.'
            drop column '.$column_name.'
            ';
        $this->prepare();
        $this->run();
    }
    
    private function createKeys($table_name, $keys) {
        $pk = '';

        foreach($keys as $k) {
            $pk .= $k.',';
        }
        
        $pk = substr_replace($pk, '', -1);
        
        Utils::log('Creating the keys '.$pk.' for '.$table_name);

        // Do we really need to do this ?
        $this->_sql = '
            truncate table '.$table_name;

        $this->prepare();
        $this->run();
            
        switch($this->_dbtype) {
            case 'mysql':
                $this->_sql = '
                    alter table '.$table_name.'
                    drop primary key';
            break;
            case 'pgsql':
                $this->_sql = '
                    alter table '.$table_name.'
                    drop constraint '.$table_name.'_prim_key';
            break;
        }
            
        $this->prepare();
        $this->run();
        
        $this->_sql = '
            alter table '.$table_name.'
            add constraint '.$table_name.'_prim_key primary key('.$pk.')
            ';
        $this->prepare();
        $this->run();
    }
}
