<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstagramAccount;

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
        $accountInfo = $this->instagramApiService
            ->getMe($accessToken);

        $account = InstagramAccount::updateOrCreate(
            [
                'instagram_id' => $accountInfo['id'],
            ],
            [
                'workspace_id' => $workspaceId,
                'username' => $accountInfo['username'],
                'is_active' => true,
            ]
        );

        $account->setAccessToken($accessToken);

        $account->save();

        return $account;
    }
}
