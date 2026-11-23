<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Tenancy\TenantContext;

abstract class Controller
{
    protected function tenant(): Organization
    {
        return app(TenantContext::class)->organization();
    }
}
