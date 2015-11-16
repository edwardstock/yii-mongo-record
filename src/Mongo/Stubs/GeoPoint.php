<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class GeoPoint
{

	/**
	 * @var double
	 */
	private $latitude;

	/**
	 * @var double
	 */
	private $longitude;

	/**
	 * @var int
	 */
	private $minDistance;

	/**
	 * @var int
	 */
	private $maxDistance;

	/**
	 * @param double $latitude Широта
	 * @param double $longitude Долгота
	 * @param int $minDistance Минимальная дистанция в километрах
	 * @param int $maxDistance Максимальная дистанция в километрах
	 */
	public function __construct($latitude, $longitude, $minDistance = null, $maxDistance = null)
	{
		$this->latitude = $latitude;
		$this->longitude = $longitude;
		$this->minDistance = $minDistance;
		$this->maxDistance = $maxDistance;
	}

	/**
	 * @return int
	 */
	public function getMinDistance()
	{
		return (int)$this->minDistance;
	}

	/**
	 * @param int $minDistance
	 */
	public function setMinDistance($minDistance)
	{
		$this->minDistance = $minDistance;
	}

	/**
	 * @return int
	 */
	public function getMaxDistance()
	{
		return (int)$this->maxDistance;
	}

	/**
	 * @param int $maxDistance
	 */
	public function setMaxDistance($maxDistance)
	{
		$this->maxDistance = $maxDistance;
	}

	/**
	 * @return mixed
	 */
	public function getLatitude()
	{
		return (double)$this->latitude;
	}

	/**
	 * @return mixed
	 */
	public function getLongitude()
	{
		return (double)$this->longitude;
	}

	/**
	 * @return bool
	 */
	public function hasAllDistances()
	{
		return $this->hasMaxDistance() && $this->hasMinDistance();
	}

	/**
	 * @return bool
	 */
	public function hasMaxDistance()
	{
		return isset($this->maxDistance) && $this->maxDistance !== null;
	}

	/**
	 * @return bool
	 */
	public function hasMinDistance()
	{
		return isset($this->minDistance) && $this->minDistance !== null;
	}

}
