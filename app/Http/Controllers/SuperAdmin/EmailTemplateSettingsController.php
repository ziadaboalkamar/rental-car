<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\EmailTemplateSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('SuperAdmin/Settings/Emails', [
            'templates' => EmailTemplateSettings::load(),
            'placeholders' => EmailTemplateSettings::placeholders(),
            'actions' => [
                'update' => route('superadmin.settings.emails.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.verify_email_after_payment.subject' => ['required', 'string', 'max:255'],
            'templates.verify_email_after_payment.greeting' => ['required', 'string', 'max:500'],
            'templates.verify_email_after_payment.intro' => ['required', 'string', 'max:2000'],
            'templates.verify_email_after_payment.body' => ['required', 'string', 'max:5000'],
            'templates.verify_email_after_payment.action_text' => ['required', 'string', 'max:255'],
            'templates.verify_email_after_payment.outro' => ['required', 'string', 'max:5000'],
            'templates.verify_email_after_payment.salutation' => ['nullable', 'string', 'max:500'],
            'templates.tenant_admin_invitation.subject' => ['required', 'string', 'max:255'],
            'templates.tenant_admin_invitation.greeting' => ['required', 'string', 'max:500'],
            'templates.tenant_admin_invitation.intro' => ['required', 'string', 'max:2000'],
            'templates.tenant_admin_invitation.body' => ['required', 'string', 'max:5000'],
            'templates.tenant_admin_invitation.action_text' => ['required', 'string', 'max:255'],
            'templates.tenant_admin_invitation.outro' => ['required', 'string', 'max:5000'],
            'templates.tenant_admin_invitation.salutation' => ['nullable', 'string', 'max:500'],
        ]);

        $normalized = EmailTemplateSettings::normalize($validated['templates'] ?? []);

        SiteSetting::query()->updateOrCreate(
            ['key' => EmailTemplateSettings::KEY],
            ['value' => $normalized]
        );

        return back()->with('success', 'Email templates updated successfully.');
    }
}
