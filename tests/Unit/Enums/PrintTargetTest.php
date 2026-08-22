<?php

use Cable8mm\PromptWeaver\Enums\PrintTarget;

it('translates all print target labels to Korean', function () {
    app()->setLocale('ko');

    foreach (PrintTarget::cases() as $case) {
        expect($case->label())->not->toBe($case->value)->not->toBeEmpty();
    }
});

it('returns original print target values outside Korean locale', function () {
    app()->setLocale('en');

    foreach (PrintTarget::cases() as $case) {
        expect($case->label())->toBe($case->value);
    }
});

it('exposes translated print target options', function () {
    app()->setLocale('ko');

    expect(PrintTarget::options())->toBe([
        'black-and-white laser printer safe' => '흑백 레이저 프린터용',
        'full-color inkjet printer' => '컬러 잉크젯 프린터용',
        'RGB digital display' => 'RGB 디지털 디스플레이용',
    ]);
});
