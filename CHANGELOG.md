<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.0.1 – 2026-11-10

### Added
- Password policy integration: per-account counter for "maximum login attempts", detection of accounts locked by the policy (new state "Account locked"), one-click unlock and counter reset
- Records why and by whom an account was disabled (password policy, administrator, system)
- New audit log entries for policy locks, disabled accounts and counter resets

### Changed
- Clearer states: "IP blocked" / "IP throttled" instead of "Blocked" / "Throttled", because Nextcloud blocks IP addresses, not accounts
- The reverse proxy warning is only shown when a forwarded client address is actually ignored (no more false alarm for clients in a private network)

### Fixed
- Long reasons in the account list are wrapped and truncated cleanly

## 1.0.0 – 2026-11-09

### Added
- Per-account overview of failed logins (24 h / 7 days / selected period) with last IP, client and reason
- Live brute-force state per IP (throttled with current delay / blocked) using Nextcloud's throttler – works with the database and the Redis backend
- Unblock an account (all IP addresses of the last 48 hours) or a single IP address
- Re-enable disabled accounts (admins; optionally delegated groups, never admin accounts)
- Statistics: failed logins per day, reasons, top accounts and top IP addresses
- IP address check for any address
- Audit log of all unblock / enable actions, also written to the Nextcloud log
- Access delegation to groups in *Administration settings → Security*
- Configurable retention with daily cleanup background job
- English and German (informal and formal) translations
