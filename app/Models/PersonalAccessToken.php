<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;

class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name', 'token', 'abilities', 'expires_at', 'last_used_at',
        'tokenable_id', 'tokenable_type',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'abilities'    => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /* ================= FACTORY ================= */
    /**
     * Buat token baru untuk user. Mengembalikan [model, plainTextToken].
     * Plain text hanya muncul SEKALI saat dibuat (seperti GitHub PAT).
     */
    public static function createForUser(User $user, string $name, array $abilities = ['*'], ?string $expiresAt = null): array
    {
        $plain = self::generatePlainToken();
        $row = static::create([
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
            'name'           => $name,
            'token'          => hash('sha256', $plain),
            'abilities'      => $abilities,
            'expires_at'     => $expiresAt,
        ]);
        return [$row, $plain];
    }

    /**
     * Cari token (dari plain text), validasi expiry & update last_used_at.
     * @return static|null
     */
    public static function findValidToken(string $plainToken): ?static
    {
        if (empty($plainToken)) return null;

        $hash = hash('sha256', $plainToken);
        $token = static::where('token', $hash)->first();

        if (!$token) return null;
        if ($token->expires_at && now()->gt($token->expires_at)) return null;

        // update last_used_at (tanpa memicu events/updated_at yg ribet)
        $token->forceFill(['last_used_at' => now()])->save();

        return $token;
    }

    /**
     * Cek apakah token punya ability tertentu.
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];
        if (in_array('*', $abilities, true)) return true;
        return in_array($ability, $abilities, true);
    }

    /* ================= HELPER ================= */
    protected static function generatePlainToken(): string
    {
        return 'ids_' . bin2hex(random_bytes(32)); // ids_<64 hex>
    }
}
