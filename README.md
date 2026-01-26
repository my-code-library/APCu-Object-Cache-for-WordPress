# APCu Object Cache Drop‑In for WordPress

### Bluehost‑Safe • WP‑CLI‑Safe • Recursion‑Guarded

## Overview

This repository provides a hardened, production‑safe `object-cache.php` drop‑in that enables APCu‑powered persistent object caching for WordPress — specifically engineered for Bluehost shared hosting, where most APCu drop‑ins fail due to recursion loops, memory exhaustion, or WP‑CLI crashes.

This drop‑in is:

* Safe for WP‑CLI (APCu disabled automatically)

* Safe for shared hosting

* Fully WordPress‑compatible

* Non‑recursive

* Memory‑safe

* Theme/plugin‑safe (including Oaknut)

* Zero‑configuration

## Why This Drop‑In Exists

Bluehost exposes APCu to PHP, but WordPress does not use it automatically.

Most APCu drop‑ins break on shared hosting because:

* WP‑CLI loads WordPress before authentication is ready
* Themes/plugins call cache functions during early bootstrap
* APCu functions like `apcu_inc()` may be disabled
* Minimal drop‑ins don’t implement the full WP Cache API
* Recursive cache calls cause 512MB memory exhaustion

This drop‑in solves all of those issues with a hybrid caching strategy.

## Key Features

✔ APCu for the website

Your live WordPress site uses APCu for fast, persistent object caching.

✔ APCu disabled inside WP‑CLI

Prevents recursion loops and memory exhaustion during commands like:

```
wp plugin list
wp cache flush
wp theme activate
```
WP‑CLI instead uses a lightweight in‑memory array cache.

✔ Full WordPress Cache API coverage

Implements all required functions:

* `wp_cache_add()`
* `wp_cache_set()`
* `wp_cache_get()`
* `wp_cache_delete()`
* `wp_cache_flush()`
* `wp_cache_replace()`
* `wp_cache_incr()`
* `wp_cache_decr()`
* `wp_cache_get_multi()`
* `wp_cache_add_global_groups()`
* `wp_cache_add_non_persistent_groups()`
* `wp_cache_switch_to_blog()`
* `wp_cache_reset()`

✔ Recursion guard

Prevents infinite loops during early bootstrap or plugin initialization.

✔ Local runtime cache

Reduces APCu calls and improves performance.

✔ Graceful fallback

If APCu is unavailable, WordPress continues normally.

## Installation

1. Download or copy the `object-cache.php` file.
2. Upload it to:
3. 

```
wp-content/object-cache.php
```

3. No activation is required — WordPress automatically detects the drop‑in.

## Verification

Go to:

**Tools → Site Health → Info → Server**

You should see:

```
Persistent Object Cache: Enabled (APCu)
```

WP‑CLI will show no APCu usage (by design).

## Compatibility

* WordPress **6.x**
* PHP **7.4 – 8.3**
* Bluehost shared hosting
* WP‑CLI
* Any theme (including Oaknut)
* Any plugin using the WordPress Cache API

## Troubleshooting

### WP‑CLI shows no APCu**

This is intentional.

### Site shows “APCu not available”

APCu is disabled inside WP‑CLI to prevent recursion and memory exhaustion.

Your PHP environment may not have APCu enabled for web requests.

Check with:

```
phpinfo()
```

or contact Bluehost support.

### Memory exhaustion errors

This drop‑in includes recursion guards and WP‑CLI isolation, so memory loops should not occur.

If they do, a plugin or theme may be causing recursive cache calls.

### License

MIT License.
Use, modify, and distribute freely.
