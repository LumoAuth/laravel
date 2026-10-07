# lumoauth/laravel

```bash
composer require lumoauth/laravel
php artisan vendor:publish --tag=lumoauth-config   # optional; env vars work without it
```

```dotenv
LUMOAUTH_URL=https://app.lumoauth.dev
LUMOAUTH_ORG_ID=acme-corp
LUMOAUTH_API_KEY=lk_…
```

```php
use LumoAuth\LumoAuth;

public function __construct(private LumoAuth $lumo) {}

if ($this->lumo->permissions->check('document.edit', userId: $request->user()->id)) { … }

Route::put('/documents/{id}', UpdateDocument::class)
    ->middleware('lumoauth.permission:document.edit');
```

Inject `LumoAuth\LumoAuth` anywhere the container resolves, or use the
`LumoAuth` facade; the middleware alias `lumoauth.permission` requires every
listed permission for the signed-in user.
