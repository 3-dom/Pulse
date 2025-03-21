<?php
	namespace ThreeDom\Pulse\Driver;

	use PgSql\Result;
	use PgSql\Connection;
	use ThreeDom\Pulse\Command;
	use ThreeDom\Pulse\Command\QueryType;

	class PostGres extends Command
	{
		public Connection|false $con;

		private int $subCount = 1;

		public array $reservedWord
			= ['ALL', 'ANALYSE', 'ANALYZE', 'AND', 'ANY', 'ARRAY', 'AS', 'ASC', 'ASYMMETRIC', 'AUTHORIZATION', 'BINARY', 'BOTH',
				'CAST', 'CHECK', 'COLLATE', 'COLUMN', 'CONSTRAINT', 'CREATE', 'CROSS', 'CURRENT_CATALOG', 'CURRENT_DATE',
				'CURRENT_ROLE', 'CURRENT_SCHEMA', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'DEFAULT', 'DEFERRABLE',
				'DESC', 'DISTINCT', 'DO', 'ELSE', 'END', 'EXCEPT', 'FETCH', 'FALSE', 'FOR', 'FOREIGN', 'FROM', 'FULL', 'GRANT',
				'GROUP', 'HAVING', 'ILIKE', 'IN', 'INITIALLY', 'INNER', 'INTERSECT', 'INTO', 'IS', 'ISNULL', 'JOIN', 'LATERAL',
				'LEADING', 'LEFT', 'LIKE', 'LIMIT', 'LOCALTIME', 'LOCALTIMESTAMP', 'NATURAL', 'NOT', 'NOTNULL', 'NULL', 'OFFSET',
				'ON', 'ONLY', 'OR', 'ORDER', 'OUTER', 'OVERLAPS', 'PLACING', 'PRIMARY', 'REFERENCES', 'RETURNING', 'RIGHT',
				'SELECT', 'SESSION_USER', 'SIMILAR', 'SOME', 'SYMMETRIC', 'SYSTEM_USER', 'TABLE', 'TABLESAMPLE', 'THEN', 'TO',
				'TRAILING', 'TRUE', 'UNION', 'UNIQUE', 'USING', 'VERBOSE', 'VARIADIC', 'WHEN', 'WHERE', 'WINDOW'];

		public string|false $schema;

		public function __construct(string $src, string $usr, string $pas, string $db, string $schema = NULL, $con = NULL)
		{
			$this->con = $con ?? \pg_connect("host=$src dbname=$db user=$usr password=$pas")
			or die('Connection Refused');

			if($schema)
				pg_query($this->con, "SET search_path TO $schema");

			$this->schema = $schema;
		}

		public function limit(?int $x = NULL): Command
		{
			$this->query .= ' LIMIT ' . ($x !== NULL ? $x : '?');

			return $this;
		}

		public function offset(?int $x = NULL): Command
		{
			$this->query .= ' OFFSET ' . ($x !== NULL ? $x : '?');

			return $this;
		}

		public function values(int $count): Command
		{
			$this->query .= '(';
			for($i = 1; $i < $count; $i++)
				$this->query .= "$$i,";
			$this->query .= "$$i)";

			return $this;
		}

		public function queryObject(string $model = ''): ?object
		{
			$q = $this->query;
			$r = $this->repVar;
			$result = $this->prepare($q, $r);

			if(!$result)
				return NULL;

			return pg_fetch_object($result, $model);
		}

		public function prepare(string &$query, array &$params): false|Result
		{
			if($params)
			{
				$this->substitueValues($query);

				return pg_query_params($this->con, $query, $params);
			}

			return pg_query($this->con, $query);
		}

		public function queryRaw(string &$query, array &$params = []): ?array
		{
			switch($this->queryType)
			{
				case QueryType::INSERT:
					$query .= "RETURNING *";
					break;
				default:
					break;
			}

			$result = $this->prepare($query, $params);

			if(!$result)
				return [];

			$rs = [];
			while($row = pg_fetch_assoc($result))
			{
				$rs[] = $row;
			}

			return $this->pKey ? $this->buildFromArray($rs) : $rs;
		}

		public function ping(): bool
		{
			return pg_ping($this->con);
		}

		public function autoCommit(int $val): bool
		{
			return pg_query($this->con, "SET AUTOCOMMIT=$val") !== FALSE;
		}

		public function close(): bool
		{
			if(!$this->ping())
				return FALSE;

			return pg_close($this->con);
		}

		public function substitueValues(string &$query): void
		{
			$mask = ['"', '\'', '`', '?'];
			$quotes = ['"', '\'', '`'];

			$depth = 0;
			$lastQ = '';

			for($i = 0; $i < strlen($query); $i++)
			{
				$char = $query[$i];

				if(!in_array($char, $mask))
				{
					continue;
				}

				if(in_array($char, $quotes))
				{
					$depth += ($char === $lastQ ? -1 : 1);
					$lastQ = $char;
					continue;
				}

				if($depth > 0)
				{
					continue;
				}

				$query = substr_replace($query, $this->subCount(), $i, 1);
			}

			$this->subCount = 1;
		}

		public function subCount()
		{
			return '$' . $this->subCount++;
		}
	}