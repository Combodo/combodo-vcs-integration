<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
	// Dictionary entries go here

	// MENU elements
	'Menu:VCSRepositoryMenu' => 'VCS Repositories',
	'Menu:VCSRepositoryMenu+' => 'VCS Repositories',

	// CLASS VCSConnector
	'Class:VCSConnector' => 'Connector',
	'Class:VCSConnector+' => 'Version Control System Connector',
	'Class:VCSConnector/Attribute:label' => 'Label',
	'Class:VCSConnector/Attribute:label+' => 'Label of the connector',
	'Class:VCSConnector/Attribute:provider' => 'Provider',
	'Class:VCSConnector/Attribute:provider+' => 'Version control system provider',
	'Class:VCSConnector/Attribute:provider/Value:github' => 'GitHub',
	'Class:VCSConnector/Attribute:owner' => 'Resource owner',
	'Class:VCSConnector/Attribute:owner+' => 'Resource owner (user or organization)',
	'Class:VCSConnector/Attribute:mode' => 'Authentication Mode',
	'Class:VCSConnector/Attribute:mode+' => 'API Authentication Mode',
	'Class:VCSConnector/Attribute:mode/Value:none+' => 'None',
	'Class:VCSConnector/Attribute:mode/Value:personal' => 'Personal access token',
	'Class:VCSConnector/Attribute:mode/Value:app' => 'Application installation access token',
	'Class:VCSConnector/Attribute:personal_access_token' => 'Personal access token',
	'Class:VCSConnector/Attribute:personal_access_token+' => 'Personal access token',
	'Class:VCSConnector/Attribute:app_id' => 'App ID',
	'Class:VCSConnector/Attribute:app_id+' => 'Application ID',
	'Class:VCSConnector/Attribute:app_private_key' => 'Private key',
	'Class:VCSConnector/Attribute:app_private_key+' => 'Application private key',
	'Class:VCSConnector/Attribute:repositories' => 'Repositories',
	'Class:VCSConnector/Attribute:repositories+' => 'Connector repositories',

	// CLASS VCSRepository
	'Class:VCSRepository' => 'Repository',
	'Class:VCSRepository+' => 'Version Control System Repository',
	'Class:VCSRepository/Attribute:name' => 'Repository name',
	'Class:VCSRepository/Attribute:name+' => 'The name of the repository on version control system',
	'Class:VCSRepository/Attribute:connector_id' => 'Connector',
	'Class:VCSRepository/Attribute:connector_id+' => 'Version control system connector',
	'Class:VCSRepository/Attribute:webhook' => 'Webhook URL',
	'Class:VCSRepository/Attribute:webhook+' => 'URL to provide to version control system webhook repository configuration',
	'Class:VCSRepository/Attribute:webhook_status' => 'Status',
	'Class:VCSRepository/Attribute:webhook_status+' => 'Status on GitHub',
	'Class:VCSRepository/Attribute:webhook_status/Value:unsynchronized' => 'Unsynchronized',
	'Class:VCSRepository/Attribute:webhook_status/Value:active' => 'Active',
	'Class:VCSRepository/Attribute:webhook_status/Value:inactive' => 'Inactive',
	'Class:VCSRepository/Attribute:webhook_status/Value:error' => 'Synchronization failed',
	'Class:VCSRepository/Attribute:synchro_mode' => 'Synchronization mode',
	'Class:VCSRepository/Attribute:synchro_mode+' => 'Synchronization mode (None, Automatic or Manual)',
	'Class:VCSRepository/Attribute:synchro_mode/Value:none' => 'None',
	'Class:VCSRepository/Attribute:synchro_mode/Value:auto' => 'Automatic',
	'Class:VCSRepository/Attribute:synchro_mode/Value:manual' => 'Manual',
	'Class:VCSRepository/Attribute:secret' => 'Webhook secret key',
	'Class:VCSRepository/Attribute:secret+' => 'Sensitive webhook secret key',
	'Class:VCSRepository/Attribute:last_event_date' => 'Last event on',
	'Class:VCSRepository/Attribute:last_event_date+' => 'Last event received date',
	'Class:VCSRepository/Attribute:event_count' => 'Events counter',
	'Class:VCSRepository/Attribute:event_count+' => 'Number of events received',
	'Class:VCSRepository/Attribute:events_log' => 'Events log',
	'Class:VCSRepository/Attribute:events_log+' => 'Log for received events',
	'Class:VCSRepository/Attribute:automations' => 'Automations',
	'Class:VCSRepository/Attribute:automations+' => 'Automations to execute when receiving events',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Button'                           => 'Add an automation',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Button+'                          => 'Add an automation',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Modal:Title'                      => 'Add an automation',
	'Class:VCSRepository/UI:Button:synchronize_configuration' => 'Synchronize repository',
	'Class:VCSRepository/UI:Button:check_configuration' => 'Check synchronization',
	'Class:VCSRepository/UI:Button:get_information' => 'Update metrics',
	'Class:VCSRepository/UI:Button:open' => 'Open on GitHub',

	// CLASS lnkVCSRepositoryToVCSAutomation
	'Class:lnkVCSRepositoryToVCSAutomation' => 'Automation link',
	'Class:lnkVCSRepositoryToVCSAutomation+' => 'Link to automation objects',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:status' => 'Status',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:status+' => 'Automation status',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:condition' => 'Condition',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:condition+' => 'Condition for the automation execution (ex: context->ref=refs/heads/(develop|master).*)',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:automation_id' => 'Automation',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:automation_id+' => 'Automation object',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:repository_id' => 'Repository',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:repository_id+' => 'Repository object',

	// CLASS AbstractVCSAutomation
	'Class:AbstractVCSAutomation' => 'Automation',
	'Class:AbstractVCSAutomation+' => 'Automation executed on version control system events',
	'Class:AbstractVCSAutomation/Attribute:repository_id' => 'Repository',
	'Class:AbstractVCSAutomation/Attribute:repository_id+' => 'Version Control System Repository',
	'Class:AbstractVCSAutomation/Attribute:events' => 'Trigger on event(s)',
	'Class:AbstractVCSAutomation/Attribute:events+' => 'List events for witch automation must be executed',
	'Class:AbstractVCSAutomation/Attribute:status' => 'Status',
	'Class:AbstractVCSAutomation/Attribute:status+' => 'Automation status',
	'Class:AbstractVCSAutomation/Attribute:scope_var' => 'Scope',
	'Class:AbstractVCSAutomation/Attribute:scope_var+' => 'Automation scope',
	'Class:AbstractVCSAutomation/Attribute:label' => 'Label',
	'Class:AbstractVCSAutomation/Attribute:label+' => 'Label describing the automation',
	'Class:AbstractVCSAutomation/UI:Button:open_wiki_documentation' => 'Open WIKI documentation',
	'Class:AbstractVCSAutomation/UI:Button:open_github_documentation' => 'Open GitHub documentation',
	'Class:AbstractVCSAutomation/UI:Button:synchronize_with_github' => 'Synchronize',
	'Class:AbstractVCSAutomation/UI:Label:last_update_on' => 'Information from GitHub, last update on',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax' => 'Templating Syntax',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_1' => 'Inject event and any other available data inside your template.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_2' => 'Use @for statement to loop on array.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_3' => 'Use @hyperlink statement to create a link.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_4' => 'Use @mailto statement to create a mail link.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_5' => 'Use @image statement to create an image.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_6' => 'Use @substring statement to cut a string.',

	// CLASS VCSLogAttributeAutomation
	'Class:VCSLogAttributeAutomation' => 'Append Message to Object',
	'Class:VCSLogAttributeAutomation+' => 'Append a message to an object attribute',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_subject_data' => 'Subject data for regex',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_subject_data+' => 'Subject data for regex',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_pattern' => 'Regex to extract reference',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_pattern+' => 'Regex to extract reference',
	'Class:VCSLogAttributeAutomation/Attribute:object_att_code' => 'Attribute',
	'Class:VCSLogAttributeAutomation/Attribute:object_att_code+' => 'Attribute of the target object',
	'Class:VCSLogAttributeAutomation/Attribute:object_class' => 'Object Class',
	'Class:VCSLogAttributeAutomation/Attribute:object_class+' => 'Class of the target object',
	'Class:VCSLogAttributeAutomation/Attribute:template' => 'Message',
	'Class:VCSLogAttributeAutomation/Attribute:template+' => 'Message template',

	// CLASS VCSLogJournalAutomation
	'Class:VCSLogJournalAutomation' => 'Append Message to System Log',
	'Class:VCSLogJournalAutomation+' => 'Append a message to Log System',
	'Class:VCSLogJournalAutomation/Attribute:template' => 'Message',
	'Class:VCSLogJournalAutomation/Attribute:template+' => 'Message template',

));
?>
