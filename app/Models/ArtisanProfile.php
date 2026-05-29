<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton Active Record entity: the application is mono-artisan, so this table
 * holds a single row. UUID identity is assigned at creation by the native
 * HasUuids trait (UUIDv7 here), targeting the `uuid` column rather than the
 * auto-increment primary key (see uniqueIds()).
 */
final class ArtisanProfile extends Model
{
    use HasUuids;

    protected $table = 'artisan_profiles';

    protected $fillable = [
        'uuid',
        'postal_code',
        'professions',
        'services',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'professions' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
