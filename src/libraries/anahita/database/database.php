<?php

class AnDatabase extends KDatabaseAdapterMysqli implements KServiceInstantiatable
{
	/**
     * Force creation of a singleton
     *
     * @param 	object 	An optional KConfig object with configuration options
     * @param 	object	A KServiceInterface object
     * @return KDatabaseTableInterface
     */
    public static function getInstance(KConfigInterface $config, KServiceInterface $container)
    {
        if (!$container->has($config->service_identifier)) {
            $classname = $config->service_identifier->classname;
            $instance  = new $classname($config);
            $container->set($config->service_identifier, $instance);
        }

        return $container->get($config->service_identifier);
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   object  An optional KConfig object with configuration options.
     * @return  void
     */
    protected function _initialize(KConfig $config)
    {
        $settings = $this->getService('com:settings.setting');

        $database = $settings->db;
        $prefix = $settings->dbprefix;
        $port = NULL;
		$socket	= NULL;
        $host = $settings->host;
        $user = $settings->user;
        $password = $settings->password;

		$targetSlot = substr(strstr($host, ":"), 1);

        if (!empty($targetSlot)) {

			// Get the port number or socket name
			if (is_numeric($targetSlot)) {
                $port = $targetSlot;
            } else {
				$socket	= $targetSlot;
            }

			// Extract the host name only
			$host = substr($host, 0, strlen($host) - (strlen($targetSlot) + 1));

            // This will take care of the following notation: ":3306"
			if($host === '') {
				$host = 'localhost';
            }
		}

        //test to see if driver exists
        if (!function_exists( 'mysqli_connect' )) {
            throw new Exception('The MySQL adapter "mysqli" is not available!');
            return;
		}

        if(!($db = new mysqli($host, $user, $password, NULL, $port, $socket))) {
            throw new Exception("Couldn't connect to the database!");
			return false;
        }

        if (!$db->select_db($database)) {
            throw new Exception("The database \"$database\" doesn't seem to exist!");
			return false;
		}

		$db->set_charset("utf8mb4");

        $config->append(array(
    		'connection' => $db,
            'table_prefix' => $settings->dbprefix,
        ));

        parent::_initialize($config);
    }

	/**
	 * Retrieves the table schema information about the given table
	 *
	 * This function try to get the table schema from the cache. If it cannot be found
	 * the table schema will be retrieved from the database and stored in the cache.
	 *
	 * @param 	string 	A table name or a list of table names
	 * @return	KDatabaseSchemaTable
	 */
	public function getTableSchema($table)
	{
	    if (! isset($this->_table_schema[$table]) && isset($this->_cache)) {

			$database = $this->getDatabase();
		    $identifier = md5($database.$table);

	        if (! $schema = $this->_cache->get($identifier)) {
	            $schema = parent::getTableSchema($table);
	            //Store the object in the cache
		   	    $this->_cache->store(serialize($schema), $identifier);
	        } else {
				$schema = unserialize($schema);
			}

		    $this->_table_schema[$table] = $schema;
	    }

	    return parent::getTableSchema($table);
	}
}
