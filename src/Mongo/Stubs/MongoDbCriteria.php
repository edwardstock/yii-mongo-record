<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * @property int $limit
 * @property int $offset
 */
class MongoDbCriteria extends CApplicationComponent
{

	const GEO_TYPE_POINT = 'Point';

	const SORT_ASC = 1;
	const SORT_DESC = -1;

	const BSON_TYPE_DOUBLE = 1;
	const BSON_TYPE_STRING = 2;
	const BSON_TYPE_OBJECT = 3;
	const BSON_TYPE_ARRAY = 4;
	const BSON_TYPE_BINARY = 5;
	/** @deprecated */
	const BSON_TYPE_UNDEFINED = 6;
	const BSON_TYPE_OBJECT_ID = 7;
	const BSON_TYPE_BOOLEAN = 8;
	const BSON_TYPE_DATE = 9;
	const BSON_TYPE_NULL = 10;
	const BSON_TYPE_REGEX = 11;
	const BSON_TYPE_JAVASCRIPT = 13;
	const BSON_TYPE_SYMBOL = 14;
	const BSON_TYPE_JAVASCRIPT_WITH_SCOPE = 15;
	const BSON_TYPE_INTEGER = 16;
	const BSON_TYPE_TIMESTAMP = 17;
	const BSON_TYPE_LONG = 18;
	const BSON_TYPE_MIN_KEY = 0xFF;
	const BSON_TYPE_MAX_KEY = 0177;

	/**
	 * @var array
	 */
	private $mongoOps = [
		'='    => '$eq',
		'!='   => '$ne',
		'<>'   => '$ne',
		'<'    => '$lt',
		'<='   => '$lte',
		'>'    => '$gt',
		'>='   => '$gte',
		'asc'  => 1,
		'desc' => -1,
	];

	/**
	 * @var string[]
	 */
	private $query = [
		'select'    => [],
		'condition' => [],
		'limit'     => -1,
		'offset'    => -1,
		'order'     => null,
		'params'    => [],
	];

	/**
	 * @var \MongoCursor|null
	 */
	private $cursor = null;

	/**
	 * @var MongoCollection
	 */
	private $collection = null;

	/**
	 * MongoDbCriteria constructor.
	 * @param array $forMergeCondition
	 */
	public function __construct($forMergeCondition = [])
	{
		$this->mergeWithArray($forMergeCondition);
	}

	/**
	 * @param array $query
	 * @return $this
	 */
	public function mergeWithArray(array $query)
	{
		$this->query = array_merge_recursive($this->query, $query);
		$this->checkTopLevelOperators();

		return $this;
	}

	private function checkTopLevelOperators()
	{
		$conditions = array_keys($this->query['condition']);
		$hasAnd = false;
		$hasOr = false;
		foreach ($conditions AS $c) {
			if ($c === '$and') {
				$hasAnd = true;
			}

			if ($c === '$or') {
				$hasOr = true;
			}
		}

		if ($hasAnd || $hasOr) {
			foreach ($this->getConditions() AS $operatorOrField => $values) {
				if (!in_array($operatorOrField, ['$and', '$or'])) {
					$field = $operatorOrField;
					$this->query['condition']['$and'][] = [$field => $this->query['condition'][$field]];
					unset($this->query['condition'][$field]);
				}
			}
		}
	}

	/**
	 * @param bool $nullOnEmpty
	 * @return \mixed[]
	 */
	public function getConditions($nullOnEmpty = false)
	{
		if (sizeof($this->query['condition']) === 0 && $nullOnEmpty) {
			return null;
		}

		return $this->query['condition'];
	}

	/**
	 * @param \MongoCollection $collection
	 */
	public function setCollection(MongoCollection $collection)
	{
		$this->collection = $collection;
	}

	/**
	 * @param \MongoCursor $cursor
	 */
	public function setCursor(MongoCursor $cursor)
	{
		$this->cursor = $cursor;
	}

	/**
	 * Можно указать строкой через запятую, можно массив строк передать
	 * @param string|string[] $fields
	 * @return $this
	 */
	public function select($fields = '*')
	{
		if ($fields === '*' || (is_string($fields) && strpos($fields, ',') === false)) {
			return $this;
		} else {
			if (strpos($fields, ',') !== false) {
				$expFields = explode(',', $fields);
				array_walk($expFields, 'trim');
				$this->query['select'] = $expFields;
			} else {
				if (is_array($fields)) {
					$this->query['select'] = $fields;
				} else {
					throw new InvalidArgumentException("Fields must be a string with comma or array of fields. Given: " . gettype($fields));
				}
			}
		}

		return $this;
	}

