# Link the main NNMFB website to the portal

This repository does **not** control https://nigerianavymfb.com. Add the portal link on the main site (CMS, theme, or static HTML) after the portal is live at `/transfer`.

## Recommended URL

- Absolute: `https://nigerianavymfb.com/transfer`
- Same-site relative: `/transfer` (or `/transfer/login`)

Suggested label: **Transfer & Reversal Portal**

## HTML snippet (menu / header)

```html
<a href="https://nigerianavymfb.com/transfer" class="nav-link">
  Transfer &amp; Reversal Portal
</a>
```

Or relative:

```html
<a href="/transfer">Transfer &amp; Reversal Portal</a>
```

Button-style example:

```html
<a href="https://nigerianavymfb.com/transfer"
   style="display:inline-block;padding:0.6rem 1rem;background:#0b1f3a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
  Transfer &amp; Reversal Portal
</a>
```

## Where to add it (common cases)

### cPanel File Manager (static HTML site)

1. Open File Manager → `public_html/`.
2. Edit the homepage or shared header (`index.html`, `header.php`, theme partial).
3. Paste the snippet into the main navigation.
4. Save and hard-refresh the public site.

### WordPress

1. Appearance → Menus → add **Custom Link**.
2. URL: `https://nigerianavymfb.com/transfer`
3. Link Text: `Transfer & Reversal Portal`
4. Save menu.

### Other CMS / builders

Add a custom menu/button URL pointing to `/transfer`. Publish and verify on desktop and mobile.

## Smoke check

1. From https://nigerianavymfb.com, click the new item.
2. Land on the branded portal login (or dashboard if already signed in).
3. Confirm the browser address starts with `https://nigerianavymfb.com/transfer`.
