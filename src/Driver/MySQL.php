<?php
	namespace ThreeDom\DataMage\Driver;

	use ThreeDom\DataMage\Command;

	use mysqli;
	use mysqli_sql_exception;
	use mysqli_stmt;

	class MySQL extends Command {

		public mysqli $con;
		public array $results;
		public array $reservedWord = ['Condition', 'Desc', 'Group', 'Database'];
		public array $repVar = [];
		public string $query = '';
		public string $pKey = '';


		public function __construct(string $src, string $usr, string $pas, string $db, $con = null) {
			try {
				$this->con = $con ?? new mysqli($src, $usr, $pas, $db);
			} catch(mysqli_sql_exception  $e) {
				die($e->getMessage());
			}
		}
		
		public function call(string $procedure, string $pKey=null): Command {
			$this->emptyQuery();
			if($pKey) {
				$this->pKey = $pKey;
			}
			$this->query = "CALL $procedure;";
			return $this;
		}

		public function select(array $cols, string $pKey=null): Command {
			$this->emptyQuery();
			if($pKey) {
				$this->pKey = $pKey;
			}
			$this->query = 'SELECT ' . implode(',', $this->cols($cols));
			return $this;
		}

		public function from(string $table): Command {
			$this->query .= ' FROM ' . $this->cancelReserve($table);
			return $this;
		}

		public function where(string $filter): Command {
			$this->query .= " WHERE $filter";
			return $this;
		}

		public function order(string $order): Command {
			$this->query .= " ORDER BY $order";
			return $this;
		}

		public function limit(): Command {
			$this->query .= ' LIMIT ?, ?;';
			return $this;
		}

		public function vars(...$vars): Command {
			$this->repVar = $vars;
			return $this;
		}

		public function query(): void {
			$this->results = [];

			$q = $this->query;
			$r = $this->repVar;

			$this->results = $this->queryRaw($q, $r);
			$this->emptyQuery();
		}
		
		public function queryObject(string $model = ''): ?object {
			$q = $this->query;
			$r = $this->repVar;
			$stmt = $this->prepare($q, $r);

			$stmt->execute();
			$rs = $stmt->get_result();

			if(!$rs) { 
				return null;
			}

			return $rs->fetch_object($model);
		}

		public function queryOne(): void {
			$this->results = [];

			$q = $this->query;
			$r = $this->repVar;

			$rs = $this->queryRaw($q, $r);
			$this->results = array_slice($rs, 0, 1, true);
			$this->emptyQuery();
		}
		
		public function queryMany(int $limit, int $offset=0): void {
			$this->results = [];

			$q = $this->query;
			$r = $this->repVar;
			$rs = $this->queryRaw($q, $r);
			$this->results = array_slice($rs, $offset, $limit, true);

			$this->emptyQuery();
		}

		public function prepare(string $query, array $params): mysqli_stmt {
			$stmt = $this->con->prepare($query);
			if($params) {
				$args = $this->paramFromArray([...$params]);
				$stmt->bind_param($args, ...$params);
			}

			return $stmt;
		}

		public function queryRaw(string $query, array $params = []): ?array {
			$recordSets = [];
			$stmt = $this->prepare($query, $params);
			$stmt->execute();
			$rs = $stmt->get_result();

			$multiQuery = $stmt->more_results();

			if(!$rs) { 
				return [];
			}
			
			if(!$multiQuery) {	
				foreach($rs as $row) {
					if($this->pKey) {
						$recordSets[$row[$this->pKey]] = $row;
						continue;
					}
					$recordSets[] = $row;
				}
				
				$stmt->close();
				return $recordSets;
			}

			while($stmt->more_results()) {
				$tmp = [];
				
				foreach($rs as $row) {
					if($this->pKey) {
						$tmp[$row[$this->pKey]] = $row;
						continue;
					}
					$tmp[] = $row;
				}

				$recordSets[] = $tmp;
				$stmt->next_result();
				$rs = $stmt->get_result();
			}

			$stmt->close();
			return $recordSets;
		}
 /**
         * Gets SQL parameter types from an array.
		 * 
		 * Very simple and can have more conditions added. Simply iterates over the argument array and populates a string for binding the parameters.
         * @param array $array The list of parameters you are using for your prepared statement.
         * 
         * @return string Returns the string the argument list created
         */
		public function paramFromArray(array $array): string {
			$args = '';
			foreach($array as $x) {
				$args .= match (gettype($x)) {
					'integer' => 'i',
					'double' => 'd',
					default => 's',
				};
			}
			return $args;
		}

		public function ping(): bool {
			return $this->con->ping();
		}

		public function autoCommit(int $val): bool {
			return $this->con->autocommit($val);
		}

		public function close(): bool {
			if(!$this->ping()) {
				return false;
			}
			
			return $this->con->close();
		}
	}