<?php

namespace Tests\Feature\Auth\Concerns;

trait HasAuthEndpoints
{
    protected function loginEndpoint(): string
    {
        return '/api/v1/login';
    }

    protected function adminLoginEndpoint(): string
    {
        return '/api/v1/admin/login';
    }

    protected function logoutEndpoint(): string
    {
        return '/api/v1/logout';
    }

    protected function registerEndpoint(): string
    {
        return '/api/v1/register';
    }

    protected function userEndpoint(): string
    {
        return '/api/v1/user';
    }

    protected function adminUserEndpoint(): string
    {
        return '/api/v1/admin/user';
    }
}