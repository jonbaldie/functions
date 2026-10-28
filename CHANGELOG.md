# Changelog

## 2.0.0

- Require PHP 8.1 or later.
- Return `null` from `Functions\decrypt` for malformed ciphertext or failed authentication. Invalid key lengths still raise `SodiumException`.
- Send HTTP 404 status headers for unmatched routes on non-CLI servers.
