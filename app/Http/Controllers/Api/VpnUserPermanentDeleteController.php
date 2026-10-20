<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use App\Services\XrayManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class VpnUserPermanentDeleteController extends Controller
{
    public function __invoke(
        Request $request,
        VpnUser $vpnUser,
        XrayManagerService $xrayManagerService,
    ): JsonResponse {
        $validated = $request->validate([
            'confirmation' => [
                'required',
                'string',
                'in:DELETE',
            ],
            'confirm_name' => [
                'required',
                'string',
            ],
        ]);

        if (! hash_equals(
            $vpnUser->name,
            $validated['confirm_name'],
        )) {
            return response()->json([
                'message' =>
                    'VPN user name confirmation does not match.',
            ], 422);
        }

        try {
            // Check the currently configured Xray instance.
            // Legacy users may not exist in Xray V2.
            $emails = $xrayManagerService
                ->getInboundUserEmails();

            $existsInXray = in_array(
                $vpnUser->xray_email,
                $emails,
                true,
            );

            // Revoke the active Xray credential
            // before removing the database record.
            if ($existsInXray) {
                $xrayManagerService->removeUser(
                    $vpnUser,
                );
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    'Unable to verify or remove VPN user from Xray.',
            ], 503);
        }

        try {
            DB::transaction(function () use ($vpnUser): void {
                $vpnUser->delete();
            });
        } catch (Throwable $exception) {
            report($exception);

            // Best-effort recovery if DB deletion fails.
            // Keep the Xray runtime consistent with the DB.
            if ($existsInXray) {
                try {
                    $xrayManagerService->addUser(
                        $vpnUser,
                    );
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }

            return response()->json([
                'message' =>
                    'Unable to permanently delete VPN user.',
            ], 500);
        }

        return response()->json([
            'message' =>
                'VPN user permanently deleted.',
            'data' => [
                'id' => $vpnUser->id,
                'name' => $vpnUser->name,
                'deleted' => true,
            ],
        ]);
    }
}
