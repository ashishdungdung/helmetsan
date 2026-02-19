# Health & Alerts Module

> System health monitoring and multi-channel notifications.

## Files

| File                                                                                                           | Lines | Purpose                |
| -------------------------------------------------------------------------------------------------------------- | ----- | ---------------------- |
| [HealthService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Health/HealthService.php) | 132   | System status report   |
| [AlertService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Alerts/AlertService.php)   | 113   | Email + Slack alerting |

## HealthService

Generates a comprehensive system health report covering:

| Section        | Checks                                             |
| -------------- | -------------------------------------------------- |
| Database       | Helmet/brand/accessory CPT counts                  |
| Repository     | JSON file count, root directory exists             |
| GitHub Sync    | Configured, enabled, owner/repo/branch             |
| Ingestion Logs | Table exists, total rows, failed rows, lock status |
| Sync Logs      | Table exists, total rows, error rows               |
| Revenue        | Click tracking table exists, row count             |
| Analytics      | Event table exists, row count                      |
| Scheduler      | Enabled, next-run times for all cron jobs          |
| Alerts         | Email/Slack enabled                                |
| Integrity      | Validator results (OK/errors)                      |

### Status Values

- **`healthy`** — All integrity checks pass
- **`degraded`** — One or more integrity checks failed

## AlertService

Sends notifications via email and/or Slack:

```php
$alertService->send(
    type: 'sync_failure',
    title: 'GitHub Sync Failed',
    message: 'Pull failed after 3 retries.',
    context: ['error' => 'Rate limited', 'files' => 42]
);
```

### Channels

| Channel | Required Config                       | Format                           |
| ------- | ------------------------------------- | -------------------------------- |
| Email   | `email_enabled` + `to_email`          | Plain text with JSON context     |
| Slack   | `slack_enabled` + `slack_webhook_url` | Markdown with code block context |

### Alert Triggers

Alerts fire automatically from `SchedulerService` when:

- Sync pull fails or returns errors
- Failed ingestion retry still fails
- Health snapshot shows degraded status
