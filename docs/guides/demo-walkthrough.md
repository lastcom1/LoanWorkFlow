# DEMO walkthrough (prototype)

Synthetic data only. All seeded users are labelled **DEMO**.

## 1. Sign in

Open the portal login page (navy/gold branded with NNMFB logo). Use:

- Submitter: `submitter@nnmfb.demo` / `DemoSubmitter@123`

## 2. Submit a Fund Transfer Instruction

Dashboard → **New Ticket** → category Fund Transfer → account + amount → confirm → submit.  
Note the ticket reference (`TRP-...`).

## 3. Duplicate warning (transfers)

Submit another ticket with the **same account + amount**. A professional warning lists existing ticket meta (masked account, amount, status, branch/staff). Acknowledge to proceed, or cancel.

## 4. Reversal duplicate (reference)

Create a Reversal Complaint with original reference e.g. `REV-REF-DEMO-9001` (seeded). Matching uses that reference as the primary key across submitters/branches.

## 5. FT Officer exclusive claim

Sign out → login `ft1@nnmfb.demo` / `DemoFtOfficer1@123` → Pick Up an available ticket.  
In another browser/session as `ft2@nnmfb.demo`, claiming the same ticket fails with a message naming the assignee.

## 6. Process / reject

As the claiming officer: Start Processing → Request Info / Complete / Reject (mandatory reason + confirm modal). Rejected tickets remain searchable.

## 7. Role dashboards

| Account | What to open |
|---------|----------------|
| `supervisor@nnmfb.demo` | Queue oversight, workloads, duplicates |
| `mdceo@nnmfb.demo` | Executive metrics; masked accounts; claim notifications path |
| `admin@nnmfb.demo` | Users, branches, settings, audit (view only for audit erase) |
| `auditor@nnmfb.demo` | Read-only audit trail + CSV export |

## 8. Emails

With `MAIL_MAILER=log`, check `storage/logs/laravel.log` for HTML mail content (New Ticket, Claimed, Status Update). With Zoho SMTP configured, queued mail uses the three branded templates.
