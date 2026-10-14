# ADR-0001: Modular monolith within the standard Laravel layout

- Status: Accepted (2026-10-14)

## Context

EXAMIS has clear sub-domains: identity, exam periods, applications, documents, review workflow, receipts, notifications, audit, and administration. The project guidelines forbid new base folders without approval. Microservices would add operational cost with no proven need.

## Decision

- Keep one Laravel application. Modules are expressed as **namespaces inside the standard Laravel folders**:
    - `app/Models`, `app/Enums`: domain entities and value types
    - `app/Actions/<Module>`: application use cases, one class per business operation
    - `app/Services/<Module>`: infrastructure-facing services (storage, scanning, PDF, numbering)
    - `app/Policies`: authorization
    - `app/Http/Controllers/{Public,Applicant,Admin}`: thin presentation layer
    - `resources/js/pages/{public,applicant,admin}`: the matching UI areas
- Controllers validate (Form Request), authorize (Policy/Gate), call one Action, and return a response.
- Business rules live in Actions, Enums (state machine), and database constraints, never in React.
- Repositories are **not** introduced. Eloquent is the data-access layer.

## Consequences

- Low operational cost and simple local development.
- Module boundaries are a convention. Extracting a service later means moving a namespace plus its tables.
