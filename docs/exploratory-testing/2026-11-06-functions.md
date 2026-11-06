# Functions exploratory pass, 2026-11-06

Base commit: `8c410a4c09bdedf7ef5e8c7e251c139cdf1b65fd` on `origin/master`. The pass used a separate worktree and disposable keys. No production data was used. PHP was 8.5.4, Node was 22.22.1, and Yarn was 1.22.22.

Composer installed the committed lockfile with `--ignore-platform-req=php`. The normal locked install failed because locked `phpspec/prophecy` requires PHP below 8.2. PHP 8.1, the CI version, was not installed locally. Yarn installed with `corepack yarn install --frozen-lockfile --non-interactive`. The normal development build failed with `ERR_OSSL_EVP_UNSUPPORTED`. The development and production builds succeeded with `NODE_OPTIONS=--openssl-legacy-provider`. The setup limitations are already documented in [draft PR #25](https://github.com/jonbaldie/functions/pull/25). PHPUnit passed 63 tests and 226 assertions with the PHP version override. These results do not establish compatibility on PHP 8.1.

## Create and use a project key

Composer's post-autoload script generated `.key`. The generator preserved an existing key on a second invocation. A public API consumer encrypted and decrypted a message using a generated key. A wrong key and modified ciphertext both returned `null`.

**Confirmed bug:** the generator makes the key world-readable and world-writable even under `umask 077`. Two independent fresh key directories produced mode `666`. In one disposable directory made traversable for the experiment, an unprivileged `nobody` process read and appended to its key. An accessible parent directory is a prerequisite for another local user to exploit this. The original worktree and its parent were not traversable by other users. An attacker with access to the key could decrypt or forge protected values or break the application. See [issue #53](https://github.com/jonbaldie/functions/issues/53) for the complete reproducer and expected behavior.

## Open the application and route requests

With the generated key and development assets, the built-in PHP server returned the welcome page for `GET /` and `GET /?a=1`. An unknown route and an unconfigured `POST /` returned 404. `GET /robots.txt` and `GET /build/app.js` returned 200. These observations repeated with a fresh server process. A cookie jar retained the same session and CSRF cookie values on a revisit. Its text changed because the two entries appeared in a different order. The initial byte-comparison suspicion was rejected after comparing cookie names and values.

## Build frontend assets

With the OpenSSL workaround, both development and production builds listed `/build/app.js` as their sole JavaScript entrypoint. The checked-in welcome page referenced that entrypoint, and the production file existed. The development bundle was served over HTTP. This checks file inclusion and serving, not JavaScript execution in a browser. No browser was available in the test environment.

## Evidence and limits

The [redacted evidence](2026-11-06-functions-evidence.txt) records commands, outputs, and cleanup checks. Server processes were started under an exit trap and stopped after each run. Neither recorded PID remained, and the test port was clear. No browser, container, image, or volume was started by this pass.

The tracker was checked for open and closed issues, comments, labels, and prior rejections before filing. The key permission finding had no matching issue in that check. Existing [issue #40](https://github.com/jonbaldie/functions/issues/40) covers environment snapshot performance and was outside this pass. Existing [issue #52](https://github.com/jonbaldie/functions/issues/52) covers the locked PHPUnit advisory. An existing worktree for issue #40 was left untouched.

Production servers, alternate operating systems, browser execution, database integration, and deployment were not exercised. No other candidates remain unresolved. Product fixes are outside this report.
