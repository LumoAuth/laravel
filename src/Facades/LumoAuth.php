<?php

declare(strict_types=1);

namespace LumoAuth\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Use `LumoAuth::getFacadeRoot()->permissions` for the namespaces, or inject
 * `\LumoAuth\LumoAuth` directly — facades forward method calls, and the SDK
 * exposes namespaces as readonly properties.
 *
 * @method static \LumoAuth\Http\HttpClient http()
 * @method static object api()
 * @property-read \LumoAuth\Resource\Permissions $permissions
 * @property-read \LumoAuth\Resource\Zanzibar $zanzibar
 * @property-read \LumoAuth\Resource\Abac $abac
 * @property-read \LumoAuth\Resource\Agents $agents
 * @property-read \LumoAuth\Resource\Approvals $approvals
 * @property-read \LumoAuth\Resource\Jit $jit
 * @property-read \LumoAuth\Resource\Mcp $mcp
 * @property-read \LumoAuth\Resource\Delegation $delegation
 * @property-read \LumoAuth\Resource\Auth $auth
 *
 * @see \LumoAuth\LumoAuth
 */
final class LumoAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LumoAuth\LumoAuth::class;
    }
}
