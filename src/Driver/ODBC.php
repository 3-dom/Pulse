<?php
	namespace ThreeDom\DataMage\Driver;

	use ThreeDom\DataMage\Command;
	use Exception;

	class ODBC extends Command {

		public mixed $con;
		public array $results;
		public array $reservedWord = ['user', 'group'];

		public function __construct(string $src, string $usr, string $pas, $con = null) {
			try {
				$this->con = $con ?? odbc_connect($src, $usr, $pas);
			} catch(Exception $e) {
				die('Server Connection failed.' . $e->getMessage());
			}
		}

		public function call(string $procedure, string $pKey=NULL): Command {
			$this->emptyQuery();
			if($pKey) {
				$this->pKey = $pKey;
			}
			$this->query = "CALL $procedure;";
			return $this;
		}

		public function select(array $cols, string $pKey=NULL): Command {
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
			$this->query .= ' WHERE ' . $filter;
			return $this;
		}

		public function order(string $order): Command {
			$this->query .= ' ORDER BY ' . $order;
			return $this;
		}

		public function limit(int $size=0): Command {
			$this->query = preg_replace('/^SELECT/', "SELECT TOP $size", $this->query);
			return $this;
		}

		public function vars(...$vars): Command {
			$this->repVar = $vars;
			return $this;
		}
		
		public function query(): void {
			$q = $this->query;
			$r = $this->repVar;

			$this->setResults($this->queryRaw($q, $r));
			$this->emptyQuery();
		}

		public function queryObject(string $model): object|null {
			return null;
		}

		public function queryOne(): void {
			$q = $this->query;
			$r = $this->repVar;

			$rs = $this->queryRaw($q, $r);
			$this->setResults(array_slice($rs, 0, 1, true));
			$this->emptyQuery();
		}

		public function queryMany(int $limit, int $offset=0): void {
			$q = $this->query;
			$r = $this->repVar;
			$rs = $this->queryRaw($q, $r);
            $this->setResults(array_slice($rs, $offset, $limit, true));

			$this->emptyQuery();
		}

		public function prepare(string $query, array $params=[]): mixed {
			return odbc_prepare($this->con, $query);
		}

		public function queryRaw(string $query, array $params): ?array {
			$recordSets = [];
			$stmt = $this->prepare($query);
			odbc_execute($stmt, $params);
			$rs = $this->getResult($stmt);
			
			$multiQuery = odbc_next_result($stmt);

			if(!$rs) {
				return [];
			}
			$recordSets[] = $rs;

			if(!$multiQuery) {
				return $recordSets;
			}

			do {
				$recordSets[] = $this->getResult($stmt);
			} while(odbc_next_result($stmt));

			return $recordSets;
		}

		public function getResult($stmt): array {
			$rs = [];

			$i = 1;
			while($row = odbc_fetch_array($stmt, $i)) {
				$i++;

				if($this->pKey) {
					$rs[$row[$this->pKey]] = $row;
					continue;
				}
				$rs[] = $row;
			}

			return $rs;
		}

		public function ping(): bool {
			return $this->con != null;
		}

		public function autoCommit(int $val): bool {
			return odbc_autocommit($this->con, boolval($val));
		}

		public function close(): bool {
			odbc_close($this->con);

			return !$this->ping();
		}

	}