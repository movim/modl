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

class SmartDB extends SQL
{
    function __construct()
    {
        parent::inject($this);
    }

    private function getType($struct)
    {
        $type = $size = $csize = false;

        $ctype = $struct['type'];
        if (array_key_exists('size', $struct)) $csize = $struct['size'];

        switch ($ctype) {
            case 'int':
                $type = 'int';
                $size = '  ';
            break;
            case 'bool':
                if ($this->_dbtype == 'mysql') {
                    $type = 'tinyint';
                    $size = '(1)';
                } else {
                    $type = 'bool';
                    $size = '  ';
                }
            break;
            case 'serialized':
            case 'text':
                $type = ($this->_dbtype == 'mysql') ? 'longtext' : 'text';
                $size = '  ';
            break;
            case 'date':
                $type = ($this->_dbtype == 'mysql') ? 'datetime' : 'timestamp';
                $size = '  ';
            break;
            case 'string':
            default:
                $type = 'varchar';
                $size = '('.$csize.') ';
            break;
        }

        return [$type, $size];
    }

    public function check($apply = false)
    {
        $infos = [];

        $where = ($this->_dbtype == 'mysql')
            ? 'table_schema = :database'
            : 'table_catalog = :database and table_schema = \'public\'';

        $sql = 'select * from information_schema.columns where ' . $where;

        $results = [];
        $tables  = [];
        $columns = [];

        if (isset($this->_db)) {
            $resultset = $this->_db->prepare($sql);
            $resultset->bindValue(':database', $this->_database, \PDO::PARAM_STR);
            $resultset->execute();

            $results = $resultset->fetchAll(\PDO::FETCH_CLASS);
        }

        $uniques_constraints = $this->getUniques();

        $table = '';
        foreach ($results as $c) {
            switch ($this->_dbtype) {
                case 'mysql':
                    $table_name = strtolower($c->TABLE_NAME);
                    $column_name = strtolower($c->COLUMN_NAME);
                break;
                case 'pgsql':
                    $table_name = strtolower($c->table_name);
                    $column_name = strtolower($c->column_name);
                break;
            }

            if ($table != $table_name) {
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

        foreach ($models as $model)
        {
            $model = strtolower($model);
            $prim_keys = $this->getKeys($model);

            // We remove the default modl column
            unset($extra_columns[$model.'_modl']);

            $classname = 'Modl\\' . $model;

            $keys = [];

            $m = new $classname;

            if (!isset($tables[$model])) {
                if ($apply == true) {
                    $this->createTable($model);
                } else {
                    array_push($infos, $model.' table have to be created');
                }
            }

            foreach ($m->_struct as $key => $value) {
                $name = $model.'_'.$key;
                if (!isset($columns[$name])) {
                    if ($apply == true) {
                        $this->createColumn($model, $key, $value);
                    } else {
                        array_push($infos, $name.' column have to be created');
                    }
                } else {
                    if (!isset($value['size'])) $value['size'] = false;

                    list($type, $size) = $this->getType($value);

                    switch ($this->_dbtype) {
                        case 'mysql':
                            $dbtype = $columns[$name]->DATA_TYPE;
                            $dbsize = $columns[$name]->CHARACTER_MAXIMUM_LENGTH;
                            $dbnull = ($columns[$name]->IS_NULLABLE == 'YES');
                        break;
                        case 'pgsql':
                            $dbtype = preg_replace('/[0-9]/','', $columns[$name]->udt_name);
                            $dbsize = $columns[$name]->character_maximum_length;
                            $dbnull = ($columns[$name]->is_nullable == 'YES');
                        break;
                    }

                    $changesize = $changenull = false;
                    if ($type == 'varchar' && $dbsize != $value['size']) {
                        $changesize = true;
                    }

                    if (isset($value['mandatory']) == $dbnull
                    && !isset($value['key'])) {
                        $changenull = true;
                        if (isset($value['mandatory'])) {
                            array_push($infos, $name.' column have to be set to not null /!\ null tuples will be deleted');
                        } else {
                            array_push($infos, $name.' column have to be set to nullable');
                        }
                    }

                    if ($dbtype != $type || $changesize || $changenull) {
                        if ($apply == true) {
                            $this->updateColumn($model, $key, $value);
                        } else {
                            array_push($infos, $name.' column have to be updated from '.$dbtype.'('.$dbsize.') to '.$type.'('.$value['size'].')');
                        }
                    }
                }

                if (isset($value['key']) && $value['key']) {
                    array_push($keys, $key);
                }

                unset($extra_columns[$name]);
            }

            foreach ($m->_uniques as $unique) {
                if (!isset($uniques_constraints[$model . '_' . implode('_', $unique)])) {
                    if ($apply == true) {
                        $this->createUnique($model, $unique);
                    } else {
                        array_push(
                            $infos,
                            'a unique constraint for ' .
                            $model .
                            ' (' . implode(',', $unique). ') have to be created'
                        );
                    }
                } else {
                    unset($uniques_constraints[$model . '_' . implode('_', $unique)]);
                }
            }

            if ($keys !== $prim_keys) {
                if ($apply == true) {
                    $this->updateKeys($model, $keys);
                } else {
                    array_push($infos, $model.' keys have to be updated, /!\ the table will be truncated');
                }
            }

            unset($tables[$model]);
        }

        // And we remove the extra columns
        foreach ($extra_columns as $key => $value) {
            if ($apply == true) {
                $exp = explode('_', $key);
                $table = array_shift($exp);
                $column = implode('_', $exp);
                $this->deleteColumn($table, $column);
            } else {
                array_push($infos, $key.' column have to be removed');
            }
        }

        foreach ($tables as $key => $table) {
            if ($apply == true) {
                $this->dropTable($key);
            } else {
                array_push($infos, 'table '.$key.' have to be dropped');
            }
        }

        foreach ($uniques_constraints as $key => $unique) {
            if ($apply == true) {
                $this->dropUnique($key);
            } else {
                array_push($infos, 'constraint ' . $key . '_unique have to be removed');
            }
        }

        if (!empty($infos)) {
            return $infos;
        }

        return null;
    }

    private function getKeys($table_name)
    {
        $where = ($this->_dbtype == 'mysql')
            ? 'table_schema = :database and table_name = :table_name'
            : 'table_catalog = :database
                    and table_schema = \'public\'
                    and table_name = :table_name';

        $sql = 'select * from information_schema.key_column_usage where
                constraint_name not like \'%_unique\' and ' . $where;

        $results = [];

        if (isset($this->_db)) {
            $resultset = $this->_db->prepare($sql);
            $resultset->bindValue(':database', $this->_database, \PDO::PARAM_STR);
            $resultset->bindValue(':table_name', $table_name, \PDO::PARAM_STR);
            $resultset->execute();

            $results = $resultset->fetchAll(\PDO::FETCH_CLASS);
        }

        $arr = [];

        foreach ($results as $row) {
            array_push($arr,
                $this->_dbtype == 'mysql'
                ? $row->COLUMN_NAME
                : $row->column_name
            );
        }

        return $arr;
    }

    public function getUniques()
    {
        $where = ($this->_dbtype == 'mysql')
            ? 'table_schema = :database'
            : 'table_catalog = :database and table_schema = \'public\'';

        $sql = '
            select * from information_schema.key_column_usage
            where ' . $where . '
            and constraint_name like \'%_unique\'
            order by table_name';

        $results = [];

        if (isset($this->_db)) {
            $resultset = $this->_db->prepare($sql);
            $resultset->bindValue(':database', $this->_database, \PDO::PARAM_STR);
            $resultset->execute();

            $results = $resultset->fetchAll(\PDO::FETCH_CLASS);
        }

        $arr = [];

        foreach ($results as $row) {
            $arr[substr(
                ($this->_dbtype == 'mysql') ? $row->CONSTRAINT_NAME : $row->constraint_name,
                0,
                -7
            )] = true;
        }

        return $arr;
    }

    private function createUnique($table_name, $unique)
    {
        Utils::log('Creating the unique contraint ('. implode('_', $unique) .') for '.$table_name);

        $this->_sql = '
            alter table ' . $table_name . '
            add constraint ' . $table_name . '_' . implode('_', $unique) . '_unique
            unique (' . implode(',', $unique) . ')';

        $this->prepare();
        $this->run();
    }

    private function dropUnique($unique)
    {
        $table = reset(explode('_', $unique));

        Utils::log('Dropping the unique constraint ' . $unique);

        $constraint = $this->_dbtype == 'mysql' ? 'index' : 'constraint';

        $this->_sql = '
            alter table ' . $table . '
            drop ' . $constraint . ' ' . $unique . '_unique';

        $this->prepare();
        $this->run();
    }

    private function createTable($name)
    {
        Utils::log('Creating table '.$name);
        $name = strtolower($name);

        $this->_sql = '
            create table '.$name.' (
                modl int
            )';

        if ($this->_dbtype == 'mysql') {
            $this->_sql .= ' CHARACTER SET utf8 COLLATE utf8_bin';
        }

        $this->prepare();
        $this->run();
    }

    private function dropTable($name)
    {
        $name = strtolower($name);

        Utils::log('Dropping table ' . $name);
        $this->_sql = 'drop table ' . $name;

        $this->prepare();
        $this->run();
    }

    private function createColumn($table_name, $column_name, $struct)
    {
        $table_name  = strtolower($table_name);
        $column_name = strtolower($column_name);

        Utils::log('Creating column ' . $column_name);

        $type = $size = false;

        list($type, $size) = $this->getType($struct);

        if ($type != false && $size != false) {
            $this->_sql = '
                alter table '.$table_name.'
                add column '.$column_name.' '.$type.$size;
            if (isset($struct['mandatory'])) {Utils::log('Creating not null '.$column_name);
                $this->_sql .= ' not null';
            }

            $this->prepare();
            $this->run();
        }
    }

    private function updateColumn($table_name, $column_name, $struct)
    {
        $table_name  = strtolower($table_name);
        $column_name = strtolower($column_name);

        Utils::log('Updating column '.$column_name);

        $type = $size = false;

        list($type, $size) = $this->getType($struct);

        if ($type != false && $size != false) {
            // Remove the tuples that have not null column
            if (isset($struct['mandatory'])) {
                $this->_sql = '
                    delete from '.$table_name.'
                    where '.$column_name.' is null
                    ';
            }

            $this->prepare();
            $this->run();

            switch ($this->_dbtype) {
                case 'mysql':
                    $this->_sql = '
                        alter table '.$table_name.'
                        modify '.$column_name.' '.$type.$size;
                    if (isset($struct['mandatory'])
                    && !isset($struct['key']))
                        $this->_sql .= '
                            not null
                        ';
                break;
                case 'pgsql':
                    $this->_sql = '
                        alter table '.$table_name.'
                        alter column '.$column_name.' type '.$type.$size;

                    if ($type == 'bool') {
                        $this->_sql .= '
                            using '.$column_name.'::boolean';
                    }

                    // And we add or remove the not null restriction
                    if (isset($struct['mandatory'])) {
                        $this->_sql .= '
                            , alter column '.$column_name.' set not null
                        ';
                    } elseif (!isset($struct['key'])) {
                        $this->_sql .= '
                            , alter column '.$column_name.' drop not null
                        ';
                    }
                break;
            }

            $this->prepare();
            $this->run();

        }
    }

    private function deleteColumn($table_name, $column_name)
    {
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

    private function updateKeys($table_name, $keys)
    {
        $this->_sql = 'alter table ' . $table_name . ' ';
        $this->_sql .= ($this->_dbtype == 'mysql')
            ? 'drop primary key'
            : 'drop constraint ' . $table_name . '_prim_key';

        $this->prepare();
        $this->run();

        if (empty($keys)) return;

        // Do we really need to do this ?
        $this->_sql = '
            truncate table '.$table_name;

        $this->prepare();
        $this->run();

        $pk = '';

        foreach ($keys as $k) {
            $pk .= $k.',';
        }

        $pk = substr_replace($pk, '', -1);

        Utils::log('Creating the keys '.$pk.' for '.$table_name);

        $this->_sql = '
            alter table '.$table_name.'
            add constraint '.$table_name.'_prim_key primary key('.$pk.')
            ';
        $this->prepare();
        $this->run();
    }
}
