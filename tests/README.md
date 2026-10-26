# Testing KOMOJU for WooCommerce

There are two test suites: Cypress end-to-end tests, and standalone PHP unit
tests under `tests/php/`.

## PHP tests

These stub the WordPress/WooCommerce functions they need, so they run without
Docker, a database, or API keys:

```bash
php tests/php/blocks-translation-test.php
php tests/php/ipn-async-completion-test.php
```

Each file is self-contained and exits non-zero on failure. CI runs every
`tests/php/*-test.php` (see `.github/workflows/lint.yml`).

The completion regression covers background completion before IPN handler
initialization, duplicate jobs, paid/refunded orders, fresh
state after lock acquisition, the authorized/captured event pair, webhook lock
contention, deferred workers, database errors, exception-safe lock release, and
unchanged processing for unknown adapters (including MySQL-emulating drivers).
Serialization is enabled only for the core `wpdb` class with `is_mysql === true`;
custom drivers, even MySQL-backed ones, keep the original paths. These stubs do
not prove database locking. Changes also need concurrent MySQL/MariaDB requests
with HPOS and legacy storage, plus adapter checks that no locking SQL is issued.

## End-to-end tests

The end-to-end tests use [cypress](https://cypress.io).

The shared setup loads KOMOJU Fields from
`https://multipay.staging.komoju-dev.com/fields.js`.

These tests should work on a fresh `docker-compose up`. If your database is not fresh, you can clear it with the following commands:

```bash
# delete dev containers and database
docker-compose down --volumes
```


To run tests,

```bash
npx cypress run
```


To visually debug and inspect the in-progress tests,

```bash
npx cypress open
```
