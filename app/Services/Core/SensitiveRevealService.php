<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ActivityLog;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Step-up access to a record's unmasked sensitive fields.
 *
 * The caller re-enters their password and receives a short-lived token bound
 * to themselves and one record. The token is spent on first use, and both the
 * grant and the reveal are written to the activity log; the reveal also goes
 * to the sensitive access log with the record's id.
 */
class SensitiveRevealService
{
    public const TOKEN_TTL_SECONDS = 900;

    public const LOCKOUT_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SensitiveAccessService $accessLog) {}

    /**
     * Whether the user has used up their password attempts for now.
     */
    public function isLockedOut(User $user): bool
    {
        return Cache::get($this->attemptsKey($user), 0) >= self::MAX_ATTEMPTS;
    }

    /**
     * Checks the password, counting a failure towards the lockout and
     * clearing the count on success.
     */
    public function verifyPassword(User $user, string $password): bool
    {
        $key = $this->attemptsKey($user);

        if (! Hash::check($password, $user->password)) {
            Cache::add($key, 0, now()->addMinutes(self::LOCKOUT_MINUTES));
            Cache::increment($key);

            return false;
        }

        Cache::forget($key);

        return true;
    }

    /**
     * An encrypted token letting this user reveal this one record until it expires.
     */
    public function issueToken(User $user, string $resourceType, string $resourceId, ?string $ipAddress): string
    {
        $token = Crypt::encrypt(json_encode([
            'user_id' => $user->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'expires' => now()->addSeconds(self::TOKEN_TTL_SECONDS)->timestamp,
        ]));

        $this->recordActivity($user, 'sensitive_data_access_granted', $resourceType, $resourceId, $ipAddress);

        return $token;
    }

    /**
     * Spends the token when it was issued to this user for this record and has
     * not expired or been spent. Spending is a single atomic cache write, so
     * two requests racing with one token cannot both succeed.
     */
    public function consumeToken(User $user, string $token, string $resourceType, string $resourceId): bool
    {
        try {
            $decoded = json_decode(Crypt::decrypt($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        if (
            ! isset($decoded['user_id'], $decoded['resource_type'], $decoded['resource_id'], $decoded['expires'])
            || $decoded['user_id'] !== $user->id
            || $decoded['resource_type'] !== $resourceType
            || $decoded['resource_id'] !== $resourceId
            || $decoded['expires'] < now()->timestamp
        ) {
            return false;
        }

        return Cache::add('sensitive_token_used:'.hash('sha256', $token), true, self::TOKEN_TTL_SECONDS);
    }

    /**
     * The plaintext sensitive fields of the user's organization's record with
     * this uuid, logged as revealed; null when there is no such record.
     *
     * @return array<string, mixed>|null
     */
    public function reveal(User $user, string $resourceType, string $resourceId, ?string $ipAddress): ?array
    {
        $record = $this->findRecord($user->organization_id, $resourceType, $resourceId);

        if ($record === null) {
            return null;
        }

        $fields = match (true) {
            $record instanceof Contact => ['tax_number' => $record->tax_number],
            $record instanceof Employee => [
                'national_id' => $record->national_id,
                'passport_number' => $record->passport_number,
                'bank_account_number' => $record->bank_account_number,
                'bank_iban' => $record->bank_iban,
            ],
        };

        $this->recordActivity($user, 'sensitive_data_revealed', $resourceType, $resourceId, $ipAddress);

        $this->accessLog->logAccess(
            modelType: $resourceType,
            modelId: $record->id,
            fields: implode(',', array_keys($fields)),
            action: 'read',
        );

        return $fields;
    }

    private function findRecord(int $organizationId, string $resourceType, string $uuid): ?Model
    {
        $model = match ($resourceType) {
            'contact' => Contact::class,
            'employee' => Employee::class,
            default => null,
        };

        return $model === null ? null : $model::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->first();
    }

    /**
     * Writes to activity_logs, the business event trail, rather than
     * audit_logs, which holds model change diffs. A failed write is logged and
     * does not fail the request.
     */
    private function recordActivity(User $user, string $action, string $entityType, string $entityId, ?string $ipAddress): void
    {
        try {
            ActivityLog::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => "Sensitive data {$action} for {$entityType} {$entityId}",
                'module' => 'core',
                'severity' => 'warning',
                'ip_address' => $ipAddress,
            ]);
        } catch (Throwable $e) {
            logger()->error('Activity log write failed for sensitive data access', [
                'user_id' => $user->id,
                'action' => $action,
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function attemptsKey(User $user): string
    {
        return "sensitive_access_attempts:{$user->id}";
    }
}
