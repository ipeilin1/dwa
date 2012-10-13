<?php

// Database Class
class DB {

	// instance connection
	public $connection;

	// last connected database
	public $database;

	// singleton DB instance
	private static $instance;
	
	// toggle whether to always re-select the database -- it is a performance drain
	public static $always_select = FALSE;

	// debugging, don't send queries
	public static $debug = FALSE;

	// store all queries
	public $query_history = array();
	
	// store all query benchmarks
	public $query_benchmarks = array();

	// private constructor to enforce singleton access
	private function __construct($db = NULL) {

		// connect to database using credentials supplied by environment
		$this->connection = mysql_connect(DB_HOST, DB_USER, DB_PASS, TRUE);
		
		if(mysql_error()) {
			if (IN_PRODUCTION) {
	
					# Email app owner
					$subject = "SQL Error";
					$body    = "<h2>SQL Error</h2> ".$sql." ".mysql_error($this->connection);
					$body   .= "<h2>Query History</h2>";
					foreach($this->query_history as $k => $v) {
						$body .= $k." = ".$v."<br>";
					}
					Utils::alert_admin($subject, $body);
					
					# Show a nice cryptic error
				    die("<h2>There's been an error processing your request (#DB46)</h2>");
			
				} else {
			 		die(Debug::dump("SQL Error: ".$sql." ".mysql_error()));
				}
		} // end mysql_error
	
		// use utf8 character encoding
		mysql_set_charset('utf8', $this->connection);

	}


	/*-------------------------------------------------------------------------------------------------
	singleton pattern:
	DB::instance('db_name')->query('...');
	-------------------------------------------------------------------------------------------------*/
	public static function instance($db = NULL) {

		// use existing instance
		if (! isset(self::$instance)) {

			// create a new instance
			self::$instance = new DB($db);
		}

		// select database
		self::$instance->select_db($db);

		// return instance
		return self::$instance;

	}


	/*-------------------------------------------------------------------------------------------------

	-------------------------------------------------------------------------------------------------*/
	public function select_db($db = NULL) {
		
		// start benchmark	
		$this->benchmark_start = microtime(TRUE);
	
		// only select database if it hasn't already or a new database was specified
		if ($this->database === NULL || $db != $this->database || self::$always_select === TRUE) {
			
			// store specified database
			$this->database = $db;

			// select database
			mysql_select_db($this->database, $this->connection);
			
		}

	}


	/*-------------------------------------------------------------------------------------------------
	Perform a query with connected database
	-------------------------------------------------------------------------------------------------*/
	public function query($sql) {

		// if debugging, just return the query (if you want to see what the query looks like before executing it)
		// TODO: this should return an EXPLAIN of the query which gives us the benchmark as well
		if (self::$debug)
			return $sql;

		// store query history
		$this->query_history[] = $sql;
			
		// send query
		$result = mysql_query($sql, $this->connection);
		
		// store query benchmark
		$this->query_benchmarks[] = number_format(microtime(TRUE) - $this->benchmark_start, 4);
		
		// handle MySQL errors
		if (! $result) {
			
			// don't show error and sql query in production
			if (IN_PRODUCTION) {

				# Email app owner
				$subject = "SQL Error";
				$body    = "<h2>SQL Error</h2> ".$sql." ".mysql_error($this->connection);
				$body   .= "<h2>Query History</h2>";
				foreach($this->query_history as $k => $v) {
					$body .= $k." = ".$v."<br>";
				}
				Utils::alert_admin($subject, $body);
				
				# Show a nice cryptic error
			    die("<h2>There's been an error processing your request (#DB138)</h2>");
		
			} else {
		 		die(Debug::dump("SQL Error: ".$sql." ".mysql_error($this->connection)));
			}
		}		
		
		// return sucessful result
		return $result;

	}


	/*-------------------------------------------------------------------------------------------------
	Dump the last query
	-------------------------------------------------------------------------------------------------*/
	public function last_query($dump = TRUE) {
		
		// last query
		$last_query = end($this->query_history);

		// last query benchmarks
		$last_query_benchmark = end($this->query_benchmarks);

		// toggle dumping output or just returning query string
		return ($dump) ? Debug::dump("($last_query_benchmark sec) ".$last_query, "Last MySQL Query") : $last_query;

	}
	

	/*-------------------------------------------------------------------------------------------------
	Show entire query history w/benchmarks
	-------------------------------------------------------------------------------------------------*/
	public function query_history($dump = TRUE) {
		
		$history = array();
		
		// store total execution time
		$total_execution = 0;
		
		// build array with benchmarks
		foreach ($this->query_history as $i => $query) {
			
			if (isset($this->query_benchmarks[$i])) {

				$query = '('.$this->query_benchmarks[$i].' sec) '.$query;
				$total_execution += $this->query_benchmarks[$i];
				
			}
				
			$history[] = $query;
		}
		
		// add total query execution time to end
		$history[] = "MySQL Total Execution: $total_execution sec";
		
		// toggle dumping output or just returning query history array
		return ($dump) ? Debug::dump($history, "MySQL Query History", FALSE) : $history;

	}


