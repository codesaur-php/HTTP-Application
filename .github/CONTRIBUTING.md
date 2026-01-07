# Contributing to codesaur/http-application

First of all, thank you for taking the time to contribute ❤️  
Contributions of any kind are welcome and greatly appreciated!

---

## 🎯 Ways to Contribute

You can contribute by:

- 🐛 **Reporting bugs** - Help us identify and fix issues
- 💡 **Suggesting new features** - Share your ideas for improvements
- 📚 **Improving documentation** - Make the docs clearer and more comprehensive
- 🔧 **Submitting pull requests** - Contribute code improvements and new features
- ✅ **Writing tests** - Improve test coverage and quality
- 🔍 **Code review** - Review and provide feedback on pull requests

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.2.1 or higher
- Composer installed
- Git installed

### Setup Steps

1. **Fork and clone the repository:**

```bash
git clone https://github.com/codesaur-php/HTTP-Application.git
cd HTTP-Application
```

2. **Install dependencies:**

```bash
composer install
```

3. **Run tests to ensure everything works:**

```bash
composer test
```

4. **Check test coverage (optional):**

```bash
composer test:coverage
```

---

## 📋 Development Workflow

### 1. Create a Branch

Create a new branch for your changes:

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

### 2. Make Your Changes

- Write clean, readable code
- Follow existing code style and conventions
- Add tests for new features or bug fixes
- Update documentation if needed

### 3. Run Tests

Before submitting, make sure all tests pass:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run integration tests only
composer test:integration

# Run unit tests only
composer test:unit
```

### 4. Commit Your Changes

Use clear and descriptive commit messages following the [Conventional Commits](https://www.conventionalcommits.org/) format:

```bash
git commit -m "feat: add support for custom middleware priority"
git commit -m "fix: resolve route parameter parsing issue"
git commit -m "docs: update Application class examples"
```

### 5. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a pull request on GitHub.

---

## 📝 Coding Guidelines

### Code Style

- Follow **PSR-12** coding standard
- Use meaningful variable and method names
- Keep methods focused and single-purpose
- Add PHPDoc comments for public methods and classes
- Maintain consistency with existing codebase

### Code Structure

- **PSR-7 & PSR-15 compliance** - All code must adhere to PSR standards
- **Middleware pattern** - Follow the onion model for middleware
- **Exception handling** - Use appropriate exception types
- **Type hints** - Use strict type declarations where possible

### Testing Requirements

- Write tests for new features
- Maintain or improve test coverage
- Tests should be clear and well-documented
- Integration tests for complex workflows

---

## 🔀 Pull Request Guidelines

### Before Submitting

- ✅ All tests pass (`composer test`)
- ✅ Code follows project style guidelines
- ✅ Documentation is updated (if needed)
- ✅ Commit messages are clear and descriptive
- ✅ Branch is up to date with main branch

### PR Description Template

```markdown
## Description
Brief description of what this PR does.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How was this tested? Include test commands if applicable.

## Checklist
- [ ] Tests pass
- [ ] Documentation updated
- [ ] Code follows style guidelines
```

### PR Rules

- **One logical change per PR** - Keep PRs focused and manageable
- **Clear description** - Explain what and why, not just how
- **Reference issues** - Link to related issues if applicable
- **Small commits** - Break large changes into smaller, logical commits

---

## 💬 Commit Message Format

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Examples

```bash
feat(Application): add support for route groups
fix(Controller): resolve parameter type casting issue
docs(README): update middleware examples
test(ApplicationTest): add integration test for error handling
refactor(ExceptionHandler): improve error message formatting
```

---

## 📚 Documentation

If your change affects usage or public API:

### Required Updates

- **README.md** - Update examples, usage instructions, or feature list
- **api.md** - Update API documentation for new methods or changed behavior
- **CHANGELOG.md** - Add entry for notable changes (if exists)

### Documentation Style

- Use clear, concise language
- Include code examples where helpful
- Keep examples up-to-date and working
- Add both Mongolian and English if applicable

---

## 🧪 Testing Commands

The project includes several test commands via Composer:

```bash
# Run all tests
composer test

# Run tests with HTML and text coverage reports
composer test:coverage

# Generate Clover XML coverage (for CI/CD)
composer test:coverage-clover

# Run only integration tests
composer test:integration

# Run only unit tests
composer test:unit
```

---

## 🏗️ Project Structure

Understanding the project structure helps with contributions:

```
HTTP-Application/
├── src/                    # Source code
│   ├── Application.php    # Core Application class
│   ├── Controller.php     # Base Controller class
│   └── ExceptionHandler.php
├── tests/                  # Test files
├── example/                # Example implementations
└── docs/                   # Documentation
```

---

## 🤝 Code of Conduct

### Our Standards

- Be respectful and constructive
- Welcome newcomers and help them learn
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment or discriminatory language
- Personal attacks or trolling
- Publishing others' private information
- Other conduct that could reasonably be considered inappropriate

This project follows a friendly, inclusive open-source culture.

---

## 🔒 Security Issues

**Please do not open public issues for security vulnerabilities.**

For security-related issues, please follow the instructions in [SECURITY.md](SECURITY.md) or contact the maintainer directly:

- 📧 **Email:** codesaur@gmail.com
- 📲 **Phone:** [+976 99000287](https://wa.me/97699000287)

---

## ❓ Questions?

If you have questions or need help:

- Open an issue with the `question` label
- Check existing documentation (README.md, API.md)
- Review existing issues and pull requests

---

## 🙏 Recognition

Contributors will be:

- Listed in the project's contributors (if applicable)
- Credited in release notes for significant contributions
- Appreciated by the entire codesaur ecosystem community

---

Thank you for helping improve the **codesaur ecosystem** 🦖

Your contributions make this project better for everyone!
