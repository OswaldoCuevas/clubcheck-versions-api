<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class WhatsAppAdminRepository implements IWhatsAppAdminRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function configurations(): array
    {
        return $this->db->fetchAll(
            'SELECT wc.*, c.Name CustomerName, c.Email CustomerEmail
             FROM WhatsAppConfigurations wc
             INNER JOIN Customers c ON c.Id=wc.CustomerId
             ORDER BY wc.CreatedAt DESC',
        );
    }
    public function hasConfiguration(string $id): bool
    {
        $r = $this->db->fetchOne('SELECT COUNT(*) total FROM WhatsAppConfigurations WHERE CustomerId=? AND IsActive=1', [$id]);
        return (int)($r['total'] ?? 0) > 0;
    }
    public function configuration(string $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM WhatsAppConfigurations WHERE Id=? LIMIT 1', [$id]);
    }
    public function createConfiguration(array $d): array
    {
        foreach (['CustomerId', 'PhoneNumber', 'PhoneNumberId', 'BusinessName'] as $f) {
            if (empty($d[$f])) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Campo requerido faltante: {$f}",
                ];
            }
        }

        $id = $this->uuid();
        $now = date('Y-m-d H:i:s');

        try {
            $websites = isset($d['BusinessWebsites'])
                ? (is_array($d['BusinessWebsites'])
                    ? json_encode($d['BusinessWebsites'])
                    : $d['BusinessWebsites'])
                : null;

            $this->db->execute(
                'INSERT INTO WhatsAppConfigurations (
                    Id, CustomerId, PhoneNumber, PhoneNumberId, AccessToken,
                    BusinessName, BusinessAddress, BusinessDescription,
                    BusinessEmail, BusinessVertical, BusinessWebsites,
                    ProfilePictureUrl, IsActive, CreatedAt, UpdatedAt, CreatedBy
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)',
                [
                    $id,
                    $d['CustomerId'],
                    $d['PhoneNumber'],
                    $d['PhoneNumberId'],
                    $d['AccessToken'] ?? null,
                    $d['BusinessName'],
                    $d['BusinessAddress'] ?? null,
                    $d['BusinessDescription'] ?? null,
                    $d['BusinessEmail'] ?? null,
                    $d['BusinessVertical'] ?? null,
                    $websites,
                    $d['ProfilePictureUrl'] ?? null,
                    $now,
                    $now,
                    $d['CreatedBy'] ?? null,
                ],
            );

            return [
                'success' => true,
                'id' => $id,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Error al crear configuración: ' . $e->getMessage(),
            ];
        }
    }
    public function deleteConfiguration(string $id): array
    {
        if (!$this->configuration($id)) {
            return [
                'success' => false,
                'error' => 'Configuración no encontrada',
            ];
        }

        try {
            return $this->db->delete('WhatsAppConfigurations', 'Id=?', [$id], 1)
                ? [
                    'success' => true,
                    'error' => null,
                ]
                : [
                    'success' => false,
                    'error' => 'No se pudo eliminar la configuración',
                ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar configuración: ' . $e->getMessage(),
            ];
        }
    }
    public function variables(): array
    {
        return $this->db->fetchAll('SELECT * FROM WhatsAppTemplateVariables WHERE IsActive=1 ORDER BY SortOrder ASC,Name ASC');
    }
    public function templates(): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, e.EventKey
             FROM CustomerWhatsAppTemplates t
             LEFT JOIN CustomerWhatsAppTemplateEvents e
                ON e.TemplateId=t.Id AND e.IsActive=1
             ORDER BY t.CreatedAt DESC',
        );
    }
    public function createTemplate(array $d): array
    {
        if (empty($d['CustomerId']) || empty($d['TemplateName'])) {
            return [
                'success' => false,
                'error' => 'CustomerId y TemplateName son requeridos',
            ];
        }

        $id = $this->uuid();
        $components = is_array($d['ComponentsJson'] ?? null)
            ? json_encode($d['ComponentsJson'], JSON_UNESCAPED_UNICODE)
            : ($d['ComponentsJson'] ?? '[]');

        $this->db->execute(
            'INSERT INTO CustomerWhatsAppTemplates (
                Id, CustomerId, TemplateName, LanguageCode, Description,
                ComponentsJson, IsActive, CreatedAt, UpdatedAt, CreatedBy
             ) VALUES (?,?,?,?,?,?,1,NOW(),NOW(),?)',
            [
                $id,
                $d['CustomerId'],
                $d['TemplateName'],
                $d['LanguageCode'] ?? 'es_MX',
                $d['Description'] ?? null,
                $components,
                $d['CreatedBy'] ?? null,
            ],
        );

        if (!empty($d['EventKey'])) {
            $this->db->execute(
                'UPDATE CustomerWhatsAppTemplateEvents
                 SET IsActive=0, UpdatedAt=NOW(), UpdatedBy=?
                 WHERE CustomerId=? AND EventKey=?',
                [$d['CreatedBy'] ?? null, $d['CustomerId'], $d['EventKey']],
            );
            $this->db->execute(
                'INSERT INTO CustomerWhatsAppTemplateEvents (
                    Id, CustomerId, EventKey, TemplateId, IsActive,
                    CreatedAt, UpdatedAt, CreatedBy
                 ) VALUES (?,?,?,?,1,NOW(),NOW(),?)',
                [$this->uuid(), $d['CustomerId'], $d['EventKey'], $id, $d['CreatedBy'] ?? null],
            );
        }

        return [
            'success' => true,
            'id' => $id,
            'error' => null,
        ];
    }
    public function deleteTemplate(string $id): array
    {
        $this->db->execute('UPDATE CustomerWhatsAppTemplates SET IsActive=0,UpdatedAt=NOW() WHERE Id=?', [$id]);
        $this->db->execute('UPDATE CustomerWhatsAppTemplateEvents SET IsActive=0,UpdatedAt=NOW() WHERE TemplateId=?', [$id]);
        return [
            'success' => true,
            'error' => null,
        ];
    }
    public function messages(array $f, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $w = [];
        $p = [];
        if (!empty($f['customerId'])) {
            $w[] = 'm.CustomerApiId=?';
            $p[] = $f['customerId'];
        }

        if (($f['status'] ?? '') === 'success') {
            $w[] = 'm.Successful=1';
        } elseif (($f['status'] ?? '') === 'failed') {
            $w[] = 'm.Successful=0';
        }

        if (($f['isDebug'] ?? '') === 'debug') {
            $w[] = 'm.IsDebug=1';
        } elseif (($f['isDebug'] ?? '') === 'normal') {
            $w[] = 'm.IsDebug=0';
        }

        if (!empty($f['from'])) {
            $w[] = 'm.DateSent>=?';
            $p[] = $f['from'] . ' 00:00:00';
        }

        if (!empty($f['to'])) {
            $w[] = 'm.DateSent<?';
            $p[] = (new \DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d 00:00:00');
        }

        if (!empty($f['error'])) {
            $w[] = 'm.ErrorMessage LIKE ?';
            $p[] = '%' . $f['error'] . '%';
        }

        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $summary = $this->db->fetchOne(
            "SELECT COUNT(*) total,
                    COALESCE(SUM(m.Successful=1), 0) successful,
                    COALESCE(SUM(m.Successful=0), 0) failed,
                    COUNT(
                        DISTINCT CASE WHEN m.Successful=0 THEN m.CustomerApiId END
                    ) customersWithErrors
             FROM MessageSent m
             {$where}",
            $p,
        ) ?? [];
        $total = (int)($summary['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $this->db->fetchAll(
            "SELECT m.Id,
                    m.CustomerApiId,
                    c.Name CustomerName,
                    m.DateSent,
                    m.PhoneNumber,
                    m.Username,
                    m.Message,
                    m.Successful,
                    m.ErrorMessage,
                    m.Debug,
                    m.IsDebug
             FROM MessageSent m
             LEFT JOIN Customers c ON c.Id=m.CustomerApiId
             {$where}
             ORDER BY m.DateSent DESC, m.Id DESC
             LIMIT ? OFFSET ?",
            array_merge($p, [$perPage, ($page - 1) * $perPage]),
        );

        return [
            'data' => $rows,
            'summary' => [
                'total' => $total,
                'successful' => (int)($summary['successful'] ?? 0),
                'failed' => (int)($summary['failed'] ?? 0),
                'customersWithErrors' => (int)($summary['customersWithErrors'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $pages,
            ],
        ];
    }
    public function monthlyMessageUsage(string $id): array
    {
        return $this->db->fetchOne(
            "SELECT COUNT(*) Total,
                    DATE_FORMAT(CURRENT_DATE(), '%Y-%m') ComparedMonth
             FROM MessageSent
             WHERE Successful=1
                AND IsDebug=0
                AND CustomerApiId=?
                AND MONTH(DateSent)=MONTH(CURRENT_DATE())
                AND YEAR(DateSent)=YEAR(CURRENT_DATE())",
            [$id],
        ) ?? [
            'Total' => 0,
            'ComparedMonth' => date('Y-m'),
        ];
    }
    private function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
