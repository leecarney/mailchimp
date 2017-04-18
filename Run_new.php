<?php
// load the PHPMailer autoloader function. PHP Mailer doesn't use the standard PHP format
// for file names and namespacing so we use their custom autoloader
require 'libs/PHPMailer/PHPMailerAutoload.php';

// This is our custom autoloader function. Basically when we try to load a class, for example
// \IBase\Model\Subscriber this function will be called with the full namespace and class name, 
// it then looks up the library under the current directory (__DIR__) in the libs folder. This
// is also why I moved the MailChimp library under a folder named as its namespace, Drewm.
function ibaseAutoload($pClassName) {
	include(__DIR__ . "/libs/" . $pClassName . ".php");
}
// register our autoloader function with PHP
spl_autoload_register("ibaseAutoload");

// import our namespaces
use \IBase\DAO\DAOFactory;
use \IBase\Model\Subscriber;

// Setup some default configuration variables. This is not required, it is here just so that 
// all the possible options are visible to the developer at a glance, and we avoid getting errors
// at runtime from missing configuration parameters.
$config = array(
	"MC_LIST_ID" 		=> '',
	"MC_APP_ID" 		=> '',
	"DB_CONNECTION_STRING" 		=> '',
	"DB_USER" 		=> '',
	"DB_PASSWORD" 	=> '',
	"MAIL_HOST"		=> '',
	"MAIL_PORT"		=> '',
	"MAIL_USERNAME" => '',
	"MAIL_PASSWORD" => '',
	"UNSUB_MAIL_FROM" => '',
	"UNSUB_MAIL_TO" => '',
	"REPORT_MAIL_FROM" => '',
	"REPORT_MAIL_TO" => ''
);

// Set the default configuration file name. The service will look for the file
// in the current directory, and load it to augment the default configuration above
$configurationFile = "env.ini";

// Override variables for shell arguments
$isHelp = false;
$verbose = false;
$sendEmails = true;

// set the variables from the shell arguments
if ( isset($argv) && is_array($argv) ) {
	// loop over the parameters and set the global variables
	for ($i = 0; $i < sizeof($argv); $i++) {
		// We give the ability to override the configuration file used by the script.
		// This way, if we are running a test, we can use a different environment file, 
		// pointing to a dev database and MailChimp ML
		if ($argv[$i] == "--config" || $argv[$i] == "-c") {
			$configurationFile = $argv[$i + 1];
		}
		
		if ($argv[$i] == "--verbose" || $argv[$i] == "-v") {
			$verbose = true;
		}
		
		if ($argv[$i] == "--help" || $argv[$i] == "-h") {
			$isHelp = true;
		}
		
		if ($argv[$i] == "--noemail" || $argv[$i] == "-n") {
			$sendEmails = false;
		}
	}
}

// If we just asked for help (-h) then show this text and quit
if ($isHelp) {
	echo "\nUSAGE: " . $argv[0] . " --config/-c --verbose/-v --help/-h\n\n";
	echo "--config / -c : Provide an environment configuration file name\n";
	echo "--verbose / -v : Whether to print verbose output\n";
	echo "--noemail / -n : Do not send emails\n";
	echo "--help / -h : Print this help\n\n";
	return 0;
}

// load the ini configuration file
$fileConfig = parse_ini_file(__DIR__ . "/" . $configurationFile);

// merge the various configurations. The latest input in array merge overrides the rest
$config = array_merge($config, $fileConfig);

echo "INFO: Configuration loaded from " . $configurationFile . " - Starting script". PHP_EOL;

// get the DAO objects using the factory methods.
$subscriberDao = DAOFactory::getFactory($config)->getSubscriberDao();
$mailingListDao = DAOFactory::getFactory($config)->getMailingListDao();

