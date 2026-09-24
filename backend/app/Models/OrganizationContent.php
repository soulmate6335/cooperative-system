<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The single active homepage configuration (hero copy, optional hero image and
 * the organization introduction). A partial unique index on is_active guards
 * against two active configurations coexisting.
 */
class OrganizationContent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'organization_content';

    protected $fillable = [
        'hero_title', 'hero_description', 'hero_image_path',
        'introduction', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->latest('updated_at')->first();
    }
}
