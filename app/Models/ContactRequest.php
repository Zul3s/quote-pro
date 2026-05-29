<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Deadline;
use App\Enums\LeadQuality;
use App\Enums\Priority;
use App\Enums\Relevance;
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
        'missing_info_acknowledged',
        'relevance',
        'project_type',
        'summary',
        'estimated_amount_min',
        'estimated_amount_max',
        'lead_quality',
        'priority',
        'qualified_at',
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
            'missing_info_acknowledged' => 'boolean',
            'relevance' => Relevance::class,
            'estimated_amount_min' => 'integer',
            'estimated_amount_max' => 'integer',
            'lead_quality' => LeadQuality::class,
            'priority' => Priority::class,
            'qualified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ContactRequestFactory::new();
    }
}
