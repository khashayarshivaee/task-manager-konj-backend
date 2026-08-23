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

        $account = new InstagramAccount();

        $account->workspace_id = $workspaceId;
        $account->instagram_id = $accountInfo['id'];
        $account->username = $accountInfo['username'];

        $account->setAccessToken($accessToken);

        $account->is_active = true;

        $account->save();

        return $account;
    }
}
