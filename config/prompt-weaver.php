<?php

return [
    'uv' => [
        'binary' => env('PROMPT_WEAVER_UV', 'uv'),
        'cache_dir' => env(
            'PROMPT_WEAVER_UV_CACHE_DIR',
            function_exists('storage_path')
                ? storage_path('framework/cache/prompt-weaver/uv')
                : sys_get_temp_dir().'/prompt-weaver-uv'
        ),
    ],

    'ai' => [
        'provider' => env('PROMPT_WEAVER_PROVIDER'),
        'model' => env('PROMPT_WEAVER_MODEL'),
        'image_provider' => env('PROMPT_WEAVER_IMAGE_PROVIDER'),
        'image_model' => env('PROMPT_WEAVER_IMAGE_MODEL'),
    ],
];
