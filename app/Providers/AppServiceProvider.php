<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale('ru');

        // Поле не в #[Fillable] — ошибка при разработке, а не тихая потеря данных.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Приложение работает в UTC, а админка показывает и принимает время в поясе бизнеса.
        FilamentTimezone::set(config('booking.timezone'));

        // За HTTPS-прокси (Cloudflare Worker перед хостингом) ссылки должны строиться от APP_URL.
        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme('https');
        }
    }
}
