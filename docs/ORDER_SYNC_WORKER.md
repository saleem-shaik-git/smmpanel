# Automated Marketerum Order Synchronization

The order worker keeps active Marketerum orders synchronized and retries temporary provider failures.

## Run manually

```powershell
php bin/sync-orders.php 100
```

The argument is the maximum number of orders checked in one run. Marketerum status requests are batched at up to 100 provider order IDs.

## Retry behavior

Failed provider lookups and lifecycle processing errors are stored in `order_sync_retries`.

- First failure: retry after 1 minute.
- Later failures: exponential backoff.
- Backoff is capped at 60 minutes.
- A successful reconciliation removes the retry state.
- Terminal orders are not polled again.

## Concurrency protection

The worker obtains the MySQL named lock `smmpanel:marketerum:order-sync`. If another worker is already running, the second process exits successfully without doing duplicate work.

## Windows Task Scheduler

Run the worker every 1-5 minutes with the PHP executable used by Apache/CLI:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\smmpanel\bin\sync-orders.php 100
```

Use the same PHP installation that has the project's Composer dependencies available.

## Linux cron example

```cron
*/2 * * * * /usr/bin/php /var/www/smmpanel/bin/sync-orders.php 100 >> /var/log/smmpanel-orders.log 2>&1
```

## Required migration

Before enabling the worker, apply:

```text
database/migrations/008_order_sync_retries.sql
```

The existing `job_runs` table records each worker execution for operational monitoring.
