<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tangkap bug lebih awal: akses relasi tanpa eager load / atribut yang tidak ada.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            // Tautan yang dibuat sistem (mis. reset password) selalu memakai alamat resmi.
            if ($host = config('erp.canonical_host')) {
                URL::forceRootUrl('https://'.$host);
            }
        }
    }
}
