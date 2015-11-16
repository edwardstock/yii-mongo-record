<?php

/**
 * Description of MongoRecord
 *
 * @author yohan
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * @property bool $isNewRecord
 * @property string $primaryKey
 * @property MongoAggregation $aggregation
 * @property MongoCollection $collection
 * @property string $collectionName
 * @property MongoDbCriteria $dbCriteria
 */
abstract class MongoRecord extends CModel
{

	const BELONGS_TO = 'BelongsTo';
	const HAS_ONE = 'HasOne';
	const HAS_MANY = 'HasMany';
	const HAS_RELATION_WITH = 'HasRelationWith';

	const DATE_FORMAT = 'd/m/Y H:i:s';

	/**
	 * @var $this []|static[]
	 */
	private static $_models = [];
	/**
	 * @var MongoId
	 */
	public $id;
	/**
	 * @var bool
	 */
	public $safe = true;
	/**
	 * @var int
	 */
	public $limit = 0;
	/**
	 * @var int
	 */
	public $skip = 0;
	/**
	 * @var bool
	 */
	public $useCursor = true;
	/**
	 * Forces the mongod process to flush all pending writes from the storage layer to disk
	 * @var bool
	 */
	protected $fsync = true;
	/**
	 * @var MongoDB
	 */
	private $db;
	/**
	 * @var bool
	 */
	private $isNew;
	/**
	 * @var MongoDbCriteria
	 */
	private $criteria;
	/**
	 * @var array
	 */
	private $_attributes = [];
	/**
	 * Loaded relations
	 * @var MongoRecord[]|array
	 */
	private $relations = [];

	/**
	 * @var MongoAggregation
	 */
	private $aggregation;

