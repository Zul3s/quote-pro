<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity;

use App\Domain\Entity\UserInterface;
use Database\Factories\UserFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Eloquent implementation of the Domain UserInterface.
 *
 * Lives in Infrastructure — never imported from Domain or Application.
 * Auth still works via Authenticatable; soft-deletes track logical deletion.
 */
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements UserInterface
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    protected $table = 'users';

    protected $fillable = ['uuid', 'email', 'first_name', 'last_name', 'password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->getAttribute('uuid'))) {
                $user->setAttribute('uuid', Uuid::uuid7()->toString());
            }
        });
    }

    public function getUuid(): UuidInterface
    {
        return Uuid::fromString($this->getAttribute('uuid'));
    }

    public function getEmail(): string
    {
        return (string) $this->getAttribute('email');
    }

    public function setEmail(string $email): static
    {
        $this->setAttribute('email', $email);

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->getAttribute('first_name');
    }

    public function setFirstName(?string $firstName): static
    {
        $this->setAttribute('first_name', $firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->getAttribute('last_name');
    }

    public function setLastName(?string $lastName): static
    {
        $this->setAttribute('last_name', $lastName);

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        $value = $this->getAttribute('deleted_at');

        return $value instanceof DateTimeImmutable ? $value : null;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->setAttribute('deleted_at', $deletedAt);

        return $this;
    }
}
