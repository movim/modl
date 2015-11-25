# Modl

Modl stands for Movim Data Layer. It is the library which links the core code of Movim to the SQL database.

Modl is licensed under the AGPLv3 licence.

## Features

Modl aims to provide a lightweight and easily tweakable library as well as the possibility of optimizing your SQL requests by writing them by hand.

All the SQL requests have to be in compliance with the norm in order to return the same result on every databases. Modl will then interprete these results and return them in the form of objects.

Modl currently supports MySQL and PostGreSQL databases.

## Integration into your project

This library is [PSR-0](http://www.php-fig.org/psr/psr-0/) and [PSR-4](http://www.php-fig.org/psr/psr-4/) compliant and can thus be easily loaded via the autoloader. 
You have two ways to do this: through Composer and using the internal loader.

### Through Composer

You simply have to add it to the `composer.json` file of your project. You can find a [movim/modl package on Packagist](https://packagist.org/packages/movim/modl).

Here is an example of a ''composer.json'' file with the library integrated to it.

```js
{
    "require": {
        "movim/modl": "dev-master"
    }
}
```

### Through the internal loader

Modl also has an internal loader.

```php
require 'modl/src/Modl/Loader.php';
\Modl\Loader::register();
```

### Initialisation

Once the library loaded in your project you can instantiate it wherever you want.

The code below comes from the [bootstrap.php](https://github.com/edhelas/movim/blob/master/bootstrap.php) file of the Movim project.

```php
$db = Modl\Modl::getInstance();
$db->setModelsPath(APP_PATH.'models');
        
Modl\Utils::loadModel('Presence');
Modl\Utils::loadModel('Contact');
…
        
$db->setConnectionArray(Conf::getServerConf());
$db->connect();
```

Here a directory that will contain all the data models is created. Then the useful data models can be loaded to be used by Modl.

The setConnectionArray() method has for parameter a PHP array with the following structure.

```php
$conf = array(
  'type' => 'mysql', // or 'pgsql' for a PostGreSQL database
  'username' => 'username', // the user of the database
  'password' => 'password', // the users's password
  'host' => 'localhost', // the host of the database
  'port' => '3306', // the port
  'database' => 'movim' // the name of the database
  );
```

## How to code a Model

Let's take a the simple example of the creation of an "Item" model (which an existing model in Movim, that can be found [here](https://github.com/edhelas/movim/tree/master/app/models/item)).

In order to do that you will have to create a new directory in the models directory (defined before loading the library). Then, create two files in this new directory.

  * `Item.php`, which will be the main class of the model. Each instance of it will correspond to a tuple returned by the database.
  * `ItemDAO.php`, which will contain all the requests referring to this model.

### Item.php

```php
namespace Modl;

class Item extends Model {
    public $server;
    public $jid;
    public $name;
    public $node;
    public $updated;
    
    public function __construct() {
        $this->_struct = '
        {
            "server" : 
                {"type":"string", "size":128, "mandatory":true, "key":true },
            "jid" : 
                {"type":"string", "size":128, "mandatory":true, "key":true },
            "node" : 
                {"type":"string", "size":128, "mandatory":true, "key":true },
            "name" : 
                {"type":"string", "size":128 },
            "updated" : 
                {"type":"date"}
        }';
        
        parent::__construct();
    }
}
```

The code above is quite simple to understand. The Item inherits from `Modl\Model`. Each column of the table created in the database will be translated to an attribute of the class.

In the constructor, the `_struct` attribute inherited from `Modl\Model` has to be defined to contain the particularities of the other attributes in the form of a JSON packet.

Modl currently supports four types of data:
  * **string** for the names, keys…
  * **date** for the dates obviously
  * **int** to use intergers
  * **text** to stock a long string or a big binary value

Except for the date type, a size can be specified for each type of data using the "size" keyword. Globally, the "mandatory" and "key" keywords are respectively used to (1) forbid any empty value in the attribute to save and (2) specify the attribute as a key of the table.

Don't forget to add the following line to apply your modifications.

```php
parent::__construct();
```

### ItemDAO.php

ItemDAO inherits of the `Modl\SQL` class. The following code lets you insert an instance of `Item` into the database.

```php
namespace Modl;

class ItemDAO extends SQL { 
    function set(Item $item) {
        $this->_sql = '
            update item
            set name   = :name,
                updated = :updated
            where server = :server
                and jid  = :jid
                and node = :node';
        
        $this->prepare(
            'Item', 
            array(
                'name'   => $item->name,
                'updated'=> $item->updated,
                'server' => $item->server,
                'jid'    => $item->jid,
                'node'   => $item->node
            )
        );
        
        $this->run('Item');
        
        if(!$this->_effective) {
            $this->_sql = '
                insert into item
                (server,
                node,
                jid,
                name,
                updated
                )
                values(
                    :server,
                    :node,
                    :jid,
                    :name,
                    :updated
                    )';
            
            $this->prepare(
                'Item', 
                array(
                    'name'   => $item->name,
                    'updated'=> $item->updated,
                    'server' => $item->server,
                    'jid'    => $item->jid,
                    'node'   => $item->node
                )
            );
            
            $this->run('Item');
        }
    }
}
```

For reasons of consistency and support for all SQL databases the name of the tables created by Modl is always lowercase. You will have to test your requests on any database you plan to support and make sure that all DBMS return the same result.

You are advised to start development for PostGreSQL before testing on MySQL.

#### Preparation

The SQL request is defined by `_sql`. The `prepare()` method will change the values associated to the keys used in the request (always preceded by `:`) and check and convert the elements to the JSON structure defined in the model constructor.

The `effective()` method returns a boolean related to the validity of the request. In our example it lets us do an "insert or update" (attempts to update a tuple in the table and inserts it if it doesn't already exist) request.

#### Execution
The execution of the request is done through the `run()` method. You will have to give it the type of the instance to manipulate as a parameter (it's null by default). If you only give one parameter to this method it will return an array even for one tuple.

To get only one instance, you can put `item` as a second parameter.

```php
return $this->run('Item', 'item');
```

Following up with the example above, Modl will try to change each element of the array returned by the DBMS into as many instances of Item. If several elements have the same name (if you made a `join` for example), only the first element will be treated.

You can also return the raw array using the following.

```php
return $this->run(null, 'array'); 
```

### Call to the model
Once Modl has been loaded, the defined models can be called wherever you want in the code. Model is a [Singleton](https://fr.wikipedia.org/wiki/Singleton_%28patron_de_conception%29), that is why the connection with the database is kept during the whole execution.

In the piece of code below, a new instance of Item is created...

```php
$n = new \Modl\Item;
$n->server = 'movim.eu';
$n->node   = 'my_little_poney';
…
```

And sent to the database.

```php
$nd = new \Modl\ItemDAO();
$nd->set($n);
```

## SmartDB
Modl SmartDB is a newly added feature in Modl. It replaces the old table creation system which used to use the `create()` method in each DAO.

SmartDB enables not only to create tables automagically by defining models but also to update them if you need to modify or add attributes or models.

It's quite easy to understand how it works. When Modl and its models are loaded the `check()` method launches SmartDB.

```php
$md = Modl\Modl::getInstance();
$infos = $md->check();  
```

It returns a list of modifications to make in the database.
By giving the **true** parameter to the `check()` method you authorize it to update the database by itself. 

```php
$md->check(true); // Done ! Your database is up to date.
```
