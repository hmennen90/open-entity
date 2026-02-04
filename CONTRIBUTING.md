# Contributing to OpenEntity

Thank you for your interest in contributing to OpenEntity! This document provides guidelines and information for contributors.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment. Please be considerate and constructive in your communications.

## How to Contribute

### Reporting Bugs

Before creating a bug report, please check if the issue has already been reported. When creating a bug report, include:

- A clear and descriptive title
- Steps to reproduce the issue
- Expected behavior vs actual behavior
- Your environment (OS, PHP version, Docker version, etc.)
- Relevant logs or error messages

### Suggesting Features

We welcome feature suggestions! Please:

- Check existing issues for similar suggestions
- Provide a clear description of the feature
- Explain the use case and benefits
- Consider how it fits with OpenEntity's vision as an autonomous entity

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the commit message conventions** (see below)
3. **Write tests** for new functionality
4. **Ensure all tests pass** before submitting
5. **Update documentation** if needed
6. **Submit a pull request** with a clear description

## Development Setup

### Prerequisites

- Docker & Docker Compose
- Node.js 20+
- Git

### Getting Started

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/OpenEntity.git
cd OpenEntity

# Run the setup script
./setup.sh

# Start development environment
docker compose up -d

# Watch frontend assets
npm run dev
```

### Running Tests

```bash
# All tests
docker compose exec app php artisan test

# Specific test file
docker compose exec app php artisan test --filter=ToolRegistryTest

# With coverage (requires Xdebug)
docker compose exec app php artisan test --coverage
```

## Commit Message Convention

We use [Conventional Commits](https://www.conventionalcommits.org/) for semantic versioning:

### Format

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types

| Type | Description | Version Bump |
|------|-------------|--------------|
| `feat` | New feature | Minor |
| `fix` | Bug fix | Patch |
| `docs` | Documentation only | None |
| `style` | Code style (formatting, etc.) | None |
| `refactor` | Code change that neither fixes a bug nor adds a feature | None |
| `perf` | Performance improvement | Patch |
| `test` | Adding or updating tests | None |
| `chore` | Maintenance tasks | None |

### Scopes

Common scopes include:
- `entity` - Entity service and core functionality
- `memory` - Memory system
- `tools` - Tool system
- `api` - REST API endpoints
- `frontend` - Vue.js frontend
- `docker` - Docker configuration
- `docs` - Documentation

### Examples

```bash
feat(tools): add weather lookup tool
fix(memory): resolve embedding dimension mismatch
docs(api): update endpoint documentation
refactor(entity): extract think loop into separate service
test(memory): add consolidation service tests
```

### Breaking Changes

For breaking changes, add `!` after the type/scope or include `BREAKING CHANGE:` in the footer:

```bash
feat(api)!: change response format for entity status

BREAKING CHANGE: The status endpoint now returns `state` instead of `status`
```

## Code Style

### PHP

- Follow PSR-12 coding standards
- Use type hints and return types
- Write meaningful docblocks for public methods
- Keep methods focused and under 30 lines when possible

### JavaScript/Vue

- Use ESLint configuration provided
- Use Composition API for Vue components
- Follow Vue.js style guide recommendations

### Testing

- Write unit tests for services and utilities
- Write feature tests for API endpoints
- Use factories for test data
- Mock external services (LLM, embedding providers)

## Project Structure

```
OpenEntity/
├── app/
│   ├── Console/Commands/     # Artisan commands
│   ├── Events/               # Event classes
│   ├── Http/Controllers/     # API controllers
│   ├── Models/               # Eloquent models
│   └── Services/             # Business logic
│       ├── Entity/           # Core entity services
│       ├── LLM/              # LLM providers
│       └── Tools/            # Tool system
├── config/                   # Configuration files
├── database/
│   ├── factories/            # Model factories
│   └── migrations/           # Database migrations
├── resources/js/             # Vue.js frontend
├── storage/entity/           # Entity data storage
└── tests/                    # Test suites
```

## Architecture Guidelines

When contributing to OpenEntity, keep these principles in mind:

### Entity Autonomy

OpenEntity is designed to be an autonomous entity, not just a chatbot. Contributions should support:

- Self-directed behavior through goals and interests
- Persistent memory and personality
- Independent thought processes
- Social interaction capabilities

### Memory Layers

The memory system has four layers:

1. **Core Identity** - Personality, values, name (rarely changes)
2. **Semantic Memory** - Learned facts and knowledge
3. **Episodic Memory** - Experiences and conversations
4. **Working Memory** - Current context and focus

### Tool System

Tools enable the entity to interact with the world:

- Built-in tools provide core functionality
- Custom tools can be created by the entity itself
- All tools are validated and sandboxed for security

## Getting Help

- Open a [GitHub Issue](https://github.com/hmennen90/OpenEntity/issues) for bugs or features
- Start a [Discussion](https://github.com/hmennen90/OpenEntity/discussions) for questions
- Check the [documentation](docs/) for detailed guides

## License

By contributing to OpenEntity, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to OpenEntity! Your efforts help make this project better for everyone.
