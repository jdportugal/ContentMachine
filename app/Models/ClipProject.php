<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClipProject extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_TRANSCRIBING = 'transcribing';
    public const STATUS_PLANNING = 'planning';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public const TYPE_ANIMATION = 'animation';
    public const TYPE_OVERLAY = 'overlay';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'transcript' => 'array',
        'plan' => 'array',
        'meta' => 'array',
        'images' => 'array',
    ];

    public function isActive(): bool
    {
        return ! in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
