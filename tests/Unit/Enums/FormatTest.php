<?php

use Cable8mm\PromptWeaver\Enums\Format;

it('translates all format labels to Korean', function () {
    app()->setLocale('ko');

    foreach (Format::cases() as $case) {
        $label = $case->label();

        // 번역된 값이 원본 값과 달라야 합니다 (한글로 번역되었는지 확인)
        expect($label)->not->toBe($case->value);
        // 번역된 값이 비어있지 않아야 합니다
        expect($label)->not->toBeEmpty();
    }
});

it('returns original values when locale is not Korean', function () {
    app()->setLocale('en');

    foreach (Format::cases() as $case) {
        expect($case->label())->toBe($case->value);
    }
});

it('has Korean translations for all format options', function () {
    app()->setLocale('ko');

    $translations = json_decode((string) file_get_contents(__DIR__.'/../../../lang/ko.json'), true);

    foreach (Format::cases() as $case) {
        expect($translations)->toHaveKey($case->value);
        expect($case->label())->toBe($translations[$case->value]);
    }
});
