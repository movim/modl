<?php

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
