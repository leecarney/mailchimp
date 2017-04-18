<?php
namespace IBase\Model;

/**
 * The subscriber object. This is purely used to store data as it is passed around
 * between functions and the script. It contains NO database or application specific logic
 */
class Subscriber {
    public $id;    
    public $firstName;
    public $lastName;
    public $trialName;
    public $clientEmail;
    public $dateTrialRequestReceived;
    public $processedStatus;
    public $buttonURL1;
    public $buttonURL2;
    public $buttonURL3;
    public $unsubscribed;
    public $fullName;
    public $clickedEmail1;
    public $clickedEmail2;
    public $clickedEmail3;
    public $clickedEmail4;
    public $clickedEmail5;
    public $clickedEmail6;
	
	/**
	 * The magic __set function is called whenever a field in the object is set. In this
	 * case we are using it to perform some basic validation and transformation on the fields.
	 * For example, for the full name field we are setting the first letter of each word to 
	 * capital before saving it.
	 * @param $name The name of the field being set
	 * @param $value The valud that needs to be assigned to the field
	 */
	public function __set($name, $value) {
		switch ($name) {
			case "firstName":
			case "lastName":
			case "fullName":
				$tmpValue = ucwords($value); // ucwords is a PHP function that sets first letter capitals in each word
				$this->{$name} = $tmpValue;
				break;
			case "clientEmail":
				if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { // standard PHP email validator
					// if the email is invalid we throw an Exception. This should be checked to present
					// an error to the user
					throw new InvalidArgumentException("Invalid Email Address: " . $value);
				}
				$this->{$name} =$value;
				break;
			default:
				$this->{$name} =$value;
				break;
		}
	}
}
?>