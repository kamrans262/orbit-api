<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessagingPreference extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = ['user_id', 'read_receipts_enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_receipts_enabled' => 'boolean'];
    }
}
