# Changelog

## [1.0.1] - 2026-11-25

- Require persisted subject, requester, approver, and approval request models.
- Validate workflow key length and JSON-encodability of approval payloads.
- Validate request and step status fields and step numbering.
- Apply configured status values consistently during creation, decisions, cancellation, and pending checks.
- Validate configured status values and require distinct values.
- Expand feature coverage for invalid inputs, persisted model requirements, and configurable statuses.

## [1.0.0] - 2026-11-17

### Added
- Sequential multi-step approval requests.
- Polymorphic subject, requester, and approver relationships.
- Approval, rejection, and requester cancellation actions.
- Decision comments and lifecycle timestamps.
- Transactional state transitions with row-level locking.
- Config publishing and migrations.
- PHPUnit feature tests and GitHub Actions PHP 8.3/8.4 matrix.
