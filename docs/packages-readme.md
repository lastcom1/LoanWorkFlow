# Delivery packages

| File | Purpose |
|------|---------|
| `nnmfb-transfer-portal-package-a-source.zip` | Full development source (no vendor) |
| `nnmfb-transfer-portal-package-b-public-html-transfer.zip` | Production: `nnmfb-portal/` (app) + `public_html/transfer/` (public entry only) |
| `nnmfb-transfer-portal-package-b-shared-hosting.zip` | Alias of the Package B build |

Package B keeps `vendor/`, `app/`, and `.env` **outside** `public_html`.
