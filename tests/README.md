# Testing KOMOJU for WooCommerce

There are two test suites: Cypress end-to-end tests, and standalone PHP unit
tests under `tests/php/`.

## PHP tests

These stub the WordPress/WooCommerce functions they need, so they run without
Docker, a database, or API keys:

```bash
php tests/php/blocks-translation-test.php
```

Each file is self-contained and exits non-zero on failure. CI runs every
`tests/php/*-test.php` (see `.github/workflows/lint.yml`).

## End-to-end tests

The end-to-end tests use [cypress](https://cypress.io).

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