	/**
	 * Как использовать:
	 * 1) Можно передать просто массив строк полей, и по всем полям будет сортировка ASC
	 * 2) Можно передать массив массивов:
	 *  например
	 *    [
	 *      'id'=>'ASC',
	 *      'name'=>'DESC',
	 *    ]
	 *
	 * @param array $fields
	 */
	public function mergeOrderFields($fields = null)
	{
		$fieldsToMerge = $fields === null ? [] : $fields;

		foreach ($fieldsToMerge AS $field) {
			if (is_numeric($field)) continue;
			if ($this->hasOrderField($field)) continue;

			if (is_array($field)) {
				list($name, $arrow) = $field;
				$this->orderBy($name, $arrow);
			} else {
				$this->orderBy($field);
			}
		}
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	private function hasOrderField($field)
	{
		return isset($this->query['order'][$field]);
	}

	/**
	 * @param string $field
	 * @param string $arrow
	 * @param bool $multisort
	 * @return $this
	 */
	public function orderBy($field, $arrow = 'ASC', $multisort = true)
	{
		if ($multisort) {
			if ($this->query['order'] === null) {
				$this->query['order'] = [$field => (int)$this->replaceOperator($arrow)];
			} else {
				$this->query['order'][$field] = (int)$this->replaceOperator($arrow);
			}
		} else {
			$this->query['order'] = [$field => (int)$this->replaceOperator($arrow)];
		}

		return $this;
	}

	/**
	 * @param string $operator
	 * @return string
	 */
	private function replaceOperator($operator)
	{
		if (isset($this->mongoOps[strtolower($operator)])) {
			return strtolower($this->mongoOps[strtolower($operator)]);
		}

		return strtolower('$' . $operator);
	}

	/**
	 * @param int $limit
	 * @return $this
	 */
	public function setLimit($limit = -1)
	{
		$this->query['limit'] = $limit;
		return $this;
	}

	/**
	 * @param int $offset
	 * @return $this
	 */
	public function setOffset($offset = -1)
	{
		$this->query['offset'] = $offset;
		return $this;
	}

	/**
	 * @param string $field
	 * @param mixed $value
	 * @param string $booleanOperator
	 */
	public function compare($field, $value, $booleanOperator = 'AND')
	{
		if ($value === null || mb_strlen($value, 'UTF-8') === 0) {
			return;
		}

		$this->addCondition($field, '=', $value, $booleanOperator);
	}

	/**
	 * @param string $field
	 * @param string $compareOperator
	 * @param mixed $value
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addCondition($field, $compareOperator, $value, $booleanOperator = 'AND')
	{
		$bOperator = $this->replaceOperator($booleanOperator);
		$cOperator = $this->replaceOperator($compareOperator);

		if ($bOperator === '$or') {

			if (!array_key_exists($bOperator, $this->query['condition'])) {
				$this->query['condition'][$bOperator] = [];
			}

			$this->query['condition'][$bOperator][] = [$field => [$cOperator => $value]];
		} else {
			if (isset($this->query['condition']['$or']) && $bOperator === '$and') {
				if (isset($this->query['condition'][$bOperator])) {
					$this->query['condition'][$bOperator][] = [$field => [$cOperator => $value]];
				} else {
					$this->query['condition'][$bOperator][] = [$field => [$cOperator => $value]];
				}
			} else {
				$this->query['condition'][$field] = [$cOperator => $value];
			}
		}

		$this->checkTopLevelOperators();

		return $this;
	}

	/**
	 * @param string $field
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addExistsCondition($field, $booleanOperator = 'AND')
	{
		$this->addCondition($field, 'exists', $booleanOperator);
		return $this;
	}

	/**
	 * @param string $field
	 * @param mixed $value
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function compareLike($field, $value, $booleanOperator = 'AND')
	{
		if ($value === null || mb_strlen($value, 'UTF-8') === 0) {
			return $this;
		}

		$this->addLikeCondition($field, $value, $booleanOperator);

		return $this;
	}

	/**
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addLikeCondition($field, $value, $booleanOperator = 'AND')
	{
		$reg = new MongoRegex("/.*{$value}.*/iu");
		$this->addCondition($field, 'regex', $reg, $booleanOperator);
		return $this;
	}