// first update all button urls - one big update is more efficient than 3 separate ones
// 1. Create the buttons content
// 2. Assign the content to the relevant database field in the $fields array
// 3. Assign the conditions for our update (we only update where ProcessedStatus = 0)
// 4. Use the Subscriber DAO to run the update with the values
$buttonUrl1 = '<a class="mcnButton" title="Try it out" href="" target="_blank">Try it out</a>';
$buttonUrl2 = '<a class="mcnButton" title="Try it out" href="" target="_blank">Try it out</a>';
$buttonUrl3 = '<a class="mcnButton" title="Try it out" href="" target="_blank">Try it out</a>';
$fields = array(
	"ButtonURL1" => $buttonUrl1,
	"ButtonURL2" => $buttonUrl2,
	"ButtonURL3" => $buttonUrl3
);
$conditions = array(
	"ProcessedStatus" => 0
);
$updatedRows = $subscriberDao->updateAllSubscribers($fields, $conditions);

if ( $updatedRows == 0 ) {
	echo "INFO: No subscribers found for button url update" . PHP_EOL;
} else {
	echo "INFO: Updated " . $updatedRows . " subscribers with button urls". PHP_EOL;
}

// Use the DAO to get a list of all subscribers
$subscribers = $subscriberDao->findSubscribers();

if ( $verbose ) echo "DEBUG: Found a total of " . count($subscribers) . " subscribers" . PHP_EOL;
// loop over the subscribers. these are \IBase\Model\Subscriber objects
foreach ( $subscribers as $sub) {
	if ( $sub->processedStatus == 0 && $sub->unsubscribed == 0 ) {
		// Use the mailing list DAO to run the update
		$subId = $mailingListDao->subscribe($sub, $config["MC_LIST_ID"]); 
		 
		if ( $subId != null ) {
			$sub->processedStatus = 1;
			$subscriberDao->updateSubscriber($sub);
			if ( $verbose ) echo "DEBUG: Subscriber " . $sub->clientEmail . " subscribed to " . $config["MC_LIST_ID"]. PHP_EOL;
		} else {
			echo "ERROR: Error while subscribing " . $sub->clientEmail . " to list " . $config["MC_LIST_ID"]. PHP_EOL;
		}
	}
}

// Use the DAO object to load a list of email addresses that have unsubscribed from the mailing list
$membersList = $mailingListDao->getMembersList($config["MC_LIST_ID"], "unsubscribed");

// loop over the list of members (emails)
foreach ( $membersList as $email ) {
	// get the subscriber data from the database using our DAO
	$sub = $subscriberDao->findSubscriberByEmail($email);
	if ( $sub == null ) {
		echo "WARNING: could not find subscriber $email". PHP_EOL;
		continue;
	}
	
	// use the local sendEmail function to send a notification of the unsubscription
	// function is defined at the bottom of the file
	$subject = 'MailChimp Unsubscribe Notification';
	$body = "<b>" . $sub->clientEmail . "</b> clicked unsubscribe and their trial system can be removed.";
	if ( $sendEmails ) // override from shell arguments
		sendEmail($config["UNSUB_MAIL_FROM"], $config["UNSUB_MAIL_TO"], $subject, $body);
	
	$sub->unsubscribed = 1;
	// TODO: Should be one big update rather than on each loop
	$subscriberDao->updateSubscriber($sub);
	if ( $verbose) echo "DEBUG: Updated subscriber " . $sub->clientEmail. PHP_EOL;
}

// loop 6 times to generate the reports
for ($i = 1; $i < 7; $i++) {
	// get the current report from the DAO. We use the loop counter to load the correct
	// report id from the configuration file
	$mailSubscribers = $mailingListDao->getOpenedReport($config["MC_MAIL" . $i . "_REPORT"]);
	// loop over the list of email addresses
	foreach ( $mailSubscribers as $email ) {
		// TODO: This should also be one big update!
		$sub = $subscriberDao->findSubscriberByEmail($email);
		
		if ( $sub == null) {
			echo "ERROR: Error while finding subscriber " . $email . " from report " . $config["MC_MAIL" . $i . "_REPORT"] . PHP_EOL;
			continue;
		}
		// use the special accessor to set the clickedEmail field in the Subscriber object and 
		// update the database
		$sub->{"clickedEmail" . $i} = 1;
		$subscriberDao->updateSubscriber($sub);
	}
}

