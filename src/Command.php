<?php
	namespace ThreeDom\Pulse;

	/**
	 * This is the abstract class that all other database drivers us as a template.
	 * When you modify an abstract function be sure to follow through to the rest of
	 * the drivers in src/Driver
	 *
	 * Pulse uses a concept in object-oriented programming called "Daisy-Chaining"
	 * This essentially means it almost always returns itself. This is really cool!
	 *
	 * Instead of doing the following:
	 *
	 * $con->select([Id, FirstName, LastName], 'Id');
	 * $con->from('Customers');
	 * $con->where('FirstName = ? AND DoB <= ?');
	 * $con->vars('John', 2004);
	 * $con->query();
	 *
	 * We can do THIS!
	 *
	 * $con
	 *    ->select([Id, FirstName, LastName], 'Id')
	 *    ->from("Customers")
	 *    ->where('FirstName = ? AND DoB <= ?')
	 *    ->vars('John', 2004)
	 *    ->query();
	 *
	 * Much cleaner!
	 */
	abstract class Command
	{
		protected array $repVar = [];

		protected string $query = '';

		protected string $pKey = '';

		private array $results;

		/**
		 * Adding the values for insertion.
		 *
		 * @param int $count The column count that we'll be inputting.
		 *
		 * @return Command
		 */
		abstract public function values(int $count): Command;

		/**
		 * Limiting the records
		 *
		 * @param ?int $x - Value to be used as the limit
		 *
		 * @return Command
		 */
		abstract public function limit(?int $x): Command;

		/**
		 * Offsets the records
		 *
		 * @param ?int $x - Value to be used as the limit
		 *
		 * @return Command
		 */
		abstract public function offset(?int $x): Command;

		/**
		 * Function to return records as a collection of object
		 *
		 * @param string $model
		 *
		 * @return object|null
		 */
		abstract public function queryObject(string $model): ?object;

		/**
		 * Running a prepared statement
		 *
		 * More flexible prepared statement.
		 *
		 * This should depreciate itself as the project grows.
		 *
		 * @param string $query  A string written in standard prepared statement format.
		 * @param array  $params An array of parameters to prepare
		 *
		 * @return mixed Returns a prepared statement object, (differing depending upon the flavor)
		 */
		abstract public function prepare(string &$query, array &$params): mixed;

		/**
		 * Running a prepared statement and returning multiple record sets.
		 * Even more flexible prepared statement! Allows for multiple record sets.
		 * This shouldn't really be used, if you ever need to run multiple things it
		 * should be a StoredProcedure.
		 *
		 * @param string $query  A string written in standard prepared statement format.
		 * @param array  $params An array of parameters to prepare
		 *
		 * @return array|null This should always be the final command processed so there's no need to daisy-chain.
		 */
		abstract public function queryRaw(string &$query, array &$params): ?array;

		/**
		 * Check connection is alive.
		 *
		 * @return bool Returns 1 if the connection is alive. Returns 0 if it's ded.
		 */
		abstract public function ping(): bool;

		/**
		 * Turns on or off autoCommit.
		 *
		 * Forcefully commits any insert, updates or deletes. Dangerous!
		 *
		 * @param int $val Can either be 1 or 0. Enables/Disables autoCommit.
		 *
		 * @return bool Returns 1 if the action completed. Returns 0 if it did not.
		 */
		abstract public function autoCommit(int $val): bool;

		/**
		 * Close the connection.
		 *
		 * @return bool Returns 1 if the connection no longer exists. Returns 0 if it does.
		 */
		abstract public function close(): bool;

		/**
		 * Calling procedures
		 *
		 * @param string  $procedure The name of the procedure to run
		 * @param ?string $pKey      The column name to use as a primary key
		 *
		 * @return Command
		 */
		public function call(string $procedure, ?string $pKey = NULL): Command
		{
			$this->emptyQuery();
			if($pKey)
				$this->pKey = $pKey;

			$this->query = "CALL $procedure;";

			return $this;
		}

		/**
		 * Runs an update statement
		 *
		 * @param string $table The table we're updating
		 * @param array  $data  An array of columns to update.
		 * @param array  $vals  An array of values to bind.
		 *
		 * @return Command
		 */
		public function update(string $table, array $data, array $vals): Command
		{
			$binds = '';
			$this->query = "UPDATE $table SET ";
			$this->cols($data);

			foreach($data as $col)
				$binds .= $col . ' = ?, ';

			$binds = substr($binds, 0, -2);
			$this->query .= $binds;
			$this->vars(...$vals);

			return $this;
		}

		/**
		 * Beginning an insert statement
		 *
		 * @param array  $cols  The columns to populate.
		 * @param string $table The table we're inserting into
		 *
		 * @return Command
		 */
		public function insert(array $cols, string $table): Command
		{
			$this->emptyQuery();
			$this->cols($cols);

			$this->query = "INSERT INTO $table(" . implode(',', $cols) . ') VALUES';
			$this->values(sizeof($cols));

			return $this;
		}

		/**
		 * Beginning a select statement
		 *
		 * @param array   $cols A list of columns to pull.
		 * @param ?string $pKey The column name to use as a primary key
		 *
		 * @return Command
		 */
		public function select(array $cols, ?string $pKey = NULL): Command
		{
			$this->emptyQuery();
			if($pKey)
				$this->pKey = $pKey;

			$this->cols($cols);
			$this->query = 'SELECT ' . implode(',', $cols);

			return $this;
		}

		/**
		 * Declaring the table
		 *
		 * @param string $table Name of the table to use
		 *
		 * @return Command
		 */
		public function from(string $table): Command
		{
			$this->cancelReserve($table);
			$this->query .= ' FROM ' . $table;

			return $this;
		}

		/**
		 * Specifying filters
		 *
		 * @param string $filter Raw string of conditions to check for.
		 *
		 * @return Command
		 */
		public function where(string $filter): Command
		{
			$this->query .= ' WHERE ' . $filter;

			return $this;
		}

		/**
		 * Appending or replacing the replacement variables.
		 *
		 * @param mixed ...$vars Unpacked array of variables. Will auto-detect types.
		 *
		 * @return Command
		 */
		public function vars(...$vars): Command
		{
			$this->repVar = $vars;

			return $this;
		}

		/**
		 * Ordering the record set
		 *
		 * @param string $order Which column or condition to order on.
		 *
		 * @return Command
		 */
		public function order(string $order): Command
		{
			$this->query .= ' ORDER BY ' . $order;

			return $this;
		}

		/**
		 * Running the query
		 *
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		public function query(): void
		{
			$q = &$this->query;
			$r = &$this->repVar;

			$this->setResults($this->queryRaw($q, $r));
			$this->emptyQuery();
		}

		/**
		 * Running the query and returning one result.
		 *
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		public function queryOne(): void
		{
			$q = &$this->query;
			$r = &$this->repVar;

			$rs = $this->queryRaw($q, $r);
			$this->setResults(array_slice($rs, 0, 1, TRUE));
			$this->emptyQuery();
		}

		/**
		 * Running and splicing the query down
		 *
		 * @param int $limit  How much to limit the result by.
		 * @param int $offset What records to start from.
		 *
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		public function queryMany(int $limit, int $offset = 0): void
		{
			$q = &$this->query;
			$r = &$this->repVar;
			$rs = $this->queryRaw($q, $r);
			$this->setResults(array_slice($rs, $offset, $limit, TRUE));

			$this->emptyQuery();
		}

		/**
		 * Reset the query for the next one.
		 *
		 * Empties the query string, replace variable array and primary key. If the query is already empty does nothing.
		 *
		 * @return void This should always be run on its own and doesn't return a value.
		 */
		protected function emptyQuery(): void
		{
			if($this->query == '')
				return;

			$this->query = '';
			$this->repVar = [];
			$this->pKey = '';
		}

		/**
		 * Gets a cancelled list of columns
		 *
		 * Prepares a list of columns and cancels reserved words.
		 *
		 * @param array &$cols The list of columns to cancel out.
		 */
		protected function cols(array &$cols): void
		{
			foreach($cols as &$col)
				$this->cancelReserve($col);
		}

		/**
		 * Cancel reserved words.
		 *
		 * Cancels reserved words (Words like 'SELECT', 'JOIN', 'UPDATE' before allowing the query to run)
		 *
		 * Each Driver defines its own reserved words.
		 *
		 * @param string &$word The word to cancel
		 */
		protected function cancelReserve(string &$word): void
		{
			$word = in_array($word, $this->reservedWord) ? '`' . $word . '`' : $word;
		}

		protected function buildFromArray(mixed $rs): array
		{
			$recordSets = [];
			$pKey = &$this->pKey;

			if($pKey)
			{
				if(sizeof($rs) === 1)
				{
					$recordSets[$rs[0][$pKey]] = $rs[0];

					return $recordSets;
				}

				foreach($rs as $row)
				{
					$recordSets[$row[$pKey]][] = $row;
				}

				return $recordSets;
			}

			foreach($rs as $row)
			{
				$recordSets[] = $row;
			}

			return $recordSets;
		}

		public function getResults(): array
		{
			$r = $this->results;
			$this->results = [];

			return $r;
		}

		public function getResult(mixed $id): array
		{
			$r = $this->results;
			$this->results = [];

			return array_key_exists($id, $r) ? $r[$id] : [];
		}

		public function setResults(array $r): void
		{
			$this->results = $r;
		}
	}