	/*-------------------------------------------------------------------------------------------------
	When you just want to get one single value from the database
	ex: "SELECT field FROM table WHERE id = 55"
	-------------------------------------------------------------------------------------------------*/
	public function select_field($sql) {

		$result = $this->query($sql);
		$row 	= mysql_fetch_array($result);
		$field  = $row[0];
		return $field;

	}


	/*-------------------------------------------------------------------------------------------------
	Optional $type can be 'assoc', 'array' or 'object'
	-------------------------------------------------------------------------------------------------*/	
	public function select_row($sql, $type = 'assoc') {

		$result = $this->query($sql);
		$mysql_fetch = 'mysql_fetch_'.$type;
		return $mysql_fetch($result);

	}
	
	
	/*-------------------------------------------------------------------------------------------------
	Alias to select_row for objects
	-------------------------------------------------------------------------------------------------*/
	public function select_object($sql) {
		
		return $this->select_row($sql, 'object');
		
	}


	/*-------------------------------------------------------------------------------------------------
	Returns all the rows in an array
	Optional $type can be 'assoc', 'array' or 'object'
	-------------------------------------------------------------------------------------------------*/
	public function select_rows($sql, $type = 'assoc') {

		$rows = array();
		$mysql_fetch = 'mysql_fetch_'.$type;

		$result = $this->query($sql);

		while($row = $mysql_fetch($result)) {
			$rows[] = $row;
		}

		return $rows;

	}
	
		
	/*-------------------------------------------------------------------------------------------------
	// return a key->value array given two columns
	// example: select_kv("SELECT id, name FROM table", 'id', 'name');
	-------------------------------------------------------------------------------------------------*/
	public function select_kv($sql, $key_column, $value_column) {
				
		$array = array();
		
		foreach ($this->select_rows($sql) as $row) {
			
			// avoid empty keys, but 0 is okay
			if ($row[$key_column] !== NULL && $row[$key_column] !== "")
				$array[$row[$key_column]] = $row[$value_column];
		}
		
		return $array;
		
	}
	
	
	/*-------------------------------------------------------------------------------------------------
	
	-------------------------------------------------------------------------------------------------*/
	public function select_array($sql, $key_column) {
	
		$array = array();
		
		foreach ($this->select_rows($sql) as $row) {
			
			// avoid empty keys, but 0 is okay
			if ($row[$key_column] !== NULL && $row[$key_column] !== "")
				$array[$row[$key_column]] = $row;
		}
		
		return $array;
	
	}


	/*-------------------------------------------------------------------------------------------------
	Insert a row given an array of key => values
	-------------------------------------------------------------------------------------------------*/
	public function insert($table, $data) {
						
		// setup insert statement
		$sql = "INSERT INTO $table SET";

		// add columns and values
		foreach ($data as $column => $value)
			$sql .= " $column = '".mysql_real_escape_string($value)."',";

		// remove trailing comma
		$sql = substr($sql, 0, -1);

		// perform query
		$this->query($sql);

		// return auto_increment id
		return mysql_insert_id();

	}


	/*-------------------------------------------------------------------------------------------------
	
	-------------------------------------------------------------------------------------------------*/
	public function insert_multiple($table, $fields, $data) {
	
		# Build the fields string. Ex: (person_id,first_name,email)
			foreach($fields as $field) {
				$fields_string .= $field.",";
			}
			# Remove last comma
			$fields_string = substr($fields_string, 0, -1);
				
		# Build the data string. Ex: (1,'Ethel','ethel@aol.com'),(3,'Leroy','leroy@hotmail.com'),(3,'Francis','francis@gmail.com')
			foreach($data as $row) {
				
				$data_string .= "(";
				foreach($row as $value) {
					$data_string .= "'".$value."',";
				}
				$data_string = substr($data_string, 0, -1);
				$data_string .= "),";
			}
			# Remove last comma
			$data_string = substr($data_string, 0, -1);
			
		# Put it all together	
			$sql = "INSERT INTO ".$table." (".$fields_string.") 
					VALUES ".$data_string;
					
		# Run it
			return $this->query($sql);
	
	}


	/*-------------------------------------------------------------------------------------------------
	If the row exists, update it. Otherwise add it as new.
	Requires you pass all field values, even if doing update.
	-------------------------------------------------------------------------------------------------*/
	public function update_or_insert($table, $data, $where_condition) {
	
		$result = self::update($table, $data, $where_condition);
		
		if (mysql_affected_rows() == 0) 
			self::insert($table, $data);
		
	}


