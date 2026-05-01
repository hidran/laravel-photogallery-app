<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserData;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * DESIGN.md §6.1 + §10.2/§10.3.
 *
 * Token-mode Sanctum: every issued token carries the abilities matching
 * the user's role. Admin tokens get an extra `admin` ability; controllers
 * gate mutations with both ownership policies and tokenCan checks.
 */
final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $user->sendEmailVerificationNotification();

        return $this->issueToken(
            $user,
            $request->validated('device_name', 'unknown'),
            status: 201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            // 401 with the universal envelope per DESIGN.md §11.1; using
            // AuthenticationException so the bootstrap render closure
            // emits `{ "message": "Unauthenticated." }` rather than
            // ValidationException's 422 shape.
            throw new AuthenticationException;
        }

        return $this->issueToken(
            $user,
            $request->validated('device_name', 'unknown'),
            status: 200,
        );
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return UserData::make($request->user())->response();
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['message' => 'Email verified.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    private function issueToken(User $user, string $deviceName, int $status): JsonResponse
    {
        $abilities = [
            TokenAbility::PhotosWrite->value,
            TokenAbility::AlbumsWrite->value,
        ];
        if ($user->isAdmin()) {
            $abilities[] = TokenAbility::Admin->value;
        }

        $expiration = (int) config('sanctum.expiration', 1440);
        $expiresAt = $expiration > 0 ? now()->addMinutes($expiration) : null;

        $newAccessToken = $user->createToken($deviceName, $abilities, $expiresAt);

        return response()->json([
            'data' => [
                'token' => $newAccessToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => optional($expiresAt)->toIso8601String(),
                'user' => UserData::make($user)->resolve(),
            ],
        ], $status);
    }
}
