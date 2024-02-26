<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('FR FR', 'French', 'Français', array(
	// Dictionary entries go here

	// MENU elements
	'Menu:VCSRepositoryMenu' => 'Dépôts de sources GitHub',
	'Menu:VCSRepositoryMenu+' => 'Dépôts de sources GitHub',

	// CLASS VCSConnector
	'Class:VCSConnector' => 'Connecteur',
	'Class:VCSConnector+' => 'Connecteur au système de gestion de versions',
	'Class:VCSConnector/Attribute:label' => 'Etiquette',
	'Class:VCSConnector/Attribute:label+' => 'Etiquette du connecteur',
	'Class:VCSConnector/Attribute:provider' => 'Fournisseur',
	'Class:VCSConnector/Attribute:provider+' => 'Fournisseur du système de gestion de versions',
	'Class:VCSConnector/Attribute:provider/Value:github' => 'GitHub',
	'Class:VCSConnector/Attribute:owner' => 'Propriétaire ressource',
	'Class:VCSConnector/Attribute:owner+' => 'Propriétaire ressource (utilisateur ou organisation)',
	'Class:VCSConnector/Attribute:mode' => 'Mode d\'authentification',
	'Class:VCSConnector/Attribute:mode+' => 'Mode d\'authentification API',
	'Class:VCSConnector/Attribute:mode/Value:none+' => 'Aucun',
	'Class:VCSConnector/Attribute:mode/Value:personal' => 'Jeton personel',
	'Class:VCSConnector/Attribute:mode/Value:app' => 'Jeton installation d\'application',
	'Class:VCSConnector/Attribute:personal_access_token' => 'Jeton personel',
	'Class:VCSConnector/Attribute:personal_access_token+' => 'Personal access token',
	'Class:VCSConnector/Attribute:app_id' => 'ID de l\'application',
	'Class:VCSConnector/Attribute:app_id+' => 'ID de l\'application',
	'Class:VCSConnector/Attribute:app_private_key' => 'Clé privée',
	'Class:VCSConnector/Attribute:app_private_key+' => 'Clé privée liée à l\'application',
	'Class:VCSConnector/Attribute:repositories' => 'Dépots',
	'Class:VCSConnector/Attribute:repositories+' => 'Dépots du connecteur',

	// CLASS VCSRepository
	'Class:VCSRepository' => 'Dépot',
	'Class:VCSRepository+' => 'Dépôt de sources',
	'Class:VCSRepository/Attribute:name' => 'Nom du dépôt',
	'Class:VCSRepository/Attribute:name+' => 'Nom du dépôt sur le système de gestion de versions',
	'Class:VCSRepository/Attribute:connector_id' => 'Connecteur',
	'Class:VCSRepository/Attribute:connector_id+' => 'Connecteur au système de gestion de versions',
	'Class:VCSRepository/Attribute:webhook' => 'URL du webhook',
	'Class:VCSRepository/Attribute:webhook+' => 'URL du webhook à définir dans le système de gestion de versions',
	'Class:VCSRepository/Attribute:webhook_status' => 'Status',
	'Class:VCSRepository/Attribute:webhook_status+' => 'Status sur GitHub',
	'Class:VCSRepository/Attribute:webhook_status/Value:unset' => 'non défini',
	'Class:VCSRepository/Attribute:webhook_status/Value:unsynchronized' => 'Non Synchronisé',
	'Class:VCSRepository/Attribute:webhook_status/Value:active' => 'Actif',
	'Class:VCSRepository/Attribute:webhook_status/Value:inactive' => 'Inactif',
	'Class:VCSRepository/Attribute:webhook_status/Value:error' => 'Erreur de synchronisation',
	'Class:VCSRepository/Attribute:synchro_mode' => 'Mode de synchronization',
	'Class:VCSRepository/Attribute:synchro_mode+' => 'Mode de synchronization (Aucun, Automatique ou Manuel)',
	'Class:VCSRepository/Attribute:synchro_mode/Value:none' => 'Aucun',
	'Class:VCSRepository/Attribute:synchro_mode/Value:auto' => 'Automatique',
	'Class:VCSRepository/Attribute:synchro_mode/Value:manual' => 'Manuelle',
	'Class:VCSRepository/Attribute:secret' => 'Clé secrète webhook',
	'Class:VCSRepository/Attribute:secret+' => 'Clé secrète webhook',
	'Class:VCSRepository/Attribute:last_event_date' => 'Dernier événement le',
	'Class:VCSRepository/Attribute:last_event_date+' => 'Date de réception du dernier événement',
	'Class:VCSRepository/Attribute:event_count' => 'Compteur d\'événements',
	'Class:VCSRepository/Attribute:event_count+' => 'Nombre d\'événements reçus',
	'Class:VCSRepository/Attribute:events_log' => 'Fil d\'événements',
	'Class:VCSRepository/Attribute:events_log+' => 'Fil des événements reçus',
	'Class:VCSRepository/Attribute:automations' => 'Automatismes',
	'Class:VCSRepository/Attribute:automations+' => 'Automatismes éxécutés à la réception d\'événements',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Button'                           => 'Ajouter un automatisme',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Button+'                          => 'Ajouter un automatisme',
	'Class:VCSRepository/Attribute:automations/UI:Links:Add:Modal:Title'                      => 'Ajouter un automatisme',
	'Class:VCSRepository/UI:Button:synchronize_configuration' => 'Synchroniser le dépôt',
	'Class:VCSRepository/UI:Button:check_configuration' => 'Vérifier la synchronisation',
	'Class:VCSRepository/UI:Button:get_information' => 'Mettre à jour les données du dépôt',
	'Class:VCSRepository/UI:Button:open' => 'Ouvrir sur GitHub',

	// CLASS lnkVCSRepositoryToVCSAutomation
	'Class:lnkVCSRepositoryToVCSAutomation' => 'Lien automatisme',
	'Class:lnkVCSRepositoryToVCSAutomation+' => 'Lien avec un automatisme',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:status' => 'Status',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:status+' => 'Status automatisme',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:condition' => 'Condition',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:condition+' => 'Condition pour l\'éxécution de l\'automatisme (ex: context->ref=refs/heads/(develop|master).*)',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:automation_id' => 'Automatisme',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:automation_id+' => 'Automatisme',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:repository_id' => 'Dépot de sources',
	'Class:lnkVCSRepositoryToVCSAutomation/Attribute:repository_id+' => 'Dépot de sources',

	// CLASS AbstractVCSAutomation
	'Class:AbstractVCSAutomation' => 'Automatisme',
	'Class:AbstractVCSAutomation+' => 'Automatisme éxécutés à la réception d\'événements du système de gestion de versions',
	'Class:AbstractVCSAutomation/Attribute:repository_id' => 'Dépôt de sources',
	'Class:AbstractVCSAutomation/Attribute:repository_id+' => 'Dépôt de sources',
	'Class:AbstractVCSAutomation/Attribute:events' => 'Déclenché sur les événement(s)',
	'Class:AbstractVCSAutomation/Attribute:events+' => 'Liste des événements déclenchant l\'automatisme',
	'Class:AbstractVCSAutomation/Attribute:status' => 'Status',
	'Class:AbstractVCSAutomation/Attribute:status+' => 'Status automatisme',
	'Class:AbstractVCSAutomation/Attribute:scope_var' => 'Périmètre',
	'Class:AbstractVCSAutomation/Attribute:scope_var+' => 'Périmètre d\'éxécution',
	'Class:AbstractVCSAutomation/Attribute:label' => 'Etiquette',
	'Class:AbstractVCSAutomation/Attribute:label+' => 'Etiquette décrivant l\'automatisme',
	'Class:AbstractVCSAutomation/UI:Button:open_wiki_documentation' => 'Ouvrir la documentation wiki',
	'Class:AbstractVCSAutomation/UI:Button:open_github_documentation' => 'Ouvrir la documentation GitHub',
	'Class:AbstractVCSAutomation/UI:Button:synchronize_with_github' => 'Synchroniser',
	'Class:AbstractVCSAutomation/UI:Label:last_update_on' => 'Information de GitHub, dernière mise à jour le',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax' => 'Syntaxe des modèles',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_1' => 'Injecter l\'événement et d\'autres données disponible pour cet événement',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_2' => 'Utiliser @for pour boucler sur des tableaux.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_3' => 'Utiliser @hyperlink pour créer des liens.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_4' => 'Utiliser @mailto pour créer des liens vers destinataires.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_5' => 'Utiliser @image pour insérer des images.',
	'Class:AbstractVCSAutomation/UI:Label:templating_syntax_6' => 'Utiliser @substring pour découper une chaîne de caractères.',

	// CLASS VCSLogAttributeAutomation
	'Class:VCSLogAttributeAutomation' => 'Insérer un message dans un object',
	'Class:VCSLogAttributeAutomation+' => 'Insérer un message dans un attribut d\'un object',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_subject_data' => 'Donnée pour l\'éxécution de l\'expression régulière',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_subject_data+' => 'Donnée pour l\'éxécution de l\'expression régulière',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_pattern' => 'Expression régulière pour extraire la référence de l\'objet',
	'Class:VCSLogAttributeAutomation/Attribute:ref_regex_pattern+' => 'Expression régulière pour extraire la référence de l\'objet',
	'Class:VCSLogAttributeAutomation/Attribute:object_att_code' => 'Attribut',
	'Class:VCSLogAttributeAutomation/Attribute:object_att_code+' => 'Attribut de l\'objet destinataire',
	'Class:VCSLogAttributeAutomation/Attribute:object_class' => 'Classe de l\'objet',
	'Class:VCSLogAttributeAutomation/Attribute:object_class+' => 'Classe de l\'objet destinataire',
	'Class:VCSLogAttributeAutomation/Attribute:template' => 'Modèle de message',
	'Class:VCSLogAttributeAutomation/Attribute:template+' => 'Modèle de message',

	// CLASS VCSLogJournalAutomation
	'Class:VCSLogJournalAutomation' => 'Insérer un message dans le journal des logs',
	'Class:VCSLogJournalAutomation+' => 'Insérer un message dans le journal des logs',
	'Class:VCSLogJournalAutomation/Attribute:template' => 'Modèle de message',
	'Class:VCSLogJournalAutomation/Attribute:template+' => 'Modèle de message',

));
?>
