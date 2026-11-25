# Laravel Approvals

Sequential, multi-step approval workflows for Laravel models and business processes.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-approvals
php artisan vendor:publish --tag=itmosoft-approvals-config
php artisan migrate
```

## Usage

The package accepts persisted Eloquent models for the subject, requester, and each assigned approver.

```php
use ItmoSoft\Approvals\ApprovalManager;

$approval = app(ApprovalManager::class)->create(
    subject: $purchase,
    workflowKey: 'purchase-order',
    requester: auth()->user(),
    steps: [
        ['approver' => $departmentManager],
        ['approver' => $financeManager],
    ],
    payload: ['amount' => 1250, 'currency' => 'USD'],
);
```

Only the approver assigned to the current step can decide it:

```php
$manager = app(ApprovalManager::class);

$manager->approve($approval, auth()->user(), 'Approved.');
// Or:
$manager->reject($approval, auth()->user(), 'Please revise the amount.');
```

The next step becomes active after the current step is approved. The request is marked
`approved` after the final step is approved. A rejection ends the workflow immediately.

The original requester may cancel a pending request:

```php
$manager->cancel($approval, auth()->user());
```

## Configuration

Publish `config/approvals.php` with the `itmosoft-approvals-config` tag.

- `table_names.requests` and `table_names.steps` customize the package tables.
- `statuses.pending`, `statuses.approved`, `statuses.rejected`, and `statuses.cancelled` customize stored status values. Values must be distinct non-empty strings of at most 24 characters.

## Data model

- `ApprovalRequest`: polymorphic subject and requester, workflow key, status, current step, JSON payload, submission/completion timestamps.
- `ApprovalStep`: ordered approver, status, optional decision comment, decision timestamp.

## Security and application responsibilities

- Protect approval endpoints with your application's authentication and authorization policies.
- The manager checks that the supplied Eloquent model matches the approver assigned to the active step.
- The subject, requester, and approver models must already be persisted.
- This package does not decide who is allowed to create a request; enforce that in your application.
- Use a database engine that supports row-level locking for concurrent approval operations in production.

## Scope limitations

This release implements sequential, one-approver-per-step workflows. It does not include parallel approvals,
quorum/majority rules, dynamic branching, delegation, reminders, SLA timers, audit exports, a UI,
or automatic model observers. It does not dispatch notifications or background jobs.

## Testing

```bash
composer install
composer validate --strict
composer test
```

## License

MIT. See [LICENSE](LICENSE).
