<?php

declare(strict_types=1);
use App\Http\Controllers\Api\WorkspaceMemberController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\TaskCommentAttachmentController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TaskWatcherController;
Route::prefix('auth')->group(function (): void {
    Route::post(
        '/register',
        [
            AuthController::class,
            'register',
        ],
    )->middleware('throttle:5,1');

    Route::post(
        '/login',
        [
            AuthController::class,
            'login',
        ],
    )->middleware('throttle:10,1');

    Route::middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])->group(function (): void {
        Route::get(
            '/me',
            [
                AuthController::class,
                'me',
            ],
        );

        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout',
            ],
        );
    });
});

Route::prefix('profile')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])
    ->group(function (): void {
        Route::post(
            '/',
            [
                ProfileController::class,
                'update',
            ],
        );

        Route::put(
            '/password',
            [
                ProfileController::class,
                'updatePassword',
            ],
        );
    });

Route::prefix('workspaces')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                WorkspaceController::class,
                'index',
            ],
        );

        Route::post(
            '/',
            [
                WorkspaceController::class,
                'store',
            ],
        );

        Route::put(
            '/{workspace}',
            [
                WorkspaceController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}',
            [
                WorkspaceController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Workspace Members
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/members',
            [
                WorkspaceMemberController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/members',
            [
                WorkspaceMemberController::class,
                'store',
            ],
        );

        Route::put(
            '/{workspace}/members/{membership}',
            [
                WorkspaceMemberController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}/members/{membership}',
            [
                WorkspaceMemberController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Projects
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects',
            [
                ProjectController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/projects',
            [
                ProjectController::class,
                'store',
            ],
        );

        Route::get(
            '/{workspace}/projects/{project}',
            [
                ProjectController::class,
                'show',
            ],
        );

        Route::put(
            '/{workspace}/projects/{project}',
            [
                ProjectController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}',
            [
                ProjectController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Tasks
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/tasks',
            [
                TaskController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/projects/{project}/tasks',
            [
                TaskController::class,
                'store',
            ],
        );

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}',
            [
                TaskController::class,
                'show',
            ],
        );

        Route::put(
            '/{workspace}/projects/{project}/tasks/{task}',
            [
                TaskController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/tasks/{task}',
            [
                TaskController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Task Attachments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}/attachments',
            [
                TaskAttachmentController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/projects/{project}/tasks/{task}/attachments',
            [
                TaskAttachmentController::class,
                'store',
            ],
        );

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}/attachments/{attachment}/file',
            [
                TaskAttachmentController::class,
                'file',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/tasks/{task}/attachments/{attachment}',
            [
                TaskAttachmentController::class,
                'destroy',
            ],
        );

      Route::get(
          '/{workspace}/projects/{project}/tasks/{task}/watchers',
          [
              TaskWatcherController::class,
              'index',
          ],
      );

      Route::post(
          '/{workspace}/projects/{project}/tasks/{task}/watch',
          [
              TaskWatcherController::class,
              'store',
          ],
      );

      Route::delete(
          '/{workspace}/projects/{project}/tasks/{task}/watch',
          [
              TaskWatcherController::class,
              'destroy',
          ],
      );

        /*
        |--------------------------------------------------------------------------
        | Task Comments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}/comments',
            [
                TaskCommentController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/projects/{project}/tasks/{task}/comments',
            [
                TaskCommentController::class,
                'store',
            ],
        );

        Route::put(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}',
            [
                TaskCommentController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}',
            [
                TaskCommentController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Task Comment Attachments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}/attachments/{attachment}/file',
            [
                TaskCommentAttachmentController::class,
                'file',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}/attachments/{attachment}',
            [
                TaskCommentAttachmentController::class,
                'destroy',
            ],
        );
    });

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        EnsureUserIsAdmin::class,
    ])
    ->group(function (): void {
        Route::get(
            '/users',
            [
                UserController::class,
                'index',
            ],
        );

        Route::get(
            '/users/{user}',
            [
                UserController::class,
                'show',
            ],
        );

        Route::put(
            '/users/{user}',
            [
                UserController::class,
                'update',
            ],
        );

        Route::delete(
            '/users/{user}',
            [
                UserController::class,
                'destroy',
            ],
        );
    });
