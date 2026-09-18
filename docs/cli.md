# CLI 사용법

Prompt Weaver의 standalone CLI는 fixture를 만들고 프롬프트, 이미지 보정, 미리보기, export를 실행할 때 사용합니다.

## standalone 설치

standalone으로 사용할 때는 Composer package로 설치하지 않고 저장소를 clone합니다.

```bash
git clone https://github.com/cable8mm/prompt-weaver.git
cd prompt-weaver
composer update
uv sync --locked
```

## 도움말

```bash
./weaver --help
./weaver --version
```

## 기본 작업 흐름

기본 흐름은 `init → pipe → calibrate → preview`입니다. `pipe`가 생성한 `image.prompt`로 이미지를 생성한 뒤 `image.png`로 저장하고 `calibrate`를 실행합니다. 미리보기를 확인한 뒤 `code → export`를 실행합니다.

### 1. 작업 폴더 만들기

```bash
./weaver init cafe-restaurant
```

레이아웃이나 카테고리를 직접 지정할 수도 있습니다.

```bash
./weaver init cafe-restaurant \
  --category="Office/Coworking" \
  --format="A4/A5 Poster" \
  --layout=editorial
```

기본 작업 폴더는 `.weaver/cafe-restaurant`입니다.

### 2. 전체 AI 파이프라인 실행

```bash
./weaver pipe cafe-restaurant
```

`pipe`는 다음 파일을 한 번에 생성합니다.

```text
brief.prompt
design-brief.json
config.prompt
raw.config.json
image.prompt
```

AI 설정은 Laravel AI 설정 또는 `PROMPT_WEAVER_PROVIDER`, `PROMPT_WEAVER_MODEL` 환경 변수를 사용합니다.

```bash
PROMPT_WEAVER_PROVIDER=openrouter \
PROMPT_WEAVER_MODEL=google/gemma-4-26b-a4b-it:free \
./weaver pipe cafe-restaurant
```

### 3. 생성 이미지 저장

`image.prompt`를 이미지 생성 모델에 전달하고, 생성된 이미지를 다음 경로에 저장합니다.

```text
.weaver/cafe-restaurant/image.png
```

### 4. 이미지 위치 보정

```bash
./weaver calibrate cafe-restaurant
```

최종 설정은 `config.json`으로 저장됩니다. QR 위치 보정에는 `uv`와 `opencv-python-headless`가 필요합니다.

### 5. 미리보기 만들기

```bash
./weaver preview cafe-restaurant
./weaver preview cafe-restaurant --output=preview.png
./weaver preview cafe-restaurant --output=preview.html
```

### 6. 미리보기 확인 후 코드 확정

```bash
./weaver code cafe-restaurant
```

### 7. 서비스용 결과 내보내기

```bash
./weaver export cafe-restaurant
```

export 결과는 `dist` 아래에 서비스가 import할 파일을 생성합니다.

```text
dist/cafe-restaurant/
├── config.json
├── image.png
├── image.prompt
└── preview.png
```

## 여러 fixture 만들기

여러 디자인을 만들 때는 각 fixture를 먼저 preview까지 확인합니다.

```bash
./weaver init a
./weaver pipe a
./weaver calibrate a
./weaver preview a

./weaver init b
./weaver pipe b
./weaver calibrate b
./weaver preview b

./weaver init c
./weaver pipe c
./weaver calibrate c
./weaver preview c

./weaver init d
./weaver pipe d
./weaver calibrate d
./weaver preview d
```

모든 preview가 정상인지 확인한 뒤 한 번에 code와 export를 실행합니다.

```bash
./weaver code-all
./weaver export-all
```

그러면 `dist` 폴더에 fixture별 4개 export 파일이 생성됩니다. 이 결과물을 Laravel 서비스에 import하여 실제 서비스의 템플릿 데이터로 등록합니다.

## 단계별 디버깅

`pipe` 전체 흐름에서 어느 단계가 실패했는지 확인해야 할 때는 다음 커맨드로 나누어 실행합니다.

```bash
./weaver brief cafe-restaurant
```

생성된 `brief.prompt`를 AI에 전달하고 응답을 `design-brief.json`으로 저장한 뒤 다음을 실행합니다.

```bash
./weaver config cafe-restaurant
```

생성된 `config.prompt`를 AI에 전달하고 응답을 `raw.config.json`으로 저장한 뒤 다음을 실행합니다.

```bash
./weaver image cafe-restaurant
```

각 단계에서 생성되는 prompt와 AI 응답을 확인하면 `pipe`의 브리프, 설정, 이미지 프롬프트 중 어느 단계에서 문제가 생겼는지 분리해서 진단할 수 있습니다. 이후에는 기본 흐름과 동일하게 `image.png`를 저장하고 `calibrate`, `preview`를 실행합니다.

## 기타 명령

```bash
./weaver config-stub editorial
./weaver code cafe-restaurant
./weaver code-all --dry-run
./weaver config:validate path/to/config.json
```

`config-stub`은 등록된 레이아웃을 기준으로 샘플 설정 프롬프트를 만들고, `config:validate`는 최종 설정 JSON을 검증합니다.

## Python 환경

standalone CLI에서 QR 보정을 사용하려면 `uv sync --locked`가 먼저 완료되어 있어야 합니다. Laravel 서비스에서는 직접 `uv sync`를 실행하지 말고 `php artisan prompt-weaver:install`을 사용합니다.
