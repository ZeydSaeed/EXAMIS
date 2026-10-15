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

## Amendment (2026-10-16): the receipt is the official status form

The receipt reproduces the authority's paper "استمارة واقع حال" (same boxes, order, gold label cells and footer note), filled from the application, so it can be printed, signed and stamped by the last school. The applicant's personal photo is embedded in the photo box only when that document is verified, read from the private disk in memory (`imageVars`) and never written to a temporary file. The authority's logo is printed from `public/images/vocational-education-logo.png` when that file exists. The reference number, status, generation time and QR code sit in a thin strip below the form.

The form prints in Times New Roman, read from `examis.receipt.font_dir` (`EXAMIS_RECEIPT_FONT_DIR`, Windows fonts by default) because the font is licensed with Windows and not shipped; servers without it fall back to DejaVu Sans. Arabic is shaped through the font's OpenType tables. Trade-off: in the text layer Times maps some joined letters to their Persian code points (ی, ھ, ک), so text copied from the PDF can contain those variants; the printed page is unaffected.
