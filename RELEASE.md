# Release checklist

1. Run `composer install`.
2. Run `composer test` and ensure all tests pass.
3. Review the package contents and `CHANGELOG.md`.
4. Commit the source to the `main` branch.
5. Create and push the `v1.0.0` tag.
6. Create a GitHub Release using the title and notes below.
7. Submit the GitHub repository URL to Packagist.

## GitHub Release title

`v1.0.0 - Initial Release`

## GitHub Release notes

### Highlights
- Sequential approval workflows with one assigned approver per step.
- Polymorphic association to any Eloquent subject and requester.
- Approve, reject, and requester cancellation operations.
- Per-step comments and decision timestamps.
- Transaction-protected transitions and row-level locking.

### Installation
```bash
composer require itmosoft/laravel-approvals
php artisan vendor:publish --tag=itmosoft-approvals-config
php artisan migrate
```

### Requirements
- PHP 8.3+
- Laravel 12 or 13

### Limitations
No parallel/quorum approvals, delegation, dynamic branching, notifications, reminders,
SLA timers, UI, or automatic model observers in this release.
