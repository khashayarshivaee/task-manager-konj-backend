<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(
            function (
                object $notifiable,
                string $url,
            ): MailMessage {
                $frontendUrl = rtrim(
                    (string) config(
                        'app.frontend_url'
                    ),
                    '/',
                );

                $verificationUrl =
                    $frontendUrl
                    . '/email/verify?'
                    . http_build_query(
                        [
                            'verification_url' =>
                                $url,
                        ],
                        '',
                        '&',
                        PHP_QUERY_RFC3986,
                    );

                return (new MailMessage)
                    ->subject(
                        'Verify your email | Konj Task Manager'
                    )
                    ->view(
                        'emails.verify-email',
                        [
                            'userName' =>
                                (string) data_get(
                                    $notifiable,
                                    'name',
                                    'there',
                                ),

                            'verificationUrl' =>
                                $verificationUrl,
                        ],
                    );
            },
        );
    }
}
