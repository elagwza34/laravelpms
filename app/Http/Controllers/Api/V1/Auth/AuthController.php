<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use App\Support\Tenancy\Resolvers\MembershipTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the API.
 *
 * Phase 1 covers login, logout and the current-user endpoint. Google OAuth and
 * password reset are intentionally deferred to later phases.
 */
class AuthController extends Controller
{
    public function __construct(protected MembershipTenantResolver $companies) {}

    /**
     * Exchange credentials for an API token.
     *
     * Two deliberate security choices:
     *
     *  - The failure message is identical for an unknown email and a wrong
     *    password, so the endpoint cannot be used to enumerate accounts.
     *  - Failed attempts are rate limited per IP via the `auth` limiter.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            Log::notice('Failed login attempt.', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            // Deliberately vague: never reveal which half was wrong.
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        // A deactivated account must not receive a usable token.
        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated.'),
            ]);
        }

        $deviceName = $credentials['device_name'] ?? 'api-token';

        return response()->json([
            'message' => 'Authenticated.',
            'token_type' => 'Bearer',
            'access_token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $this->profile($user),
        ]);
    }

    /**
     * Revoke the token that authenticated this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * The authenticated user, their scopes and their companies.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->profile($request->user()),
        ]);
    }

    /**
     * Serialise a user for the API. Never includes sensitive columns.
     *
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $user->loadMissing('memberships.role.permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'is_platform_user' => $user->isPlatformUser(),
            'email_verified' => $user->isEmailVerified(),
            'permissions' => $user->permissionNames(),
            'companies' => $this->companies->companiesFor($user)
                ->map(fn ($company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'status' => $company->status->value,
                    'role' => $user->memberships
                        ->firstWhere('company_id', $company->id)?->role?->name,
                ])
                ->all(),
        ];
    }
}
