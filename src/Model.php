<?php
	namespace ThreeDom\Pulse;

	use ThreeDom\Pulse\Model\QueryBuilder;

	abstract class Model
	{
		protected array $schema;
		protected Command $con;

		public function __construct(Command $con)
		{
			$this->con = $con;
		}

		public function setL(string $key, mixed $val): void
		{
			$this->schema['columns'][$key]['value'] = $val;
		}

		public function getL(string $key): string
		{
			return $this->schema['columns'][$key]['value'];
		}

		public function create()
		{
			QueryBuilder::create($this->con, $this->schema);
		}

		public function update()
		{
			QueryBuilder::update($this->con, $this->schema);
		}

		public function retrieve()
		{

		}

		public function delete()
		{

		}
	}