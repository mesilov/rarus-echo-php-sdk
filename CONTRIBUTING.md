# Contributing to RARUS Echo PHP SDK

Thank you for your interest in contributing to the RARUS Echo PHP SDK! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Release Process](#release-process)

## Code of Conduct

We are committed to providing a welcoming and inclusive environment for all contributors. Please be respectful and constructive in all interactions.

## Getting Started

### Prerequisites

Before contributing, ensure you have:
- PHP 8.2 or higher installed
- Docker and Docker Compose
- Make utility
- Git
- Basic understanding of PSR standards and Symfony components

### Finding Issues to Work On

- Check the [Issues](../../issues) page for open issues
- Look for issues labeled `good first issue` for beginner-friendly tasks
- Look for issues labeled `help wanted` for areas where contributions are especially welcome

## Development Setup

1. **Fork the repository**
   ```bash
   # Click the "Fork" button on GitHub
   ```

2. **Clone your fork**
   ```bash
   git clone https://github.com/your-username/rarus-echo-php-sdk.git
   cd rarus-echo-php-sdk
   ```

3. **Add upstream remote**
   ```bash
   git remote add upstream https://github.com/mesilov/rarus-echo-php-sdk.git
   ```

4. **Initialize development environment**
   ```bash
   make docker-init
   make composer-install
   ```

5. **Verify setup**
   ```bash
   make test-all
   make lint-all
   ```

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix-name
```

Branch naming conventions:
- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation changes
- `refactor/` - Code refactoring
- `test/` - Test improvements

### 2. Make Your Changes

- Write clean, maintainable code following our [Coding Standards](#coding-standards)
- Add tests for new functionality
- Update documentation as needed
- Keep commits atomic and meaningful

### 3. Run Tests and Linters

Before committing, ensure all checks pass:

```bash
# Run all tests
make test-all

# Run all linters
make lint-all

# Run complete CI pipeline locally
make ci
```

### 4. Commit Your Changes

Follow conventional commit format:

```bash
git commit -m "feat: add support for new transcription type"
git commit -m "fix: resolve file upload timeout issue"
git commit -m "docs: update API examples in README"
git commit -m "test: add unit tests for FileValidator"
```

Commit message format:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

### 5. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a Pull Request on GitHub with:
- Clear title and description
- Reference to related issues (e.g., "Closes #123")
- Description of changes made
- Any breaking changes highlighted

## Coding Standards

### PSR Compliance

This project follows these PSR standards:
- **PSR-1**: Basic Coding Standard
- **PSR-4**: Autoloading Standard
- **PSR-12**: Extended Coding Style Guide

### PHP Standards

- **PHP Version**: Write code compatible with PHP 8.2+
- **Strict Types**: Always use `declare(strict_types=1);`
- **Type Declarations**: Use type hints for all parameters and return types
- **Readonly Properties**: Use `readonly` for immutable properties
- **Named Arguments**: Support named arguments in public APIs

### Code Style

Automated tools enforce code style:

```bash
# Check code style
make lint-cs-fixer

# Fix code style automatically
make lint-cs-fixer-fix
```

**Key style rules:**
- Use 4 spaces for indentation
- Use PascalCase for class names
- Use camelCase for methods and properties
- Use UPPER_CASE for constants
- Maximum line length: 120 characters
- Add PHPDoc blocks for classes and public methods

### Static Analysis

Code must pass PHPStan level 8:

```bash
make lint-phpstan
```

**Rules:**
- No unused variables
- No undefined variables
- Proper type hints
- No dead code
- Consistent return types

## Testing

### Test Organization

Tests are organized by type:
```
tests/
├── Unit/           # Unit tests (fast, isolated)
├── Integration/    # Integration tests (with real dependencies)
└── Fixtures/       # Test fixtures and data
```

### Writing Tests

1. **Unit Tests**: Test individual classes in isolation
   ```php
   public function testCredentialsCreation(): void
   {
       $credentials = Credentials::create('api-key', 'user-id');

       $this->assertSame('api-key', $credentials->getApiKey());
       $this->assertSame('user-id', $credentials->getUserId());
   }
   ```

2. **Use Mocks**: Mock external dependencies
   ```php
   $apiClient = $this->createMock(ApiClient::class);
   $apiClient->expects($this->once())
       ->method('post')
       ->willReturn($response);
   ```

3. **Test Edge Cases**: Test both happy path and error cases

4. **Use Data Providers**: For testing multiple scenarios
   ```php
   /**
    * @dataProvider invalidApiKeyProvider
    */
   public function testInvalidApiKey(string $apiKey): void
   {
       $this->expectException(ValidationException::class);
       Credentials::create($apiKey, 'user-id');
   }
   ```

### Running Tests

```bash
# Run all tests
make test-all

# Run specific test suites
make test-unit
make test-integration

# Run with coverage
make test-coverage

# Run specific test file
make php-cli-bash
./vendor/bin/phpunit tests/Unit/Core/Credentials/CredentialsTest.php
```

### Test Coverage

- Aim for 80%+ code coverage
- All new code should have corresponding tests
- Critical paths must have 100% coverage

## Submitting Changes

### Pull Request Checklist

Before submitting a PR, ensure:

- [ ] Code follows PSR-12 coding standards
- [ ] All tests pass (`make test-all`)
- [ ] All linters pass (`make lint-all`)
- [ ] PHPStan level 8 passes (`make lint-phpstan`)
- [ ] New code has unit tests
- [ ] Documentation is updated
- [ ] Commit messages follow conventional format
- [ ] CHANGELOG.md is updated (if applicable)
- [ ] No breaking changes (or clearly documented)

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Related Issues
Closes #123

## Testing
Describe how you tested the changes

## Checklist
- [ ] Tests pass
- [ ] Linters pass
- [ ] Documentation updated
- [ ] CHANGELOG updated
```

### Review Process

1. Automated checks run on all PRs
2. At least one maintainer review required
3. Address review feedback
4. Once approved, maintainers will merge

## Architecture Guidelines

### Layer Separation

Maintain clear layer separation:
```
Application → Services → Core → Infrastructure
```

- **Application**: User-facing API, service contracts
- **Services**: Business logic
- **Core**: API client, credentials, responses
- **Infrastructure**: HTTP, filesystem, serialization

### Design Principles

- **SOLID Principles**: Follow SOLID design principles
- **Immutability**: Prefer immutable objects
- **Type Safety**: Use strict types everywhere
- **PSR Compliance**: Follow PSR standards
- **Dependency Injection**: Inject dependencies, don't create them
- **Interface Segregation**: Define clear interfaces

### Adding New Features

When adding new features:

1. **Define Interface**: Start with interface in `Application/Contracts/`
2. **Implement Service**: Create service in `Services/`
3. **Add Models**: Create request/result models in `Services/.../Request` and `Services/.../Result`
4. **Update Application**: Add service getter in `ServiceFactory`
5. **Add Tests**: Create comprehensive unit tests
6. **Document**: Update README and add examples

## Documentation

### Inline Documentation

- Add PHPDoc blocks to all classes and public methods
- Include `@param`, `@return`, `@throws` tags
- Add examples in `@example` tags where helpful

Example:
```php
/**
 * Submit files for transcription
 *
 * @param array<string>        $files   Array of file paths to transcribe
 * @param TranscriptionOptions $options Transcription configuration options
 *
 * @return TranscriptPostResult Result containing file IDs
 *
 * @throws FileException           If file is invalid or cannot be read
 * @throws ValidationException     If request validation fails
 * @throws AuthenticationException If authentication fails
 * @throws ApiException            If API request fails
 *
 * @example
 * ```php
 * $result = $service->submitTranscription(
 *     ['/path/to/audio.mp3'],
 *     TranscriptionOptions::default()
 * );
 * ```
 */
public function submitTranscription(
    array $files,
    TranscriptionOptions $options
): TranscriptPostResult {
    // ...
}
```

### README Updates

Update README.md when:
- Adding new features
- Changing public APIs
- Adding new examples
- Changing requirements

### Examples

Add examples to `examples/` directory for new features.

## Release Process

Maintainers handle releases:

1. Update version in appropriate files
2. Update CHANGELOG.md
3. Create release tag
4. Publish to Packagist
5. Create GitHub release

### Semantic Versioning

We follow [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

## Getting Help

If you need help:
- Check existing [documentation](README.md)
- Search [existing issues](../../issues)
- Create a new issue with your question
- Ask in Pull Request discussions

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Thank You!

Your contributions help make this project better for everyone. Thank you for taking the time to contribute!
