<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Deadline;
use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Active Record entity. UUID identity is assigned at creation by the native
 * HasUuids trait (UUIDv7 here), targeting the `uuid` column rather than the
 * auto-increment primary key (see uniqueIds()).
 */
final class ContactRequest extends Model
{
    use HasUuids;

    protected $table = 'contact_requests';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'request_type',
        'deadline',
        'postal_code',
        'description',
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
            'request_type' => RequestType::class,
            'deadline' => Deadline::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
