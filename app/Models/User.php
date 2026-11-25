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

    /** New accounts are customers (the `admin` gate); never mass-assignable. */
    protected $attributes = ['is_admin' => false];

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

    /** Videos this customer can still make: unspent, unexpired, across purchases. */
    public function videosDisponiveis(): int
    {
        return (int) DB::table('compras')->where('user_id', $this->id)->where('tipo', 'videos')
            ->where('expira_em', '>', now())->sum('restantes');
    }

    /** The account an email signs in to: its own address, or one it shares the account with (Conta settings). */
    public static function porEmail(string $email): ?self
    {
        $email = strtolower(trim($email));

        return self::where('email', $email)->first()
            ?? self::whereIn('id', DB::table('user_emails')->where('email', $email)->select('user_id'))->first();
    }

    /** The other emails that sign in to this account. @return list<string> */
    public function emailsPartilhados(): array
    {
        return DB::table('user_emails')->where('user_id', $this->id)->orderBy('id')->pluck('email')->all();
    }

    /** A customer for whom Imoplanta costs nothing (config imoplanta.gratis): prices show 0 €, no payment. */
    public function imoplantaGratis(): bool
    {
        return in_array(strtolower((string) $this->email), (array) config('contentmachine.imoplanta.gratis'), true);
    }

    /** Imoplanta properties this customer can still start: unspent, unexpired credits. */
    /** Imoplanta is open, for now, only to the accounts listed in imoplanta.acesso. */
    public function veImoplanta(): bool
    {
        return in_array(strtolower($this->email), config('contentmachine.imoplanta.acesso'), true);
    }

    public function imoveisImoplantaDisponiveis(): int
    {
        return (int) DB::table('compras')->where('user_id', $this->id)->where('tipo', 'imoplanta')
            ->where('expira_em', '>', now())->sum('restantes');
    }
}
