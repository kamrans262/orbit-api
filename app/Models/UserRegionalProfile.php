<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserRegionalProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['user_id', 'country_code', 'platform', 'app_version', 'locale'];

    protected function casts(): array
    {
        return ['user_id' => 'integer'];
    }
}
