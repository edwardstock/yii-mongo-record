<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class MongoPagination extends CPagination
{

	/**
	 * Applies LIMIT and OFFSET to the specified query criteria.
	 * @param MongoDbCriteria $criteria the query criteria that should be applied with the limit
	 */
	public function applyLimit($criteria)
	{
		$criteria->limit = $this->getLimit();
		$criteria->offset = $this->getOffset();
	}
}