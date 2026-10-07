<?php

namespace Controllers;

use App\Exceptions\ApiException;
use App\Middleware\CustomerJwtMiddleware;
use App\Middleware\DesktopJwtMiddleware;
use App\Services\MemberImageService;
use App\Services\MemberImageStorage;
use App\Services\MemberImageUpload;

class MemberImagesController
{
    private function execute(string $id, bool $write, bool $portal = false): void
    {
        header('Cache-Control: no-store');
        $customerId = $portal ? DesktopJwtMiddleware::getCurrentCustomerId() : CustomerJwtMiddleware::getCurrentCustomerId();
        if (!$customerId) { throw new ApiException('Autenticacion requerida', 401, [], 'TOKEN_MISSING'); }
        try {
            $service = new MemberImageService(new \Database(), new MemberImageStorage((string) (getenv('MEMBER_IMAGES_DIRECTORY') ?: '')));
            if ($write) {
                if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MemberImageUpload::MAX_REQUEST_BYTES) {
                    throw new ApiException('Imagen demasiado grande', 413, [], 'IMAGE_TOO_LARGE');
                }
                $body = file_get_contents('php://input', false, null, 0, MemberImageUpload::MAX_REQUEST_BYTES + 1);
                $upload = MemberImageUpload::parse($_SERVER['CONTENT_TYPE'] ?? '', $body === false ? '' : $body);
                $result = $service->put($customerId, $id, $upload);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode($result);
            } else {
                $image = $service->get($customerId, $id);
                header('Content-Type: ' . $image['contentType']);
                header('Content-Length: ' . strlen($image['bytes']));
                header('Cache-Control: private, no-store');
                header('X-Content-Type-Options: nosniff');
                echo $image['bytes'];
            }
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Do not expose database details, identifiers, paths, or image bytes.
            throw new ApiException('Servicio de imagenes temporalmente no disponible', 503, [], 'IMAGE_TEMPORARY_FAILURE');
        }
    }

    public function put(string $id): void { $this->execute($id, true); }
    public function get(string $id): void { $this->execute($id, false); }
    public function portalGet(string $id): void { $this->execute($id, false, true); }
}
