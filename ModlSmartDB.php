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

namespace modl;

class ModlSmartDB extends ModlSQL {    
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

        // Now we get the models structs
        $modl = Modl::getInstance();
        $models = $modl->_models;
        
        foreach($models as $model) {
            $model = strtolower($model);
            $classname = 'modl\\'.$model;

            $keys = array();

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
                        break;
                        case 'pgsql':
                            $dbtype = preg_replace('/[0-9]/','', $columns[$name]->udt_name);
                        break;
                    }   
                    
                    if($dbtype != $type) {
                        if($apply == true)
                            $this->updateColumn($model, $key, $value);
                        else
                            array_push($infos, $name.' column have to be updated from '.$dbtype.' to '.$type);
                    }
                }

                if(isset($value->key) && $value->key) {
                    if($prim_keys[$name] != true) {
                        if($apply == true)
                            array_push($keys, $key);
                        else
                            array_push($infos, $name.' key have to be created');
                    }
                }
            }
            
            if(!empty($keys)) {
                $this->createKeys($model, $keys);
            }
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
        ModlLogger::log('Creating table '.$name);
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
        
        ModlLogger::log('Creating column '.$column_name);
        
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
        
        ModlLogger::log('Updating column '.$column_name);
        
        $type = $size = false;

        list($type, $size) = $this->getType($struct->type, $struct->size);
        
        if($type != false && $size != false) {
            $this->_sql = '
                alter table '.$table_name.'
                modify '.$column_name.' '.$type.$size.'
                ';
            $this->prepare();
            $this->run();
        }
    }
    
    private function createKeys($table_name, $keys) {
        $pk = '';

        foreach($keys as $k) {
            $pk .= $k.',';
        }
        
        $pk = substr_replace($pk, '', -1);
        
        ModlLogger::log('Creating the keys '.$pk.' for '.$table_name);
            
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
