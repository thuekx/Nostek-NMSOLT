<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\Site;
use App\Models\SnmpOlt;
use App\Support\AuditLogger;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UI-F1b: organisasi yang sedang berlaku dan site-nya (POP/datacenter/area).
 *
 * - Semua user: ganti organisasi (hanya yang ia anggotai) dan pilih site penyaring di header.
 * - Admin: kelola site dan tempatkan OLT ke site.
 *
 * Site dan OLT organisasi lain tidak ada (404) lewat {@see OrganizationScope}.
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function index(): Response
    {
        $organization = Organization::query()->findOrFail($this->context->currentId());

        $sites = Site::query()
            ->withCount('olts')
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'key' => $site->key,
                'name' => $site->name,
                'kind' => $site->kind,
                'region' => $site->region,
                'timezone' => $site->timezone,
                'olt_count' => $site->olts_count,
            ]);

        return Inertia::render('Organization/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'kind' => $organization->kind,
                'cc_linked' => $organization->cc_org_id !== null,
            ],
            'sites' => $sites,
            'olts' => SnmpOlt::query()
                ->orderBy('name')
                ->get(['id', 'name', 'vendor', 'site_id'])
                ->map(fn (SnmpOlt $olt) => [
                    'id' => $olt->id,
                    'name' => $olt->name,
                    'vendor' => $olt->vendor,
                    'site_id' => $olt->site_id,
                ]),
            'siteKinds' => Site::KINDS,
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $site = new Site($request->validate($this->siteRules()));
        $site->organization_id = $this->context->currentId();
        $site->save();
        $this->audit('created', $site);

        return back()->with('success', __('flash.site_added'));
    }

    public function updateSite(Request $request, Site $site): RedirectResponse
    {
        $site->fill($request->validate($this->siteRules($site)));
        $changes = $site->getDirty();
        $site->save();
        $this->audit('updated', $site, ['changes' => $changes]);

        return back()->with('success', __('flash.site_updated'));
    }

    /** OLT di site ini kembali ke "tanpa site" (FK nullOnDelete); datanya tidak tersentuh. */
    public function destroySite(Site $site): RedirectResponse
    {
        $this->audit('deleted', $site, ['olt_count' => $site->olts()->count()]);
        $site->delete();

        return back()->with('success', __('flash.site_deleted'));
    }

    public function assignOltSite(Request $request, SnmpOlt $olt): RedirectResponse
    {
        $data = $request->validate(['site_id' => ['nullable', 'uuid', $this->siteExists()]]);

        // Bukan fillable: site hanya datang dari validasi di atas (site organisasi ini).
        $olt->forceFill(['site_id' => $data['site_id'] ?? null])->save();

        return back()->with('success', __('flash.olt_site_updated'));
    }

    public function switchOrganization(Request $request): RedirectResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'uuid']]);
        $user = $request->user();

        // Organisasi yang bukan miliknya tidak ada baginya.
        abort_unless($this->context->isMember($user, $data['organization_id']), 404);

        $user->forceFill([
            'current_organization_id' => $data['organization_id'],
            'current_site_id' => null,
        ])->save();
        $this->context->forget($user);

        return redirect()->route('dashboard');
    }

    public function selectSite(Request $request): RedirectResponse
    {
        $data = $request->validate(['site_id' => ['nullable', 'uuid', $this->siteExists()]]);

        $request->user()->forceFill(['current_site_id' => $data['site_id'] ?? null])->save();

        return back();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function siteRules(?Site $site = null): array
    {
        return [
            // = site_key Control Center: huruf kecil, angka, tanda minus.
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('sites', 'key')
                    ->where('organization_id', $this->context->currentId())
                    ->ignore($site?->id)],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(Site::KINDS)],
            'region' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(string $event, Site $site, array $extra = []): void
    {
        $verb = ['created' => 'Menambahkan', 'updated' => 'Memperbarui', 'deleted' => 'Menghapus'][$event];

        AuditLogger::log($event, null, [
            'subject_label' => 'Site',
            'subject_title' => $site->name,
            'site_id' => $site->id,
            'site_key' => $site->key,
        ] + $extra, trim("$verb Site {$site->name}"));
    }

    private function siteExists(): Exists
    {
        return Rule::exists('sites', 'id')->where('organization_id', $this->context->currentId());
    }
}
