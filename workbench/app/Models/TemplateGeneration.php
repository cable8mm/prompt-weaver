<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateGeneration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'brief_json' => 'array',
            'raw_config' => 'array',
            'config' => 'array',
        ];
    }
}
