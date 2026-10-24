<?php

namespace App\Models\Scopes;

use App\Models\SnmpOlt;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * UI-F1a: isolasi antar-organisasi. Dipasang pada {@see SnmpOlt} (kolom `organization_id`) dan
 * pada setiap model ber-`snmp_olt_id` (organisasi ikut OLT-nya). Digabung AND dengan
 * {@see DemoScope} dan {@see PartnerOltScope}.
 *
 * Organisasi lain tidak pernah terlihat: route model binding menghasilkan 404, bukan 403.
 * Baris tanpa OLT (`snmp_olt_id` NULL, mis. profil global, setelan device write global) adalah
 * milik instalasi: hanya terlihat oleh organisasi instalasi ({@see $installRows}).
 *
 * Lapis kedua (PostgreSQL RLS) menyusul di F1d, sebelum launching (keputusan owner D3).
 */
class OrganizationScope implements Scope
{
    public function __construct(private readonly bool $installRows = false) {}

    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);

        if (! $context->restricted()) {
            return;
        }

        $organizationId = $context->currentId();
        $table = $model->getTable();

        if ($organizationId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($model instanceof SnmpOlt) {
            $builder->where($table.'.organization_id', $organizationId);

            return;
        }

        $olts = SnmpOlt::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->select('id');

        if ($this->installRows && $context->isPrimary($organizationId)) {
            $builder->where(fn (Builder $q) => $q
                ->whereIn($table.'.snmp_olt_id', $olts)
                ->orWhereNull($table.'.snmp_olt_id'));

            return;
        }

        $builder->whereIn($table.'.snmp_olt_id', $olts);
    }
}
