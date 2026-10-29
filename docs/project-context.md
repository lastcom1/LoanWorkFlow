# NNMFB Transfer and Reversal Complaint Management Portal

Internal banking operations platform for Nigerian Navy Microfinance Bank (NNMFB) staff to submit fund-transfer instructions and reversal complaints, for Fund Transfer Officers to claim and resolve them, and for supervisors and the MD/CEO to monitor accountability.

## Branding

- Official logo: `/cursor/stores/self/media/branding/nnmfb-logo.png`
- Primary: navy blue; accent: gold; neutrals for readability
- Premium internal banking UI suitable for management, risk, audit, and compliance review

## Stack

- Laravel (PHP), Eloquent, migrations, Blade (or equivalent Laravel views)
- MySQL/MariaDB suitable for XAMPP and cPanel shared hosting
- Zoho SMTP via environment variables (no hardcoded secrets)
- Role-based access control; immutable audit trail; exclusive ticket claim with DB locking

## Roles

System Administrator, MD/CEO, Supervisor / Head of Operations, Fund Transfer Officer (four demo accounts), Branch Staff / Ticket Submitter, optional Auditor (read-only).

## Core invariants

1. Only one FT Officer may successfully claim an available ticket (atomic DB transaction / locking).
2. Duplicate detection (server-side only): transfers — normalized account + amount; reversals — original/transaction reference number as primary key (also store account + amount).
3. Audit logs are append-only through the application UI.
4. Mask account numbers in lists and emails; no PINs, CVVs, passwords, or OTPs collected.
5. Email failure must never double-claim a ticket; DB assignment is authoritative.

## Deliverables

- Working application (not a prototype shell)
- Migrations, seeders, tests, docs
- Package A: full source/dev ZIP
- Package B: shared-hosting production ZIP for `public_html`
- Windows local install guide + cPanel deploy guide
- Web installer for shared hosting without SSH/Composer when needed

## Demo data

Synthetic only. Demo credentials clearly labelled; change before production.