# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.1] - 27 November 2026
### Added
- Host, port, trusted proxies, worker lifetime and maximum request size can be configured with `SWOOLE_API_HOST`, `SWOOLE_API_PORT`, `SWOOLE_API_TRUSTED_PROXIES`, `SWOOLE_API_MAX_REQUEST` and `SWOOLE_API_PACKAGE_MAX_LENGTH`
- PHP 8.5 support

### Changed
- BREAKING: Magento 2.4.7 or higher is required, because the per-request state reset depends on it
- Dropped PHP 8.1 support
- The server listens on 127.0.0.1 instead of 0.0.0.0 by default
- Request logging is opt-in via `SWOOLE_API_DEBUG=1` and only records metadata (operation name, size, status, duration)

### Fixed
- Security: customer identity, response headers and cached store URLs leaked from one request into the next
- Security: request headers like `Authorization` stayed in `$_SERVER` for later requests
- Security: full GraphQL bodies, including customer passwords, were written to `var/log/swoole.log`
- Security: clients could spoof HTTPS or their IP address with forwarding headers unless coming from a trusted proxy
- Security: concurrent coroutines could overwrite the shared request and authentication state
- Security: `appserver.php` was reachable over HTTP when copied into the Magento root
- Security: unhandled exceptions killed the worker and error details were shown to clients; a worker whose state reset fails is now retired
- Invalid JSON returns 400 instead of 500, and empty response bodies are sent correctly
- PHP 8.2 compatibility by replacing `json_validate()`, plus missing composer dependencies
