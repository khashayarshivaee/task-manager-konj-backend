<?php

declare(strict_types=1);
use App\Http\Controllers\Api\VpnUserDestinationController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WorkspaceNoteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WorkspaceNoteAttachmentController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Controllers\Api\ProjectActivityController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ProjectTaskCalendarController;
use App\Http\Controllers\Api\ProjectTaskSummaryController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\TaskCommentVoiceMessageController;
use App\Http\Controllers\Api\TaskCommentAttachmentController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskCommentReadController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\VpnUserController;
use App\Http\Controllers\Api\TaskWatcherController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceDashboardController;
use App\Http\Controllers\Api\WorkspaceMemberController;
use App\Http\Controllers\InstagramController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VpnSessionController;
use App\Http\Middleware\EnsureCanAccessVpnSessions;
use App\Http\Controllers\InstagramMediaProxyController;
/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    |
    | This route does not require Sanctum because the user may open
    | the verification email on another browser or device.
    | The signed middleware protects the verification URL.
    |
    */

    Route::get(
        '/email/verify/{id}/{hash}',
        [
            EmailVerificationController::class,
            'verify',
        ],
    )
        ->middleware([
            'signed',
            'throttle:10,1',
        ])
        ->name('verification.verify');

    /*
    |--------------------------------------------------------------------------
    | Authenticated User Routes
    |--------------------------------------------------------------------------
    |
    | These routes intentionally do NOT require email verification.
    | An unverified user must still be able to:
    | - load their account
    | - resend verification
    | - logout
    |
    */

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
            '/email/verification-notification',
            [
                EmailVerificationController::class,
                'resend',
            ],
        )
            ->middleware('throttle:3,1')
            ->name('verification.send');

        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout',
            ],
        );
      });
});

/*
|--------------------------------------------------------------------------
| Instagram Media Proxy
|--------------------------------------------------------------------------
*/

Route::get(
    '/instagram/media-proxy',
    InstagramMediaProxyController::class,
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
    ]);


/*
|--------------------------------------------------------------------------
| Push Devices
|--------------------------------------------------------------------------
*/

Route::prefix('push-devices')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
    ])
    ->group(function (): void {
        Route::post(
            '/',
            [
                PushDeviceController::class,
                'store',
            ],
        );

        Route::delete(
            '/',
            [
                PushDeviceController::class,
                'destroy',
            ],
        );
    });

/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

Route::prefix('profile')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
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

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

Route::prefix('notifications')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                NotificationController::class,
                'index',
            ],
        );

        Route::get(
            '/unread-count',
            [
                NotificationController::class,
                'unreadCount',
            ],
        );

        Route::patch(
            '/read-all',
            [
                NotificationController::class,
                'readAll',
            ],
        );

        Route::patch(
            '/{notification}/read',
            [
                NotificationController::class,
                'read',
            ],
        );
    });

/*
|--------------------------------------------------------------------------
| Global Search
|--------------------------------------------------------------------------
*/

Route::get(
    '/search',
    [
        GlobalSearchController::class,
        'index',
    ],
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
    ]);

/*
|--------------------------------------------------------------------------
| Workspaces
|--------------------------------------------------------------------------
*/

