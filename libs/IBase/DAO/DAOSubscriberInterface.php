<?php
namespace IBase\DAO;

use \IBase\Model\Subscriber;

/**
 * Interface for the Subscriber model in our local storage/database. See comment in the DAOMailingListInterface
 * for the purpose of this interface.
 */
interface DAOSubscriberInterface {
	/**
	 * Find a single subscriber by id
	 * @param $id The unique identifier for the subscriber
	 * @return \IBase\Model\Subscriber
	 */
    public function findSubscriberById($id);
	/**
	 * Finds a single subscriber by email address
	 * @param $email The email address
	 * @return \IBase\Model\Subscriber
	 */
	public function findSubscriberByEmail($email);
	/**
	 * Finds all subscribers matching the conditions passed as an associative array
	 * @param $conditions An associative array of conditions (FieldName => Value)
	 * @return \IBase\Model\Subscriber[]
	 */
	public function findSubscribers($conditions = array());
	/**
	 * Updates all fields in the given Subscriber object
	 * @param $sub an \IBase\Model\Subscriber object populated with the information to be updated
	 * @return int The number of rows updated
	 */
    public function updateSubscriber(Subscriber $sub);
	/**
	 * Runs an update statement on the table for all subscribers matching the given conditions.
	 * @param $fields This parameters should contain an associative array of fieldName => fieldValue
	 * @param $conditions An associative array of "AND" conditions for the update
	 * @return int The number of rows update
	 */
	public function updateAllSubscribers($fields, $conditions = array());
}
?>