<?php

namespace App\Core;

class EmailTemplateSettings
{
    public const KEY = 'email_templates';

    public static function defaults(): array
    {
        return [
            'verify_email_after_payment' => [
                'name' => 'Verify Email After Payment',
                'description' => 'Sent to new tenant accounts after successful payment to verify their email address.',
                'subject' => 'Verify your email to activate your account',
                'greeting' => 'Hello {name},',
                'intro' => 'Your account for {tenant_name} has been created successfully after payment.',
                'body' => 'Please confirm your email address to activate your account and continue using {app_name}.',
                'action_text' => 'Verify Email Address',
                'outro' => 'This verification link will expire in {expire_minutes} minutes. If you did not create this account, you can safely ignore this email.',
                'salutation' => 'Regards, {app_name}',
            ],
            'tenant_admin_invitation' => [
                'name' => 'Tenant Admin Invitation',
                'description' => 'Sent when the super admin creates a tenant admin account.',
                'subject' => 'Activate your tenant admin account',
                'greeting' => 'Hello {name},',
                'intro' => 'A tenant admin account has been created for you for {tenant_name}.',
                'body' => 'Use the button below to set your password, verify your email, and activate your dashboard access.',
                'action_text' => 'Set Password And Activate Account',
                'outro' => 'After activation, you can sign in and manage your tenant dashboard immediately. If you were not expecting this invitation, you can ignore this email.',
                'salutation' => 'Regards, {app_name}',
            ],
        ];
    }

    public static function load(): array
    {
        $stored = \App\Models\SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function normalize(?array $data): array
    {
        $templates = [];
        $defaults = self::defaults();

        foreach ($defaults as $key => $defaultTemplate) {
            $incoming = is_array($data[$key] ?? null) ? $data[$key] : [];

            $templates[$key] = [
                'name' => trim((string) ($incoming['name'] ?? $defaultTemplate['name'])),
                'description' => trim((string) ($incoming['description'] ?? $defaultTemplate['description'])),
                'subject' => trim((string) ($incoming['subject'] ?? $defaultTemplate['subject'])),
                'greeting' => trim((string) ($incoming['greeting'] ?? $defaultTemplate['greeting'])),
                'intro' => trim((string) ($incoming['intro'] ?? $defaultTemplate['intro'])),
                'body' => trim((string) ($incoming['body'] ?? $defaultTemplate['body'])),
                'action_text' => trim((string) ($incoming['action_text'] ?? $defaultTemplate['action_text'])),
                'outro' => trim((string) ($incoming['outro'] ?? $defaultTemplate['outro'])),
                'salutation' => trim((string) ($incoming['salutation'] ?? $defaultTemplate['salutation'])),
            ];
        }

        return $templates;
    }

    public static function placeholders(): array
    {
        return [
            '{app_name}' => 'Application name',
            '{name}' => 'Recipient name',
            '{email}' => 'Recipient email',
            '{tenant_name}' => 'Tenant or company name',
            '{tenant_slug}' => 'Tenant subdomain slug',
            '{expire_minutes}' => 'Verification link expiry in minutes',
        ];
    }
}
