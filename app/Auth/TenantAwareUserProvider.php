<?php

namespace App\Auth;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class TenantAwareUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?AuthenticatableContract
    {
        /** @var \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder $model */
        $model = $this->createModel();

        $query = $model->newQueryWithoutScopes()
            ->where($model->getKeyName(), $identifier);

        // Re-apply tenant boundary when browsing a tenant host.
        if ($tenantId = TenantContext::id()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): ?AuthenticatableContract
    {
        $tenantId = TenantContext::id();
        \Illuminate\Support\Facades\Log::info('TenantAwareUserProvider@retrieveByCredentials', [
            'credentials' => array_keys($credentials),
            'email' => $credentials['email'] ?? null,
            'tenant_id_context' => $tenantId,
        ]);

        if (empty($credentials) ||
            (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder $query */
        $query = $this->createModel()->newQueryWithoutScopes();

        // Re-apply tenant boundary when browsing a tenant host.
        if ($tenantId = TenantContext::id()) {
            $query->where('tenant_id', $tenantId);
        }

        foreach ($credentials as $key => $value) {
            if (in_array($key, ['password', 'password_confirmation', 'token'], true)) {
                continue;
            }

            if (is_array($value) || $value instanceof \Countable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
