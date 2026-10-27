# lumoauth/laravel

The LumoAuth package for Laravel 10, 11 and 12. It adds three things:

- A service provider that registers `LumoAuth\LumoAuth` as a singleton.
- The `LumoAuth` facade.
- The `lumoauth.permission` route middleware.

The package builds on the core SDK, [`lumoauth/lumoauth`](../lumoauth/README.md). That
package documents the namespaces, credentials, scopes and errors.

## Install

```bash
composer require lumoauth/laravel
php artisan vendor:publish --tag=lumoauth-config   # optional; the environment variables work without it
```

Package discovery registers the service provider and the facade alias.

## Configuration

```dotenv
LUMOAUTH_URL=https://app.lumoauth.dev
LUMOAUTH_ORG_ID=acme-corp
LUMOAUTH_API_KEY=lmk_…
```

| Key in `config/lumoauth.php` | Environment variable | Default |
|---|---|---|
| `url` | `LUMOAUTH_URL` | `https://app.lumoauth.dev` (EU: `https://eu.app.lumoauth.dev`) |
| `org_id` | `LUMOAUTH_ORG_ID` | none |
| `api_key` | `LUMOAUTH_API_KEY` | none; sent as `X-API-Key` |
| `user_subject_attribute` | `LUMOAUTH_USER_SUBJECT_ATTRIBUTE` | `lumoauth_sub`; the user model attribute that holds the LumoAuth user ID |

## Use the client

Inject `LumoAuth\LumoAuth` anywhere that the container resolves classes. The container
also binds it as `lumoauth`.

```php
use LumoAuth\LumoAuth;

final class DocumentController
{
    public function __construct(private readonly LumoAuth $lumo) {}

    public function update(Request $request, string $id)
    {
        abort_unless($this->lumo->permissions->check('document.edit'), 403);
        // …
    }
}
```

The SDK exposes its namespaces as properties, and a facade forwards only method calls.
To reach a namespace through the facade, get the client first:

```php
use LumoAuth\Laravel\Facades\LumoAuth;

LumoAuth::getFacadeRoot()->permissions->check('document.edit');
```

The configured client uses the organization API key. A permission check with that key is
about the user who created the key. To check a signed-in user, pass the user's LumoAuth
user ID as `userId:`. The API key needs the `authz:check` scope for this:

```php
$this->lumo->permissions->check('document.edit', userId: $request->user()->lumoauth_sub);
```

The LumoAuth user ID is a UUID: the `sub` claim of the user's LumoAuth tokens. Your app's
own user ID does not work. Store the `sub` on the user model when the user signs in with
LumoAuth, for example in a `lumoauth_sub` column. You can also call LumoAuth with the
user's own access token:

```php
$asUser = new \LumoAuth\LumoAuth(orgId: 'acme-corp', tokenProvider: fn () => session('lumoauth_access_token'));
$asUser->permissions->check('document.edit');
```

## Route middleware

```php
Route::put('/documents/{id}', UpdateDocument::class)
    ->middleware('lumoauth.permission:document.edit,document.view');
```

The middleware requires every listed permission. It checks the signed-in user's
LumoAuth user ID, and it never uses your app's own user ID. It reads that ID from the user
model attribute that `user_subject_attribute` names, `lumoauth_sub` by default.

| Situation | Response |
|---|---|
| Nobody is signed in | 401 |
| The user has no LumoAuth user ID | 403 |
| LumoAuth does not know the ID (`SUBJECT_NOT_FOUND`) | 403 |
| A listed permission is missing | 403 |
| Every listed permission is granted | The request continues |

The configured API key needs the `authz:check` scope. Without it, the SDK throws
`LumoAuthPermissionDeniedError`, which Laravel reports as a server error. Treat that as a
configuration error.

When the ID lives somewhere else, set a resolver in a service provider's `boot()` method:

```php
use LumoAuth\Laravel\Http\Middleware\RequirePermission;

RequirePermission::resolveSubjectUsing(fn ($user) => $user->identities()->where('provider', 'lumoauth')->value('subject'));
```

## Upgrading from 1.0.x

The `lumoauth.permission` middleware now checks the user's LumoAuth user ID. Version
1.0.x sent your app's own user ID (`getAuthIdentifier()`) as `userId:`. LumoAuth does not
know that ID. The check then failed with `SUBJECT_NOT_FOUND`, or it asked about a
different subject.

What changed:

- The middleware reads the LumoAuth user ID from the user model attribute that
  `user_subject_attribute` names. The default is `lumoauth_sub`. Set
  `LUMOAUTH_USER_SUBJECT_ATTRIBUTE` to use another attribute.
- When that attribute is empty, the middleware answers 403. It never falls back to your
  app's user ID.
- When the ID lives somewhere else, call `RequirePermission::resolveSubjectUsing()`. See
  [Route middleware](#route-middleware).

A user passes the middleware only after your app has stored the user's LumoAuth user ID.
To upgrade, add a column, and store the `sub` claim when the user signs in.

**1. Add the column.**

```php
<?php
// database/migrations/2026_10_26_000000_add_lumoauth_sub_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lumoauth_sub')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['lumoauth_sub']);
            $table->dropColumn('lumoauth_sub');
        });
    }
};
```

**2. Store the `sub` claim in the sign-in callback.** The example uses the SDK's
authorization code flow with PKCE. It reads `sub` from the userinfo response. Put your
OAuth client's ID and secret in `config/services.php` under `lumoauth`.

```php
// routes/web.php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LumoAuth\LumoAuth;

Route::get('/auth/lumoauth', function (LumoAuth $lumo) {
    $state = Str::random(40);
    $verifier = Str::random(64);
    session(['lumoauth_state' => $state, 'lumoauth_verifier' => $verifier]);

    return redirect()->away($lumo->auth->authorizationUrl(
        clientId: config('services.lumoauth.client_id'),
        redirectUri: route('lumoauth.callback'),
        scope: 'openid profile email',
        state: $state,
        codeChallenge: rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
    ));
});

Route::get('/auth/lumoauth/callback', function (Request $request, LumoAuth $lumo) {
    abort_unless(hash_equals((string) session()->pull('lumoauth_state'), (string) $request->query('state')), 400);

    $tokens = $lumo->auth->exchangeCode(
        code: (string) $request->query('code'),
        redirectUri: route('lumoauth.callback'),
        clientId: config('services.lumoauth.client_id'),
        clientSecret: config('services.lumoauth.client_secret'),
        codeVerifier: (string) session()->pull('lumoauth_verifier'),
    );
    $claims = $lumo->auth->userinfo($tokens['access_token']);

    // Find the user by the LumoAuth user ID first. Match an existing account by email
    // only when LumoAuth has verified that email.
    $user = User::firstWhere('lumoauth_sub', $claims['sub']);
    if ($user === null && ($claims['email_verified'] ?? false)) {
        $user = User::firstWhere('email', $claims['email']);
    }
    abort_if($user === null, 403, 'No account for this LumoAuth user.');

    $user->forceFill(['lumoauth_sub' => $claims['sub']])->save();
    Auth::login($user);
    session(['lumoauth_access_token' => $tokens['access_token']]);

    return redirect()->intended('/');
})->name('lumoauth.callback');
```

Existing users get their LumoAuth user ID on their next sign-in. Until then, the
middleware answers 403 for them.

## More

- Core SDK guide: [`lumoauth/lumoauth`](../lumoauth/README.md)
- Full guide and troubleshooting: [sdk-php README](../../README.md)

## Licence

MIT
