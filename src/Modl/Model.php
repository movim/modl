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

class Model extends Modl implements \JsonSerializable
{
    public function jsonSerialize()
    {
        $this->clean();
        return get_object_vars($this);
    }

    public function toJSON()
    {
        $this->clean();
        return json_encode(get_object_vars($this));
    }

    public function toArray()
    {
        $this->clean();
        return get_object_vars($this);
    }

    public function clean()
    {
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
        unset($this->modelspath);
    }
}
