<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstagramAccount;
use Illuminate\Validation\ValidationException;

class InstagramConnectionService
{
    public function __construct(
        private InstagramApiService $instagramApiService
    ) {
    }

    public function connect(
        string $accessToken,
        int $workspaceId
    ): InstagramAccount {
        $accountInfo =
            $this->instagramApiService->getMe(
                $accessToken
            );

        $instagramId =
            (string) $accountInfo['id'];

        $existingAccount =
            InstagramAccount::query()
                ->where(
                    'instagram_id',
                    $instagramId
                )
                ->first();

        if (
            $existingAccount !== null
            && $existingAccount->workspace_id
            !== $workspaceId
        ) {
            throw ValidationException::withMessages([
                'instagram_account' => [
                    'This Instagram account is already connected to another workspace.',
                ],
            ]);
        }

        $account =
            $existingAccount
            ?? new InstagramAccount();

        $account->workspace_id =
            $workspaceId;

        $account->instagram_id =
            $instagramId;

        $account->username =
            (string) $accountInfo['username'];

        $account->is_active =
            true;

        $account->setAccessToken(
            $accessToken
        );

        $account->save();

        return $account;
    }

    public function disconnect(
        int $workspaceId
    ): ?InstagramAccount {
        $account = InstagramAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return null;
        }

        $account->is_active = false;
        $account->save();

        return $account;
    }
}
