<?php
namespace IBase\DAO;

use \IBase\Model\Subscriber;

/**
 * This interface defines our use-cases for a mailing list DAO. All implementing
 * classes need to define the methods below and make sure they return the same types
 */
interface DAOMailingListInterface {
	/**
	 * Subscribes a Subscriber object to a mailing list with the given id
	 * @param $sub The subscriber object
	 * @param $listId the unique identifier for the mailing list
	 * @return string The unique identifier for the subscription, null if the subscription failed
	 */
	public function subscribe(Subscriber $sub, $listId);
	/**
	 * Retrieves a list of the subscribers to the given mailing list
	 * @param $listId The mailing list id
	 * @param $status The status of the members
	 * @return string[] An Array of email addresses matching the criterias
	 */
	public function getMembersList($listId, $status);
	/**
	 * Returns the report for the opened emails.
	 * @param $campaignId The email campaign id
	 * @return string[] An Array of email addresses that have opened the given campaignId
	 */
	public function getOpenedReport($campaignId);
}
?>