<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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

    /** The account an email signs in to: its own address, or one it shares the account with (Definições da conta). */
    public static function porEmail(string $email): ?self
    {
        $email = strtolower(trim($email));

        return self::where('email', $email)->first()
            ?? self::whereIn('id', \Illuminate\Support\Facades\DB::table('user_emails')->where('email', $email)->select('user_id'))->first();
    }

    /** The other emails that sign in to this account. @return list<string> */
    public function emailsPartilhados(): array
    {
        return \Illuminate\Support\Facades\DB::table('user_emails')->where('user_id', $this->id)->orderBy('id')->pluck('email')->all();
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
            'agente' => 'array',
        ];
    }

    /** A customer for whom Imoplanta costs nothing (config imoplanta.gratis): prices show 0 €, no payment. */
    public function imoplantaGratis(): bool
    {
        return in_array(strtolower((string) $this->email), (array) config('contentmachine.imoplanta.gratis'), true);
    }

    /** Imoplanta is open, for now, only to the accounts listed in imoplanta.acesso. */
    public function veImoplanta(): bool
    {
        return in_array(strtolower((string) $this->email), (array) config('contentmachine.imoplanta.acesso'), true);
    }

    /** Imoplanta properties bought and not yet started, across their purchases (`compras.imoveis`). */
    public function imoveisImoplantaDisponiveis(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('compras')->where('user_id', $this->id)
            ->selectRaw('COALESCE(SUM(imoveis - imoveis_usados), 0) as n')->value('n');
    }
}
