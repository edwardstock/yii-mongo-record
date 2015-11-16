<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class MongoAggregation
{

	/**
	 * @var MongoRecord
	 */
	private $model;

	private $aggregationPipeline = [];

	/**
	 * @param \MongoRecord $mongoRecord
	 */
	public function __construct(MongoRecord $mongoRecord)
	{
		$this->model = $mongoRecord;
	}

	/**
	 * Fetch max value.
	 * @param string $field
	 * @return mixed Result value can be any type, including internal \MongoDate, \MongoId
	 */
	public function max($field)
	{
		$result = $this->model->getCollection()->aggregate([
			[
				'$match' => $this->model->getDbCriteria()->getConditions(),
				'$group' => [
					'_id'    => null,
					'maxVal' => ['$max' => '$' . $field],
				],
			],
		]);

		if (isset($result['result']) && isset($result['result'][0]) && isset($result['result'][0]['maxVal'])) {
			return $result['result'][0]['maxVal'];
		}

		return $result;
	}

	/**
	 * Fetch min value
	 * @param string $field
	 * @return mixed Result value can be any type, including internal \MongoDate, \MongoId
	 */
	public function min($field)
	{
		$result = $this->model->getCollection()->aggregate([
			[
				'$match' => $this->model->getDbCriteria()->getConditions(),
				'$group' => [
					'_id'    => null,
					'minVal' => ['$min' => '$' . $field],
				],
			],
		]);

		if (isset($result['result']) && isset($result['result'][0]) && isset($result['result'][0]['minVal'])) {
			return $result['result'][0]['minVal'];
		}

		return $result;
	}

	/**
	 * Fetch average value
	 * @param string $field
	 * @return mixed Result value can be any type, including internal \MongoDate, \MongoId
	 */
	public function avg($field)
	{
		$result = $this->model->getCollection()->aggregate([
			[
				'$match' => $this->model->getDbCriteria()->getConditions(),
				'$group' => [
					'_id'    => null,
					'avgVal' => ['$avg' => '$' . $field],
				],
			],
		]);

		if (isset($result['result']) && isset($result['result'][0]) && isset($result['result'][0]['avgVal'])) {
			return $result['result'][0]['avgVal'];
		}

		return $result;
	}

	/**
	 * Fetch sum of values
	 * @param string $field
	 * @return mixed Result value can be any type, including internal \MongoDate, \MongoId
	 */
	public function sum($field)
	{
		$result = $this->model->getCollection()->aggregate([
			[
				'$match' => $this->model->getDbCriteria()->getConditions(),
				'$group' => [
					'_id'    => null,
					'sumVal' => ['$sum' => '$' . $field],
				],
			],
		]);

		if (isset($result['result']) && isset($result['result'][0]) && isset($result['result'][0]['sumVal'])) {
			return (int)$result['result'][0]['sumVal'];
		}

		return $result;
	}

	/**
	 * @param array $params
	 * @return $this
	 */
	public function group(array $params)
	{
		$this->aggregationPipeline[] = [
			'$group' => $params,
		];

		return $this;
	}

	/**
	 * This is a $project operator in MongoDB
	 * @param array $params
	 * @return $this
	 */
	public function select(array $params)
	{
		$this->aggregationPipeline[] = [
			'$project' => $params,
		];

		return $this;
	}

	/**
	 * @param array $params
	 * @return $this
	 */
	public function match(array $params)
	{
		$this->aggregationPipeline[] = [
			'$match' => $params,
		];

		return $this;
	}

	/**
	 * @param $expression
	 * @return $this
	 */
	public function unwind($expression)
	{
		$this->aggregationPipeline[] = [
			'$unwind' => $expression,
		];

		return $this;
	}

	/**
	 * @param array $fields
	 * @return $this
	 */
	public function sort(array $fields)
	{
		$this->aggregationPipeline[] = [
			'$sort' => $fields,
		];

		return $this;
	}

	/**
	 * @param int $limit
	 * @return $this
	 */
	public function limit($limit)
	{
		$this->aggregationPipeline[] = [
			'$limit' => (int)$limit,
		];

		return $this;
	}

	/**
	 * Собирает и отправляет запрос
	 * Вызывать после чейнинга методов выборки, группировки и тд
	 * @return array
	 */
	public function aggregate()
	{
		$result = $this->model->getCollection()->aggregate($this->aggregationPipeline);
		$this->aggregationPipeline = [];
		if (isset($result['result']) && isset($result['result'])) {
			return $result['result'];
		}

		return $result;
	}

}