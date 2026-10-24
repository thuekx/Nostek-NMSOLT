<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UI-F1: perusahaan pemilik OLT (ISP pelanggan, reseller, atau demo). Isolasi antar-organisasi
 * ditegakkan {@see Scopes\OrganizationScope}; organisasi lain = 404, bukan 403.
 *
 * Organisasi ber-`is_installation` = organisasi instalasi ({@see OrganizationContext::primaryId()}).
 */
class Organization extends Model
{
    use HasUuids;

    public const KIND_ISP = 'isp';

    public const KIND_RESELLER = 'reseller';

    public const KIND_DEMO = 'demo';

    public const ROLE_ORG_ADMIN = 'org_admin';

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_TECHNICIAN = 'technician';

    public const ROLE_VIEWER = 'viewer';

    protected $fillable = ['slug', 'name', 'kind', 'cc_org_id', 'status'];

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<SnmpOlt, $this>
     */
    public function olts(): HasMany
    {
        return $this->hasMany(SnmpOlt::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** Peran organisasi awal dari role aplikasi (dipakai backfill dan user baru). */
    public static function roleFor(?UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => self::ROLE_ORG_ADMIN,
            UserRole::Demo => self::ROLE_VIEWER,
            default => self::ROLE_OPERATOR,
        };
    }
}
