<?php
require 'libs/PHPMailer/PHPMailerAutoload.php';
/**
 * Autoload function for PHP to read classes from the libs subfolder
 */
function ibaseAutoload($pClassName) {
	include(__DIR__ . "/libs/" . $pClassName . ".php");
}
spl_autoload_register("ibaseAutoload");

// import our namespaces
use \IBase\DAO\DAOFactory;
use \IBase\DAO\DAOMailingListInterface;
use \IBase\DAO\DAOSubscriberInterface;
use \IBase\Model\Subscriber;

// configuration variables
$config = array(
	"MC_LIST_ID" 	=> '',
	"MC_APP_ID" 	=> '',
	"DB_CONNECTION_STRING" 		=> '',
	"DB_USER" 		=> '',
	"DB_PASSWORD" 	=> ''
);

// load the environment configuration from an .ini file
$configurationFile = "env.ini";
$fileConfig = parse_ini_file(__DIR__ . "/" . $configurationFile);

// merge the various configurations. The latest input in array merge overrides the rest
$config = array_merge($config, $fileConfig);

// get the DAO object using the factory
$subscriberDao = DAOFactory::getFactory($config)->getSubscriberDao();
$mailingListDao = DAOFactory::getFactory($config)->getMailingListDao();

$membersList = $mailingListDao->getMembersList($config["MC_LIST_ID"], "unsubscribed");

echo json_encode($membersList);
?>