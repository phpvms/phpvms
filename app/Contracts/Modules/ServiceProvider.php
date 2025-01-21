<?php

namespace App\Contracts\Modules;

/**
 * Base class for module service providers
 * Add-on module service providers must extend this class. Docs on Service Providers:
 * https://laravel.com/docs/7.x/providers.
 *
 * For a sample service provider, view the sample module one:
 * https://github.com/nabeelio/phpvms-module/blob/master/Providers/SampleServiceProvider.php
 */
abstract class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * A boot method is required, even if it doesn't do anything.
     * https://laravel.com/docs/7.x/providers#the-boot-method.
     *
     * This is normally where you'd register the routes or other startup tasks for your module
     */
    public function boot(): void
    {
    }

    /**
     * This is required to register the links in either the public or admin toolbar
     * For example, adding a frontend link:
     *
     * $this->moduleSvc->addFrontendLink('Sample', '/sample', '', $logged_in=true);
     *
     * Or an admin link:
     *
     * $this->moduleSvc->addAdminLink('Sample', '/admin/sample');
     */
    public function registerLinks(): void
    {
    }

    /**
     * Deferred providers:
     * https://laravel.com/docs/7.x/providers#deferred-providers
     */
    public function provides(): array
    {
        return [];
    }
}
