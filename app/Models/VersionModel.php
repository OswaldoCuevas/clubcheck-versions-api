<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/ApplicationModel.php';

use Core\Model;
use Models\ApplicationModel;

class VersionModel extends Model
{
    private function appModel(): ApplicationModel
    {
        return new ApplicationModel();
    }

    private function hasAppField(): bool
    {
        return $this->appModel()->columnExists('AppVersions', 'AppId');
    }

    private function resolveAppId(?string $appId): string
    {
        return $appId ?: $this->appModel()->getDefaultApp()['id'];
    }

    public function getLatestVersion(?string $appId = null): array
    {
        $hasAppField = $this->hasAppField();
        $where = '';
        $params = [];

        if ($hasAppField) {
            // Las versiones son por app; sin migracion se conserva el comportamiento global anterior.
            $where = 'WHERE `AppId` = ?';
            $params[] = $this->resolveAppId($appId);
        }

        $sql = "SELECT `Id`, `Name`, `Url`, `Sha256`, `SetupUrl`, `SetupSha256`, `SetupFileSize`, `IsMandatory`, `ReleaseNotes`, `UploadDate`"
            . ($hasAppField ? ', `AppId`' : '')
            . " FROM `AppVersions`
                {$where}
                ORDER BY `UploadDate` DESC, `Id` DESC
                LIMIT 1";

        $row = $this->db->fetchOne($sql, $params);
        if ($row) {
            return $this->mapVersion($row);
        }

        return [
            'latestVersion' => '0.0.0.0',
            'url' => '',
            'sha256' => '',
            'setupUrl' => '',
            'setupSha256' => '',
            'setupFileSize' => null,
            'mandatory' => false,
            'releaseNotes' => '',
            'uploadDate' => null,
            'timestamp' => null,
            'appId' => $this->resolveAppId($appId),
        ];
    }

    public function saveVersion(
        $version,
        $url,
        $sha256,
        $isMandatory,
        $releaseNotes,
        $uploadDate,
        $setupUrl = '',
        $setupSha256 = '',
        $setupFileSize = null,
        ?string $appId = null
    ): bool {
        $columns = ['Name', 'Url', 'Sha256', 'SetupUrl', 'SetupSha256', 'SetupFileSize', 'IsMandatory', 'ReleaseNotes', 'UploadDate'];
        $params = [
            $version,
            $url,
            $sha256,
            $setupUrl,
            $setupSha256,
            $setupFileSize ? (int) $setupFileSize : null,
            $isMandatory ? 1 : 0,
            $releaseNotes,
            $uploadDate,
        ];

        if ($this->hasAppField()) {
            array_unshift($columns, 'AppId');
            array_unshift($params, $this->resolveAppId($appId));
        }

        $fields = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = "INSERT INTO `AppVersions`
                ({$fields})
                VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE
                `Url` = VALUES(`Url`),
                `Sha256` = VALUES(`Sha256`),
                `SetupUrl` = VALUES(`SetupUrl`),
                `SetupSha256` = VALUES(`SetupSha256`),
                `SetupFileSize` = VALUES(`SetupFileSize`),
                `IsMandatory` = VALUES(`IsMandatory`),
                `ReleaseNotes` = VALUES(`ReleaseNotes`),
                `UploadDate` = VALUES(`UploadDate`)";

        $this->db->execute_query($sql, $params);
        return true;
    }

    public function versionExists($version, ?string $appId = null): bool
    {
        $where = '`Name` = ?';
        $params = [$version];

        if ($this->hasAppField()) {
            $where .= ' AND `AppId` = ?';
            $params[] = $this->resolveAppId($appId);
        }

        $row = $this->db->fetchOne("SELECT `Id` FROM `AppVersions` WHERE {$where} LIMIT 1", $params);

        return $row !== null;
    }

    public function getVersion($version, ?string $appId = null): ?array
    {
        $hasAppField = $this->hasAppField();
        $where = '`Name` = ?';
        $params = [$version];

        if ($hasAppField) {
            $where .= ' AND `AppId` = ?';
            $params[] = $this->resolveAppId($appId);
        }

        $sql = "SELECT `Id`, `Name`, `Url`, `Sha256`, `SetupUrl`, `SetupSha256`, `SetupFileSize`, `IsMandatory`, `ReleaseNotes`, `UploadDate`"
            . ($hasAppField ? ', `AppId`' : '')
            . " FROM `AppVersions`
                WHERE {$where}
                LIMIT 1";

        $row = $this->db->fetchOne($sql, $params);

        return $row ? $this->mapVersion($row) : null;
    }

    private function mapVersion(array $row): array
    {
        return [
            'id' => $row['Id'],
            'latestVersion' => $row['Name'],
            'url' => $row['Url'],
            'sha256' => $row['Sha256'],
            'setupUrl' => $row['SetupUrl'],
            'setupSha256' => $row['SetupSha256'],
            'setupFileSize' => $row['SetupFileSize'],
            'mandatory' => (bool) $row['IsMandatory'],
            'releaseNotes' => $row['ReleaseNotes'],
            'uploadDate' => $row['UploadDate'],
            'timestamp' => $row['UploadDate'],
            'appId' => $row['AppId'] ?? $this->appModel()->getDefaultApp()['id'],
        ];
    }
}
