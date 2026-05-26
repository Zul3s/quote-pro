<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity;

use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ContactRequest extends Model implements ContactRequestInterface
{
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

    protected static function booted(): void
    {
        self::creating(function (self $contactRequest): void {
            if (empty($contactRequest->getAttribute('uuid'))) {
                $contactRequest->setAttribute('uuid', Uuid::uuid7()->toString());
            }
        });
    }

    public function getUuid(): UuidInterface
    {
        return Uuid::fromString($this->getAttribute('uuid'));
    }

    public function getName(): string
    {
        return (string) $this->getAttribute('name');
    }

    public function getEmail(): string
    {
        return (string) $this->getAttribute('email');
    }

    public function getPhone(): ?string
    {
        return $this->getAttribute('phone');
    }

    public function getRequestType(): RequestType
    {
        return $this->getAttribute('request_type');
    }

    public function getDeadline(): Deadline
    {
        return $this->getAttribute('deadline');
    }

    public function getPostalCode(): ?string
    {
        return $this->getAttribute('postal_code');
    }

    public function getDescription(): string
    {
        return (string) $this->getAttribute('description');
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        $value = $this->getAttribute('created_at');

        if (! $value instanceof DateTimeImmutable) {
            throw new \RuntimeException('ContactRequest::created_at must be persisted before access.');
        }

        return $value;
    }
}
