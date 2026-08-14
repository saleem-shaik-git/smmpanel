# SMM Panel

PHP 8.2+ / MySQL 8 / Bootstrap 5 reseller panel with Marketerum API integration.

## Current implementation

- Bootstrap landing page
- PDO/MySQL foundation
- Environment configuration via `.env`
- Provider abstraction (`SmmProviderInterface`)
- Marketerum provider client
- Service catalogue synchronization
- Local provider/selling rates and configurable markup
- Initial users, services, orders, transactions and provider logs schema
- Cron entrypoint for service synchronization

## Setup

```bash
composer install
cp .env.example .env
```

Create the MySQL database and import `database/schema.sql`.

Point your web server document root at `public/`.

Configure the exact Marketerum API URL and credentials in `.env`.

Run service synchronization:

```bash
php cron/sync_services.php
```

Then schedule it with cron, for example hourly:

```text
0 * * * * /usr/bin/php /path/to/smmpanel/cron/sync_services.php
```

## Security

Never commit `.env` or a real provider API key. The provider key must remain server-side and must never be sent to browser JavaScript.

Because an API key was shared in the development conversation, rotate/revoke that key in Marketerum before using production credentials.

## Marketerum

Marketerum publicly states that its API can pull services, place orders and check order status, which is the integration model used here. The API client is deliberately isolated so the platform can later support additional providers.

The exact API endpoint and response fields should be confirmed against the current Marketerum API documentation before enabling production order submission. The configurable endpoint defaults to the commonly documented `/api/v2` route but should be changed if your account documentation specifies another URL.
