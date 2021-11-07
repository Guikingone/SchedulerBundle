# Installation

This bundle can be built locally using Docker:

```bash
make boot
```

Once installed, you can list the available commands:

```bash
make
```

# Tests

Every test SHOULD pass before submitting a PR (consider using `make tests`), 
if a test fail due to an unknown
error, the PR can be submitted with it and a discussion opened.

# Style

The style can be checked via `make php-cs-fixer`, consider fixing
the errors BEFORE sending the PR.
