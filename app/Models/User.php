<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** The studio's operator (everything) vs a customer (their own videos and credits, at /conta). Not fillable. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** Videos bought and not yet used, across all their purchases. */
    public function creditos(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('compras')->where('user_id', $this->id)
            ->selectRaw('COALESCE(SUM(videos - videos_usados), 0) as n')->value('n');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
