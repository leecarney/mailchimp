<?php
namespace IBase\DAO;

use \IBase\Model\Subscriber;
use \Drewm\MailChimp;

/**
 * This is the MailChimp implementation of our MailingList DAO Interface. By using this pattern
 * we abstract the actual data storage/source from the script. For example, in the future we could implement
 * a new version of the mailing list interface that stores data in our own database, the script will not need
 * to know, all it cares about is that it respects the DAOMailingListInterface. This is also very helpful for
 * testing. When running automated unit tests we don't want to touch our production mailing list, so we could
 * have a dummy implementation that returns expected values, or uses a different mailing list id 
 */
class DAOMailingListMailChimp implements DAOMailingListInterface {
	private $mailchimp;
	
	public function __construct($config) {
    	// initialize the MailChimp client
		$this->mailchimp = new MailChimp($config["MC_APP_ID"]);
		
		//Set up custom MailChimp merge tags
		$this->mailchimp->call('lists/merge-var-add',array(
		                'id'                => $config["MC_LIST_ID"],
		               	'tag'               => 'BUTTONURL1',	
		               	'name'		    	=> 'Button URL1',
		));
		$this->mailchimp->call('lists/merge-var-add',array(
		                'id'                => $config["MC_LIST_ID"],
		               	'tag'               => 'BUTTONURL2',	
		               	'name'		    	=> 'Button URL2',
		));
		$this->mailchimp->call('lists/merge-var-add',array(
		                'id'                => $config["MC_LIST_ID"],
		               	'tag'               => 'BUTTONURL3',	
		               	'name'		    	=> 'Button URL3',
		));
    }
	
	public function subscribe(Subscriber $sub, $listId) {
		$out = $this->mailchimp->call('lists/subscribe', array(
			'id'                => $listId,
			'email'             => array('email'=>$sub->clientEmail),
			'merge_vars'        => array(
				'FNAME'		=>$sub->firstName, 
				'LNAME'		=>$sub->lastName,
				'ButtonURL1'=>$sub->buttonURL1,
				'ButtonURL2'=>$sub->buttonURL2,
				'ButtonURL3'=>$sub->buttonURL3
			),
			'double_optin'      => false,
			'update_existing'   => true,
			'replace_interests' => false,
			'send_welcome'      => false,
		));
		
		if ( array_key_exists('leid', $out) ) {
			return $out["leid"];
		} else {
			return null;
		}
	}
	
	public function getMembersList($listId, $status) {
		$out = $this->mailchimp->call('lists/members', array(
			'id' => $listId,
			'status' =>$status,	
		));
		
		$subscribers = array();
		
		foreach ( $out["data"] as $subscriber ) {
			$subscribers[] = $subscriber["email"];	
		}
	
		return $subscribers;
	}
	
	public function getOpenedReport($campaignId) {
		$out = $this->mailchimp->call('reports/opened', array(
		    'cid' => $campaignId,
		));
		//echo json_encode($out["data"]);
		$subscribers = array();
		
		foreach ( $out["data"] as $subscriber ) {
			$subscribers[] = $subscriber["member"]["email"];	
		}
	
		return $subscribers;
	}
}
?>