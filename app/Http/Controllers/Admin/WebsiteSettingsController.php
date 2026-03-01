<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class WebsiteSettingsController extends Controller
{
    public function __construct(private readonly FilePondService $filePondService)
    {
    }

    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting.files');

        $logoFiles = $tenant->siteSetting
            ? $tenant->siteSetting->files()
                ->where('collection', 'logo')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ];
                })
                ->values()
                ->all()
            : [];

        return Inertia::render('Admin/Settings/Website', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'logoFiles' => $logoFiles,
            'actions' => [
                'update' => route('admin.settings.website.update', ['subdomain' => $tenant->slug]),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:1000'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
            'primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],

            'hero.title.en' => ['nullable', 'string', 'max:255'],
            'hero.title.ar' => ['nullable', 'string', 'max:255'],
            'hero.description.en' => ['nullable', 'string', 'max:2000'],
            'hero.description.ar' => ['nullable', 'string', 'max:2000'],
            'hero.button_text.en' => ['nullable', 'string', 'max:100'],
            'hero.button_text.ar' => ['nullable', 'string', 'max:100'],
            'hero.button_link' => ['nullable', 'string', 'max:500'],

            'contact.phone' => ['nullable', 'string', 'max:100'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.address.en' => ['nullable', 'string', 'max:500'],
            'contact.address.ar' => ['nullable', 'string', 'max:500'],

            'footer.description.en' => ['nullable', 'string', 'max:2000'],
            'footer.description.ar' => ['nullable', 'string', 'max:2000'],
        ]);

        $siteSetting = TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'site_name' => $this->nullableString($validated['site_name'] ?? null),
                'logo_url' => $this->nullableString($validated['logo_url'] ?? null),
                'primary_color' => strtolower((string) $validated['primary_color']),
                'secondary_color' => strtolower((string) $validated['secondary_color']),
                'hero' => [
                    'title' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.title.ar')),
                    ],
                    'description' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.description.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.description.ar')),
                    ],
                    'button_text' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.button_text.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.button_text.ar')),
                    ],
                    'button_link' => $this->nullableString(data_get($validated, 'hero.button_link')),
                ],
                'contact' => [
                    'phone' => $this->nullableString(data_get($validated, 'contact.phone')),
                    'email' => $this->nullableString(data_get($validated, 'contact.email')),
                    'address' => [
                        'en' => $this->nullableString(data_get($validated, 'contact.address.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact.address.ar')),
                    ],
                ],
                'footer' => [
                    'description' => [
                        'en' => $this->nullableString(data_get($validated, 'footer.description.en')),
                        'ar' => $this->nullableString(data_get($validated, 'footer.description.ar')),
                    ],
                ],
            ]
        );

        $tempFolders = $request->input('logo_temp_folders', []);
        $removedIds = $request->input('logo_removed_files', []);

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', 'logo')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            is_array($tempFolders) ? $tempFolders : [],
            is_array($removedIds) ? $removedIds : [],
            'logo'
        );

        return back()->with('success', 'Website settings updated successfully.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
