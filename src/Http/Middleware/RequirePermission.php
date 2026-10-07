<?php

declare(strict_types=1);

namespace LumoAuth\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LumoAuth\LumoAuth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Route middleware: `->middleware('lumoauth.permission:document.edit,document.view')`
 * requires ALL listed permissions for the authenticated user (checked
 * server-side against LumoAuth with the user's identifier). Responds 403
 * when any is missing, 401 when nobody is signed in.
 */
final class RequirePermission
{
    public function __construct(private readonly LumoAuth $lumo)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $userId = $user?->getAuthIdentifier();
        if ($userId === null) {
            throw new HttpException(401, 'Authentication required.');
        }
        if ($permissions !== [] && !$this->lumo->permissions->checkAll($permissions, userId: (string) $userId)) {
            throw new HttpException(403, 'Missing permission: ' . implode(', ', $permissions));
        }

        return $next($request);
    }
}
