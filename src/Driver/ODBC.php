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

		public function limit(?int $x=NULL): Command {
			$this->query = preg_replace('/^SELECT/', 'SELECT TOP ' . ($x ?: '?'), $this->query);
			return $this;
		}

        public function offset(?int $x=NULL): Command
        {
            //$todo;
            return $this;
        }

		public function queryObject(string $model): object|null {
			return null;
		}

		public function prepare(string &$query, array &$params=[]): mixed {
			return odbc_prepare($this->con, $query);
		}

		public function queryRaw(string &$query, array $params): ?array {
			$recordSets = [];
			$stmt = $this->prepare($query);
			odbc_execute($stmt, $params);
			$rs = $this->getResult($stmt);
			
			$multiQuery = odbc_next_result($stmt);

			if(!$rs)
				return [];

			$recordSets[] = $rs;

			if(!$multiQuery)
				return $recordSets;

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

        public function values(int $cols): Command
        {
            // TODO: Implement values() method.
            return $this;
        }
    }