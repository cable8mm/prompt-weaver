# Laravel 서비스 통합

Prompt Weaver를 사용하는 Laravel 서비스는 패키지의 PHP 기능을 직접 호출하거나, 서비스가 제공하는 Artisan command/job 안에서 패키지 기능을 호출합니다.

## 설치

```bash
composer require cable8mm/prompt-weaver
```

설정 파일을 서비스로 복사하려면 다음을 실행합니다.

```bash
php artisan vendor:publish --tag=prompt-weaver-config
```

## Python 환경 준비

QR calibration을 사용하는 서비스는 `uv`를 먼저 설치해야 합니다. `uv`는 패키지에 포함되지 않으므로 Dockerfile 또는 서버 이미지에서 서비스가 설치해야 합니다.

`uv` 설치 후 다음 명령을 실행합니다.

```bash
php artisan prompt-weaver:install
php artisan prompt-weaver:doctor
```

`install`은 패키지 내부의 `pyproject.toml`과 `uv.lock`을 기준으로 Python 환경을 설치합니다. `doctor`는 `uv`와 OpenCV를 실제 Laravel 실행 환경에서 확인합니다.

## 환경 변수

```dotenv
UV_PROJECT_ENVIRONMENT=/opt/prompt-weaver/venv
PROMPT_WEAVER_UV_CACHE_DIR=/var/cache/prompt-weaver/uv
```

PHP-FPM과 queue worker가 동일한 값을 사용할 수 있어야 합니다. 실행 사용자는 venv를 읽고 실행할 수 있어야 하며, uv cache에는 쓸 수 있어야 합니다.

다른 `uv` 실행 파일이나 Python interpreter를 사용하려면 다음 변수를 사용합니다.

```dotenv
PROMPT_WEAVER_UV=/usr/local/bin/uv
PROMPT_WEAVER_PYTHON=/opt/prompt-weaver/venv/bin/python
```

## 서비스 command/job에서 사용하기

패키지는 서비스의 도메인 command를 만들지 않습니다. 예를 들어 서비스가 fixture 보정과 미리보기를 제공한다면 서비스 쪽에서 다음과 같은 command를 만듭니다.

```bash
php artisan wifi:calibrate cafe-restaurant
php artisan wifi:preview cafe-restaurant
```

보정 실패 시 후속 미리보기나 export를 실행하지 않도록 서비스 command가 실패를 전파해야 합니다.

## 브라우저 폰트

Vite를 사용하는 서비스는 `resources/css/app.css`에 다음을 추가합니다.

```css
@import "../../vendor/cable8mm/prompt-weaver/resources/css/prompt-weaver.css";
```

```blade
<span class="prompt-weaver-font">{{ $ssid }}</span>
<span class="prompt-weaver-font">{{ $password }}</span>
```

서비스가 패키지 폰트의 절대 경로를 필요로 하는 경우에는 `Cable8mm\PromptWeaver\Support\FontPath`를 사용합니다.

## 참고 문서

- [PHP API](php-api.md)
- [Docker와 Coolify 배포](coolify.md)
