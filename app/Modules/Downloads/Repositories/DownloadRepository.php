<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class DownloadRepository implements IDownloadRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function latestVersion(): array
    {
        $row = $this->db->fetchOne(
            'SELECT Id,
                    Name,
                    Url,
                    Sha256,
                    SetupUrl,
                    SetupSha256,
                    SetupFileSize,
                    IsMandatory,
                    ReleaseNotes,
                    UploadDate
             FROM AppVersions
             ORDER BY UploadDate DESC, Id DESC
             LIMIT 1',
        );

        if ($row === null) {
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
            ];
        }

        return [
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
        ];
    }

    public function saveVersion(array $version): bool
    {
        try {
            $this->db->execute(
                'INSERT INTO AppVersions (
                    Name, Url, Sha256, SetupUrl, SetupSha256, SetupFileSize,
                    IsMandatory, ReleaseNotes, UploadDate
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    Url = VALUES(Url),
                    Sha256 = VALUES(Sha256),
                    SetupUrl = VALUES(SetupUrl),
                    SetupSha256 = VALUES(SetupSha256),
                    SetupFileSize = VALUES(SetupFileSize),
                    IsMandatory = VALUES(IsMandatory),
                    ReleaseNotes = VALUES(ReleaseNotes),
                    UploadDate = VALUES(UploadDate)',
                [
                    $version['name'],
                    $version['url'],
                    $version['sha256'],
                    $version['setupUrl'],
                    $version['setupSha256'],
                    $version['setupFileSize'],
                    $version['mandatory'] ? 1 : 0,
                    $version['releaseNotes'],
                    $version['uploadDate'],
                ],
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function recordDownload(array $download): void
    {
        $this->db->insert('DownloadLogs', [
            'DownloadType' => $download['type'],
            'Version' => $download['version'],
            'FileName' => $download['filename'],
            'IpAddress' => $download['ipAddress'],
            'UserAgent' => $download['userAgent'],
            'Referrer' => $download['referrer'],
            'FileSize' => $download['fileSize'],
            'DownloadedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    public function grouped(int $page, int $perPage, ?string $ip): array
    {
        $offset = ($page - 1) * $perPage;
        $where = $ip ? 'WHERE IpAddress LIKE ?' : '';
        $params = $ip ? ["%{$ip}%"] : [];
        $count = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT IpAddress) Total
             FROM DownloadLogs {$where}",
            $params,
        );
        $rows = $this->db->fetchAll(
            "SELECT IpAddress,
                    COUNT(*) TotalDownloads,
                    COUNT(CASE WHEN DownloadType='exe' THEN 1 END) ExeDownloads,
                    COUNT(CASE WHEN DownloadType='setup' THEN 1 END) SetupDownloads,
                    GROUP_CONCAT(DISTINCT Version ORDER BY Version DESC SEPARATOR ', ') Versions,
                    MAX(DownloadedAt) LastDownload,
                    MIN(DownloadedAt) FirstDownload,
                    MAX(UserAgent) LastUserAgent
             FROM DownloadLogs {$where}
             GROUP BY IpAddress
             ORDER BY LastDownload DESC
             LIMIT ? OFFSET ?",
            array_merge(
                $params,
                [
                    $perPage,
                    $offset,
                ],
            ),
        );
        $total = (int) ($count['Total'] ?? 0);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    public function summary(): array
    {
        return $this->db->fetchOne(
            "SELECT COUNT(*) TotalDownloads,
                    COUNT(DISTINCT IpAddress) UniqueIps,
                    COUNT(CASE WHEN DownloadType = 'exe' THEN 1 END) TotalExe,
                    COUNT(CASE WHEN DownloadType = 'setup' THEN 1 END) TotalSetup,
                    COUNT(CASE WHEN DATE(DownloadedAt) = CURDATE() THEN 1 END) TodayDownloads,
                    COUNT(CASE WHEN DownloadedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) WeekDownloads,
                    COUNT(CASE WHEN DownloadedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) MonthDownloads
             FROM DownloadLogs",
        ) ?? [];
    }

    public function byIp(string $ip, int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT *
             FROM DownloadLogs
             WHERE IpAddress = ?
             ORDER BY DownloadedAt DESC
             LIMIT ?',
            [
                $ip,
                $limit,
            ],
        );
    }
}
