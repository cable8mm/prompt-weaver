# Docker와 Coolify 배포

이 문서는 Prompt Weaver를 사용하는 Laravel 서비스를 Coolify의 Dockerfile 방식으로 배포할 때 필요한 부분만 설명합니다.

## 서비스 책임

Prompt Weaver 패키지는 `pyproject.toml`, `uv.lock`, Python script와 `prompt-weaver:install`, `prompt-weaver:doctor`를 제공합니다. 다음은 Laravel 서비스의 책임입니다.

- Docker 이미지에 `uv` 설치
- Python venv와 cache 경로 지정
- 이미지 빌드 중 `prompt-weaver:install` 실행
- 이미지 빌드 중 `prompt-weaver:doctor` 실행
- PHP-FPM과 queue worker의 파일 권한 설정

## Coolify 환경 변수

Coolify 애플리케이션의 Environment Variables에 다음을 설정합니다.

```dotenv
UV_PROJECT_ENVIRONMENT=/opt/prompt-weaver/venv
PROMPT_WEAVER_UV_CACHE_DIR=/var/cache/prompt-weaver/uv
```

## Dockerfile 예시

서비스의 PHP base image와 runtime user에 맞게 조정해야 합니다.

```dockerfile
RUN curl -LsSf https://astral.sh/uv/install.sh | sh \
    && install -m 0755 /root/.local/bin/uv /usr/local/bin/uv

ENV UV_PROJECT_ENVIRONMENT=/opt/prompt-weaver/venv
ENV PROMPT_WEAVER_UV_CACHE_DIR=/var/cache/prompt-weaver/uv

RUN mkdir -p /opt/prompt-weaver/venv /var/cache/prompt-weaver/uv

RUN composer install --no-dev --prefer-dist --optimize-autoloader
RUN php artisan prompt-weaver:install --no-interaction
RUN php artisan prompt-weaver:doctor

# 컨테이너가 www-data로 실행되는 경우에만 사용합니다.
RUN chown -R www-data:www-data \
    /opt/prompt-weaver /var/cache/prompt-weaver
```

`www-data`는 예시일 뿐입니다. Dockerfile의 `USER`, PHP-FPM 설정, queue-worker 설정이 실제 실행 사용자를 결정합니다. 다른 사용자로 실행한다면 해당 사용자 또는 numeric UID/GID로 `chown`해야 합니다.

## Persistent Storage

venv와 cache는 빌드 시 이미지에 준비되므로 일반적으로 Coolify Persistent Storage mount가 필요하지 않습니다. 경로를 mount하기로 했다면 mount의 Destination Path가 환경 변수의 경로와 정확히 일치해야 합니다.

또한 컨테이너 실행 사용자가 다음 경로를 읽고 쓸 수 있어야 합니다.

```text
/opt/prompt-weaver/venv
/var/cache/prompt-weaver/uv
```

컨테이너 내부에만 기록한 파일은 배포로 컨테이너가 교체될 때 보존되지 않습니다. Coolify의 Persistent Storage는 애플리케이션이 실제로 쓰는 경로에만 연결해야 합니다.

## 배포 후 확인

Coolify 배포 로그에서 Python 환경 설치 단계를 확인하고, 문제가 있으면 배포된 컨테이너에서 다음 명령을 실행합니다.

```bash
php artisan prompt-weaver:doctor
```

`uv`가 없거나 OpenCV를 import하지 못하거나 권한이 부족하면 `doctor`가 실패합니다.

자세한 Coolify 설정은 [Dockerfile 배포 문서](https://coolify.io/docs/applications/builds/dockerfile)와 [Persistent Storage 문서](https://coolify.io/docs/applications/configuration/persistent-storage)를 참고하세요.
