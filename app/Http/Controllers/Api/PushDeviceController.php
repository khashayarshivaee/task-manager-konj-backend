<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushDevice\RegisterPushDeviceRequest;
use App\Http\Requests\PushDevice\UnregisterPushDeviceRequest;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;

class PushDeviceController extends Controller
{
    public function store(
        RegisterPushDeviceRequest $request,
    ): JsonResponse {
        $user = $request->user();

        $validated =
            $request->validated();

        /*
         * A token belongs to the currently
         * signed-in user.
         *
         * If the same installation was
         * previously registered to another
         * account, ownership is moved to the
         * current account.
         */
        $device =
            PushDevice::query()
                ->updateOrCreate(
                    [
                        'token' =>
                            $validated['token'],

                        'environment' =>
                            $validated[
                                'environment'
                            ],
                    ],
                    [
                        'user_id' =>
                            $user->id,

                        'platform' =>
                            $validated[
                                'platform'
                            ],

                        'device_name' =>
                            $validated[
                                'device_name'
                            ] ?? null,

                        'is_active' =>
                            true,

                        'last_seen_at' =>
                            now(),
                    ],
                );

        return response()->json([
            'message' =>
                'Push device registered successfully.',

            'data' => [
                'device' => [
                    'id' =>
                        $device->id,

                    'platform' =>
                        $device->platform,

                    'environment' =>
                        $device->environment,

                    'device_name' =>
                        $device->device_name,

                    'is_active' =>
                        $device->is_active,

                    'last_seen_at' =>
                        $device->last_seen_at,
                ],
            ],
        ]);
    }

    public function destroy(
        UnregisterPushDeviceRequest $request,
    ): JsonResponse {
        $validated =
            $request->validated();

        PushDevice::query()
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'token',
                $validated['token'],
            )
            ->where(
                'environment',
                $validated[
                    'environment'
                ],
            )
            ->update([
                'is_active' => false,
            ]);

        return response()->json([
            'message' =>
                'Push device unregistered successfully.',
        ]);
    }
}
