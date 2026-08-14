# Production Cron Configuration

Run these jobs from the application root. Replace `/var/www/smmpanel` with the actual deployment path and PHP binary.

```cron
*/5 * * * * cd /var/www/smmpanel && /usr/bin/php bin/sync-marketerum.php orders >> storage/logs/orders-sync.log 2>&1
*/5 * * * * cd /var/www/smmpanel && /usr/bin/php bin/sync-marketerum-refills.php >> storage/logs/refills-sync.log 2>&1
0 * * * * cd /var/www/smmpanel && /usr/bin/php bin/sync-marketerum.php services >> storage/logs/services-sync.log 2>&1
*/15 * * * * cd /var/www/smmpanel && /usr/bin/php bin/sync-marketerum.php balance >> storage/logs/balance-sync.log 2>&1
```

## Recommended production safeguards

- Use the absolute PHP binary path.
- Run jobs as the same OS user that owns the application files.
- Ensure `storage/logs` is writable by that user.
- Do not put API keys in this file; provider credentials must remain in environment configuration.
- Keep the dashboard behind admin authentication.
- Monitor `job_runs` for failed/stale jobs.
- Configure server-level log rotation.
- If the hosting provider does not support 5-minute cron, use its closest supported interval.
