<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

class SmartOltProfile extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope(installRows: true));
    }

    use Auditable;

    public const TYPES = ['onu_type', 'tcont', 'vlan', 'ip'];

    protected $table = 'smartolt_profiles';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['last_synced_at'];

    public function auditLabel(): string
    {
        return 'Profil';
    }

    public function auditTitle(): string
    {
        return trim(($this->profile_type ? $this->profile_type.' ' : '').(string) $this->name);
    }

    protected $fillable = [
        'snmp_olt_id',
        'profile_type',
        'name',
        'source',
        'vlan',
        'params',
        'notes',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'vlan' => 'integer',
            'params' => 'array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
