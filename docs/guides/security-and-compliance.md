# Security controls & compliance notes

## Implemented controls

- Session auth + login throttle / temporary lockout after failed attempts  
- RBAC on routes (admin, md_ceo, supervisor, ft_officer, submitter, auditor)  
- CSRF on state-changing requests; Blade XSS escaping  
- Parameterized Eloquent/Query Builder usage  
- Exclusive claim with `lockForUpdate()` inside a DB transaction  
- Server-side duplicate detection (not browser-only)  
- Account masking in lists and email templates  
- Attachment type/size validation; authorized downloads only  
- IDOR checks (submitters see own tickets; officers act on assigned)  
- Immutable audit log (no UI edit/delete; Eloquent model refuses `update`/`delete`)  
- Secrets via `.env`; production uses `.env.production.example` (not local `.env.example`)  
- Installer off by default + setup secret; lock after every deploy path  

## Audit log database hardening

Application code cannot update or delete `audit_logs` rows (model boot guards throw).

Also restrict the **MySQL application user** so even compromised app credentials cannot erase the trail:

```sql
-- After migrations, as a privileged DBA account:
REVOKE UPDATE, DELETE ON your_db.audit_logs FROM 'portal_app'@'localhost';
-- Keep SELECT + INSERT for the app user on audit_logs.
GRANT SELECT, INSERT ON your_db.audit_logs TO 'portal_app'@'localhost';
FLUSH PRIVILEGES;
```

Document the restricted grants in your runbook; do not grant `ALL PRIVILEGES` on production.

## Duplicate matching limitations

- Transfers: typos in account numbers or split amounts may evade account+amount matching  
- Reversals: alternate reference formats may evade normalized reference matching  
- Completed vs active matches are distinguished in the UI; override is audited  

## CBN / NDPA

Operational design supports accountability expectations (need-to-know access, auditability, data minimization in notifications). This software does **not** constitute CBN licensing evidence or NDPA certification. Engage compliance before go-live.
