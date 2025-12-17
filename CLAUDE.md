# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

RARUS Echo PHP SDK is a PHP library for the RARUS Echo transcription service. It provides PSR-compliant interfaces for audio/video transcription with support for 13 languages, timestamps, speaker diarization, and queue management.

**Requirements**: PHP 8.2+, Docker & Docker Compose for development.

## Development Commands

### Docker Environment
```bash
make docker-init          # First-time setup: build, start, install dependencies
make docker-up            # Start containers
make docker-down          # Stop containers
make php-cli-bash         # Enter PHP container shell
```

### Dependencies
```bash
make composer-install     # Install dependencies
make composer-update      # Update dependencies
```

### Code Quality
```bash
make lint-all                # Run all linters (CS Fixer, PHPStan, Rector)
make lint-cs-fixer          # Check code style (PSR-12)
make lint-cs-fixer-fix      # Auto-fix code style
make lint-phpstan           # Static analysis (level 8)
make lint-rector            # Check Rector rules
make lint-rector-fix        # Apply Rector fixes
```

### Testing
```bash
make test-unit              # Run unit tests only
make test-integration       # Run integration tests only (requires API credentials)
make test-all               # Run all tests
make test-coverage          # Generate coverage report

# Run specific service tests
make test-integration-transcription
make test-integration-status
make test-integration-queue
```

### CI Pipeline
```bash
make ci                     # Full CI: install, lint-all, test-all
make pre-commit             # Quick pre-commit: fix style, phpstan, unit tests
```

### Environment Configuration
For integration tests and local development, copy `.env` to `.env.local` in the project root and configure:
- `RARUS_ECHO_API_KEY` - Your API key
- `RARUS_ECHO_USER_ID` - Your User UUID
- `RARUS_ECHO_BASE_URL` - API endpoint (default: https://production-ai-ui-api.ai.rarus-cloud.ru)
- `RARUS_ECHO_TEST_AUDIO_PATH` - Path to test audio file (default: tests/Fixtures/audio/sample.mp3)

Example `.env.local`:
```bash
RARUS_ECHO_API_KEY=your-actual-api-key-here
RARUS_ECHO_USER_ID=12345678-1234-1234-1234-123456789abc
RARUS_ECHO_BASE_URL=https://production-ai-ui-api.ai.rarus-cloud.ru
RARUS_ECHO_TEST_AUDIO_PATH=tests/Fixtures/audio/sample.mp3
```

**Note:** The `.env.local` file is automatically loaded by Docker Compose and is git-ignored.

## Automatic Testing

**IMPORTANT:** This project has Claude Code hooks configured in `.claude/settings.json`.

After you (Claude) modify any files using Write or Edit tools, unit tests automatically run via `make test-unit`.

**Expected behavior:**
1. You modify code (Write/Edit tools)
2. PostToolUse hook triggers automatically
3. `make test-unit` runs in Docker
4. Test output (last 30 lines) is shown
5. If tests fail, analyze output and fix issues

**Do not:**
- Skip or disable hooks during normal development
- Manually run tests after every change (hooks do this automatically)
- Be surprised when tests run - this is expected behavior

**Test failures:**
- Read the test output carefully
- Identify which test failed and why
- Fix the issue in the code
- Tests will run automatically again after your fix

## Architecture

The SDK uses a layered architecture inspired by Bitrix24 PHP SDK:

```
ServiceFactory
    └── Entry point for all SDK operations
    └── Creates and manages service instances

Services Layer (Transcription, Status, Queue)
    └── Business logic for specific API domains
    └── Final classes with direct ApiClient dependency
    └── Services return strongly-typed Result objects

Core Layer (ApiClient, Credentials, Pagination)
    └── ApiClient: Handles HTTP communication (GET, POST)
    └── Credentials: Manages API authentication (key + user ID)
    └── Response handling and error mapping

Infrastructure Layer
    └── Filesystem: File validation and upload handling
    └── PSR-18/PSR-17 auto-discovery via php-http/discovery
```

### Key Design Patterns

**ServiceFactory**: Single entry point for SDK initialization. Auto-discovers PSR-18 HTTP client if not provided.
```php
$factory = new ServiceFactory($credentials);
$transcription = $factory->getTranscriptionService();
```

**Builder Pattern**: `TranscriptionOptions`, `Credentials` use builders for fluent configuration.

**Result Objects**: All service methods return immutable result objects (e.g., `TranscriptBatchResult`, `StatusItemResult`) that encapsulate response data.

**PSR Auto-Discovery**: Uses `php-http/discovery` to automatically find PSR-17/PSR-18 implementations if not explicitly provided.

## Service Layer Structure

Each service follows the same pattern:
- **Service class** (e.g., `Transcription.php`) in `Services/{Domain}/Service/`
- **Result objects** (e.g., `TranscriptBatchResult.php`) in `Services/{Domain}/Result/`
- **Request objects** (e.g., `TranscriptionOptions.php`) in `Services/{Domain}/Request/`

Services are final classes that receive `ApiClient` and optional `LoggerInterface` via constructor injection.

## Exception Hierarchy

All exceptions extend `EchoException`:
- `ApiException` - General API errors (base class for HTTP errors)
- `AuthenticationException` - 401 Unauthorized (invalid credentials)
- `AuthorizationException` - 403 Forbidden (insufficient permissions)
- `BadRequestException` - 400 Bad Request (invalid request format)
- `ValidationException` - 422 Unprocessable Entity (structured validation errors)
- `ServerErrorException` - 500 Internal Server Error
- `NetworkException` - Network/connection failures (PSR-18 client errors)
- `FileException` - File operations (not found, unreadable, invalid format)

HTTP status codes are automatically mapped to appropriate exception types in `ResponseHandler`.

## Important Implementation Notes

1. **Credentials**: Always passed as `Authorization` header (API key) + `user-id` header (UUID). Base URL defaults to production but can be overridden.

2. **File Uploads**: Handled by `FileUploader` which uses Symfony MIME to create multipart requests. Files are validated before upload (format, readability, size).

3. **Pagination**: All list methods require a `Pagination` parameter (limit + offset). This is intentionally required (not optional).

4. **Logging**: Uses PSR-3 logger throughout. NullLogger by default; pass real logger to ServiceFactory for debugging.

5. **Type Safety**: Strict types everywhere (`declare(strict_types=1)`). PHPStan level 8. Enums for fixed values (Language, TaskType, TranscriptionStatus).

6. **Immutability**: Result objects and configuration objects (Credentials, TranscriptionOptions) are immutable after creation.

## Testing Strategy

- **Unit tests** (`tests/Unit/`): Test individual classes in isolation with mocks
- **Integration tests** (`tests/Integration/`): Test against real API (require credentials in `.env.local`)
- All tests use PHPUnit 10+
- Mock HTTP client available via `php-http/mock-client` for unit tests

## Code Style

- **PSR-12** coding standard enforced by PHP CS Fixer
- **Readonly properties** preferred for immutability
- **Final classes** by default unless designed for extension
- **Named parameters** used for clarity in constructor calls
- **Type declarations** required on all parameters and return types