	/**
	 * @param string $field
	 * @param int|string|MongoDate $value
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function compareDate($field, $value, $booleanOperator = 'AND')
	{
		if ($value === null || mb_strlen($value, 'UTF-8') === 0) {
			return $this;
		}

		if ($value instanceof MongoDate) {
			$this->addCondition($field, '=', $value, $booleanOperator);
			return $this;
		} else {
			if (is_string($value) && !is_numeric($value)) {
				$date = strtotime($value);
				$this->addCondition($field, '=', new MongoDate($date));
				return $this;
			} else {
				if (is_string($value) && is_numeric($value)) {
					$this->addCondition($field, '=', new MongoDate((int)$value));
					return $this;
				} else {
					if (is_int($value)) {
						$this->addCondition($field, '=', new MongoDate($value));
						return $this;
					}
				}
			}
		}

		return $this;
	}

	/**
	 * @param string $field
	 * @param \MongoRegex $regex
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addRegexpCondition($field, MongoRegex $regex, $booleanOperator = 'AND')
	{
		$this->addCondition($field, 'regex', $regex, $booleanOperator);
		return $this;
	}

	public function addSubDocumentCondition($field, $subField, $operator, $value, $booleanOperator = 'AND')
	{

		if (is_string($operator) && !is_array($value)) {
			$prepared = [
				$subField => [
					$this->replaceOperator($operator) => $value
				],
			];
		} else {
			if (is_array($operator) && !is_array($value)) {
				throw new InvalidArgumentException("Wrong conditions. If Operator is array, values must be too array");
			} else {
				if (is_array($operator) && is_array($value) && sizeof($operator) === sizeof($value)) {
					$ops = [];
					for ($i = 0; $i < sizeof($operator); $i++) {
						$ops[$operator[$i]] = $value[$i];
					}

					$prepared = [
						$subField => $ops,
					];
				} else {
					throw new InvalidArgumentException("Wrong arguments. Both params (operator, value) must be a strings or arrays that must be equals by sizes");
				}
			}
		}

		$this->addRawCondition($field, $prepared, $booleanOperator);
	}

	/**
	 * @param string $field
	 * @param array $rawExpression
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addRawCondition($field, array $rawExpression, $booleanOperator = 'AND')
	{
		$bOperator = $this->replaceOperator($booleanOperator);
		if (isset($this->query['condition'][$bOperator]) || $bOperator !== '$and') {
			$this->query['condition'][$bOperator][][$field] = $rawExpression;
		} else {
			$this->query['condition'][$field] = $rawExpression;
		}
		$this->checkTopLevelOperators();

		return $this;
	}

	/**
	 * Add raw expression
	 * @param array $expression
	 * @return $this
	 */
	public function setCondition(array $expression)
	{
		$this->query['condition'] = $expression;

		return $this;
	}

	public function addNearCondition($field, GeoPoint $point, $booleanOperator = 'AND')
	{

		if ($point->hasAllDistances()) {
			$prepared = [
				'$near' => [
					'$geometry' => [
						'type'        => 'Point',
						'coordinates' => [
							$point->getLatitude(),
							$point->getLongitude(),
						],
					],
				],

				'$minDistance' => $point->getMinDistance(),
				'$maxDistance' => $point->getMaxDistance(),
			];
		} else {
			if ($point->hasMaxDistance()) {
				$prepared = [
					'$near' => [
						'$geometry' => [
							'type'        => 'Point',
							'coordinates' => [
								$point->getLatitude(),
								$point->getLongitude(),
							],
						],
					],

					'$maxDistance' => $point->getMaxDistance(),
				];
			} else {
				if ($point->hasMinDistance()) {
					$prepared = [
						'$near' => [
							'$geometry' => [
								'type'        => 'Point',
								'coordinates' => [
									$point->getLatitude(),
									$point->getLongitude(),
								],
							],
						],

						'$minDistance' => $point->getMinDistance(),
					];
				} else {
					$prepared = [
						'$near' => [
							'$geometry' => [
								'type'        => 'Point',
								'coordinates' => [
									$point->getLatitude(),
									$point->getLongitude(),
								],
							],
						],
					];
				}
			}
		}

		$this->addRawCondition($field, $prepared, $booleanOperator);
	}

