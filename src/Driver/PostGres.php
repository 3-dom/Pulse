<?php
    namespace ThreeDom\DataMage\Driver;

    use PgSql\Result;
    use ThreeDom\DataMage\Command;

    class PostGres extends Command
    {
        public \PgSql\Connection|false $con;
        public array $reservedWord =
            ['ALL', 'ANALYSE','ANALYZE','AND','ANY','ARRAY','AS','ASC','ASYMMETRIC','AUTHORIZATION','BINARY','BOTH',
            'CAST','CHECK','COLLATE','COLUMN','CONSTRAINT','CREATE','CROSS','CURRENT_CATALOG','CURRENT_DATE',
            'CURRENT_ROLE','CURRENT_SCHEMA','CURRENT_TIME','CURRENT_TIMESTAMP','CURRENT_USER','DEFAULT','DEFERRABLE',
            'DESC','DISTINCT','DO','ELSE','END','EXCEPT','FETCH','FALSE','FOR','FOREIGN','FROM','FULL','GRANT',
            'GROUP','HAVING','ILIKE','IN','INITIALLY','INNER','INTERSECT','INTO','IS','ISNULL','JOIN','LATERAL',
            'LEADING','LEFT','LIKE','LIMIT','LOCALTIME','LOCALTIMESTAMP','NATURAL','NOT','NOTNULL','NULL','OFFSET',
            'ON','ONLY','OR','ORDER','OUTER','OVERLAPS','PLACING','PRIMARY','REFERENCES','RETURNING','RIGHT',
            'SELECT','SESSION_USER','SIMILAR','SOME','SYMMETRIC','SYSTEM_USER','TABLE','TABLESAMPLE','THEN','TO',
            'TRAILING','TRUE','UNION','UNIQUE','USING','VERBOSE','VARIADIC','WHEN','WHERE','WINDOW'];

        public function __construct(string $src, string $usr, string $pas, string $db, string $schema = NULL, $con = NULL)
        {
            $this->con =
                $con
                ?? \pg_connect("host=$src dbname=$db user=$usr password=$pas")
                or die('Connection Refused');

            if($schema) {
                pg_query($this->con, "SET search_path TO $schema");
            }
        }

        #[\Override]
        public function from(string $table): Command {
            $this->cancelReserve($table);
            $this->appendSchema($table);

            $this->query .= ' FROM ' . $this->cancelReserve($table);
            return $this;
        }

        public function limit(): Command
        {
            $this->query .= ' LIMIT ?, ?;';
            return $this;
        }

        public function values(int $count): Command
        {
            $this->query .= '(';
            for($i = 1; $i <= $count; $i++)
                $this->query .= "$$i,";
            $this->query .= '?)';
            
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

        public function prepare(string $query, array $params): false|Result
        {
            $stmt = pg_prepare($this->con, '', $query);

            if ($params) {
                $args = $this->paramFromArray([...$params]);
                $stmt->bind_param($args, ...$params);
            }

            return pg_prepare($this->con, '', $query);
        }

        public function queryRaw(string $query, array $params = []): ?array
        {
            $recordSets = [];
            $stmt = $this->prepare($query, $params);
            $stmt->execute();
            $rs = $stmt->get_result();

            $multiQuery = $stmt->more_results();

            if (!$rs)
                return [];

            if (!$multiQuery) {
                foreach ($rs as $row) {
                    if ($this->pKey) {
                        $recordSets[$row[$this->pKey]] = $row;
                        continue;
                    }
                    $recordSets[] = $row;
                }

                $stmt->close();
                return $recordSets;
            }

            while ($stmt->more_results()) {
                $tmp = [];

                foreach ($rs as $row) {
                    if ($this->pKey) {
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
            return pg_ping($this->con);
        }

        public function autoCommit(int $val): bool
        {
            return pg_query($this->con, "SET AUTOCOMMIT=$val") !== false;
        }

        public function close(): bool
        {
            if (!$this->ping())
                return FALSE;

            return pg_close($this->con);
        }
    }