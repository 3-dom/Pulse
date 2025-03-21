<?php
	namespace ThreeDom\Pulse\Model;

	use ThreeDom\Pulse\Command;

	class QueryBuilder
	{
		public static function create(Command &$con, array $schema): ?array
		{
			$table = $schema['info']['table'];
			$data = QueryBuilder::splitColumns($schema);

			$con
				->insert($data['columns'], $table, $schema['info']['pKey'])
				->vars(...$data['values'])
				->query();

			return $con->getResults();
		}

		public static function update(Command &$con, array $schema): void
		{
			$table = $schema['info']['table'];
			$pKey = $schema['info']['pKey'];
			$pKeyVal = $schema['columns'][$pKey]['value'];

			if(!$pKeyVal || !$pKey)
				return;

			$data = QueryBuilder::splitColumns($schema);

			$con
				->update($table, $data['columns'], $data['values'])
				->where("$pKey = ?")
				->vars($pKeyVal)
				->query();
		}

		private static function splitColumns(array $schema): array
		{
			$columns = [];
			$values = [];
			foreach($schema['columns'] as $column => $data)
			{
				if(!$data['fillable'])
					continue;
				if($data['value'] === NULL)
					continue;

				$columns[] = $column;
				$values[] = $data['value'];
			}

			return ['columns' => $columns, 'values' => $values];
		}
	}