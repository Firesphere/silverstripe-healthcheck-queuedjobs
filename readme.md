# Healthchecks enabled Silverstripe Queued Jobs

[GPL v3 or later](LICENSE.md)

You can either self-host healthchecks, or create an account at healthchecks.io

If no title is set, the classname will be used as title.

## Configuration:

```yaml
---
name: my-healthchecks
---
Firesphere\HealthcheckJobs\Services\HealthcheckService:
  endpoint: 'https://health.example.com'
  api_key: 'my-api-key-here' # Note, API Keys are per PROJECT, not 
```

## Add a time-out and grace time

Add the following to your queued job:

```php
public function getTimeout()
{
    return $time_in_seconds;
}

public function getGrace()
{
    return $time_in_seconds;
}
```

## Add a cron formatted schedule

```php
public function getSchedule()
{
    return '*/5 * * * *'; // A valid cron schedule
}
```
