<?php
namespace IBase\DAO;

/**
 * This object constructs and returns our DAOs. We used the factory pattern because, if
 * in the future we decide to move away from SQL Server or MailChimp, this is the only class
 * where we will have to change the type of object initialized.
 * 
 * The factory is a singleton.
 */
class DAOFactory {
	// the local instance of the object, following the singleton pattern
	private static $_instance;
	
	// the environment configuration, passed from the main script
	private $envConfig;
	
	/**
	 * The default constructor for the object, it receives a $config array. This
	 * method should never be called from the script. Following the singleton pattern
	 * we should use the getFactory method of the class.
	 */
	public function __construct($config) {
		$this->envConfig = $config;
	}
 
	/**
	 * Set the factory instance
	 * @param DAOFactory $f
	 */
	public static function setFactory(DAOFactory $f) {
		self::$_instance = $f;
	}
 
	/**
	 * Get a factory instance. If the local instance is not initialized id creates a new one 
	 * @param $config An associative array containing the environment configuration
	 * @return DAOFactory
	 */
	public static function getFactory($config = array()) {
		if(!self::$_instance)
			self::$_instance = new self($config);
 
		return self::$_instance;
	}
 
	/**
	 * Get a Subscriber DAO
	 * @return \IBase\DAO\DAOSubscriberInterface
	 */
	public function getSubscriberDao()
	{
		return new DAOSubscriberMsSQL($this->envConfig);
	}
	
	/**
	 * Get a Mailing List DAO
	 * @return \IBase\DAO\DAOMailingListMailChimp
	 */
	public function getMailingListDao()
	{
		return new DAOMailingListMailChimp($this->envConfig);
	}
}
?>