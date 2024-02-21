
<?php

/**
 * GitHub webhook handler template.
 *
 * @see  https://developer.github.com/webhooks/
 * @author  Miloslav Hůla (https://github.com/milo)
 */

// In this file: /etc/apache2/sites-available/example.com.conf
// Put this line: SetEnv GITHUB_WEBHOOK_SECRET MY_SECRET
//$hookSecret = getenv('GITHUB_WEBHOOK_SECRET');

use Combodo\iTop\VCSManagement\Service\AutomationManager;

require_once('../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/startup.inc.php');

set_error_handler(function($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
	die();
});

// retrieve VCS repository
try
{
	/** @var \VCSRepository $oRepository */
	$oRepository = MetaModel::GetObject('VCSRepository', $_GET['repository']);
}
catch (Exception $e)
{
	ExceptionLog::LogException($e);
	die;
}

// get repository hook secret
$hookSecret = $oRepository->Get('secret');

$rawPost = NULL;

$res = parse_url($_SERVER['REQUEST_URI']);
echo json_encode($res);

if ($hookSecret !== NULL) {
	if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
	} elseif (!extension_loaded('hash')) {
		throw new \Exception("Missing 'hash' extension to check the secret code validity.");
	}
	list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
	if (!in_array($algo, hash_algos(), TRUE)) {
		throw new \Exception("Hash algorithm '$algo' is not supported.");
	}
	$rawPost = file_get_contents('php://input');
	if (!hash_equals($hash, hash_hmac($algo, $rawPost, $hookSecret))) {
		throw new \Exception('Hook secret does not match.');
	}
};

if (!isset($_SERVER['CONTENT_TYPE'])) {
	throw new \Exception("Missing HTTP 'Content-Type' header.");
} elseif (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
	throw new \Exception("Missing HTTP 'X-Github-Event' header.");
}

switch ($_SERVER['CONTENT_TYPE']) {
	case 'application/json':
		$json = $rawPost ?: file_get_contents('php://input');
		break;
	case 'application/x-www-form-urlencoded':
		$json = $_POST['payload'];
		break;
	default:
		throw new \Exception("Unsupported content type: $_SERVER[CONTENT_TYPE]");
}

# Payload structure depends on triggered event
# https://developer.github.com/v3/activity/events/types/
$payload = json_decode($json, true);

$sType = strtolower($_SERVER['HTTP_X_GITHUB_EVENT']);


// retrieve webhook user
$sWebhookUser = \Combodo\iTop\VCSManagement\Helper\ModuleHelper::GetModuleSetting('webhook_user_id', null);

// handle webhook
$iActionsTriggeredCount = AutomationManager::GetInstance()->HandleWebhook($sType, $oRepository, $payload);

// get sender login
$sSenderLogin = $payload['sender']['login'];

// increment events count and last date
$oRepository->DBIncrement('event_count');
$oRepository->Set('last_event_date', time());

// add event to case log
$ormCaseLog = $oRepository->Get('events_log');
$oDateTimeFormat =  \AttributeDateTime::GetFormat();
$sLogEntry = "Event <b>$sType</b> at " .  $oDateTimeFormat->Format(new DateTime('now')) . ' by ' . $sSenderLogin . '<br>' . $iActionsTriggeredCount . ' executed automation(s).';
if($sWebhookUser !== null){
	$ormCaseLog->AddLogEntry($sLogEntry, '', $sWebhookUser);
}
else{
	$ormCaseLog->AddLogEntry($sLogEntry, 'github');
}

$oRepository->Set('events_log', $ormCaseLog);
$oRepository->DBUpdate();
