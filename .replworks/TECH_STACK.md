# Runtime

- PHP MUST be `^8.3`.
- Python MUST be `>=3.9`.
- The PHP package MUST support Laravel `^12.0` and `^13.0` in its development workbench.
- Symfony Console MUST satisfy `^7.4` or `^8.1`.

# PHP Dependencies

- PHP dependencies MUST be managed with Composer.
- `composer.lock` MUST remain consistent with `composer.json`.
- Required PHP extensions MUST include cURL, GD, and ZIP.
- The package MUST use the dependency constraints declared in `composer.json`.

# Python Dependencies

- Python dependencies MUST be managed with uv.
- Python dependency versions MUST satisfy the constraints declared in `pyproject.toml`.
- OpenCV MUST use the `opencv-python-headless` package with version `>=4.10,<5`.
- The committed uv lockfile MUST be honored when installing dependencies.

# Development Checks

- PHP unit tests MUST run with Pest through `composer test`.
- Python QR-calibration tests MUST pass with `uv run python scripts/test_calibrate_qr.py`.
- PHP formatting MUST follow Laravel Pint.
- PHP formatting verification MUST pass with `composer inspect`.
- Composer metadata and lockfile validation MUST pass with `composer validate`.
- Python dependencies MUST install from the committed lockfile with `uv sync --locked`.
