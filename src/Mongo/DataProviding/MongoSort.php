<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class MongoSort extends CSort
{

	/**
	 * Sort ascending
	 * @since 1.1.10
	 */
	const SORT_ASC = MongoDbCriteria::SORT_ASC;

	/**
	 * Sort descending
	 * @since 1.1.10
	 */
	const SORT_DESC = MongoDbCriteria::SORT_DESC;

	public $descTag = MongoDbCriteria::SORT_DESC;

	/**
	 * Modifies the query criteria by changing its {@link CDbCriteria::order} property.
	 * This method will use {@link directions} to determine which columns need to be sorted.
	 * They will be put in the ORDER BY clause. If the criteria already has non-empty {@link CDbCriteria::order} value,
	 * the new value will be appended to it.
	 * @param MongoDbCriteria $criteria the query criteria
	 */
	public function applyOrder($criteria)
	{
		if (!isset($_GET[$this->sortVar])) {
			return;
		}

		$temp = explode('.', $_GET[$this->sortVar]);
		$field = $temp[0];
		if (isset($temp[1]) && $temp[1] == self::SORT_ASC) {
			$arrow = 'ASC';
		} else if (isset($temp[1]) && $temp[1] == self::SORT_DESC) {
			$arrow = 'DESC';
		} else {
			$arrow = 'ASC';
		}

		$criteria->orderBy($field, $arrow, $this->multiSort);
	}

	/**
	 * @param MongoDbCriteria $criteria the query criteria
	 * @return string the order-by columns represented by this sort object.
	 * This can be put in the ORDER BY clause of a SQL statement.
	 * @since 1.1.0
	 */
	public function getOrderBy($criteria = null)
	{
		//лишние запчасти
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
}