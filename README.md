# Prompt Weaver

[![code-style](https://github.com/cable8mm/prompt-weaver/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/prompt-weaver/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/prompt-weaver/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/prompt-weaver/actions/workflows/run-tests.yml)
![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/prompt-weaver/php)
![Packagist Version](https://img.shields.io/packagist/v/cable8mm/prompt-weaver)
![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/prompt-weaver)
![Packagist License](https://img.shields.io/packagist/l/cable8mm/prompt-weaver)

Prompt Weaver는 Wi-Fi signage 제작을 위한 프롬프트와 이미지 결과를 생성·보정·내보내는 PHP/Laravel 패키지입니다.

주요 기능은 다음과 같습니다.

- 디자인 브리프 프롬프트 생성
- JSON 설정 프롬프트 생성
- 이미지 생성 프롬프트 생성
- 생성 이미지의 텍스트·QR 위치 보정
- PNG, HTML, 최종 설정 내보내기

## 요구사항

- PHP 8.3 이상
- Composer 2.x
- GD PHP extension
- QR calibration을 사용할 경우 `uv`와 OpenCV 실행 환경

## 설치

```bash
composer require cable8mm/prompt-weaver
```

Laravel package discovery가 service provider를 자동으로 등록합니다. 설정을 서비스의 `config/prompt-weaver.php`로 복사하려면 다음을 실행합니다.

```bash
php artisan vendor:publish --tag=prompt-weaver-config
```

## 어떤 방식으로 사용하는가

### standalone CLI

standalone으로 사용할 때는 저장소를 clone한 뒤 Composer 의존성을 설치합니다.

```bash
git clone https://github.com/cable8mm/prompt-weaver.git
cd prompt-weaver
composer update
uv sync --locked
```

기본 CLI 흐름은 `init → pipe → calibrate → preview`입니다. 미리보기를 확인한 뒤 `code → export`를 실행합니다.

```bash
./weaver init cafe-restaurant
./weaver pipe cafe-restaurant
./weaver calibrate cafe-restaurant
./weaver preview cafe-restaurant
./weaver code cafe-restaurant
./weaver export cafe-restaurant
```

`pipe` 대신 `brief`, `config`, `image`를 단계별로 실행하면 각 AI 단계의 입력과 결과를 확인하면서 디버깅할 수 있습니다. 자세한 명령 순서는 [CLI 사용법](docs/cli.md)을 참고하세요.

### Laravel 서비스

Laravel 서비스에서는 Python 환경을 먼저 준비한 뒤 서비스의 Artisan command나 job에서 패키지를 사용합니다.

```bash
php artisan prompt-weaver:install
php artisan prompt-weaver:doctor
```

서비스에서 실행할 calibration command와 도메인 workflow는 패키지가 대신 만들지 않습니다. Laravel 통합 방법은 [Laravel 서비스 통합](docs/laravel.md)을 참고하세요.

### Docker와 Coolify

Docker 또는 Coolify 배포에서는 서비스 이미지에 `uv`를 설치하고, 이미지 빌드 중 위 Artisan 명령을 실행해야 합니다. 첫 웹 요청에서 Python 환경을 설치하면 안 됩니다.

자세한 내용은 [Docker와 Coolify 배포](docs/coolify.md)를 참고하세요.

## 문서

- [CLI 사용법](docs/cli.md)
- [Laravel 서비스 통합](docs/laravel.md)
- [Docker와 Coolify 배포](docs/coolify.md)
- [PHP API](docs/php-api.md)

## 패키지 개발

패키지 자체를 수정하는 경우 다음과 같이 개발 의존성을 설치합니다.

```bash
git clone https://github.com/cable8mm/prompt-weaver.git
cd prompt-weaver
composer install
uv sync --locked
```

PHP 테스트와 정적 검사는 다음 명령으로 실행합니다.

```bash
composer test
composer lint
```

Python detector 테스트는 다음과 같습니다.

```bash
uv run --locked python scripts/test_calibrate_qr.py
```

실제 AI provider를 사용하는 E2E 테스트는 명시적으로 활성화해야 합니다.

```bash
composer test:e2e
```

패키지의 CLI 명령을 변경하면 [CLI 사용법](docs/cli.md)과 테스트를 함께 수정합니다. Laravel 통합 명령을 변경하면 [Laravel 서비스 통합](docs/laravel.md)과 [Docker/Coolify 배포](docs/coolify.md)를 함께 확인합니다. `pyproject.toml` 또는 `uv.lock`을 변경하면 Python 환경 설치와 Docker 배포 예시도 함께 확인합니다.

서비스 고유의 command 이름은 패키지에 추가하지 않고 소비 서비스에서 정의합니다.

## 라이선스

MIT
