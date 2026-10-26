<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApiEndpoint extends Model {
    protected $table = 'api_endpoints';
    protected $fillable = ['url', 'desc', 'group', 'http_method', 'default_payload', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($q){ return $q->where('is_active', true); }
    public function scopeGroup($q, $grp){ return $q->where('group', $grp); }

    // Helper to auto-categorize if group not specified
    public static function detectGroup(string $url, ?string $desc = ''): string {
        $u = strtolower($url);
        $d = strtolower($desc ?? '');
        if (str_contains($u, 'soyal') || str_contains($d, 'soyal')) return 'soyal';
        if (str_contains($u, 'sztimmy') || str_contains($d, 'tasoft')) return 'tasoft';
        if (str_contains($u, 'dreampos') || str_contains($d, 'dreampos') || str_contains($u, ':8080')) return 'dreampos';
        return 'device';
    }
}
