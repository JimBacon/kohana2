<?php defined('SYSPATH') or die('No direct script access.');

class ORM2_Core {

	// Current relationships
	protected $has_one                 = array();
	protected $belongs_to              = array();
	protected $has_many                = array();
	protected $has_and_belongs_to_many = array();

	// Current object
	protected $object  = array();
	protected $changed = array();
	protected $loaded  = FALSE;
	protected $saved   = FALSE;

	// Model table name
	protected $table_name;
	protected $table_columns;

	// Model configuration
	protected $table_names_plural = TRUE;
	protected $reload_on_wakeup   = TRUE;

	// Table primary key
	protected $primary_key = 'id';

	// Database instance name
	protected $db = 'default';

	/**
	 * Creates and returns a new model.
	 *
	 * @chainable
	 * @param   string  model name
	 * @param   mixed   parameter for find()
	 * @return  ORM
	 */
	public static function factory($model, $id = NULL)
	{
		// Set class name
		$model = ucfirst($model).'_Model';

		return new $model($id);
	}

	/**
	 * Prepares the model database connection and loads the object.
	 *
	 * @param   mixed  parameter for find or object to load
	 * @return  void
	 */
	public function __construct($id = NULL)
	{
		// Initialize database
		$this->__initialize();

		if ($id === NULL)
		{
			// Clear the object
			$this->clear();
		}
		elseif (is_object($id))
		{
			// Load an object
			$this->load_values((array) $id);

			// Object is loaded and saved
			$this->loaded = $this->saved = TRUE;
		}
		else
		{
			// Find an object
			$this->find($id);
		}
	}

