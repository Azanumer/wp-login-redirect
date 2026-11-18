# WP Login Redirect

Redirect users after login by role — customers to their account page, editors to the dashboard, everyone else to a default page — plus a custom logout redirect. No code edits, no extra plugins.

## Why

WordPress sends everyone to the same place after login (usually `/wp-admin/`). On a membership site, a shop, or a client site, that is rarely what you want: customers should land on their account page, subscribers on a welcome page, staff on the dashboard. This plugin gives you that in one settings screen.

## Features

- **Redirect by role** — one URL per role; a user with several roles takes the first matching rule.
- **Default redirect** — used when no role rule matches.
- **Logout redirect** — send users to a chosen page after they log out.
- **Open-redirect safe** — only relative paths or URLs on your own site are accepted; external URLs are ignored.
- **`wlr_redirect_url` filter** — override the final URL in code, e.g. to send first-time logins somewhere special.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Configure it under **Settings → Login Redirect**.

## Filter example

```php
// Send users logging in for the first time to the welcome page.
add_filter( 'wlr_redirect_url', function ( $url, $user, $role ) {
    if ( ! get_user_meta( $user->ID, 'wlr_seen_welcome', true ) ) {
        update_user_meta( $user->ID, 'wlr_seen_welcome', 1 );
        return home_url( '/welcome/' );
    }
    return $url;
}, 10, 3 );
```

## What it does not do

It does not change the login URL itself — see [wp-change-login-url](https://github.com/Azanumer/wp-change-login-url) for that — and it does not add any login security (2FA, brute-force limits); it only decides where a successful login lands.

## Uninstall

Deleting the plugin removes its settings. Login behaviour returns to the WordPress default.
