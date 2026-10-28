<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Stops one paid account being shared by many people: a student can be signed
 * in on a few devices at once, and signing in on one more signs out the oldest.
 * Admins are never limited. The limit is set in Admin > Settings (0 = no limit).
 */
class DeviceLimit
{
    public const SESSION_KEY = 'device_token';

    public const DEFAULT = 2;

    public static function limit(): int
    {
        $value = setting('student_device_limit');

        return $value === null || $value === '' ? self::DEFAULT : max(0, (int) $value);
    }

    public static function applies(?User $user): bool
    {
        return $user !== null && ! $user->isAdmin() && self::limit() > 0;
    }

    /** Remember this browser as one of the user's devices; the oldest ones beyond the limit drop off. */
    public static function register(User $user): void
    {
        $token = Str::random(40);
        $devices = collect($user->device_sessions ?? [])
            ->push([
                'token' => hash('sha256', $token),
                'at' => now()->toIso8601String(),
                'agent' => Str::limit((string) request()->userAgent(), 120, ''),
            ])
            ->take(-self::limit())
            ->values()
            ->all();

        $user->forceFill(['device_sessions' => $devices])->saveQuietly();
        session()->put(self::SESSION_KEY, $user->id.'|'.$token);
    }

    /** The token this browser's session holds for the user (null = none for this user yet). */
    public static function tokenFor(User $user, mixed $sessionValue): ?string
    {
        [$id, $token] = array_pad(explode('|', (string) $sessionValue, 2), 2, null);

        return $id === (string) $user->id && $token ? $token : null;
    }

    public static function isKnown(User $user, string $token): bool
    {
        return collect($user->device_sessions ?? [])->contains('token', hash('sha256', $token));
    }

    /** Signing out frees the device's place. */
    public static function forget(User $user, mixed $sessionValue): void
    {
        $token = self::tokenFor($user, $sessionValue);
        if (! $token || ! $user->device_sessions) {
            return;
        }

        $user->forceFill([
            'device_sessions' => collect($user->device_sessions)->reject(fn ($d) => $d['token'] === hash('sha256', $token))->values()->all(),
        ])->saveQuietly();
    }

    /** Sign the student out everywhere (admin button on the student page). */
    public static function clear(User $user): void
    {
        $user->forceFill(['device_sessions' => []])->saveQuietly();
    }
}