	/**
	 * @param string $arg
	 */
	public function __construct($arg = 'insert')
	{
		if ($arg === null) // internally used by populateRecord() and model()
		{
			return;
		} elseif (is_array($arg)) {
			$this->setAttributes($arg);
			return;
		}

		$this->init(func_get_args());

		$this->setScenario($arg);
		$this->setIsNewRecord(true);

		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	/**
	 * Use this instead of __construct()
	 * @param array $arguments Constructor args
	 */
	public function init($arguments = [])
	{
	}

	/**
	 * @param boolean $value whether the record is new and should be inserted when calling {@link save}.
	 * @see getIsNewRecord
	 */
	public function setIsNewRecord($value)
	{
		$this->isNew = $value;
	}

	/**
	 * This method is invoked after a record instance is created by new operator.
	 * The default implementation raises the {@link onAfterConstruct} event.
	 * You may override this method to do postprocessing after record creation.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterConstruct()
	{
		if ($this->hasEventHandler('onAfterConstruct')) {
			$this->onAfterConstruct(new CEvent($this));
		}
	}

	/**
	 * This event is raised after the record instance is created by new operator.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onAfterConstruct($event)
	{
		$this->raiseEvent('onAfterConstruct', $event);
	}

	/**
	 * Validates one or several models and returns the results in JSON format.
	 * This is a helper method that simplifies the way of writing AJAX validation code.
	 * @param static|static[]|MongoRecord|MongoRecord[] $models
	 * @param array $attributes list of attributes that should be validated. Defaults to null,
	 * meaning any attribute listed in the applicable validation rules of the models should be
	 * validated. If this parameter is given as a list of attributes, only
	 * the listed attributes will be validated.
	 * @param boolean $loadInput whether to load the data from $_POST array in this method.
	 * If this is true, the model will be populated from <code>$_POST[ModelClass]</code>.
	 * @return string the JSON representation of the validation error messages.
	 */
	public static function validateModels($models, $attributes = null, $loadInput = true)
	{
		$result = [];
		if (!is_array($models))
			$models = [$models];

		foreach ($models as $model) {
			$modelName = CHtml::modelName($model);
			if ($loadInput && isset($_POST[$modelName]))
				$model->setAttributes($_POST[$modelName]);
			$model->validate($attributes);
			foreach ($model->getErrors() as $attribute => $errors)
				$result[CHtml::activeId($model, $attribute)] = $errors;
		}

		return function_exists('json_encode') ? json_encode($result) : CJSON::encode($result);
	}

	/**
	 * PHP getter magic method.
	 * This method is overridden so that AR attributes can be accessed like properties.
	 * @param string $name property name
	 * @return mixed property value
	 * @see getAttribute
	 */
	public function __get($name)
	{
		if (property_exists($this, $name)) {
			return $this->{$name};
		} else {
			if (isset($this->_attributes[$name])) {
				return $this->_attributes[$name];
			} else {
				if (isset($this->relations[$name])) {
					return $this->relations[$name];
				} else {
					if (isset($this->relations()[$name])) {
						return $this->getRelated($name);
					} else {
						return parent::__get($name);
					}
				}
			}
		}
	}

	/**
	 * PHP setter magic method.
	 * This method is overridden so that AR attributes can be accessed like properties.
	 * @param string $name property name
	 * @param mixed $value property value
	 * @return mixed|void
	 */
	public function __set($name, $value)
	{
		if ($this->setAttribute($name, $value) === false) {
			parent::__set($name, $value);
		}
	}

	/**
	 * @return array
	 */
	public function relations()
	{
		return [
		];
	}

	/**
	 * Связи по ObjectId соединяются только через строки!
	 *
	 * @param string $name
	 * @return MongoRecord|MongoRecord[]|bool
	 * @throws \CDbException
	 */
	public function getRelated($name)
	{
		$params = null;
		foreach ($this->relations() AS $relationName => $relation) {
			if ($name === $relationName) {
				$params = $relation;
				break;
			}
		}

		if ($params === null) {
			return null;
		}

		list($relationType, $relationCollection, $foreignKey) = $params;

		$criteria = new MongoDbCriteria();
		$modelObject = MongoRecord::model($relationCollection);
		$result = null;

		switch ($relationType) {
			case(self::BELONGS_TO): {
				if (!isset($this->{$foreignKey})) {
					throw new CDbException("Неправильно задана связь $name c ключем: {$foreignKey}=>_id");
				}
				$criteria->addCondition('_id', '=', (string)$this->{$foreignKey});
				$criteria->setLimit(1);
				$result = $modelObject->find($criteria, false);
			};
				break;

			case(self::HAS_ONE): {
				if (!property_exists($modelObject, $foreignKey)) {
					throw new CDbException("Неправильно задана связь $name c ключем: _id=>{$foreignKey}");
				}
				$criteria->addCondition($foreignKey, '=', (string)$this->id);
				$criteria->setLimit(1);
				$result = $modelObject->find($criteria, false);
			};
				break;

			case(self::HAS_MANY): {
				if (!property_exists($modelObject, $foreignKey)) {
					throw new CDbException("Неправильно задана связь $name c ключем: _id=>{$foreignKey}");
				}
				$criteria->addCondition($foreignKey, '=', $this->id);
				$result = $modelObject->findAll($criteria);
			};
				break;

			case(self::HAS_RELATION_WITH): {
				$cursor = $modelObject->getCollection()->find([
					$foreignKey => (string)$this->id,
				], [$foreignKey]);
				$cursor->limit(1);

				$result = $cursor->count() === 1;
			};
				break;

			default:
				throw new CDbException("Wrong relation type for relation: {$name}");
		}

		$this->relations[$name] = $result;

		return $result;

	}

	/**
	 * @param string $className
	 * @return $this
	 */
	public static function model($className = __CLASS__)
	{
		if (isset(self::$_models[$className])) {
			return self::$_models[$className];
		} else {
			/** @var CModel $model */
			$model = self::$_models[$className] = new $className(null);
			$model->attachBehaviors($model->behaviors());
			return $model;
		}
	}

	/**
	 * @param MongoDbCriteria|array|null $query
	 * @param bool $mergeCriteria
	 * @return $this|null
	 */
	public function find($query = null, $mergeCriteria = false)
	{
		$criteria = null;
		if ($mergeCriteria) {
			if ($query instanceof MongoDbCriteria) {
				$criteria = $this->getDbCriteria()->mergeWith($query);
				$doc = $this->getCollection()->findOne($criteria->getConditions(), $criteria->getSelect());
			} else {
				if (is_array($query) && sizeof($query) > 0) {
					$criteria = $this->getDbCriteria()->mergeWith($query);
					$doc = $this->getCollection()->findOne($criteria->getConditions(), $criteria->getSelect());
				} else {
					$criteria = $this->getDbCriteria();
					$doc = $this->getCollection()->findOne($criteria->getConditions(), $criteria->getSelect());
				}
			}
			Yii::log("Mongo Query: " . var_export($criteria, true), CLogger::LEVEL_TRACE, 'mongo.MongoRecord::find()');
		} else {
			if ($query instanceof MongoDbCriteria) {
				$doc = $this->getCollection()->findOne($query->getConditions(), $query->getSelect());
			} else {
				if (is_array($query) && sizeof($query) > 0) {
					$doc = $this->getCollection()->findOne($query);
				} else {
					$doc = $this->getCollection()->findOne([]);
				}
			}

			Yii::log("Mongo Query: " . var_export($query, true), CLogger::LEVEL_TRACE, 'mongo.MongoRecord::find()');
		}

		if ($doc !== null) {
			$this->afterFind();
			return $this->instantiate($doc);
		} else {
			return null;
		}
	}

	/**
	 * @return \MongoDbCriteria
	 */
	public function getDbCriteria()
	{
		if ($this->criteria === null) {
			$this->criteria = new MongoDbCriteria();
		}

		return $this->criteria;
	}

	/**
	 * @return \MongoCollection
	 */
	public function getCollection()
	{
		return $this->getDbConnection()->selectCollection($this->getCollectionName());
	}

	/**
	 * @return \MongoDB
	 */
	public function getDbConnection()
	{
		if ($this->db instanceof MongoDB) {
			return $this->db;
		}

		/** @var MongoDbConnection $component */
		$component = Yii::app()->getComponent('mongodb');
		if ($component === null) {
			throw new RuntimeException('Component mongodb is not set in config file.');
		}
		$this->db = $component->getDb();
		return $this->db;
	}

	/**
	 * Collection name
	 * @return mixed
	 */
	abstract public function getCollectionName();

	/**
	 * This method is invoked after each record is instantiated by a find method.
	 * The default implementation raises the {@link onAfterFind} event.
	 * You may override this method to do postprocessing after each newly found record is instantiated.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterFind()
	{
		if ($this->hasEventHandler('onAfterFind')) {
			$this->onAfterFind(new CEvent($this));
		}
	}

	/**
	 * This event is raised after the record is instantiated by a find method.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onAfterFind($event)
	{
		$this->setIsNewRecord(false);
		$this->raiseEvent('onAfterFind', $event);
	}

	/**
	 * Creates an active record instance.
	 * This method is called by {@link populateRecord} and {@link populateRecords}.
	 * You may override this method if the instance being created
	 * depends the attributes that are to be populated to the record.
	 * For example, by creating a record based on the value of a column,
	 * you may implement the so-called single-table inheritance mapping.
	 * @param array $document list of attribute values for the active records.
	 * @return CActiveRecord the active record
	 * @since 1.0.2
	 */
	protected function instantiate($document)
	{
		$class = get_class($this);

		/** @var MongoRecord|$this $model */
		$model = new $class(null);
		$attributes = [];

		$model->id = $document['_id'];

		foreach ($this->attributeNames() AS $name) {
			if (isset($document[$name])) {
				$attributes[$name] = $document[$name];
			} else {
				$attributes[$name] = null;
			}

		}

		$model->setAttributes($attributes, false);
		$model->afterFind();

		return $model;
	}

	/**
	 * @param \MongoDbCriteria|array|null $query
	 * @return $this[]
	 */
	public function findAll($query = null)
	{
		if ($query instanceof MongoDbCriteria) {
			$query->setCollection($this->getCollection());
			$documents = $query->buildCursor();
		} else {
			if (is_array($query) && sizeof($query) > 0) {
				$q = isset($query['condition']) && isset($query['select']) ? $query['condition'] : $query;
				$s = isset($query['select']) ? $query['select'] : [];
				$documents = $this->getCollection()->find($q, $s);
			} else {
				$documents = $this->getCollection()->find();
			}
		}

		$this->afterFind();
		Yii::log("Mongo Query: <pre>" . var_export($query, true), CLogger::LEVEL_TRACE, 'mongo.MongoRecord::findAll()');

		return $this->populateRecords($documents);
	}

	/**
	 * @param MongoCursor $documents
	 * @return array
	 */
	protected function populateRecords(MongoCursor $documents)
	{
		$records = [];
		foreach ($documents as $doc) {
			$records[] = $this->instantiate($doc);
		}

		return $records;
	}

	/**
	 * Sets the named attribute value.
	 * You may also use $this->AttributeName to set the attribute value.
	 * @param string $name the attribute name
	 * @param mixed $value the attribute value.
	 * @return boolean whether the attribute exists and the assignment is conducted successfully
	 * @see hasAttribute
	 */
	public function setAttribute($name, $value)
	{
		if (property_exists($this, $name)) {
			$this->$name = $value;
		} else {
			return false;
		}

		return true;
	}

	/**
	 * @param string $field
	 * @param string $format
	 * @return string
	 */
	public function getStringDate($field, $format = self::DATE_FORMAT)
	{
		$date = $this->getFormattedDate($field);
		if ($date instanceof DateTime) {
			return $date->format($format);
		}

		return (string)$date;
	}

	/**
	 * @param string|MongoDate $field
	 * @return \DateTime|mixed
	 */
	public function getFormattedDate($field)
	{
		/** @var MongoDate|\stdClass $notConverted */
		$notConverted = null;

		if ($field instanceof MongoDate) {
			$notConverted = $field;
		} else {
			$notConverted = $this->{$field};
		}

		if ($notConverted === null) {
			return '';
		}

		if (!($notConverted instanceof MongoDate)) {
			$notConverted = new \stdClass();
			$notConverted->sec = time();
		}

		return (new DateTime())->setTimestamp($notConverted->sec);
	}

	/**
	 * @param $field
	 * @param MongoDbCriteria|array $query
	 * @return mixed[]
	 */
	public function distinct($field, $query = null)
	{
		$criteria = null;
		if ($query instanceof MongoDbCriteria) {
			$criteria = $query->getConditions(true);
		} else if (is_array($query)) {
			$criteria = $query;
		} else {
			$criteria = $this->getDbCriteria()->getConditions(true);
		}

		$result = $this->getCollection()->distinct($field, $criteria);
		if ($result === false) {
			return [];
		}

		return $result;
	}

	/**
	 * @param \MongoDbCriteria|null $criteria
	 */
	public function setDbCriteria(MongoDbCriteria $criteria = null)
	{
		$this->criteria = $criteria;
	}

	/**
	 * @param bool|true $runValidation
	 * @param null $attributes
	 * @return bool
	 * @throws \CDbException
	 */
	public function save($runValidation = true, $attributes = null)
	{
		if (!$runValidation || $this->validate($attributes)) {
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function getIsNewRecord()
	{
		return $this->isNew;
	}

	/**
	 * Save document to database
	 * @param null $attributes
	 * @return bool
	 * @throws \CDbException
	 */
	public function insert($attributes = null)
	{
		if ($this->beforeSave()) {
			try {
				$doc = $this->getAttributes($attributes);
				unset($doc['id']);
				$insert = $this->getCollection()->insert($doc, ['fsync' => $this->fsync]);
				Yii::trace("MongoCollection::insert result: " . var_export($insert, true), __METHOD__);
				$this->id = $doc['_id'];
				$this->afterSave();
				$this->setIsNewRecord(false);
				return true;
			} catch (MongoCursorException $e) {
				throw new CDbException($e->getMessage(), $e->getCode());
			}
		}
		return false;


	}

	/**
	 * This method is invoked before saving a record (after validation, if any).
	 * The default implementation raises the {@link onBeforeSave} event.
	 * You may override this method to do any preparation work for record saving.
	 * Use {@link isNewRecord} to determine whether the saving is
	 * for inserting or updating record.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the saving should be executed. Defaults to true.
	 */
	protected function beforeSave()
	{
		if ($this->hasEventHandler('onBeforeSave')) {
			$event = new CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		} else {
			return true;
		}
	}

	/**
	 * This event is raised before the record is saved.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onBeforeSave($event)
	{
		$this->raiseEvent('onBeforeSave', $event);
	}

	/**
	 * Returns all attribute values.
	 * @param array $names list of attributes whose value needs to be returned.
	 * Defaults to null, meaning all attributes as listed in {@link attributeNames} will be returned.
	 * If it is an array, only the attributes in the array will be returned.
	 * @param bool $includeId
	 * @return array attribute values (name=>value).
	 */
	public function getAttributes($names = null, $includeId = true)
	{
		$prepared = [];
		if ($includeId) {
			$prepared['id'] = $this->id;
		}
		$attributes = parent::getAttributes($names);


		return array_merge($prepared, $attributes);
	}

	/**
	 * This method is invoked after saving a record successfully.
	 * The default implementation raises the {@link onAfterSave} event.
	 * You may override this method to do postprocessing after record saving.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterSave()
	{
		if ($this->hasEventHandler('onAfterSave')) {
			$this->onAfterSave(new CEvent($this));
		}
	}

	/**
	 * This event is raised after the record is saved.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onAfterSave($event)
	{
		$this->raiseEvent('onAfterSave', $event);
	}

	/**
	 * Update record in database with data from document
	 * @param null $attributes
	 * @return bool whether record is succesfully updated or not
	 * @throws \CDbException
	 */
	public function update($attributes = null)
	{
		if ($this->getIsNewRecord()) {
			throw new CDbException(Yii::t('yii', 'The active record cannot be updated because it is new.'));
		}
		if ($this->beforeSave()) {
			$doc = $this->getAttributes($attributes);
			unset($doc['id']);
			$id = $this->id instanceof MongoId ? $this->id : new MongoId($this->id);
			try {
				$update = $this->getCollection()->update(['_id' => $id], ['$set' => $doc], ['fsync' => $this->fsync, 'multiple' => false]);
				if (!$update) {
					return $update;
				}

				$this->afterSave();
				return true;
			} catch (MongoCursorException $e) {
				throw new CDbException($e->getMessage(), $e->getCode());
			}
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getPrimaryKey()
	{
		return (string)$this->id;
	}

	/**
	 * @param $id
	 * @param array $values
	 * @return bool
	 */
	public function updateById($id, array $values)
	{
		unset($values['id'], $values['_id']);
		return $this->getCollection()->update(['_id' => $id instanceof MongoId ? $id : new MongoId($id)], ['$set' => $values], ['fsync' => $this->fsync, 'multiple' => false]);
	}

	/**
	 * Checks if a property value is null.
	 * This method overrides the parent implementation by checking
	 * if the named attribute is null or not.
	 * @param string $name the property name or the event name
	 * @return boolean whether the property value is null
	 */
	public function __isset($name)
	{
		if (isset($this->_attributes[$name])) {
			return true;
		} else {
			return parent::__isset($name);
		}
	}

	/**
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttribute($name, $default = null)
	{
		$attributes = $this->getAttributes();
		if (isset($attributes[$name])) {
			return $attributes[$name];
		}

		return $default;
	}

	/**
	 * Update record in database with data from document
	 * @param array|MongoDbCriteria $criteria
	 * @param array $document
	 * @return bool whether record is succesfully updated or not
	 */
	public function updateAll($criteria, $document)
	{
		if ($this->beforeSave()) {
			$updateCriteria = [];
			if ($this->criteria instanceof MongoDbCriteria) {
				if (is_array($criteria)) {
					$this->criteria->mergeWithArray($criteria);
					$updateCriteria = $this->criteria->getConditions();
				} else {
					if ($criteria instanceof MongoDbCriteria) {
						$this->criteria->mergeWithBuilder($criteria);
						$updateCriteria = $this->criteria->getConditions();
					} else {
						$updateCriteria = $criteria;
					}
				}
			}
			$saved = $this->getCollection()->update($updateCriteria, ['$set' => $document], ['w' => $this->safe, 'fsync' => $this->fsync, 'multiple' => true]);

			if ($saved) {
				$this->afterSave();
				return true;
			}
		}

		return false;
	}

	/**
	 * Merge attributes with document
	 * @param array $attributes
	 * @param bool $multiple Use TRUE only if u using criteria for update multiple rows without _id in criteria
	 * @return bool
	 * @throws CDbException
	 */
	public function saveAttributes($attributes = [], $multiple = false)
	{
		$doc = [];
		if (sizeof($attributes) === 0) {
			foreach ($this->attributeNames() AS $attributeName) {
				$doc[$attributeName] = $this->{$attributeName};
			}
		} else {
			foreach ($attributes AS $attributeName) {
				$doc[$attributeName] = $this->{$attributeName};
			}
		}

		try {
			$id = $this->id instanceof MongoId ? $this->id : new MongoId($this->id);
			if ($this->criteria instanceof MongoDbCriteria) {
				$updateCriteria = $this->criteria->getConditions();
			} else {
				$updateCriteria = ['_id' => $id];
			}

			return $this->getCollection()->update($updateCriteria, ['$set' => $doc], ['w' => $this->safe, 'fsync' => $this->fsync, 'multiple' => $multiple]);
		} catch (MongoCursorException $e) {
			throw new CDbException($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * Remove record from database
	 * @return bool whether record is succesfully removed or not
	 * @throws \CDbException
	 */
	public function delete()
	{
		if ($this->getIsNewRecord()) {
			throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
		}

		Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
		if ($this->beforeDelete()) {
			if ($this->criteria === null) {
				return $this->deleteById($this->id);
			} else {
				if ($this->criteria instanceof MongoDbCriteria) {
					$result = $this->getCollection()->remove($this->criteria->getConditions(), ['justOne' => false, 'w' => $this->safe]);
				} else {
					return false;
				}
			}

			$this->afterDelete();
			return isset($result['ok']) && $result['ok'] == 1;
		} else {
			return false;
		}
	}

	/**
	 * This method is invoked before deleting a record.
	 * The default implementation raises the {@link onBeforeDelete} event.
	 * You may override this method to do any preparation work for record deletion.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the record should be deleted. Defaults to true.
	 */
	protected function beforeDelete()
	{
		if ($this->hasEventHandler('onBeforeDelete')) {
			$event = new CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		} else {
			return true;
		}
	}

	/**
	 * This event is raised before the record is deleted.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onBeforeDelete($event)
	{
		$this->raiseEvent('onBeforeDelete', $event);
	}

	/**
	 * Remove record with specific $id from database
	 * @param string $id record id to remove
	 * @return boolean whether record is succesfully removed or not
	 */
	public function deleteById($id)
	{
		$_id = ($id instanceof MongoId ? $id : new MongoId($id));
		$result = $this->getCollection()->remove(['_id' => $_id], ['justOne' => true, 'w' => $this->safe]);

		if (is_array($result) && isset($result['ok'])) {
			return $result['ok'] == 1;
		} else {
			return true;
		}
	}

	/**
	 * This method is invoked after deleting a record.
	 * The default implementation raises the {@link onAfterDelete} event.
	 * You may override this method to do postprocessing after the record is deleted.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterDelete()
	{
		if ($this->hasEventHandler('onAfterDelete')) {
			$this->onAfterDelete(new CEvent($this));
		}
	}

	/**
	 * This event is raised after the record is deleted.
	 * @param CEvent $event the event parameter
	 * @since 1.0.2
	 */
	public function onAfterDelete($event)
	{
		$this->raiseEvent('onAfterDelete', $event);
	}

	/**
	 * Remove all record that match with criteria from database
	 * @param array $criteria record criteria to remove
	 * @return int return number of deleted record
	 */
	public function deleteAll($criteria)
	{
		$result = $this->getCollection()->remove($criteria, ['justOne' => false, 'w' => $this->safe]);
		if (is_array($result) && isset($result['n'])) {
			return $result['n'];
		} else {
			return true;
		}

	}

	/**
	 * Refresh record by repull data from database
	 * @return bool
	 */
	public function refresh()
	{
		Yii::trace(get_class($this) . '.refresh()', 'mongo.MongoRecord');
		if (!$this->getIsNewRecord() && ($record = $this->findById($this->id)) !== null) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param MongoId|string $id
	 * @return $this
	 */
	public function findById($id)
	{
		return $this->find(['_id' => $id instanceof MongoId ? $id : new MongoId($id)], false);
	}

	/**
	 * @param MongoDbCriteria|array|null $query
	 * @return int
	 */
	public function count($query = null)
	{
		if ($query instanceof MongoDbCriteria) {
			return $this->getCollection()->count($query->getConditions());
		} else {
			if (is_array($query) && sizeof($query) > 0) {
				return $this->getCollection()->count($query);
			} else {
				return $this->getCollection()->count();
			}
		}
	}

	/**
	 * @param array $fields
	 * @see MongoDbCriteria::mergeOrderFields
	 * @return $this
	 */
	public function sort($fields = [])
	{
		$this->getDbCriteria()->mergeOrderFields($fields);
		return $this;
	}

	/**
	 * @param string[]|string $fields Array or field list with comma
	 * @return $this
	 */
	public function select($fields = '*')
	{
		$this->getDbCriteria()->mergeWithArray([
			'select' => $fields,
		]);
		return $this;
	}

	/**
	 * Limit criteria
	 * @param int $limit
	 * @return $this
	 */
	public function limit($limit = 50)
	{
		$this->getDbCriteria()->mergeWith([
			'limit' => $limit,
		]);

		return $this;
	}

	/**
	 * Ищет наличие документа по  полю => значению
	 * @param string $field
	 * @param mixed $value
	 * @return bool
	 */
	public function exists($field, $value)
	{
		$cursor = $this->getCollection()->find([
			$field => $value,
		], [$field]);

		$cursor->limit(1);
		return $cursor->count() === 1;
	}

	/**
	 *
	 * Aggregation framework
	 * @link http://php.net/manual/ru/mongocollection.aggregate.php
	 * @param array $pipeline
	 * @param array $op
	 * @param array $pipelineOperators
	 * @return array
	 */
	public function aggregate(array $pipeline, array $op = [], array $pipelineOperators = [])
	{
		return $this->getCollection()->aggregate($pipeline, $op, $pipelineOperators);
	}

	/**
	 * Билдер для аггрегации
	 * @see MongoAggregation
	 * @return \MongoAggregation
	 */
	public function getAggregation()
	{
		if ($this->aggregation === null) {
			$this->aggregation = new MongoAggregation($this);
		}

		return $this->aggregation;
	}

	/**
	 * Return collection object as array
	 * @return array
	 */
	public function toArray()
	{
		$array = $this->getAttributes();
		$array['id'] = (string)$array['id'];
		return $array;
	}

	/**
	 * This is dummy method.
	 * @return $this
	 */
	public function cache($time, $dependency = null)
	{
		//dummy
		return $this;
	}

	/**
	 * This method is invoked before an AR finder executes a find call.
	 * The find calls include {@link find}, {@link findAll}, {@link findByPk},
	 * {@link findAllByPk}, {@link findByAttributes} and {@link findAllByAttributes}.
	 * The default implementation raises the {@link onBeforeFind} event.
	 * If you override this method, make sure you call the parent implementation
	 * so that the event is raised properly.
	 * @since 1.0.9
	 */
	protected function beforeFind()
	{
		if ($this->hasEventHandler('onBeforeFind')) {
			$this->onBeforeFind(new CEvent($this));
		}
	}

	/**
	 * This event is raised before an AR finder performs a find call.
	 * @param CEvent $event the event parameter
	 * @see beforeFind
	 * @since 1.0.9
	 */
	public function onBeforeFind($event)
	{
		$this->raiseEvent('onBeforeFind', $event);
	}
}
