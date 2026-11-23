<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => ['name' => config('app.name'), 'version' => '1.0']);
Route::get('plans', [Api\BillingController::class, 'plans']);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('auth/register', [Api\AuthController::class, 'register']);
    Route::post('auth/login', [Api\AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Authenticated, not tenant-specific
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [Api\AuthController::class, 'me']);
    Route::post('auth/logout', [Api\AuthController::class, 'logout']);

    Route::get('organizations', [Api\OrganizationController::class, 'index']);
    Route::post('organizations', [Api\OrganizationController::class, 'store']);
    Route::post('organizations/{organization}/switch', [Api\OrganizationController::class, 'switch']);
    Route::post('invitations/{token}/accept', [Api\InvitationController::class, 'accept']);

    /*
    |----------------------------------------------------------------------
    | Tenant-scoped (current organization from X-Organization-Id header or
    | the user's current_organization_id)
    |----------------------------------------------------------------------
    */
    Route::middleware('tenant')->group(function () {
        Route::get('organization', [Api\OrganizationController::class, 'current']);
        Route::get('usage', [Api\UsageController::class, 'summary']);
        Route::get('models', [Api\ModelController::class, 'index']);

        // Knowledge base (RAG)
        Route::get('documents', [Api\DocumentController::class, 'index']);
        Route::post('documents', [Api\DocumentController::class, 'store']);
        Route::get('documents/{document}', [Api\DocumentController::class, 'show']);
        Route::post('documents/{document}/reprocess', [Api\DocumentController::class, 'reprocess']);
        Route::delete('documents/{document}', [Api\DocumentController::class, 'destroy']);
        Route::post('search', [Api\SearchController::class, 'search'])->middleware('throttle:ai');

        // Chat
        Route::apiResource('conversations', Api\ConversationController::class);
        Route::post('conversations/{conversation}/messages', [Api\MessageController::class, 'store'])
            ->middleware('throttle:ai');

        // Team
        Route::get('members', [Api\MemberController::class, 'index']);
        Route::get('invitations', [Api\InvitationController::class, 'index']);

        // Billing (read)
        Route::get('billing', [Api\BillingController::class, 'show']);

        // Org admin only (owner/admin role)
        Route::middleware('tenant.role:owner,admin')->group(function () {
            Route::patch('organization', [Api\OrganizationController::class, 'update']);
            Route::patch('members/{user}', [Api\MemberController::class, 'update']);
            Route::delete('members/{user}', [Api\MemberController::class, 'destroy']);
            Route::post('invitations', [Api\InvitationController::class, 'store']);
            Route::delete('invitations/{invitation}', [Api\InvitationController::class, 'destroy']);
            Route::post('billing/checkout', [Api\BillingController::class, 'checkout']);
            Route::post('billing/portal', [Api\BillingController::class, 'portal']);
        });
    });

    /*
    |----------------------------------------------------------------------
    | Platform admin (users.is_super_admin)
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('super-admin')->group(function () {
        Route::get('stats', [Admin\StatsController::class, 'index']);
        Route::get('usage', [Admin\StatsController::class, 'usage']);
        Route::get('organizations', [Admin\OrganizationController::class, 'index']);
        Route::get('organizations/{organization}', [Admin\OrganizationController::class, 'show']);
        Route::patch('organizations/{organization}', [Admin\OrganizationController::class, 'update']);
        Route::get('users', [Admin\UserController::class, 'index']);
        Route::patch('users/{user}', [Admin\UserController::class, 'update']);
    });
});
