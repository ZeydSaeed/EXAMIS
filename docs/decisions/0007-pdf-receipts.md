# ADR-0007: PDF receipts with mPDF

- Status: Accepted (2026-10-14)

## Context

Receipts must render Arabic correctly (letter shaping, RTL, mixed digits), print on A4, carry a QR code, and keep extractable text.

## Options considered

- dompdf: no proper Arabic shaping.
- Headless Chrome (Browsershot): best fidelity, but adds Node and Chromium to every server.
- mPDF: pure PHP, built-in Arabic shaping and bidi, QR codes through `mpdf/qrcode`.

## Decision

Use mPDF with `mpdf/qrcode`. The receipt is generated **on demand** from the persisted application state and is not stored, so no stale copies exist. Each issue is audited (`receipt.downloaded`). The PDF prints its generation time and the status at that time, so a printed copy describes itself as a point-in-time document. The QR code holds only the public verification URL with a random `verification_token`. It contains no personal data.