// use the current directory (__DIR__) to load the output file 
$fileName = __DIR__ . '\EmailClickTableOutput\Output.html';
print_r(file_put_contents($fileName, "<html><head><script type='text/javascript' src='https://ajax.microsoft.com/ajax/jQuery/jquery-1.4.2.min.js'></script></head>
<body><table border='1' cellpadding='1' cellspacing='1'><tbody><tr><th style='text-align: left;'>Subscriber</th><th>Clicked Mail 1?</th><th>Clicked Mail 2?</th><th>Clicked Mail 3?</th><th>Clicked Mail 4?</th><th>Clicked Mail 5?</th><th>Clicked Mail 6?</th></tr>"),true);
// get all active subscribers from the DAO. This returns a list of \IBase\Model\Subscriber objects
$subscribers = $subscriberDao->findSubscribers(array("Unsubscribed" => 0));

foreach ( $subscribers as $sub) {				
      print_r(file_put_contents($fileName, "<tr>",FILE_APPEND),true);
      print_r(file_put_contents($fileName, "<td style='text-align: left;'>" . $sub->clientEmail . "</td>",FILE_APPEND),true);
	  for ($i = 1; $i < 7; $i++) {
	  	// here we use the special accessor for the clickedEmailX field using the loop counter
      	print_r(file_put_contents($fileName, "<td style='text-align: center;'>" . $sub->{"clickedEmail" . $i} . "</td>",FILE_APPEND),true);
	  }
      print_r(file_put_contents($fileName, "</tr>",FILE_APPEND),true);
}
print_r(file_put_contents($fileName, "</tbody></table><script>$('td').each(function () ".'{'."$(this).html($(this).html().replace(/(0)/g, '<span style=\"color: #ff0000;font-size:8pt\">$1</span>'));$(this).html($(this).html().replace(/(1)/g, '<span style=\"color: #66CD00;font-size:16pt\">$1</span>'));".'}'.");</script></body></html>",FILE_APPEND),true);

// sends the final confirmation email
$from = 'ChimpStats@ibase.com';
$to = 'sales@ibase.com';     
$subject = 'Mailchimp Daily Email Click Summary Table';
$body = "Report Attached";
if ( $sendEmails )
	sendEmail($from, $to, $subject, $body, $fileName);
																							

/**
 * Sends an email using the PHPMailer library
 * @param $from The from email address
 * @param $to The recipient email address
 * @param $subject The email subject
 * @param $body The email body content (only HTML)
 * @param $attachment The name of the attachment file. By default this is blank and no attachment is sent
 */
function sendEmail($from, $to, $subject, $body, $attachment = "")  {
	// this instruction gives the function access to the $config variable in the global
	// scope. The variable is loaded at the beginning of the script and wouldn't be accessible
	// here without this.
	global $config;
	$mailer = new PHPMailer;
	$mailer->isSMTP();                                     
	$mailer->Host = $config["MAIL_HOST"];  
	$mailer->SMTPAuth = true;                              
	$mailer->Username = $config["MAIL_USERNAME"];                
	$mailer->Password = $config["MAIL_PASSWORD"];                          
	$mailer->Port = $config["MAIL_PORT"];                                   
	$mailer->From = $from;
	$mailer->FromName = 'Mailchimp Unsubscribe Notification';
	$mailer->addAddress(explode(",", $to));     
	$mailer->isHTML(true);                                 
	$mailer->Subject = $subject;
	$mailer->Body    = $body;
	
	if ( $attachment != "" ) {
		$mail->AddAttachment($attachment);
	}
	
	if( !$mailer->send() ) {
		echo 'MAILER: Message could not be sent.' . PHP_EOL;
		echo 'MAILER: Mailer Error: ' . $mailer->ErrorInfo . PHP_EOL;
	} else {
		echo 'MAILER: Message has been sent' . PHP_EOL;
	}
}
?>