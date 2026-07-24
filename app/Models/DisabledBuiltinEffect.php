<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A built-in primitive the user has disallowed for the AI planner. Presence of
 * a row = disallowed; absence = allowed (the default). Built-ins can't be
 * deleted (they're core primitives), so this is how they get switched off.
 */
class DisabledBuiltinEffect extends Model
{
    protected $guarded = [];
}
