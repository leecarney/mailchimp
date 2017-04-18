<?php
namespace IBase\DAO;

use \IBase\Model\Subscriber;

/**
 * An implementation for the DAOSubscriberInterface that support SQL. 
 */
class DAOSubscriberMsSQL implements DAOSubscriberInterface {
	// our database connection
	private $connection; 
	
	// The list of fields in the database table
	private static $fieldList = array(
		"FirstName",
		"LastName",
		"TrialName",
		"ClientEmail",
		"DateTrialRequestReceived",
		"ProcessedStatus",
		"ButtonURL1",
		"ButtonURL2",
		"ID",
		"unsubscribed",
		"FullName",
		"ButtonURL3",
		"ClickedEmail1",
		"ClickedEmail2",
		"ClickedEmail3",
		"ClickedEmail4",
		"ClickedEmail5",
		"ClickedEmail6",
	);
	// the name of the database table
	private static $tableName = "dbo.Subscribers";
	
	// this is a private variable used only to name fields in prepared statements
	private static $pdoUpdateFieldPrefix = "upd_";
	
	/**
	 * Default constructor that recieves the $config array form the main script. It uses
	 * the config to load the database odbc connection string, username and password.
	 */
    public function __construct($config) {
    	$this->connection = new \PDO($config["DB_CONNECTION_STRING"], $config["DB_USER"], $config["DB_PASSWORD"]);
    }
	
	public function findSubscriberById($id) {
		$sqlQuery = $this->buildSelectString(array("ID" => $id));
		
		// We use the PHP PDO object. 
		// 1. We ask the connection to prepare a query with the given SQL string
		// 2. We bind the named parameters: our query will contain a WHERE ID = :ID, the bindParam method
		//    sets the $id value for the :ID named field
		// 3. We ask the query to execute
		$query = $this->connection->prepare($sqlQuery);
		$query->bindParam(":ID", $id);
		
		$query->execute();
		
		if ( $query->rowCount() == 0 )
			return null;
		
		$subscriberData = $query->fetch(\PDO::FETCH_ASSOC);
		
		return $this->sqlDataToModel($subscriberData);
	}
	
	public function findSubscriberByEmail($email) {
		$sqlQuery = $this->buildSelectString(array("ClientEmail" => $email));
		
		$query = $this->connection->prepare($sqlQuery);
		$query->bindParam(":ClientEmail", $email);
		
		$query->execute();
		
		if ( $query->rowCount() == 0 )
			return null;
		
		$subscriberData = $query->fetch(\PDO::FETCH_ASSOC);
		
		return $this->sqlDataToModel($subscriberData);
	}
	
	public function findSubscribers($conditions = array()) {
		$subscribers = array();
		$sqlQuery = $this->buildSelectString($conditions);
		
		$query = $this->connection->prepare($sqlQuery);
		foreach ( $conditions as $field => $value ) {
			$query->bindParam(":" . $field, $value);
		}
		
		$query->execute();
		
		if ( $query->rowCount() == 0 )
			return $subscribers;
		
		while ($sub = $query->fetch(\PDO::FETCH_ASSOC)) {
			$subscribers[] = $this->sqlDataToModel($sub);
		}
		
		return $subscribers;
	}
	
    public function updateSubscriber(Subscriber $sub) {
    	$sqlQuery = $this->buildUpdateString(self::$fieldList, array("ID" => $sub->id));
		$query = $this->connection->prepare($sqlQuery);
		//echo $sqlQuery . PHP_EOL;
		// prepare the value bindings
		foreach (self::$fieldList as $field ) {
			// skip ID
			if ( $field == "ID" )
				continue;
			
			$query->bindParam(":" . self::$pdoUpdateFieldPrefix . $field, $value);
		}
		// bind the static ID param
		$query->bindParam(":ID", $sub->id);
		
		$query->execute();
		
		return $query->rowCount();
    }
    
	public function updateAllSubscribers($fields, $conditions = array()) {
		$sqlQuery = $this->buildUpdateString(array_keys($fields), $conditions);
		//echo "prepared SQL: " . $sqlQuery;
		$query = $this->connection->prepare($sqlQuery);
		
		// prepare the value bindings
		foreach ($fields as $field => $value ) {
			// skip ID
			if ( $field == "ID" )
				continue;
			
			$query->bindParam(":" . self::$pdoUpdateFieldPrefix . $field, $value);
		}
		
		// bind conditions
		foreach ($conditions as $field => $value ) {
			$query->bindParam(":" . $field, $value);
		}
		
		$query->execute();
		
		return $query->rowCount();
	}
	
	/**
	 * Private method to build the SELECT query string from the list of fields stored in the
	 * $fieldList static variable. It can receive an associative array of "AND" conditions. These
	 * are used to generate the where clause. The array should contain fieldName => fieldValue, and only
	 * "=" comparisons are supported.
	 * 
	 * The query string returned by this function contains named parameters for a prepared statement.
	 * 
	 * @param $conditions An associative array of conditions for the where clause
	 * @return string The query string with named parameters
	 */
	private function buildSelectString($conditions) {
		$query = "SELECT " . implode(",", self::$fieldList) . " FROM " . self::$tableName;
		
		if ( count($conditions) > 0 ) {
			$cnt = 0;
			
			foreach ( $conditions as $field => $value ) {
				$query .= ($cnt == 0?" WHERE":" AND") . " " . $field . " = :" . $field;
				$cnt++;
			}
		}

		return $query;
	}
	
	/**
	 * Similarly to the buildSelectString method this function creates an SQL string for UPDATE
	 * statements.
	 * @param $fields An array of field names ["ID", "clickedEmail1", ...] - this list should contain all the fields to be update
	 * @param $conditions An associative array of conditions for the where clause
	 */
	private function buildUpdateString($fields, $conditions) {
		$query = "UPDATE " . self::$tableName . " SET ";
		
		foreach ( $fields as $fieldName ) {
			// skip the ID row, we are never going to update it
			if ( $fieldName == "ID" )
				continue;
			$query .= " " . $fieldName . " = :" . self::$pdoUpdateFieldPrefix . $fieldName;
		}
		
		if ( count($conditions) > 0 ) {
			$cnt = 0;
			
			foreach ( $conditions as $field => $value ) {
				$query .= ($cnt == 0?" WHERE":" AND") . " " . $field . " = :" . $field;
				$cnt++;
			}
		}
		
		return $query;
	}
	
	/**
	 * Converts the results from a PDO query row into a Subscriber object
	 * @param $sqlResult An associative array contaning the values returned from the database
	 * @return \IBase\Model\Subscriber A populated Subscriber object 
	 */
	private function sqlDataToModel($sqlResult) {
		$subscriber = new Subscriber();
		
		foreach (self::$fieldList as $field ) {
			$fieldValue = $sqlResult[$field];
			
			$classProperty = $this->getClassPropertyForField($field);
			
			$subscriber->{$classProperty} = $fieldValue;
		}
		
		return $subscriber;
	}
	
	/**
	 * This utility function transforms a database field name into a class property name.
	 * For example in our database the field is called ClickedEmail1, the class property is
	 * clickedEmail1. Changes the CamelCase and makes the exception for the ID field.
	 * @param $field The name of the database field
	 * @return string The name of the field in the Model class
	 */
	private function getClassPropertyForField($field) {
		// transform the first letter to lowercase if it's not
		$classProperty = lcfirst($field);
		// exception for ID
		if ( $field == "ID" )
			$classProperty = 'id';
		
		return $classProperty;
	}
}
?>