<?php
declare(strict_types=1);
namespace App\Core\Security\Authentication;

use App\Core\Session;

final class SessionAuthentication
{
    private const CLAIMS_KEY = '_auth_claims';

    public function authenticate(): ClaimsPrincipal
    {
        $stored = Session::get(self::CLAIMS_KEY);
        if (is_array($stored)) {
            $claims = [];
            foreach ($stored as $claim) {
                if (!is_array($claim)) continue;
                $type = $claim['type'] ?? null;
                $value = $claim['value'] ?? null;
                if (!is_string($type) || (!is_string($value) && !is_int($value))) continue;
                $claims[] = new Claim($type, (string) $value);
            }
            return new ClaimsPrincipal([new ClaimsIdentity($claims, 'session')]);
        }

        $legacyId = Session::get('auth_id');
        if (is_string($legacyId) || is_int($legacyId)) {
            $claims = [new Claim('sub', (string) $legacyId)];
            $role = Session::get('auth_type');
            if (is_string($role)) {
                $claims[] = new Claim('role', $role);
            }
            return new ClaimsPrincipal([new ClaimsIdentity($claims, 'session')]);
        }

        return ClaimsPrincipal::anonymous();
    }

    public function signIn(ClaimsPrincipal $principal): void
    {
        Session::regenerate();
        $claims = array_map(static fn (Claim $claim): array => ['type' => $claim->type, 'value' => $claim->value], $principal->identity()->claims());
        Session::set(self::CLAIMS_KEY, $claims);
    }

    public function signOut(): void { Session::remove(self::CLAIMS_KEY); }
}
