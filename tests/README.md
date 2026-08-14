# QA / Test Plan

## Financial invariants

1. A refund must create exactly one `refund_events` row for an order/reason.
2. A completed refund must have exactly one matching wallet transaction reference.
3. Wallet balance changes must equal the sum of ledger movements.
4. Order profit must equal charge minus refunded amount minus provider cost.
5. Re-running status synchronization must not create another refund.

## Provider behavior

- Service sync inserts new services and updates existing ones.
- Removed provider services become inactive.
- Bulk order status requests are limited to 100 IDs.
- Refill status synchronization updates both `refill_events` and `orders`.
- Provider errors do not silently mark orders completed.

## Security

- Customer order queries are scoped to the authenticated user.
- Admin actions require admin authorization and CSRF verification.
- Provider API credentials are never rendered in HTML or committed to the repository.
- Refunds and cancellations are auditable.

## Suggested automated test stack

For this PHP application, use PHPUnit in CI and a dedicated test database. Keep provider calls mocked; production Marketerum API calls should never be part of the test suite.

Minimum CI checks:

```bash
php -l app/Services/RefundService.php
php -l app/Services/OrderLifecycleService.php
php -l app/Services/RefillSyncService.php
php -l app/Services/SecurityAuditService.php
php -l app/Services/JobRunService.php
```

Then run PHPUnit once the project's `phpunit.xml` and test bootstrap are configured.
