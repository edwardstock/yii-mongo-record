<?php

/**
 * This is a MongoDbConnection component that used for connect to mongoDb database
 *
 * Usage: in config file in "components" array, insert following:
 *
 * 'mongodb'=>[
 *        'class'=>'path.to.mongo.Mongo.Connection.MongoDbConnection',
 *        'host'=>'localhost',
 *        'user'=>'mongo_user',
 *        'password'=>'mongo_password',
 *        'db'=>'my_db_name',
 *    ],
 *
 * @author yohan
 * @author Eduard Maximovich
 */
class MongoDbConnection extends CApplicationComponent
{

	/**
	 * @var MongoClient
	 */
	private $dbConnection;

	/**
	 * @var string
	 */
	private $db;

	/**
	 * @var string
	 */
	private $user;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var int
	 */
	private $port = 27017;

	/**
	 * @var string
	 */
	private $host = 'localhost';

	/**
	 * Driver options
	 * @var array
	 */
	private $driverOptions = [];

	/**
	 * Basic options
	 * @var array
	 */
	private $options = ['connect' => true];

	/**
	 * @return MongoDB
	 */
	public function getDb()
	{
		return $this->getConnection()->selectDB($this->db);
	}

	/**
	 * @param string $dbName
	 */
	public function setDb($dbName)
	{
		$this->db = $dbName;
	}

	/**
	 * @return \MongoClient
	 * @throws \CDbException
	 */
	public function getConnection()
	{
		if ($this->dbConnection === null) {
			try {
				if ($this->user !== null && $this->password !== null) {
					$this->dbConnection = new MongoClient($this->buildConnectionString(), $this->options, $this->driverOptions);
				} else {
					$this->dbConnection = new MongoClient($this->buildAnonymousConnectionString(), $this->options, $this->driverOptions);
				}
			} catch (MongoConnectionException $e) {
				throw new CDbException("Can't connect to Mongo DB (" . $e->getMessage() . ")");
			}
		}
		return $this->dbConnection;
	}

	/**
	 * @return string
	 */
	private function buildConnectionString()
	{
		return "mongodb://{$this->user}:{$this->password}@{$this->host}:{$this->port}/{$this->db}";
	}

	/**
	 * @return string
	 */
	private function buildAnonymousConnectionString()
	{
		return "mongodb://{$this->host}:{$this->port}/{$this->db}";
	}

	public function init()
	{
		parent::init();
		if (!extension_loaded('mongodb')) {
			throw new \RuntimeException("PHP extension MongoDB not loaded. Please check your configuration. See: http://php.net/manual/en/book.mongo.php");
		}
	}

	/**
	 * @return string
	 */
	public function getUser()
	{
		return $this->user;
	}

	/**
	 * @param string $config Username
	 */
	public function setUser($config)
	{
		$this->user = $config;
	}

	/**
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @param string $config
	 */
	public function setPassword($config)
	{
		$this->password = $config;
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * @param string $config
	 */
	public function setHost($config)
	{
		$this->host = $config;
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * @param int $port
	 */
	public function setPort($port)
	{
		$this->port = $port;
	}

	/**
	 * @link http://php.net/manual/ru/mongoclient.construct.php
	 * @param array $options
	 */
	public function setOptions(array $options)
	{
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * @link http://php.net/manual/ru/mongoclient.construct.php
	 * @param array $driverOptions
	 */
	public function setDriverOptions(array $driverOptions)
	{
		$this->driverOptions = $driverOptions;
	}
}