	/*-------------------------------------------------------------------------------------------------
	Update a single row given an array of key => values
	example $where_condition: "WHERE id = 1 LIMIT 1"
	-------------------------------------------------------------------------------------------------*/
	public function update($table, $data, $where_condition) {
	
		// setup update statement
		$sql = "UPDATE $table SET";

		// add columns and values
		foreach ($data as $column => $value) {
			// allow setting columns to NULL
			if ($value === NULL) {
				$sql .= " $column = NULL,";
			} else {
				$sql .= " $column = '".mysql_real_escape_string($value)."',";
			}
		}

		// remove trailing comma
		$sql = substr($sql, 0, -1);

		// Add condition
		$sql .= " ".$where_condition;

		// perform query
		return $this->query($sql);
		
	}
	
	
	/*-------------------------------------------------------------------------------------------------
	
	-------------------------------------------------------------------------------------------------*/
	public function insert_multiple_rows($table, $data) {
	
		# Fields
			$fields_string = "";
			foreach($data[0] as $field => $row) {
				$fields_string .= $field.",";
			}
			
			$fields_string = substr($fields_string, 0, -1);
							
		# Rows
			$row_string = "";
			$rows_string = "";
			foreach($data as $row) {				
				$row_string = "(";
				foreach($row as $field => $value) {
					$row_string .= "'".mysql_real_escape_string($value)."',";
				}	
				$row_string   = substr($row_string, 0, -1);
				$row_string  .= "),";
				$rows_string .= $row_string;
			}
			
			$rows_string = substr($rows_string, 0, -1);
			
		# Query
			$q = "INSERT INTO ".$table."
				  (".$fields_string.")
				VALUES
				  ".$rows_string;
				  				
		# Run it
			$run = $this->query($q);
			return mysql_affected_rows();		  
				 
	}
	
	
	/*-------------------------------------------------------------------------------------------------
	Example SQL string we're aiming for:
	
		INSERT INTO tasks (person_id,first_name,email) 
		VALUES (1,'Ethel','ethel@aol.com'),(3,'Leroy','leroy@hotmail.com'),(3,'Francis','francis@gmail.com')
		ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),email=VALUES(email)'
		
		What this says in plain English:
		"Insert these values into the table; if the key already exists, then use the values to update the row instead"
		
	This is what a call to this method would like like:
	(The first field always has to be the primary id)
	
		$data[] = Array("person_id" => 1, "first_name" => 'Ethel', "email" => 'ethel@aol.com');
		$data[] = Array("person_id" => 2, "first_name" => 'Leroy', "email" => 'leroy@hotmail.com');
		$data[] = Array("person_id" => 3, "first_name" => 'Francis', "email" => 'francis@gmail.com.com');	
		$update = DB::instance("courses_webstartwomen_com")->update_multiple_rows('people', $data);
							
	// http://stackoverflow.com/questions/3432/multiple-updates-in-mysql			
	-------------------------------------------------------------------------------------------------*/
	public function update_multiple_rows($table, $data) {
	
		# Build the fields string. Ex: (person_id,first_name,email)
		# And the duplicate key update string. Ex: first_name=VALUES(first_name),email=VALUES(email)
		# We do this by using the indexes on the first row of data
		# NOTE: The index of the data array has to start at 0 in order for this to work
			$fields_string = ""; 
			$dupicate_key_string = "";
			foreach($data[0] as $index => $value) {
				$fields_string 		 .= $index.",";
				$dupicate_key_string .= $index."=VALUES(".$index."),";
			}
			
			# Remove last comma
			$fields_string = substr($fields_string, 0, -1);
			$dupicate_key_string = substr($dupicate_key_string, 0, -1);
				
		# Build the data string. Ex: (1,'Ethel','ethel@aol.com'),(3,'Leroy','leroy@hotmail.com'),(3,'Francis','francis@gmail.com')
			$data_string = "";
			foreach($data as $row) {
				
				$data_string .= "(";
				foreach($row as $value) {
					$data_string .= "'".mysql_real_escape_string($value)."',";
				}
				$data_string = substr($data_string, 0, -1);
				$data_string .= "),";
			}
			# Remove last comma
			$data_string = substr($data_string, 0, -1);
					
		# Put it all together	
			$sql = "INSERT INTO ".$table." (".$fields_string.") 
					VALUES ".$data_string."
					ON DUPLICATE KEY UPDATE ".$dupicate_key_string;
		
		# Run it
			$run = $this->query($sql);
			return mysql_affected_rows();	
	}


	/*-------------------------------------------------------------------------------------------------
	
	-------------------------------------------------------------------------------------------------*/
	public function delete($table, $where_condition) {

		$sql = 'DELETE FROM '.$table.' '.$where_condition; 

		return $this->query($sql);

	}
	
	
	
}
