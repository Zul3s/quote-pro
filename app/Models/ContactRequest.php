<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Deadline;
use App\Enums\RequestType;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Active Record entity. UUID identity is assigned at creation by the native
 * HasUuids trait (UUIDv7 here), targeting the `uuid` column rather than the
 * auto-increment primary key (see uniqueIds()).
 */
#[UseFactory(ContactRequestFactory::class)]
final class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory, HasUuids;

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

    protected static function newFactory(): Factory
    {
        return ContactRequestFactory::new();
    }
}
