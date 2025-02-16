<?php
	namespace ThreeDom\DataMage;

	/**
	 * This is the abstract classes all other database drivers us as a template.
	 * When you modify an abstract function be sure to follow through to the rest of
	 * the drivers in src/Driver
	 * 
	 * DataMage uses a concept in object-oriented programming called "Daisy-Chaining"
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
	 *	->select([Id, FirstName, LastName], 'Id')
	 * 	->from("Customers")
	 * 	->where('FirstName = ? AND DoB <= ?')
	 * 	->vars('John', 2004)
	 * 	->query();
	 * 
	 * Much cleaner!
	 */
	abstract class Command {
		public array $repVar = [];
		public string $query = '';
		public string $pKey = '';
		private array $results;

		/**
		 * Calling procedures
		 * @param string $procedure The name of the procedure to run
		 * @param string $pKey The column name to use as a primary key
		 * @return Command
		 */
		abstract public function call(string $procedure, string $pKey): Command;

		/**
		 * Beginning a select statement
		 * @param array $cols A list of columns to pull.
		 * @param string $pKey The column name to use as a primary key
		 * @return Command
		 */
		abstract public function select(array $cols, string $pKey): Command;

		/**
		 * Declaring the table
		 * @param string $table Name of the table to use
		 * @return Command
		 */
		abstract public function from(string $table): Command;

		/**
		 * Specifying filters
		 * @param string $filter Raw string of conditions to check for.
		 * @return Command
		 */
		abstract public function where(string $filter): Command;

		/**
		 * Ordering the record set
		 * @param string $order Which column or condition to order on.
		 * @return Command
		 */
		abstract public function order(string $order): Command;

		/**
		 * Limiting the records
		 * @return Command
		 */
		abstract public function limit(): Command;

		/**
		 * Providing the replacement variables.
		 * @return Command
		 */
		abstract public function vars(): Command;
		
		/**
		 * Running the query
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		abstract public function query(): void;

		/**
		 * Running the query and returning one result.
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		abstract public function queryOne(): void;

		/**
		 * Running and splicing the query down
		 * @param int $limit How much to limit the result by.
		 * @param int $offset What records to start from.
		 * @return void This should always be the final command processed so there's no need to daisy-chain.
		 */
		abstract public function queryMany(int $limit, int $offset=0): void;

		/**
		 * Function to return records as a collection of object
		 * @param string $model
		 * @return object|null
		 */
		abstract public function queryObject(string $model): ?object;
		
		/**
		 * Running a prepared statement
		 * 
		 * More flexible prepared statement.
		 * 
		 * This should depreciate itself as the project grows.
		 * @param string $query A string written in standard prepared statement format.
		 * @param array $params An array of parameters to prepare
		 * @return mixed Returns a prepared statement object, (differing depending upon the flavor)
		 */
		abstract public function prepare(string $query, array $params): mixed;

		/**
		 * Running a prepared statement and returning multiple record sets.
		 * Even more flexible prepared statement! Allows for multiple record sets.
		 * This shouldn't really be used, if you ever need to run multiple things it
		 * should be a StoredProcedure.
		 * @param string $query A string written in standard prepared statement format.
		 * @param array $params An array of parameters to prepare
		 * @return array|null This should always be the final command processed so there's no need to daisy-chain.
		 */
		abstract public function queryRaw(string $query, array $params): ?array;

		/**
		 * Check connection is alive.
		 * @return bool Returns 1 if the connection is alive. Returns 0 if it's ded.
		 */
		abstract public function ping(): bool;

		/**
		 * Turns on or off autoCommit.
		 * 
		 * Forcefully commits any insert, updates or deletes. Dangerous!
		 * @param int $val Can either be 1 or 0. Enables/Disables autoCommit.
		 * @return bool Returns 1 if the action completed. Returns 0 if it did not.
		 */
		abstract public function autoCommit(int $val): bool;

		/**
		 * Close the connection.
		 * @return bool Returns 1 if the connection no longer exists. Returns 0 if it does.
		 */
		abstract public function close(): bool;

		/**
		 * Reset the query for the next one.
		 * 
		 * Empties the query string, replace variable array and primary key. If the query is already empty does nothing.
		 * @return void This should always be run on its own and doesn't return a value.
		 */
		public function emptyQuery(): void {
			if($this->query == '') {
				return;
			}

			$this->query = '';
			$this->repVar = [];
			$this->pKey = '';
		}

		/**
		 * Gets a cancelled list of columns
		 * 
		 * Prepares a list of columns and cancels reserved words.
		 * @param array $cols The list of columns to cancel out.
		 * @return array
		 */
		public function cols(array $cols): array {
			$colList = [];

			foreach($cols as $v) {
				$colList[] = $this->cancelReserve($v);
			}

			return $colList;
		}

		/**
		 * Cancel reserved words.
		 * 
		 * Cancels reserved words (Words like 'SELECT', 'JOIN', 'UPDATE' before allowing the query to run)
		 * 
		 * Each Driver defines its own reserved words.
		 * @param string $word The word to cancel
		 * @return string Returns the cancelled word (if it was cancelled)
		 */
		public function cancelReserve(string $word): string {
			if(in_array($word, $this->reservedWord)) {
				return '`' . $word . '`';
			}
			return $word;
		}

        public function getResults(): array {
            $r = $this->results;
            $this->results = [];

            return $r;
        }

        public function getResult(mixed $id): array {
            $r = $this->results;
            $this->results = [];

            $record = [];
            if(array_key_exists($id, $r)) {
                $record = $r[$id];
            }

            return $record;
        }

        public function setResults(array $r): void {
            $this->results = $r;
        }
	}