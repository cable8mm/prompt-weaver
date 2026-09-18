# PHP API

대부분의 Laravel 서비스는 자체 Artisan command나 job에서 Prompt Weaver를 사용합니다. 이 문서는 패키지의 PHP API를 직접 호출해야 하는 경우를 위한 간단한 참고 문서입니다.

## Prompt builder

```php
use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\ImagePrompt;

$brief = new DesignBriefPrompt($category, $format);
$brief->build();
$briefPrompt = $brief->prompt();

$config = new ConfigPrompt($description, $colorDirection, $fontMood, Format::A45_POSTER);
$config->build();
$configPrompt = $config->prompt();

$image = new ImagePrompt($configJson);
$image->build();
$imagePrompt = $image->prompt();
```

각 prompt builder는 prompt text만 만들고 AI 요청은 실행하지 않습니다. AI 실행은 `Pipe`와 Laravel AI 설정이 담당합니다.

## Pipe

```php
use Cable8mm\PromptWeaver\Pipe;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;

$result = app(Pipe::class)->run(
    Category::CAFE_RESTAURANT,
    Format::A45_POSTER,
);
```

서비스에서 직접 사용할 때에도 최종 설정은 패키지의 validator와 동일한 계약을 따라야 합니다.

## 유틸리티 API

```php
use Cable8mm\PromptWeaver\Support\FontPath;
use Cable8mm\PromptWeaver\Tools\Code;

$code = (new Code)->deriveFromTheme($theme);
$ttf = FontPath::outputRegular();
$woff2 = FontPath::webRegular();
```

## 권장 사용 위치

서비스의 Controller에서 직접 긴 파이프라인을 실행하기보다는 Artisan command, queue job, application service 안에서 실행하고 실패를 서비스의 도메인 오류로 변환하는 것을 권장합니다.
