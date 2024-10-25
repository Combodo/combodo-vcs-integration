
<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;

require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/startup.inc.php');

// Temporary workaround to make sure mandatory parameters are provided
if (!array_key_exists('transaction_id', $_REQUEST)) {
    $_REQUEST['transaction_id'] = utils::GetNewTransactionId();
}
if (!array_key_exists('HTTP_REFERER', $_SERVER)) {
    $_SERVER['HTTP_REFERER'] = 'https://github.com/';
}

set_error_handler(function($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
	die();
});

// retrieve VCS webhook
try
{
	/** @var VCSWebhook $oWebhook */
	$oWebhook = MetaModel::GetObject('VCSWebhook', $_GET['webhook']);
}
catch (Exception $e)
{
	ExceptionLog::LogException($e, [
		'happened when' => 'Receiving github webhook in GitHub.php',
		'error message' => 'Webhook not found',
		'webhook id' => $_GET['webhook'],
	]);
	throw $e;
}

// get webhook secret
$sHookSecret = $oWebhook->Get('secret');

$sRawPost = NULL;

$res = parse_url($_SERVER['REQUEST_URI']);
echo json_encode($res);

if ($sHookSecret !== NULL) {
	if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
	} elseif (!extension_loaded('hash')) {
		throw new \Exception("Missing 'hash' extension to check the secret code validity.");
	}
	list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
	if (!in_array($algo, hash_algos(), TRUE)) {
		throw new \Exception("Hash algorithm '$algo' is not supported.");
	}
	$sRawPost = file_get_contents('php://input');
	if (!hash_equals($hash, hash_hmac($algo, $sRawPost, $sHookSecret))) {
		throw new \Exception('Hook secret does not match.');
	}
}

if (!isset($_SERVER['CONTENT_TYPE'])) {
	throw new \Exception("Missing HTTP 'Content-Type' header.");
} elseif (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
	throw new \Exception("Missing HTTP 'X-Github-Event' header.");
}

switch ($_SERVER['CONTENT_TYPE']) {
	case 'application/json':
		$json = $sRawPost ?: file_get_contents('php://input');
		break;
	case 'application/x-www-form-urlencoded':
		$json = $_POST['payload'];
		break;
	default:
		throw new \Exception("Unsupported content type: $_SERVER[CONTENT_TYPE]");
}

# retrieve event type & delivery id
$sType = strtolower($_SERVER['HTTP_X_GITHUB_EVENT']);
$sDeliveryId = $_SERVER['HTTP_X_GITHUB_DELIVERY'];
$sUuid = $_SERVER['HTTP_X_GITHUB_HOOK_ID'];

// retrieve webhook user
$sWebhookUser = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_WEBHOOK_USER_ID);

# Payload structure depends on triggered event
# https://developer.github.com/v3/activity/events/types/
$aPayload = json_decode($json, true);

// get sender login
$sSenderLogin = $aPayload['sender']['login'];

// Log in log system
ModuleHelper::LogInfo("Receiving GitHub Event #" . $sDeliveryId, [
	'webhook id' => $_GET['webhook'],
	'sender' => $sSenderLogin,
	'delivery' => $sDeliveryId,
	'uuid' => $sUuid,
	'type' => $sType
]);

// handle webhook
/** @var VCSWebhookPayload $oWebhookPayload */
$oWebhookPayload = MetaModel::NewObject('VCSWebhookPayload');
$oWebhookPayload->Set('provider', 'github');
$oWebhookPayload->Set('type', $sType);
$oWebhookPayload->Set('webhook_id', $oWebhook->GetKey());
$oWebhookPayload->Set('payload', $json);
$oWebhookPayload->DBInsert();