	/**
	 * @param string $field
	 * @param array $values
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addInCondition($field, array $values, $booleanOperator = 'AND')
	{
		$bOperator = $this->replaceOperator($booleanOperator);
		if (isset($this->query['condition'][$bOperator]) || $bOperator !== '$and') {
			$this->query['condition'][$bOperator][][$field] = [
				'$in' => $values,
			];
		} else {
			$this->query['condition'][$field] = [
				'$in' => $values,
			];
		}

		$this->checkTopLevelOperators();

		return $this;
	}

	/**
	 * @param string $field
	 * @param array $values
	 * @param string $booleanOperator
	 * @return $this
	 */
	public function addNotInCondition($field, array $values, $booleanOperator = 'AND')
	{
		$bOperator = $this->replaceOperator($booleanOperator);
		if (isset($this->query['condition'][$bOperator]) || $bOperator !== '$and') {
			$this->query['condition'][$bOperator][][$field] = [
				'$nin' => $values,
			];
		} else {
			$this->query['condition'][$field] = [
				'$nin' => $values,
			];
		}

		$this->checkTopLevelOperators();
		return $this;
	}

	/**
	 * @param array|MongoDbCriteria $criteria
	 * @return $this
	 */
	public function mergeWith($criteria)
	{
		if ($criteria instanceof MongoDbCriteria) {
			$this->mergeWithBuilder($criteria);
		} else {
			if (is_array($criteria)) {
				$this->mergeWithArray($criteria);
			}
		}

		return $this;
	}

	/**
	 * @param \MongoDbCriteria $builder
	 * @return $this
	 */
	public function mergeWithBuilder(MongoDbCriteria $builder)
	{
		$this->query = array_merge_recursive($this->query, $builder->getQuery());

		if (is_array($this->query['limit']) && sizeof($this->query['limit']) > 0) {
			$this->query['limit'] = $this->query['limit'][0];
		}

		if (is_array($this->query['offset']) && sizeof($this->query['offset']) > 0) {
			$this->query['offset'] = $this->query['offset'][0];
		}
		$this->checkTopLevelOperators();

		return $this;
	}

	/**
	 * @return \string[]
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * @return \MongoCursor
	 */
	public function buildCursor()
	{
		if ($this->collection === null) {
			throw new RuntimeException("Collection has not set");
		}

		$this->cursor = $this->collection->find($this->getConditions(), $this->getSelect());
		$this->configureCursor();

		return $this->cursor;
	}

	/**
	 * @return string[]
	 */
	public function getSelect()
	{
		return $this->query['select'];
	}

	public function configureCursor()
	{
		if ($this->cursor === null) {
			return;
		}
		if ($this->getOffset() > -1) $this->cursor->skip($this->getOffset());
		if ($this->getLimit() > -1) $this->cursor->limit($this->getLimit());
		if ($this->getOrder() !== null) $this->cursor->sort($this->getOrder());
	}

	/**
	 * @return int
	 */
	public function getOffset()
	{
		return $this->query['offset'];
	}

	/**
	 * @return int
	 */
	public function getLimit()
	{
		return $this->query['limit'];
	}

	/**
	 * @return string[]|null
	 */
	public function getOrder()
	{
		return $this->query['order'];
	}

	public function validate(MongoRecord $model)
	{
		$modelFields = $model->attributeNames();
		$modelClass = get_class($model);

		$sortFields = $this->getOrder();
		$selectFields = $this->getSelect();

		if (is_array($sortFields)) {
			foreach ($sortFields AS $name => $arrow) {
				if (!in_array($name, $modelFields)) {
					throw new CDbException("Bad sorting criteria. Field \"{$name}\" does not exists in model {$modelClass}");
				}
			}
		}


		foreach ($selectFields AS $name => $arrow) {
			if (!in_array($name, $modelFields)) {
				throw new CDbException("Bad select criteria. Field \"{$name}\" does not exists in model {$modelClass}");
			}
		}
	}

	/**
	 * @param $field
	 */
	public function removeCondition($field)
	{
		if (isset($this->getConditions()[$field])) {
			unset($this->query['condition'][$field]);
		}

		foreach ($this->query['condition'] AS $op => &$arr) {
			if (is_array($arr) && isset($arr[$field])) {
				unset($arr[$field]);
			}
		}

	}

	/**
	 * @return \string[]
	 */
	public function __debugInfo()
	{
		return $this->getQuery();
	}
}