Route::prefix('workspaces')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
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
        | Workspace Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/dashboard',
            [
                WorkspaceDashboardController::class,
                'show',
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

        Route::post(
            '/{workspace}/instagram/connect',
            [
                InstagramController::class,
                'connect',
            ],
        );

        Route::get(
            '/{workspace}/instagram',
            [
                InstagramController::class,
                'show',
            ],
        );

        Route::delete(
            '/{workspace}/instagram',
            [
                InstagramController::class,
                'disconnect',
            ],
        );

        Route::get(
            '/{workspace}/instagram/profile',
            [
                InstagramController::class,
                'profile',
            ],
        );

        Route::get(
            '/{workspace}/instagram/insights',
            [
                InstagramController::class,
                'insights',
            ],
        );

        Route::get(
            '/{workspace}/instagram/dashboard',
            [
                InstagramController::class,
                'dashboard',
            ],
        );

        Route::get(
            '/{workspace}/instagram/media',
            [
                InstagramController::class,
                'media',
            ],
        );

        Route::post(
            '/{workspace}/instagram/media/image',
            [
                InstagramController::class,
                'publishImage',
            ],
        );

        Route::post(
            '/{workspace}/instagram/publications/{publication}/continue',
            [
                InstagramController::class,
                'continuePublication',
            ],
        );

        Route::post(
            '/{workspace}/instagram/media/reel',
            [
                InstagramController::class,
                'publishReel',
            ],
        );

        Route::post(
            '/{workspace}/instagram/media/story',
            [
                InstagramController::class,
                'publishStoryVideo',
            ],
        );

        Route::post(
            '/{workspace}/instagram/media/story/image',
            [
                InstagramController::class,
                'publishStoryImage',
            ],
        );

        Route::get(
            '/{workspace}/instagram/media/{mediaId}/comments',
            [
                InstagramController::class,
                'comments',
            ],
        );

        Route::post(
            '/{workspace}/instagram/comments/{commentId}/reply',
            [
                InstagramController::class,
                'replyToComment',
            ],
        );

        Route::post(
            '/{workspace}/instagram/comments/{commentId}/hide',
            [
                InstagramController::class,
                'hideComment',
            ],
        );

        Route::post(
            '/{workspace}/instagram/comments/{commentId}/unhide',
            [
                InstagramController::class,
                'unhideComment',
            ],
        );

        Route::delete(
            '/{workspace}/instagram/comments/{commentId}',
            [
                InstagramController::class,
                'deleteComment',
            ],
        );

        Route::get(
            '/{workspace}/instagram/conversations',
            [
                InstagramController::class,
                'conversations',
            ],
        );

        Route::get(
            '/{workspace}/instagram/conversations/{conversationId}',
            [
                InstagramController::class,
                'conversation',
            ],
        );

        Route::get(
            '/{workspace}/instagram/messages/{messageId}',
            [
                InstagramController::class,
                'message',
            ],
        );

        Route::post(
            '/{workspace}/instagram/messages',
            [
                InstagramController::class,
                'sendMessage',
            ],
        );

        Route::post(
            '/{workspace}/instagram/publications/schedule',
            [
                InstagramController::class,
                'schedulePublication',
            ],
        );

        Route::get(
            '/{workspace}/instagram/publications',
            [
                InstagramController::class,
                'publications',
            ],
        );

        Route::delete(
            '/{workspace}/instagram/publications/{publication}',
            [
                InstagramController::class,
                'cancelPublication',
            ],
        );

        Route::patch(
            '/{workspace}/instagram/publications/{publication}/reschedule',
            [
                InstagramController::class,
                'reschedulePublication',
            ],
        );






        /*
        |--------------------------------------------------------------------------
        | Workspace Notes
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/notes',
            [
                WorkspaceNoteController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/notes',
            [
                WorkspaceNoteController::class,
                'store',
            ],
        );

        Route::get(
            '/{workspace}/notes/{note}',
            [
                WorkspaceNoteController::class,
                'show',
            ],
        );

        Route::put(
            '/{workspace}/notes/{note}',
            [
                WorkspaceNoteController::class,
                'update',
            ],
        );

        Route::delete(
            '/{workspace}/notes/{note}',
            [
                WorkspaceNoteController::class,
                'destroy',
            ],
        );
        /*
        |--------------------------------------------------------------------------
        | Workspace Note Attachments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/notes/{note}/attachments',
            [
                WorkspaceNoteAttachmentController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/notes/{note}/attachments',
            [
                WorkspaceNoteAttachmentController::class,
                'store',
            ],
        );

        Route::get(
            '/{workspace}/notes/{note}/attachments/{attachment}/file',
            [
                WorkspaceNoteAttachmentController::class,
                'file',
            ],
        );

        Route::delete(
            '/{workspace}/notes/{note}/attachments/{attachment}',
            [
                WorkspaceNoteAttachmentController::class,
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

        Route::post(
            '/{workspace}/instagram/connect',
            [
                InstagramController::class,
                'connect',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Project Activity
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/activities',
            [
                ProjectActivityController::class,
                'index',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Project Members
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/projects/{project}/members',
            [
                ProjectMemberController::class,
                'index',
            ],
        );

        Route::post(
            '/{workspace}/projects/{project}/members',
            [
                ProjectMemberController::class,
                'store',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/members/{membership}',
            [
                ProjectMemberController::class,
                'destroy',
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Tasks
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{workspace}/tasks',
            [
                TaskController::class,
                'workspaceIndex',
            ],
        );

        Route::get(
            '/{workspace}/projects/{project}/tasks',
            [
                TaskController::class,
                'index',
            ],
        );

        Route::get(
            '/{workspace}/projects/{project}/tasks/calendar',
            ProjectTaskCalendarController::class,
        );

        Route::get(
            '/{workspace}/projects/{project}/task-summary',
            ProjectTaskSummaryController::class,
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

        Route::patch(
            '/{workspace}/projects/{project}/tasks/{task}/status',
            [
                TaskController::class,
                'updateStatus',
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

        /*
        |--------------------------------------------------------------------------
        | Task Watchers
        |--------------------------------------------------------------------------
        */

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

        Route::patch(
            '/{workspace}/projects/{project}/tasks/{task}/comments/read',
            TaskCommentReadController::class,
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

        /*
|--------------------------------------------------------------------------
| Task Comment Voice Messages
|--------------------------------------------------------------------------
*/

        Route::get(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}/voice/file',
            [
                TaskCommentVoiceMessageController::class,
                'file',
            ],
        );

        Route::delete(
            '/{workspace}/projects/{project}/tasks/{task}/comments/{comment}/voice',
            [
                TaskCommentVoiceMessageController::class,
                'destroy',
            ],
        );
    });

/*
|--------------------------------------------------------------------------
| VPN Sessions
|--------------------------------------------------------------------------
*/

Route::get(
    '/vpn/sessions',
    VpnSessionController::class,
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
        EnsureCanAccessVpnSessions::class,
    ]);

Route::get(
    '/vpn/users',
    [
        VpnUserController::class,
        'index',
    ],
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
        EnsureCanAccessVpnSessions::class,
    ]);

Route::post(
    '/vpn/users',
    [
        VpnUserController::class,
        'store',
    ],
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
        EnsureCanAccessVpnSessions::class,
    ]);

Route::delete(
    '/vpn/users/{vpnUser}',
    [
        VpnUserController::class,
        'destroy',
    ],
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
        EnsureCanAccessVpnSessions::class,
    ]);

Route::post(
    '/vpn/users/{vpnUser}/enable',
    [
        VpnUserController::class,
        'enable',
    ],
)
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
        EnsureCanAccessVpnSessions::class,
    ]);

Route::get(
    '/vpn/users/{vpnUser}/destinations',
    [
        VpnUserDestinationController::class,
        'index',
    ],
);



/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
        'verified',
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
