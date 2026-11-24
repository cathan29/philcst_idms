<?php

namespace App\Providers;

use App\Services\ActiveAcademicTerm;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('activeAcademicTerm', app(ActiveAcademicTerm::class)->current());
        });
    }
}