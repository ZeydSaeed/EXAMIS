# ADR-0013: Decision messages sent by staff over WhatsApp

- Status: Accepted (2026-11-03), the owner's decision

## Context

A student's account closes once the form is printed (ADR-0010), and the system has no mail or SMS. A decision therefore reached nobody in the usual case. The owner chose a free channel with no outside service.

## Decision

- Administrators keep ready-made messages on the settings page, one or more for each of accepted, rejected and incomplete paperwork. They can add, edit and delete them.
- A message holds placeholders for the student's four-part name, student number, branch and specialization, filled in automatically, and gaps staff complete when deciding. A line whose gaps stay empty is dropped, and a message with no gap filled is refused.
- The finished text is copied onto the application's decision event, so editing a template never changes a message already sent.
- The system sends nothing. After the decision, a `wa.me` link opens the staff member's own WhatsApp on the student's number with the text ready, and they press send. The application page offers the same link again.

## Consequences

- The system cannot know whether a message was sent or received. The audit trail records the decision, not the delivery.
- The student's name, number and specialization pass through WhatsApp, and the student sees the staff member's number. A shared office number avoids exposing personal ones.
- A decision taken with the buttons on the application page has no ready-made message. Its reason is offered for sending as written.