	/**
	 * Prepares the model database connection, determines the table name,
	 * and loads column information.
	 *
	 * @return  void
	 */
	public function __initialize()
	{
		if ( ! is_object($this->db))
		{
			// Get database instance
			$this->db = Database::instance($this->db);
		}

		if (empty($this->table_name))
		{
			// Set the table name
			$this->table_name = strtolower(substr(get_class($this), 0, -6));

			if ($this->table_names_plural === TRUE)
			{
				// Make the table name plural
				$this->table_name = inflector::plural($this->table_name);
			}
		}

		if ( ! is_array($this->table_columns))
		{
			// Load table columns
			$this->table_columns = $this->db->list_fields($this->table_name);

			if (empty($this->table_columns))
				throw new Kohana_Exception('database.table_not_found', $this->table);
		}
	}

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		// Store only information about the object
		return array('object', 'changed', 'loaded', 'saved');
	}

	/**
	 * Prepares the database connection and reloads the object.
	 *
	 * @return  void
	 */
	public function __wakeup()
	{
		// Initialize database
		$this->__initialize();

		if ($this->reload_on_wakeup === TRUE)
		{
			// Reload the object
			$this->reload();
		}
	}

	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param   string  method name
	 * @param   array   method arguments
	 * @return  mixed
	 */
	public function __call($method, array $args)
	{
		if (method_exists($this->db, $method))
		{
			if (in_array($method, array('query', 'get', 'insert', 'update', 'delete')))
				throw new Kohana_Exception('orm.query_methods_not_allowed');

			// Number of arguments passed
			$num_args = count($args);

			if ($method === 'select' AND $num_args > 3)
			{
				// Call select() manually to avoid call_user_func_array
				$this->db->select($args);
			}
			else
			{
				// We use switch here to manually call the database methods. This is
				// done for speed: call_user_func_array can take over 300% longer to
				// make calls. Mose database methods are 4 arguments or less, so this
				// avoids almost any calls to call_user_func_array.

				switch ($num_args)
				{
					case 0:
						// Support for things like reset_select, reset_write, list_tables
						return $this->db->$method();
					break;
					case 1:
						$this->db->$method($args[0]);
					break;
					case 2:
						$this->db->$method($args[0], $args[1]);
					break;
					case 3:
						$this->db->$method($args[0], $args[1], $args[2]);
					break;
					case 4:
						$this->db->$method($args[0], $args[1], $args[2], $args[3]);
					break;
					default:
						// Here comes the snail...
						call_user_func_array(array($this->db, $method), $args);
					break;
				}
			}

			return $this;
		}
		else
		{
			throw new Kohana_Exception('core.invalid_method', $method, get_class($this));
		}
	}

	/**
	 * Handles retrieval of all model values, relationships, and metadata.
	 *
	 * @param   string  column name
	 * @return  mixed
	 */
	public function __get($column)
	{
		if (isset($this->object[$column]) OR array_key_exists($column, $this->object))
		{
			return $this->object[$column];
		}
		elseif ($column === 'primary_key_value')
		{
			return $this->object[$this->primary_key];
		}
		elseif (($owner = isset($this->has_one[$column])) OR isset($this->belongs_to[$column]))
		{
			// Determine the model name
			$model = ($owner === TRUE) ? $this->has_one[$column] : $this->belongs_to[$column];

			// Load model
			$model = ORM2::factory($model);

			if (isset($this->object[$column.'_'.$model->primary_key]))
			{
				// Use the FK that exists in this model as the PK
				$where = array($model->primary_key => $this->object[$column.'_'.$model->primary_key]);
			}
			else
			{
				// Use this model PK as the FK
				$where = array($this->foreign_key() => $this->object[$this->primary_key]);
			}

			// one<>alias:one relationship
			return $this->object[$column] = $model->find($where);
		}
		elseif (in_array($column, $this->has_one) OR in_array($column, $this->belongs_to))
		{
			$model = ORM2::factory($column);

			if (isset($this->object[$column.'_'.$model->primary_key]))
			{
				// Use the FK that exists in this model as the PK
				$where = array($model->primary_key => $this->object[$column.'_'.$model->primary_key]);
			}
			else
			{
				// Use this model PK as the FK
				$where = array($this->foreign_key() => $this->object[$this->primary_key]);
			}

			// one<>one relationship
			return $this->object[$column] = ORM2::factory($column, $where);
		}
		elseif (isset($this->has_many[$column]))
		{
			// Load the "middle" model
			$through = ORM2::factory(inflector::singular($this->has_many[$column]));

			// Load the "end" model
			$model = ORM2::factory(inflector::singular($column));

			// Load JOIN info
			$join_table = $through->table_name;
			$join_col1  = $model->foreign_key(NULL, $join_table);
			$join_col2  = $model->foreign_key(TRUE);

			// one<>alias:many relationship
			return $this->object[$column] = $model
				->join($join_table, $join_col1, $join_col2)
				->where($this->foreign_key(NULL, $join_table), $this->object[$this->primary_key])
				->find_all();
		}
		elseif (in_array($column, $this->has_many))
		{
			// one<>many relationship
			return $this->object[$column] = ORM2::factory(inflector::singular($column))
				->where($this->foreign_key($column), $this->object[$this->primary_key])
				->find_all();
		}
		elseif (in_array($column, $this->has_and_belongs_to_many))
		{
			// Load the remote model, always singular
			$model = ORM2::factory(inflector::singular($column));

			// Load JOIN info
			$join_table = $model->join_table($this->table_name);
			$join_col1  = $model->foreign_key(NULL, $join_table);
			$join_col2  = $model->foreign_key(TRUE);

			// many<>many relationship
			return $this->object[$column] = $model
				->join($join_table, $join_col1, $join_col2)
				->where($this->foreign_key(NULL, $join_table), $this->object[$this->primary_key])
				->find_all();
		}
		elseif (in_array($column, array
			(
				'primary_key', 'table_name', // Table
				'loaded', 'saved', // Status
				'has_one', 'belongs_to', 'has_many', 'has_many_and_belongs_to', // Relationships
			)))
		{
			// Model meta information
			return $this->$column;
		}
		else
		{
			throw new Kohana_Exception('core.invalid_property', $column, get_class($this));
		}
	}

	/**
	 * Handles setting of all model values, and tracks changes between values.
	 *
	 * @param   string  column name
	 * @param   mixed   column value
	 * @return  void
	 */
	public function __set($column, $value)
	{
		if (isset($this->object[$column]) OR array_key_exists($column, $this->object))
		{
			if (isset($this->table_columns[$column]))
			{
				// Data has changed
				$this->changed[$column] = $column;

				// Object is no longer saved
				$this->saved = FALSE;
			}

			$this->object[$column] = $this->load_type($column, $value);
		}
		else
		{
			throw new Kohana_Exception('core.invalid_property', $column, get_class($this));
		}
	}

	/**
	 * Checks if object data is set.
	 *
	 * @param   string  column name
	 * @return  boolean
	 */
	public function __isset($column)
	{
		return isset($this->object[$column]);
	}

	/**
	 * Unsets object data.
	 *
	 * @param   string  column name
	 * @return  void
	 */
	public function __unset($column)
	{
		unset($this->object[$column], $this->changed[$column]);
	}

	/**
	 * Displays the primary key of a model when it is converted to a string.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return (string) $this->object[$this->primary_key];
	}

	/**
	 * Finds and loads a single database row into the object.
	 *
	 * @chainable
	 * @param   mixed  primary key or an array of clauses
	 * @return  ORM
	 */
	public function find($id = NULL)
	{
		if (func_num_args() > 0)
		{
			if (is_array($id))
			{
				// Search for all clauses
				$this->db->where($id);
			}
			else
			{
				// Search for a specific column
				$this->db->where($this->unique_key($id), $id);
			}
		}

		return $this->load_result();
	}

	/**
	 * Finds multiple database rows and returns an iterator of the rows found.
	 *
	 * @chainable
	 * @param   integer  SQL limit
	 * @param   integer  SQL offset
	 * @return  ORM_Iterator
	 */
	public function find_all($limit = NULL, $offset = 0)
	{
		if (func_num_args() > 0)
		{
			// Set limit and offset
			$this->db->limit($limit, $offset);
		}

		return $this->load_result(TRUE);
	}

	/**
	 * Saves the current object. If the object is new, it will be reloaded
	 * after being saved.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function save()
	{
		if (empty($this->changed))
			return $this;

		$data = array();
		foreach ($this->changed as $column)
		{
			// Compile changed data
			$data[$column] = $this->object[$column];
		}

		if ($this->loaded === TRUE)
		{
			$query = $this->db
				->where($this->primary_key, $this->object[$this->primary_key])
				->update($this->table_name, $data);

			// Object has been saved
			$this->saved = TRUE;
		}
		else
		{
			$query = $this->db
				->insert($this->table_name, $data);

			if ($query->count() > 0)
			{
				if (empty($this->object[$this->primary_key]))
				{
					// Load the insert id as the primary key
					$this->object[$this->primary_key] = $query->insert_id();
				}

				// Reload the object
				$this->reload();
			}
		}

		return $this;
	}

	/**
	 * Deletes the current object from the database. This does NOT destroy
	 * relationships that have been created with other objects.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function delete()
	{
		// Delete this object
		$this->db->where($this->primary_key, $this->object[$this->primary_key])->delete($this->table_name);

		return $this->clear();
	}

	/**
	 * Delete all objects in the associated table. This does NOT destroy
	 * relationships that have been created with other objects.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function delete_all()
	{
		// Delete all objects
		$this->db->delete($this->table_name, TRUE);

		return $this->clear();
	}

	/**
	 * Unloads the current object and clears the status.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function clear()
	{
		// Replace the current object with an empty one
		$this->load_values(array());

		// Object is no longer loaded or saved
		$this->loaded = $this->saved = FALSE;

		// Nothing has been changed
		$this->changed = array();

		return $this;
	}

	/**
	 * Reloads the current object from the database.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function reload()
	{
		return $this->find($this->object[$this->primary_key]);
	}

	/**
	 * Tests if this object has a relationship to a different model.
	 *
	 * @param   string   model name
	 * @param   mixed    primary key
	 * @return  boolean
	 */
	public function has($object, $id = NULL)
	{
		if (($join_table = array_search(inflector::plural($object), $this->has_and_belongs_to_many)) === FALSE)
			return FALSE;

		// Load the model
		$model = ORM2::factory($object);

		if (is_int($join_table))
		{
			// Load JOIN table
			$join_table = $model->join_table($this->table_name);
		}

		if ($id !== NULL)
		{
			// Select only objects of a specific id
			$this->db->where($model->foreign_key(NULL, $join_table), $id);
		}

		// Return the number of rows that exist
		return $this->db
			->where($this->foreign_key(NULL, $join_table), $this->object[$this->primary_key])
			->count_records($join_table);
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 * @param   string   model name
	 * @param   mixed    primary key
	 * @return  boolean
	 */
	public function add($object, $id)
	{
		if ($this->has($object, $id))
			return TRUE;

		if ( ! is_int(array_search(inflector::plural($object), $this->has_and_belongs_to_many)))
			return FALSE;

		// Load the model
		$model = ORM2::factory($object);

		// Load JOIN table
		$join_table = $model->join_table($this->table_name);

		// Insert the new relationship
		$this->db->insert($join_table, array
		(
			$this->foreign_key(NULL, $join_table)  => $this->object[$this->primary_key],
			$model->foreign_key(NULL, $join_table) => $id,
		));

		return TRUE;
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 * @param   string   model name
	 * @param   mixed    primary key
	 * @return  boolean
	 */
	public function remove($object, $id)
	{
		if ( ! $this->has($object, $id))
			return FALSE;

		if ( ! is_int(array_search(inflector::plural($object), $this->has_and_belongs_to_many)))
			return FALSE;

		// Load the model
		$model = ORM2::factory($object);

		// Load JOIN table
		$join_table = $model->join_table($this->table_name);

		// Return the number of rows deleted
		return $this->db
			->where($this->foreign_key(NULL, $join_table), $this->object[$this->primary_key])
			->where($model->foreign_key(NULL, $join_table), $id)
			->delete($join_table)
			->count();
	}

	/**
	 * Proxy method to Database list_fields.
	 *
	 * @param   string  table name
	 * @return  array
	 */
	public function list_fields($table)
	{
		// Proxy to database
		return $this->db->list_fields($table);
	}

	/**
	 * Proxy method to Database field_data.
	 *
	 * @param   string  table name
	 * @return  array
	 */
	public function field_data($table)
	{
		// Proxy to database
		return $this->db->field_data($table);
	}

	/**
	 * Proxy method to Database last_query.
	 *
	 * @return  string
	 */
	public function last_query()
	{
		// Proxy to database
		return $this->db->last_query();
	}

	/**
	 * Proxy method to Database field_data.
	 *
	 * @chainable
	 * @param   string  SQL query to clear
	 * @return  ORM
	 */
	public function clear_cache($sql = NULL)
	{
		// Proxy to database
		$this->db->clear_cache($sql);

		return $this;
	}

	/**
	 * Returns the unique key for a specific value. This method is expected
	 * to be overloaded in models if the model has other unique columns.
	 *
	 * @param   mixed   unique value
	 * @return  string
	 */
	public function unique_key($id)
	{
		return $this->primary_key;
	}

	/**
	 * Determines the name of a foreign key for a specific table.
	 *
	 * @param   string  related table name
	 * @param   string  prefix table name (used for JOINs)
	 * @return  string
	 */
	public function foreign_key($table = NULL, $prefix_table = NULL)
	{
		if ($table === TRUE)
		{
			// Return the name of this tables PK
			return $this->table_name.'.'.$this->primary_key;
		}

		if (is_string($prefix_table))
		{
			// Add a period for prefix_table.column support
			$prefix_table .= '.';
		}

		if ( ! is_string($table) OR ! isset($this->object[$table.'_'.$this->primary_key]))
		{
			// Use this table
			$table = $this->table_name;

			if ($this->table_names_plural === TRUE)
			{
				// Make the key name singular
				$table = inflector::singular($table);
			}
		}

		return $prefix_table.$table.'_'.$this->primary_key;
	}

	/**
	 * This uses alphabetical comparison to choose the name of the table.
	 *
	 * Example: The joining table of users and roles would be roles_users,
	 * because "r" comes before "u". Joining products and categories would
	 * result in categories_prouducts, because "c" comes before "p".
	 *
	 * Example: zoo > zebra > robber > ocean > angel > aardvark
	 *
	 * @param   string  table name
	 * @return  string
	 */
	public function join_table($table)
	{
		if ($this->table_name > $table)
		{
			$table = $table.'_'.$this->table_name;
		}
		else
		{
			$table = $this->table_name.'_'.$table;
		}

		return $table;
	}

	/**
	 * Loads a value according to the types defined by the column metadata.
	 *
	 * @param   string  column name
	 * @param   mixed   value to load
	 * @return  mixed
	 */
	protected function load_type($column, $value)
	{
		if (is_object($value) OR is_array($value) OR ! isset($this->table_columns[$column]))
			return $value;

		// Load column data
		$column = $this->table_columns[$column];

		if ($value === NULL AND ! empty($column['null']))
			return $value;

		if ( ! empty($column['binary']) AND ! empty($column['exact']) AND (int) $column['length'] === 1)
		{
			// Use boolean for BINARY(1) fields
			$column['type'] = 'boolean';
		}

		switch ($column['type'])
		{
			case 'int':
				$value = ($value === '' AND ! empty($data['null'])) ? NULL : (int) $value;
			break;
			case 'float':
				$value = (float) $value;
			break;
			case 'boolean':
				$value = (bool) $value;
			break;
			case 'string':
				$value = (string) $value;
			break;
		}

		return $value;
	}

	/**
	 * Loads an array of values into into the current object.
	 *
	 * @chainable
	 * @param   array  values to load
	 * @return  ORM
	 */
	protected function load_values(array $values)
	{
		// Get the table columns
		$columns = array_keys($this->table_columns);

		// Make sure all the columns are defined
		$this->object += array_combine($columns, array_fill(0, count($columns), NULL));

		foreach ($columns as $column)
		{
			// Value for this column
			$value = isset($values[$column]) ? $values[$column] : NULL;

			// Set value manually, to avoid triggering changes
			$this->object[$column] = $this->load_type($column, $value);
		}

		return $this;
	}

	/**
	 * Loads a database result, either as a new object for this model, or as
	 * an iterator for multiple rows.
	 *
	 * @chainable
	 * @param   boolean       return an iterator or load a single row
	 * @return  ORM           for single rows
	 * @return  ORM_Iterator  for multiple rows
	 */
	protected function load_result($array = FALSE)
	{
		if ($array === FALSE)
		{
			// Only fetch 1 record
			$this->db->limit(1);
		}

		// Load the result
		$result = $this->db->get($this->table_name);

		if ($array === TRUE)
		{
			// Return an iterated result
			return new ORM_Iterator(get_class($this), $result);
		}

		if ($result->count() === 1)
		{
			// Load object values
			$this->load_values($result->result(FALSE)->current());

			// Model is loaded and saved
			$this->loaded = $this->saved = TRUE;

			// Nothing has been changed
			$this->changed = array();
		}
		else
		{
			// Clear the object, nothing was found
			$this->clear();
		}

		return $this;
	}

} // End ORM