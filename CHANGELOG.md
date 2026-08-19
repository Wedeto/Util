# Changelog

## 1.1.5

- Replace deprecated reflection parameter class inspection with named-type handling.

## 1.1.4

- Handle missing doc-comment annotations without passing null to string functions.

## 1.1.3

- Make cache shutdown-hook registration idempotent.

## 1.1.2

- Fix cache shutdown-hook removal by retaining the subscription reference.

## 1.1.1

- Require PHPUnit 9.6.33 or newer to avoid CVE-2026-24765 in development environments.

## 1.1.0

- Add support for PHP 8.1 through PHP 8.5.
- Retain support for PHP 7.4 while explicitly excluding PHP 8.0.
- Allow compatible PSR Log 1.x releases.
- Update the test suite to PHPUnit 9.6.
