# Contributing

Thanks for helping to improve Stup Image! Bug reports, fixes, docs and ideas are all welcome.

## Guiding principle

Stup Image aims to be the simplest way to store images in Laravel. Before proposing a feature, ask yourself: **can it be used without an extra table and without required configuration?** If the answer is no, it probably belongs in a package like [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary).

## Workflow

1. Fork the repository and create a branch from `dev`.
2. Install the dependencies: `composer install` (PHP 8.2+ with the `gd` extension).
3. Write a failing test first, then make it pass.
4. Make sure everything is green:

   ```bash
   composer test      # Pest
   composer analyse   # Larastan, level max
   composer format    # Pint (use `vendor/bin/pint --test` to only check)
   ```

5. Update `README.md` for any public API change and add an entry under `[Unreleased]` in `CHANGELOG.md`.
6. Open a pull request against `dev`.

## Guidelines

- Keep the public API small and fluent. Add new options to `PendingUpload` so single and batch uploads both get them.
- Every failure should throw a subclass of `StupImageException`, never return an error value.
- Never write through `Storage::path()`, so every disk keeps working.
- Tests must not touch real disks: use `Storage::fake()` or `StupImage::fake()`.
- Follow [Semantic Versioning](https://semver.org). Breaking changes must be documented in `UPGRADE.md`.

## Reporting bugs

Please use the bug report template and include your PHP, Laravel and package versions, the disk driver and a minimal reproduction.

Security issues must **not** be reported publicly. See [SECURITY.md](SECURITY.md).
