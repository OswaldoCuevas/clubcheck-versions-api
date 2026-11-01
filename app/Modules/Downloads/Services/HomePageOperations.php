<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Services;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class HomePageOperations
{
    public function __construct(
        private IDownloadRepository $repository,
        private ICurrentUserProvider $currentUser,
        private ReleaseUploadService $uploads,
    ) {
    }

    public function show(): array
    {
        return $this->viewData('', '');
    }

    public function upload(array $fields, array $files, array $server): array
    {
        $result = $this->uploads->upload($fields, $files, $server);

        return $this->viewData($result['message'], $result['type']);
    }

    private function viewData(string $message, string $messageType): array
    {
        $user = $this->currentUser->current();
        $permissions = is_array($user['permissions'] ?? null)
            ? $user['permissions']
            : [];

        return [
            'message' => $message,
            'messageType' => $messageType,
            'isAuthenticated' => $user !== [],
            'canUpload' => in_array('upload_files', $permissions, true),
            'canAdminAccess' => in_array('admin_access', $permissions, true),
            'currentUser' => $user !== [] ? $user : null,
            'currentVersion' => $this->repository->latestVersion(),
        ];
    }
}
