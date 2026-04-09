<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Avatar;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Avatar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow variables to be available in all views
        View::composer('*', function ($view) {
            $user = auth()->user();
            $bookUrl = route('login');
            $dashboardUrl = route('login');
            $historyUrl = route('login');

            if ($user) {
                $bookUrl = match(true) {
                    $user->isStudent() => route('student.bookings'),
                    $user->isMentor()  => route('mentor.bookings'),
                    default            => route('login'),
                };

                $dashboardUrl = match(true) {
                    $user->isStudent() => route('student.dashboard'),
                    $user->isMentor()  => route('mentor.dashboard'),
                    $user->isAdmin()   => route('admin.dashboard'),
                    default            => route('login'),
                };

                $historyUrl = match(true) {
                    $user->isStudent() => route('student.history'),
                    $user->isMentor()  => route('mentor.history'),
                    default            => route('login'),
                };
            }

            // Bind the variables to the view
            $view->with([
                'bookUrl' => $bookUrl,
                'dashboardUrl' => $dashboardUrl,
                'historyUrl' => $historyUrl,
                'shouldShowBookNow' => !($user && $user->isAdmin()),
            ]);
        });
    }
}
