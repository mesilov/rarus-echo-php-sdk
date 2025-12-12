# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- PHP 8.4 support
- Initial SDK implementation with complete functionality

### Changed
- **BREAKING**: Minimum PHP version increased from 8.1 to 8.2
- Support for asynchronous audio/video transcription
- 14 language support (including auto-detection)
- 4 transcription types: basic, timestamps, diarization, raw
- PSR-3, PSR-7, PSR-17, PSR-18 compliance
- Symfony components integration (HttpClient, Serializer, Filesystem, Validator)
- Auto-discovery of HTTP client via php-http/discovery
- Comprehensive exception hierarchy
- File validation (max 500MB, 17 audio/video formats)
- Service layer with 3 main services:
  - TranscriptionService: file upload, transcript retrieval, Drive integration
  - StatusService: status checking for transcription tasks
  - QueueService: queue monitoring and statistics
- Builder pattern for complex objects (Credentials, TranscriptionOptions)
- Immutable data structures using PHP 8.2+ readonly properties
- PSR-3 logger support throughout
- Retry logic with configurable attempts
- Docker development environment
- Makefile with 40+ commands for development workflow
- Comprehensive unit test suite (43+ tests)
- Code quality tools:
  - PHPStan level 8 static analysis
  - PHP CS Fixer with PSR-12 compliance
  - Rector for code modernization
  - PHPUnit for testing
- GitHub Actions CI/CD workflow
- Complete examples:
  - basic-usage.php: demonstrates core functionality
  - advanced-usage.php: batch processing, monitoring, statistics
- Full documentation in README.md

### Architecture
- Multi-layered architecture:
  - Application Layer: EchoApplication entry point with service contracts
  - Services Layer: Business logic for API operations
  - Core Layer: ApiClient, Credentials, Response handling
  - Infrastructure Layer: HTTP client, Serializer, Filesystem operations

### Dependencies
- PHP ^8.2 || ^8.3 || ^8.4
- symfony/http-client ^6.4 || ^7.0
- symfony/serializer ^6.4 || ^7.0
- symfony/filesystem ^6.4 || ^7.0
- symfony/validator ^6.4 || ^7.0
- php-http/discovery ^1.19
- psr/log ^2.0 || ^3.0
- psr/http-client ^1.0
- psr/http-message ^1.1 || ^2.0
- psr/http-factory ^1.0

## [0.1.0] - TBD

### Added
- Initial release (coming soon)

---

## Release Notes

### Version 0.1.0 (Upcoming)

This is the initial release of the RARUS Echo PHP SDK. The SDK provides a modern, PSR-compliant interface for interacting with the RARUS Echo transcription service.

**Key Features:**
- Full API coverage for transcription operations
- Type-safe enum-based configuration
- Comprehensive error handling
- Cross-platform file operations
- Extensive test coverage
- Production-ready code quality

**PHP Version Support:**
- PHP 8.2 (minimum)
- PHP 8.3 (supported)
- PHP 8.4 (latest)

**PSR Compliance:**
- PSR-3: Logger Interface
- PSR-4: Autoloading Standard
- PSR-7: HTTP Message Interface
- PSR-12: Extended Coding Style Guide
- PSR-17: HTTP Factories
- PSR-18: HTTP Client

For migration guides and upgrade instructions, see the documentation.
