<?php
	namespace ThreeDom\DataMage;

	class Model {

		private array $schema;
		public array $active, $results;
		public string $table, $pKey;
		public object $con;

		/**
		 * Very bare bones Model class.
		 *
		 * When using a Database to store information regarding your application
		 * it's often good practice to store this information in little "bags
		 * of data". The best kind of "bag of data" in my opinion is a class...
		 * okay technically it's a struct, but we don't have those so!
		 * 
		 * Here's an example of a schema this Model class could use:
		 * 
		 * 	[
		 *		'info' => [
		 *			'table' => 'User',
		 *			'p_key' => 'UserID'
		 *		],
		 *
		 *		'cols' => [
		 *			'UserID' => ['map' => 'UserID']
		 *			'FirstName' => ['map' => 'FirstName'],
		 *			'LastName' => ['map' => 'LastName']
		 *			'Email' => ['map' => 'Email'],
		 *			'Bio' => ['map' => 'Description'],
		 *		]
		 * ];
		 * 
		 * This provides us with some benefits over direct SQL manipulation:
		 * We create a map of columns, binding SQL columns to "Aliases" that
		 * the business logic of our application uses. This means that if you change
		 * the schema of the SQL database updating the application is MUCH more controlled.
		 * 
		 * @param Command $con Using our package's database drivers is essential. The model was built for it.
		 * @param array $schema A schema as described in the above description.
		 * @param mixed|null $id Optional Parameter for pre-fetching information about a specific ID
		 * @return void
		 */
		public function __construct(Command $con, array $schema, int $id = NULL) {
			$this->table = $schema['info']['table'];
			$this->pKey = $schema['info']['p_key'];

			if(!$id) {
				return;
			}

			$this->find($id, $con, $schema);
		}

		/**
		 * Used for pulling specific records
		 * 
		 * Should only ever ba called if our object was supplied with an ID.
		 * In this circumstance we preload the object's results with data
		 * from the server with a matching ID.
		 * @param int $id The ID to search for
		 * @return void Hydrates the class so doesn't need to return anything.
		 */
		private function find(int $id, Command $con, array $schema): void {
			$this->with($this->pKey, $id, $con, $schema);
			$this->active = $this->results[$id];
		}

		/**
		 * Bulk search of the model's defined table for matching records.
		 *
		 * If an ID was not provided on the models initialization, we can
		 * instead choose to do a search later on.
		 * @param string $col The column to search for (need to conver this to the mapped column)
		 * @param mixed $val The value which is should equal
		 * @param Command $con
		 * @param array $schema
		 * @return void
		 */
		public function with(string $col, mixed $val, Command $con, array $schema): void {

			# Begin with a select statement, we don't need all the rows off the table,
			# as we only care about what our schema will pull.
			$con
				->select($this->colsFromSchema($con, $schema['cols']), $this->pKey)
				->from($this->table)
				->where($col . ' = ?')
				->vars($val)
				->query();


			# Buckle up for this shit.
			# We create a map, a reference to our schema and a reference to our key.
			$map = [];
			$primKey = $this->pKey;

			# Loop over our found records
			foreach($con->results as $row) {
				# Begin a dictionary for each row
				$mR = [];
				# Get the primary key value for each.
				$primVal = $row[$primKey];

				# Take each column and map it to our schema.
				foreach($row as $k => $v) {
					$col = $schema['cols'][$k];
					$mR[$col['map']] = $v;
				}
				# Add the row to the map, using the primary key as reference.
				$map[$primVal] = $mR;
			}

			# Set our results to the new map.
			$this->results = $map;
		}

		/**
		 * Pulls the first result from a result list
		 * @return mixed
		 */
		public function firstResult(): array {
			$firstID = array_key_first($this->results);
			return $this->results[$firstID];
		}

		/**
		 * Pulls the last result from a result list
		 * @return mixed
		 */
		public function lastResult(): array {
			$firstID = array_key_last($this->results);
			return $this->results[$firstID];
		}

		/**
		 * Quick little method for getting a SQL acceptable list of
		 * column names (as defined by the model's schema).
		 * @return array Should always return an array of strings.
		 */
		public function colsFromSchema(Command $con, array $schema): array {
			$cols = [];
			foreach($schema as $k => $v) {
				$cols[] = $con->cancelReserve($k);
			}
			
			return $cols;
		}

		/**
		 * Updates the local Schema for the active record
		 * 
		 * We update the local model first. This means we can write cleaner
		 * code and not have to worry about constantly updating the SQL.
		 * 
		 * Instead, we can write a "sync" method which can run a comparison
		 * on the existing row's information (stored via a Hash?) and decide
		 * what to do with the data based upon this.
		 * 
		 * @param mixed $key The name of the column we are updating.
		 * @param mixed $val The value in which we will populate it with.
		 * @return void
		 */
		public function setL(string $key, mixed $val): void {
			$this->active[$key] = $val;
		}

		/**
		 * Small method to make retrieving the active record's information easier.
		 * 
		 * $user->getL('FirstName') 
		 * 
		 * Would return the first name of the user in the model (Not necessarily the 
		 * database's value if the model has not synced.)
		 * @param mixed $key The name of the column to search for
		 * @return mixed The value of the column we searched for
		 */
		public function getL(string $key): mixed {
			return $this->active[$key];
		}
	}