<?php

namespace Controllers;


use ApiHelper;
use Core\Controller;
use Models\AnnouncementModel;

class AnnouncementsController extends Controller
{
    private AnnouncementModel $announcements;

    public function __construct()
    {
        parent::__construct();
        $this->announcements = new AnnouncementModel();
    }

    public function current(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = ApiHelper::getCustomerIdFromSession();
        if (!$customerId) {
            ApiHelper::respond(['error' => 'No se pudo obtener el cliente del token'], 401);
        }

        $currentClientVersion = $this->currentClientVersionFromRequest();
        $announcement = $this->announcements->pendingForCustomer($customerId, $currentClientVersion);
        if (!$announcement) {
            ApiHelper::respond(['hasAnnouncement' => false, 'announcement' => null]);
        }

        ApiHelper::respond([
            'hasAnnouncement' => true,
            'announcement' => [
                'id' => $announcement['id'],
                'version' => $announcement['version'],
                'title' => $announcement['title'],
                'subtitle' => $announcement['subtitle'],
                'slides' => $announcement['slides'],
            ],
        ]);
    }

    private function currentClientVersionFromRequest(): ?string
    {
        $version = $_GET['version'] ?? $_GET['currentVersion'] ?? $_SERVER['HTTP_X_CLIENT_VERSION'] ?? null;
        if ($version === null) {
            return null;
        }

        $version = trim((string) $version);
        return $version === '' ? null : substr($version, 0, 50);
    }

    public function viewed(string $id): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $customerId = ApiHelper::getCustomerIdFromSession();
        if (!$customerId) {
            ApiHelper::respond(['error' => 'No se pudo obtener el cliente del token'], 401);
        }

        if (!$this->announcements->find($id)) {
            ApiHelper::respond(['error' => 'Anuncio no encontrado'], 404);
        }

        $this->announcements->markViewed(
            $id,
            $customerId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        ApiHelper::respond(['success' => true]);
    }

    public function viewedCurrent(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $customerId = ApiHelper::getCustomerIdFromSession();
        if (!$customerId) {
            ApiHelper::respond(['error' => 'No se pudo obtener el cliente del token'], 401);
        }

        $announcement = $this->announcements->activeForCustomer($customerId);
        if (!$announcement) {
            ApiHelper::respond(['error' => 'No hay anuncio activo para este cliente'], 404);
        }

        $this->announcements->markViewed(
            $announcement['id'],
            $customerId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        ApiHelper::respond(['success' => true, 'announcementId' => $announcement['id']]);
    }
}
