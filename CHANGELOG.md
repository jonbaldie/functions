# Changelog

## 3.0.0

- `has_encryption_key()` now requires a non-empty key saved with `save_env('ENCRYPTION_KEY', ...)` or `bind_encryption_key()`, and the current `$_ENV` value must match it. Setting `$_ENV['ENCRYPTION_KEY']` directly no longer marks the key as bound.
- The front controller requires `.key` and always binds its contents, overwriting any ambient `ENCRYPTION_KEY`. Provision `.key` before serving requests.
- `expose_env()` includes local process environment values beneath `$_ENV` values. These values also reach route handlers through `expose_all()`. Avoid logging or exposing them. FastCGI-only values are not added by the process merge.
- Restrict built-in server static file handoff to files inside the public directory, excluding the front controller. Directories and symlinks resolving outside the public directory are no longer handed off.
- Return the not-found route for unconfigured methods and handle request URIs without a path.
- Normalize request paths once during route matching.
- Bundle the welcome page entrypoint without separate runtime or vendor chunks and install its configured `core-js` polyfills.

## 2.0.0

- Require PHP 8.1 or later.
- Return `null` from `Functions\decrypt` for malformed ciphertext or failed authentication. Invalid key lengths still raise `SodiumException`.
- Send HTTP 404 status headers for unmatched routes on non-CLI servers.
