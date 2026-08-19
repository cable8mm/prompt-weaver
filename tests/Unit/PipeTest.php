<?php

use Cable8mm\NanoAI\Client;
use Cable8mm\NanoAI\Http\HttpClientInterface;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;

/**
 * Fake HTTP client that returns canned responses for each call.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int, string> */
    private array $responses;

    private int $callIndex = 0;

    /**
     * @param  array<int, string>  $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function post(string $url, array $headers, array $payload): array
    {
        $response = $this->responses[$this->callIndex] ?? '{}';
        $this->callIndex++;

        return [200, json_encode([
            'choices' => [
                ['message' => ['content' => $response]],
            ],
        ])];
    }

    public function callCount(): int
    {
        return $this->callIndex;
    }
}

it('runs the full three-step pipeline and returns all prompts', function () {
    $briefJson = json_encode([
        'name' => '따뜻한 카페',
        'description' => 'A warm cafe Wi-Fi sign with cream and brown tones.',
        'color_direction' => 'warm brown and cream',
        'font_mood' => 'rounded sans-serif',
    ]);

    $configJson = json_encode([
        'canvas' => ['width_pc' => 100, 'height_pc' => 100, 'aspect_ratio' => '3:4'],
        'style' => [
            'theme' => 'warm cafe',
            'background' => 'cream paper',
            'print_target' => 'black-and-white laser printer safe',
        ],
        'content' => [
            'title' => ['text' => '와이파이 연결', 'x_pc' => 50, 'y_pc' => 10, 'align' => 'center', 'style' => 'bold'],
            'wifi_icon' => ['x_pc' => 50, 'y_pc' => 20, 'width_pc' => 15, 'style' => 'simple icon'],
            'message' => ['text' => '스캔하여 연결하세요.', 'x_pc' => 50, 'y_pc' => 62, 'align' => 'center'],
            'footer' => ['text' => '제작: WIFI NOTE', 'x_pc' => 50, 'y_pc' => 96, 'align' => 'center'],
        ],
        'placeholders' => [
            'ssid' => [
                'box_x_pc' => 50, 'box_y_pc' => 40, 'box_width_pc' => 70, 'box_height_pc' => 8,
                'label' => 'SSID:', 'label_position' => 'outside_above',
                'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid flat white cutout',
                'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111',
            ],
            'password' => [
                'box_x_pc' => 50, 'box_y_pc' => 52, 'box_width_pc' => 70, 'box_height_pc' => 8,
                'label' => 'PASSWORD:', 'label_position' => 'outside_above',
                'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid flat white cutout',
                'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111',
            ],
            'qr' => ['x_pc' => 50, 'y_pc' => 80, 'width_pc' => 28, 'style' => 'clean square'],
        ],
    ]);

    $httpClient = new FakeHttpClient([$briefJson, $configJson]);

    $client = new Client(
        provider: 'openai',
        apiKey: 'sk-test',
        httpClient: $httpClient,
    );

    $pipe = new Pipe($client);
    $result = $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
    );

    // Should have made exactly 2 API calls (brief + config)
    expect($httpClient->callCount())->toBe(2);

    // Brief prompt should contain expected markers
    expect($result->briefPrompt)
        ->toContain('[Role]')
        ->toContain('You are a creative director for a Wi-Fi signage template');

    // Brief JSON should be parsed correctly
    expect($result->briefJson['description'])->toBe('A warm cafe Wi-Fi sign with cream and brown tones.');

    // Config prompt should contain the design brief
    expect($result->configPrompt)
        ->toContain('[Role]')
        ->toContain('A warm cafe Wi-Fi sign with cream and brown tones.');

    // Config should be the parsed JSON
    expect($result->config['style']['theme'])->toBe('warm cafe');

    // Image prompt should be the final output
    expect($result->imagePrompt)
        ->toContain('[Task] Generate a high-contrast Wi-Fi signage image ONLY')
        ->toContain('와이파이 연결')
        ->toContain('SSID:')
        ->toContain('PASSWORD:');
});

it('strips markdown code fences from model responses', function () {
    $briefJson = "```json\n".json_encode([
        'name' => '테스트',
        'description' => 'A test design brief.',
        'color_direction' => 'test colors',
        'font_mood' => 'test font',
    ])."\n```";

    $configJson = "```json\n".json_encode([
        'canvas' => ['width_pc' => 100, 'height_pc' => 100, 'aspect_ratio' => '3:4'],
        'style' => ['theme' => 'test', 'background' => 'test bg', 'print_target' => 'black-and-white laser printer safe'],
        'content' => [
            'title' => ['text' => '와이파이 연결', 'x_pc' => 50, 'y_pc' => 10, 'align' => 'center', 'style' => 'bold'],
            'wifi_icon' => ['x_pc' => 50, 'y_pc' => 20, 'width_pc' => 15, 'style' => 'icon'],
            'message' => ['text' => '스캔하여 연결하세요.', 'x_pc' => 50, 'y_pc' => 62, 'align' => 'center'],
            'footer' => ['text' => '제작: WIFI NOTE', 'x_pc' => 50, 'y_pc' => 96, 'align' => 'center'],
        ],
        'placeholders' => [
            'ssid' => ['box_x_pc' => 50, 'box_y_pc' => 40, 'box_width_pc' => 70, 'box_height_pc' => 8, 'label' => 'SSID:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid', 'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111'],
            'password' => ['box_x_pc' => 50, 'box_y_pc' => 52, 'box_width_pc' => 70, 'box_height_pc' => 8, 'label' => 'PASSWORD:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid', 'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111'],
            'qr' => ['x_pc' => 50, 'y_pc' => 80, 'width_pc' => 28, 'style' => 'clean'],
        ],
    ])."\n```";

    $httpClient = new FakeHttpClient([$briefJson, $configJson]);

    $client = new Client(
        provider: 'openai',
        apiKey: 'sk-test',
        httpClient: $httpClient,
    );

    $pipe = new Pipe($client);
    $result = $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
    );

    expect($result->briefJson['description'])->toBe('A test design brief.');
    expect($result->config['style']['theme'])->toBe('test');
});

it('throws when the design brief response is missing the description field', function () {
    $briefJson = json_encode(['name' => 'test']); // missing description

    $httpClient = new FakeHttpClient([$briefJson]);

    $client = new Client(
        provider: 'openai',
        apiKey: 'sk-test',
        httpClient: $httpClient,
    );

    $pipe = new Pipe($client);

    $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
    );
})->throws(RuntimeException::class, 'Design brief response missing "description" field.');

it('passes the color option to DesignBriefPrompt', function () {
    $briefJson = json_encode([
        'name' => '색칠',
        'description' => 'A colorful design brief.',
        'color_direction' => 'vibrant colors',
        'font_mood' => 'bold font',
    ]);

    $configJson = json_encode([
        'canvas' => ['width_pc' => 100, 'height_pc' => 100, 'aspect_ratio' => '3:4'],
        'style' => ['theme' => 'colorful', 'background' => 'rainbow', 'print_target' => 'black-and-white laser printer safe'],
        'content' => [
            'title' => ['text' => '와이파이 연결', 'x_pc' => 50, 'y_pc' => 10, 'align' => 'center', 'style' => 'bold'],
            'wifi_icon' => ['x_pc' => 50, 'y_pc' => 20, 'width_pc' => 15, 'style' => 'icon'],
            'message' => ['text' => '스캔하여 연결하세요.', 'x_pc' => 50, 'y_pc' => 62, 'align' => 'center'],
            'footer' => ['text' => '제작: WIFI NOTE', 'x_pc' => 50, 'y_pc' => 96, 'align' => 'center'],
        ],
        'placeholders' => [
            'ssid' => ['box_x_pc' => 50, 'box_y_pc' => 40, 'box_width_pc' => 70, 'box_height_pc' => 8, 'label' => 'SSID:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid', 'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111'],
            'password' => ['box_x_pc' => 50, 'box_y_pc' => 52, 'box_width_pc' => 70, 'box_height_pc' => 8, 'label' => 'PASSWORD:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid', 'align' => 'center', 'font_family' => 'Pretendard', 'font_size_px' => 36, 'font_weight' => 'bold', 'color' => '#111111'],
            'qr' => ['x_pc' => 50, 'y_pc' => 80, 'width_pc' => 28, 'style' => 'clean'],
        ],
    ]);

    $httpClient = new FakeHttpClient([$briefJson, $configJson]);

    $client = new Client(
        provider: 'openai',
        apiKey: 'sk-test',
        httpClient: $httpClient,
    );

    $pipe = new Pipe($client);
    $result = $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
        color: 'ocean blue and coral',
    );

    expect($result->briefPrompt)->toContain('use a color scheme based on ocean blue and coral');
    expect($result->briefPrompt)->not->toContain('assume high-contrast monochrome/greyscale-safe design');
});
