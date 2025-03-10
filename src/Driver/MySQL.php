<?php
	namespace ThreeDom\DataMage\Driver;

	use ThreeDom\DataMage\Command;

	use mysqli;
	use mysqli_sql_exception;
	use mysqli_stmt;

	class MySQL extends Command
	{

		public mysqli $con;
		public array $reservedWord = ['Condition', 'Desc', 'Group', 'Database', 'File', 'Subject', 'Locked'];

		public function __construct(string $src, string $usr, string $pas, string $db, $con = NULL)
		{
			try
            {
				$this->con = $con ?? new mysqli($src, $usr, $pas, $db);
			}
            catch (mysqli_sql_exception  $e)
            {
				die($e->getMessage());
			}
		}

        public function limit(?int $x=NULL): Command
        {
            $this->query .= ' LIMIT ' . ($x ?: '?');
            return $this;
        }

        public function offset(?int $x=NULL): Command
        {
            $this->query .= ',' . ($x ?: '?');
            return $this;
        }

		public function values(int $count): Command
		{
			$this->query .= '(' . str_repeat('?,', $count - 1) . '?)';
			return $this;
		}

		public function queryObject(string $model = ''): ?object
		{
			$q = $this->query;
			$r = $this->repVar;
			$stmt = $this->prepare($q, $r);

			$stmt->execute();
			$rs = $stmt->get_result();

			if (!$rs)
				return NULL;

			return $rs->fetch_object($model);
		}

		public function prepare(string &$query, array &$params): mysqli_stmt
		{
			$stmt = $this->con->prepare($query);
			if ($params)
            {
				$args = $this->paramFromArray([...$params]);
				$stmt->bind_param($args, ...$params);
			}

			return $stmt;
		}

		public function queryRaw(string &$query, array &$params = []): ?array
		{
			$recordSets = [];
			$stmt = $this->prepare($query, $params);
			$stmt->execute();
			$rs = $stmt->get_result();

			$multiQuery = $stmt->more_results();

			if (!$rs)
				return [];

			if (!$multiQuery)
            {
                $recordSets = $this->buildFromArray($rs);
				$stmt->close();
				return $recordSets;
			}

			while ($stmt->more_results()) {
                $recordSets[] = $this->buildFromArray($rs);
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
		public function paramFromArray(array $array): string
		{
			$args = '';
			foreach ($array as $x) {
				$args .= match (gettype($x)) {
					'integer' => 'i',
					'double' => 'd',
					default => 's',
				};
			}

			return $args;
		}

		public function ping(): bool
		{
			return $this->con->ping();
		}

		public function autoCommit(int $val): bool
		{
			return $this->con->autocommit($val);
		}

		public function close(): bool
		{
			if (!$this->ping())
				return FALSE;

			return $this->con->close();
		}
	}