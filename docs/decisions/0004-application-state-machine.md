# ADR-0004: Explicit application state machine and append-only history

- Status: Accepted (2026-10-14). The transition list is provisional until the examination authority approves it.

## States

`draft`, `submitted`, `under_review`, `requires_correction`, `resubmitted`, `accepted`, `rejected`, `withdrawn`

## Transitions

| From                                  | To                    | Actor                                                                            | Reason required |
| ------------------------------------- | --------------------- | -------------------------------------------------------------------------------- | --------------- |
| draft                                 | submitted             | owner (period open, form complete, required documents uploaded and not rejected) | no              |
| submitted, resubmitted                | under_review          | staff with `applications.review`                                                 | no              |
| under_review                          | requires_correction   | staff with `applications.review`                                                 | **yes**         |
| requires_correction                   | resubmitted           | owner (same completeness checks as submission)                                   | no              |
| under_review                          | accepted              | staff with `applications.decide`                                                 | no              |
| under_review                          | rejected              | staff with `applications.decide`                                                 | **yes**         |
| draft, submitted, requires_correction | withdrawn             | owner                                                                            | optional        |
| accepted, rejected                    | under_review (reopen) | staff with `applications.reopen`                                                 | **yes**         |

The authoritative table is `App\Enums\ApplicationStatus::transitions()`. A unit test pins it.

## Rules

- Transitions run only through `App\Actions\Applications\TransitionApplication`. It opens a transaction, locks the row (`SELECT … FOR UPDATE`), re-checks the transition against the **locked** state, writes the new status, appends an `application_events` row and an audit event, and sends notifications **after commit**.
- `application_events` and `audit_events` are append-only. Models throw on update and delete. On PostgreSQL, a trigger rejects `UPDATE` and `DELETE`.
- Decisions are never edited in place. A wrong decision is corrected by **reopen**, which carries a mandatory reason and keeps the earlier decision event.
- The owner may edit form data and documents only in `draft` or `requires_correction`.

## Note on documents at submission

A required document counts as present once it is uploaded and has not been rejected by the scanner. A document still being scanned (`pending`) does not block submission, so a slow scanner never stops an applicant at the deadline. Staff can only open documents once they are `verified`, and a document rejected later surfaces in the review (`document_rejected` event), where the reviewer can request a correction.
