<?php

declare(strict_types=1);

namespace LumoAuth\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LumoAuth\Error\LumoAuthNotFoundError;
use LumoAuth\LumoAuth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Route middleware: `->middleware('lumoauth.permission:document.edit,document.view')`
 * requires ALL listed permissions for the signed-in user. The check runs in
 * LumoAuth about that user's LumoAuth user ID (a UUID, the `sub` of their
 * LumoAuth tokens), not about the app's own user ID.
 *
 * The LumoAuth user ID comes from the resolver set with
 * {@see self::resolveSubjectUsing()}, or else from the user model attribute
 * named in `lumoauth.user_subject_attribute` (default `lumoauth_sub`).
 *
 * Responses: 401 when nobody is signed in; 403 when a permission is missing,
 * when the user has no LumoAuth user ID, or when LumoAuth does not know that ID.
 * The configured API key needs the `authz:check` scope (or its creator needs the
 * `authz.check` permission) to ask about other users; otherwise the SDK throws
 * LumoAuthPermissionDeniedError, which is a configuration error.
 */
final class RequirePermission
{
    /** @var (Closure(mixed): ?string)|null */
    private static ?Closure $subjectResolver = null;

    public function __construct(private readonly LumoAuth $lumo, private readonly ?string $subjectAttribute = null)
    {
    }

    /**
     * Set how the middleware finds the LumoAuth user ID of the signed-in user.
     * Call it from a service provider's boot(). Pass null to restore the default.
     *
     * @param (callable(mixed): ?string)|null $resolver receives the authenticated user
     */
    public static function resolveSubjectUsing(?callable $resolver): void
    {
        self::$subjectResolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /**
     * The LumoAuth user ID for $user, or null when there is none.
     * Never falls back to the app's own user ID: that ID means nothing to LumoAuth.
     */
    public static function subjectFor(mixed $user, string $attribute = 'lumoauth_sub'): ?string
    {
        if ($user === null) {
            return null;
        }
        if (self::$subjectResolver !== null) {
            $subject = (self::$subjectResolver)($user);
        } elseif (is_object($user) && method_exists($user, 'getAttribute')) {
            $subject = $user->getAttribute($attribute);
        } elseif (is_object($user) && isset($user->{$attribute})) {
            $subject = $user->{$attribute};
        } elseif (is_array($user)) {
            $subject = $user[$attribute] ?? null;
        } else {
            $subject = null;
        }

        return is_string($subject) && $subject !== '' ? $subject : null;
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new HttpException(401, 'Authentication required.');
        }
        if ($permissions === []) {
            return $next($request);
        }

        $attribute = $this->subjectAttribute ?? (string) (function_exists('config') ? config('lumoauth.user_subject_attribute', 'lumoauth_sub') : 'lumoauth_sub');
        $subject = self::subjectFor($user, $attribute);
        if ($subject === null) {
            // Fail closed: without a LumoAuth user ID the check would be about the API key's owner.
            throw new HttpException(403, 'The signed-in user has no LumoAuth user ID.');
        }

        try {
            $allowed = $this->lumo->permissions->checkAll($permissions, userId: $subject);
        } catch (LumoAuthNotFoundError) {
            throw new HttpException(403, 'LumoAuth does not know the signed-in user.');
        }
        if (!$allowed) {
            throw new HttpException(403, 'Missing permission: ' . implode(', ', $permissions));
        }

        return $next($request);
    }
}
