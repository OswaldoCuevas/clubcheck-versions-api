<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\UserPhotos\Contracts\IUserPhotoRepository;
use App\Modules\UserPhotos\Contracts\IUserPhotoStorage;
use Throwable;

final readonly class UserPhotoOperations
{
    public function __construct(
        private IUserPhotoRepository $photos,
        private IUserPhotoStorage $storage,
        private UserPhotoProcessor $processor,
    ) {
    }

    public function upload(
        string $customerId,
        string $userId,
        string $contentType,
        int $contentLength,
        array $fields,
        array $file,
    ): array {
        if (!str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            $this->error(ApiErrorCode::UserPhotoInvalid, 'Se requiere multipart/form-data');
        }
        if ($file === [] && $contentLength > UserPhotoProcessor::MAX_BYTES) {
            $this->error(
                ApiErrorCode::UserPhotoTooLarge,
                'La imagen excede el limite de carga',
            );
        }
        if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $this->error(
                    ApiErrorCode::UserPhotoTooLarge,
                    'La imagen excede el limite de carga',
                );
            }
            $this->error(ApiErrorCode::UserPhotoInvalid, 'image es obligatoria');
        }
        $image = $this->processor->process($fields, $file);
        $this->photos->begin();
        $paths = [];
        $committing = false;
        try {
            if (!$this->photos->lockUser($customerId, $userId)) {
                $this->error(ApiErrorCode::UserPhotoNotFound, 'Socio no accesible');
            }
            $old = $this->photos->lockPhoto($customerId, $userId);
            if ($old !== null
                && ($image['version'] < (int) $old['Version']
                    || ($image['version'] === (int) $old['Version']
                        && $image['sha256'] !== $old['Sha256']))) {
                $this->error(
                    ApiErrorCode::UserPhotoConflict,
                    'Conflicto de version de fotografia',
                );
            }
            if ($old !== null && $image['version'] === (int) $old['Version']) {
                $this->photos->commit();
                return $this->confirmation($userId, $old);
            }
            $key = bin2hex(random_bytes(32));
            $paths = $this->storage->store($key, $image['bytes'], $image['thumbnail']);
            $row = [
                'UserId' => $userId,
                'CustomerApiId' => $customerId,
                'Version' => $image['version'],
                'Sha256' => $image['sha256'],
                'Mime' => $image['mime'],
                'Size' => $image['size'],
                'StorageKey' => $key,
                'UpdatedAt' => gmdate('Y-m-d H:i:s'),
            ];
            $this->photos->save($row, $old !== null);
            $committing = true;
            $this->photos->commit();
        } catch (Throwable $exception) {
            try {
                $this->photos->rollback();
            } catch (Throwable) {
            }
            if (!$committing) {
                $this->storage->remove($paths);
            }
            throw $exception;
        }

        return $this->confirmation($userId, $row);
    }

    public function read(string $customerId, string $userId, string $variant): array
    {
        $photo = $this->photos->findAccessible($customerId, $userId);
        $validMime = $photo !== null
            && in_array($photo['Mime'], ['image/jpeg', 'image/png'], true);
        if ($photo === null || !$validMime) {
            $this->error(ApiErrorCode::UserPhotoNotFound, 'Fotografia no accesible');
        }
        $bytes = $this->storage->read($photo['StorageKey'], $variant);
        if ($bytes === null) {
            $this->error(ApiErrorCode::UserPhotoNotFound, 'Fotografia no accesible');
        }

        return [
            'bytes' => $bytes,
            'mime' => $variant === 'png' ? 'image/png' : $photo['Mime'],
        ];
    }

    private function confirmation(string $userId, array $row): array
    {
        return [
            'userId' => $userId,
            'photo' => [
                'version' => (int) $row['Version'],
                'sha256' => $row['Sha256'],
                'mime' => $row['Mime'],
                'size' => (int) $row['Size'],
                'updatedAt' => $row['UpdatedAt'],
            ],
        ];
    }

    private function error(ApiErrorCode $code, string $message): never
    {
        throw ApiException::preservingResponse($code, ['error' => $message]);
    }
}
