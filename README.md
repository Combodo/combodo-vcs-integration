# combodo-vcs-integration

## Overview

`combodo-vcs-integration` connects GitHub events to iTop objects through configurable automations.

Main use cases:

- append commit / pull request activity to tickets
- enrich iTop objects with repository events
- monitor webhook synchronization status

## What the extension adds

Core objects:

- `VCSConnector`
- `VCSWebhook`
- `VCSWebhookPayload` (queue for asynchronous processing)
- `VCSEvent`
- `VCSAutomation` (abstract)
- `VCSLogJournalAutomation`
- `VCSLogAttributeAutomation`

Link objects:

- `lnkVCSAutomationToVCSWebhook`
- `lnkVCSAutomationToVCSEvent`
- `lnkContactToVCSWebhook`
- `lnkDocumentToVCSWebhook`

## Dependencies

Module dependencies:

- `itop-structure/3.2.0`
- `itop-tickets/2.7.0`
- `itop-request-mgmt/3.2.0`

Composer dependency:

- `fastvolt/markdown`

## Installation

1. Install the extension package in your iTop instance.
2. Ensure dependencies are available in your environment.
3. Run iTop setup/upgrade.
4. (If installed from sources) install PHP dependencies.

```bash
cd /path/to/combodo-vcs-integration
composer install
```

## Configuration settings

The module reads the following settings:

- `webhook_user_id`: technical iTop user used by webhook processing
- `user_resolver_class_name`: optional custom resolver class for VCS user -> iTop user mapping
- `synchro_auto_interval`: background synchronization interval (seconds)
- `asynchronous_handler_interval`: async payload handler interval (seconds)
- `asynchronous_disabled`: if `true`, process payload synchronously in `github.php`
- `webhook_host_overload`: optional host override used to build webhook callback URL
- `webhook_scheme_overload`: optional scheme override (`http` / `https`)

## GitHub webhook flow

Inbound endpoint:

- `github.php?webhook=<id>`

Processing steps:

1. Resolve `VCSWebhook` from the `webhook` query parameter.
2. Validate `X-Hub-Signature` when a secret is set.
3. Parse payload (`application/json` or `application/x-www-form-urlencoded`).
4. Read event type from `X-Github-Event`.
5. Process immediately or enqueue into `VCSWebhookPayload` depending on `asynchronous_disabled`.

## Automation model

Automations are linked to webhooks and events, then executed when conditions match.

- regex condition format: `path=valueRegex`
- helper expressions supported in conditions:
  - `NOT_NULL(...)`
  - `IS_KNOWN_USER(...)`
  - `IS_UNKNOWN_USER(...)`

Built-in sample data includes events such as `push`, `pull_request`, `issues`, and example automations.

## Authentication modes

`VCSConnector` supports multiple GitHub authentication modes:

- `personal`
- `app_repository`
- `app_user`
- `app_organization`

## Permissions

The extension introduces a dedicated rights group/profile for management actions:

- group: `VCS`
- profile: `VCS Manager`

Popup menu actions (synchronize, check synchronization, regenerate token) are restricted to authorized profiles.

## Main files

- `module.combodo-vcs-integration.php`
- `datamodel.combodo-vcs-integration.xml`
- `github.php`
- `src/Service/AutomationManager.php`
- `src/Service/GitHubManager.php`
- `src/Service/GitHubAPIService.php`
- `src/BackgroundProcess/VCSWebhookSynchroProcess.php`
- `src/BackgroundProcess/VCSWebhookAsynchronousHandler.php`

## Download

Stable packages are available on the [iTop Hub Store](https://store.itophub.io/en_US/taxons/all-extensions).

## About

This extension is sponsored, led, and supported by [Combodo](https://www.combodo.com).




