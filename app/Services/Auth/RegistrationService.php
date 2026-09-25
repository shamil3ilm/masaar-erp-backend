<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Core\Role;
use App\Models\Core\UserEvent;
use App\Models\User;
use App\Notifications\Auth\WelcomeNotification;
use App\Services\Core\UserEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-service sign-up: a new organization with its head office, the signing
 * user as its administrator and the default modules.
 *
 * A registration always creates its own organization; nothing in the request
 * can place the user in an existing one. The account is written in one
 * transaction and the welcome and verification emails go out after it commits.
 */
final class RegistrationService
{
    /** Modules a new organization starts with. */
    private const DEFAULT_MODULES = ['core', 'accounting', 'inventory', 'sales', 'purchase', 'hr', 'crm', 'manufacturing'];

    public function __construct(
        private readonly UserEventService $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated registration fields, with the email already normalized
     * @return User the new user, with its organization loaded
     */
    public function register(array $data, Request $request): User
    {
        $user = DB::transaction(function () use ($data, $request): User {
            $organization = Organization::create([
                'name' => trim($data['organization_name']),
                // A random suffix keeps two sign-ups with the same name from colliding.
                'slug' => Str::slug($data['organization_name']).'-'.Str::random(6),
                'country_code' => $data['country_code'],
                'tax_scheme' => $this->taxScheme($data['country_code']),
                'base_currency' => $this->currency($data['country_code']),
                'email' => $data['email'],
                'is_active' => true,
                'activated_at' => now(),
            ]);

            $branch = $this->createHeadOffice($organization);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'timezone' => $this->timezone($data['country_code']),
                'registration_source' => $data['registration_source'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'utm_term' => $data['utm_term'] ?? null,
                'utm_content' => $data['utm_content'] ?? null,
                'referral_code' => $data['referral_code'] ?? null,
                'registration_device_type' => $data['registration_device_type'] ?? null,
                'invited_by_user_id' => $data['invited_by_user_id'] ?? null,
            ]);

            // Set outside mass assignment so it is always the server-side IP.
            $user->registration_ip = $request->ip();
            $user->save();

            $user->branches()->attach($branch->id, ['is_default' => true]);

            $adminRole = Role::withoutGlobalScopes()
                ->where('slug', 'admin')
                ->whereNull('organization_id')
                ->first();

            if ($adminRole) {
                $user->roles()->attach($adminRole->id);
            }

            foreach (self::DEFAULT_MODULES as $moduleCode) {
                OrganizationModule::create([
                    'organization_id' => $organization->id,
                    'module_code' => $moduleCode,
                    'is_enabled' => true,
                    'enabled_at' => now(),
                ]);
            }

            $this->events->track(
                UserEvent::USER_REGISTERED,
                ['organization' => $organization->name, 'country' => $data['country_code']],
                $user->id,
                $organization->id,
                $request,
            );

            return $user->setRelation('organization', $organization);
        });

        $user->notify(new WelcomeNotification($user->organization->name));
        $user->sendEmailVerificationNotification();

        return $user;
    }

    private function createHeadOffice(Organization $organization): Branch
    {
        // Built by hand and saved quietly: there is no tenant context to scope
        // it to yet, and the initial branch needs no audit entry.
        $branch = new Branch();
        $branch->uuid = (string) Str::uuid();
        $branch->organization_id = $organization->id;
        $branch->name = 'Head Office';
        $branch->code = 'HO';
        $branch->country_code = $organization->country_code;
        $branch->is_default = true;
        $branch->is_active = true;
        $branch->saveQuietly();

        return $branch;
    }

    private function taxScheme(string $countryCode): string
    {
        return match ($countryCode) {
            'IN' => 'GST',
            'SA', 'AE', 'BH', 'OM', 'QA', 'KW' => 'VAT',
            default => 'NONE',
        };
    }

    private function currency(string $countryCode): string
    {
        return match ($countryCode) {
            'SA' => 'SAR',
            'AE' => 'AED',
            'IN' => 'INR',
            'QA' => 'QAR',
            'OM' => 'OMR',
            'BH' => 'BHD',
            'KW' => 'KWD',
            default => 'USD',
        };
    }

    private function timezone(string $countryCode): string
    {
        return match ($countryCode) {
            'SA', 'QA', 'BH', 'KW' => 'Asia/Riyadh',
            'AE', 'OM' => 'Asia/Dubai',
            'IN' => 'Asia/Kolkata',
            default => 'UTC',
        };
    }
}
