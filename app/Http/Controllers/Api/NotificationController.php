<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\ProjectActivityNotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Events\UserNotificationsReadUpdated;
final class NotificationController extends Controller
{
    /**
     * Get paginated notifications for the
     * authenticated user.
     */
    public function index(
        ListNotificationsRequest $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $validated =
            $request->validated();

        $notifications =
            $user
                ->activityRecipients()
                ->with([
                    'activity.actor:id,name,email,avatar_path',
                ])
                ->latest('id')
                ->paginate(
                    (int) $validated['per_page'],
                    ['*'],
                    'page',
                    (int) $validated['page'],
                );

        $unreadCount =
            $user
                ->activityRecipients()
                ->whereNull('read_at')
                ->count();

        return response()->json([
            'data' => [
                'notifications' =>
                    ProjectActivityNotificationResource::collection(
                        $notifications->items(),
                    )->resolve(
                        $request,
                    ),

                'unread_count' =>
                    $unreadCount,
            ],

            'meta' => [
                'current_page' =>
                    $notifications->currentPage(),

                'per_page' =>
                    $notifications->perPage(),

                'total' =>
                    $notifications->total(),

                'last_page' =>
                    $notifications->lastPage(),

                'from' =>
                    $notifications->firstItem(),

                'to' =>
                    $notifications->lastItem(),

                'has_more_pages' =>
                    $notifications
                        ->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get only the unread notification count.
     */
    public function unreadCount(
        Request $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $unreadCount =
            $user
                ->activityRecipients()
                ->whereNull('read_at')
                ->count();

        return response()->json([
            'data' => [
                'unread_count' =>
                    $unreadCount,
            ],
        ]);
    }

  /**
   * Mark one notification as read.
   */
  public function read(
      Request $request,
      int $notification,
  ): JsonResponse {
      $user = $request->user();

      abort_unless(
          $user instanceof User,
          Response::HTTP_UNAUTHORIZED,
      );

      $recipient =
          $user
              ->activityRecipients()
              ->with([
                  'activity.actor:id,name,email,avatar_path',
              ])
              ->findOrFail(
                  $notification,
              );

      $wasUnread =
          $recipient->read_at === null;

      if ($wasUnread) {
          $recipient->forceFill([
              'read_at' => now(),
          ])->save();
      }

      $unreadCount =
          $user
              ->activityRecipients()
              ->whereNull('read_at')
              ->count();

      if ($wasUnread) {
          UserNotificationsReadUpdated::dispatch(
              (int) $user->id,
              $unreadCount,
              (int) $recipient->id,
              false,
          );
      }

      return response()->json([
          'message' =>
              'Notification marked as read.',

          'data' => [
              'notification' =>
                  (new ProjectActivityNotificationResource(
                      $recipient,
                  ))->resolve(
                      $request,
                  ),

              'unread_count' =>
                  $unreadCount,
          ],
      ]);
  }

   /**
    * Mark all notifications as read.
    */
   public function readAll(
       Request $request,
   ): JsonResponse {
       $user = $request->user();

       abort_unless(
           $user instanceof User,
           Response::HTTP_UNAUTHORIZED,
       );

       $now = now();

       $updatedCount =
           $user
               ->activityRecipients()
               ->whereNull('read_at')
               ->update([
                   'read_at' =>
                       $now,

                   'updated_at' =>
                       $now,
               ]);

       if ($updatedCount > 0) {
           UserNotificationsReadUpdated::dispatch(
               (int) $user->id,
               0,
               null,
               true,
           );
       }

       return response()->json([
           'message' =>
               'All notifications marked as read.',

           'data' => [
               'unread_count' => 0,
           ],
       ]);
   }
   }
