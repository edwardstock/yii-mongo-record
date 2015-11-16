<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class MongoDataProvider extends CActiveDataProvider
{
	/**
	 * @var MongoRecord the MR finder instance (eg <code>Post::model()</code>).
	 * This property can be set by passing the finder instance as the first parameter
	 * to the constructor. For example, <code>Post::model()->published()</code>.
	 * @since 1.1.3
	 */
	public $model;
	/**
	 * @var string the name of key attribute for {@link modelClass}. If not set,
	 * it means the primary key of the corresponding database table will be used.
	 */
	public $keyAttribute = 'id';

	/**
	 * @var MongoDbCriteria
	 */
	private $_criteria;
	/**
	 * @var MongoDbCriteria
	 */
	private $_countCriteria;

	/**
	 * @var MongoPagination
	 */
	private $_pagination;

	/**
	 * Constructor.
	 * @param mixed $modelClass the model class (e.g. 'Post') or the model finder instance
	 * (e.g. <code>Post::model()</code>, <code>Post::model()->published()</code>).
	 * @param array $config configuration (name=>value) to be applied as the initial property values of this class.
	 */
	public function __construct($modelClass, $config = [])
	{
		if (is_string($modelClass)) {
			$this->modelClass = $modelClass;
			$this->model = $this->getModel($this->modelClass);
		} elseif ($modelClass instanceof MongoRecord) {
			$this->modelClass = get_class($modelClass);
			$this->model = $modelClass;
		}
		$this->setId(CHtml::modelName($this->model));
		foreach ($config as $key => $value)
			$this->$key = $value;
	}

	/**
	 * Given active record class name returns new model instance.
	 *
	 * @param string $className active record class name.
	 * @return MongoRecord active record model instance.
	 *
	 * @since 1.1.14
	 */
	protected function getModel($className)
	{
		return MongoRecord::model($className);
	}

	/**
	 * Fetches the data from the persistent data storage.
	 * @return array list of data items
	 */
	protected function fetchData()
	{
		$criteria = clone $this->getCriteria();

		if (($pagination = $this->getPagination()) !== false) {
			$pagination->setItemCount($this->getTotalItemCount());
			$pagination->applyLimit($criteria);
		}

		$baseCriteria = $this->model->getDbCriteria();

		if (($sort = $this->getSort()) !== false) {
			// set model criteria so that CSort can use its table alias setting
			if ($baseCriteria !== null) {
				$c = clone $baseCriteria;
				$c->mergeWith($criteria);
				$this->model->setDbCriteria($c);
			} else {
				$this->model->setDbCriteria($criteria);
			}

			$sort->applyOrder($criteria);
		}

		$criteria->validate($this->model);


		$this->model->setDbCriteria($baseCriteria !== null ? clone $baseCriteria : null);
		$data = $this->model->findAll($criteria);
		$this->model->setDbCriteria($baseCriteria);  // restore original criteria
		return $data;
	}

	/**
	 * Returns the query criteria.
	 * @return MongoDbCriteria the query criteria
	 */
	public function getCriteria()
	{
		if ($this->_criteria === null) {
			$this->_criteria = new CDbCriteria;
		}
		return $this->_criteria;
	}

	/**
	 * Sets the query criteria.
	 * @param MongoDbCriteria|array $value the query criteria. This can be either a CDbCriteria object or an array
	 * representing the query criteria.
	 */
	public function setCriteria($value)
	{
		$this->_criteria = $value instanceof MongoDbCriteria ? $value : new MongoDbCriteria($value);
	}

	/**
	 * Returns the pagination object.
	 * @param string $className the pagination object class name. Parameter is available since version 1.1.13.
	 * @return MongoPagination|false the pagination object. If this is false, it means the pagination is disabled.
	 */
	public function getPagination($className = 'MongoPagination')
	{
		if ($this->_pagination === null) {
			$this->_pagination = new $className;
			if (($id = $this->getId()) != '') {
				$this->_pagination->pageVar = $id . '_page';
			}
		}
		return $this->_pagination;
	}

	/**
	 * Returns the sorting object.
	 * @param string $className the sorting object class name. Parameter is available since version 1.1.13.
	 * @return MongoSort the sorting object. If this is false, it means the sorting is disabled.
	 */
	public function getSort($className = 'MongoSort')
	{
		if (($sort = parent::getSort($className)) !== false)
			$sort->modelClass = $this->modelClass;
		return $sort;
	}

	/**
	 * Calculates the total number of data items.
	 * @return integer the total number of data items.
	 */
	protected function calculateTotalItemCount()
	{
		$baseCriteria = $this->model->getDbCriteria();
		if ($baseCriteria !== null) {
			$baseCriteria = clone $baseCriteria;
		}
		$count = $this->model->count($this->getCountCriteria());
		$this->model->setDbCriteria($baseCriteria);
		return $count;
	}

	/**
	 * Returns the count query criteria.
	 * @return MongoDbCriteria the count query criteria.
	 * @since 1.1.14
	 */
	public function getCountCriteria()
	{
		if ($this->_countCriteria === null) {
			return $this->getCriteria();
		}
		return $this->_countCriteria;
	}

	/**
	 * Sets the count query criteria.
	 * @param CDbCriteria|array $value the count query criteria. This can be either a CDbCriteria object
	 * or an array representing the query criteria.
	 * @since 1.1.14
	 */
	public function setCountCriteria($value)
	{
		$this->_countCriteria = $value instanceof MongoDbCriteria ? $value : new MongoDbCriteria($value);
	}
}