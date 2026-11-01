<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IWhatsAppCustomerRepository;
use Throwable;

final readonly class WhatsAppCustomerRepository implements IWhatsAppCustomerRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function monthlySuccessfulCount(string $customerId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM MessageSent
             WHERE Successful = 1
               AND IsDebug = 0
               AND CustomerApiId = ?
               AND MONTH(DateSent) = MONTH(CURRENT_DATE())
               AND YEAR(DateSent) = YEAR(CURRENT_DATE())',
            [$customerId],
        );

        return (int) ($row['total'] ?? 0);
    }

    public function searchMessages(
        string $customerId,
        array $filters,
        int $page,
        int $perPage,
    ): array {
        $where = ['CustomerApiId = ?', 'IsDebug = 0'];
        $parameters = [$customerId];

        if (!empty($filters['startDate'])) {
            $where[] = 'DateSent >= ?';
            $parameters[] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $where[] = 'DateSent <= ?';
            $parameters[] = $filters['endDate'];
        }

        $status = strtolower((string) ($filters['status'] ?? ''));
        if ($status === 'success') {
            $where[] = 'Successful = 1';
        } elseif ($status === 'failed') {
            $where[] = 'Successful = 0';
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(PhoneNumber LIKE ? OR Message LIKE ? OR ErrorMessage LIKE ? OR Username LIKE ?)';
            array_push($parameters, $search, $search, $search, $search);
        }

        $whereClause = implode(' AND ', $where);
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM MessageSent WHERE {$whereClause}",
            $parameters,
        );
        $total = (int) ($count['total'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT * FROM MessageSent
             WHERE {$whereClause}
             ORDER BY DateSent DESC
             LIMIT ? OFFSET ?",
            array_merge($parameters, [$perPage, $offset]),
        );

        foreach ($rows as &$row) {
            unset($row['Debug'], $row['IsDebug']);
        }
        unset($row);

        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1,
            ],
        ];
    }

    public function configurationByCustomer(string $customerId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM WhatsAppConfigurations
             WHERE CustomerId = ? AND IsActive = 1
             LIMIT 1',
            [$customerId],
        );
    }

    public function configurationById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM WhatsAppConfigurations WHERE Id = ? LIMIT 1',
            [$id],
        );
    }

    public function upsertConfiguration(array $data): array
    {
        $existing = $this->configurationByCustomer((string) $data['CustomerId']);
        $websites = isset($data['BusinessWebsites'])
            ? json_encode($data['BusinessWebsites'])
            : null;
        $now = date('Y-m-d H:i:s');

        try {
            if ($existing !== null) {
                $id = (string) $existing['Id'];
                $this->db->execute(
                    'UPDATE WhatsAppConfigurations
                     SET PhoneNumber = ?, PhoneNumberId = ?, AccessToken = ?,
                         BusinessName = ?, BusinessAddress = ?, BusinessDescription = ?,
                         BusinessEmail = ?, BusinessVertical = ?, BusinessWebsites = ?,
                         UpdatedAt = ?
                     WHERE Id = ?',
                    [
                        $data['PhoneNumber'],
                        $data['PhoneNumberId'],
                        $data['AccessToken'] ?? null,
                        $data['BusinessName'],
                        $data['BusinessAddress'] ?? null,
                        $data['BusinessDescription'] ?? null,
                        $data['BusinessEmail'] ?? null,
                        $data['BusinessVertical'] ?? null,
                        $websites,
                        $now,
                        $id,
                    ],
                );

                return [
                    'success' => true,
                    'id' => $id,
                    'created' => false,
                    'error' => null,
                ];
            }

            $id = $this->uuid();
            $this->db->execute(
                'INSERT INTO WhatsAppConfigurations (
                    Id, CustomerId, PhoneNumber, PhoneNumberId, AccessToken,
                    BusinessName, BusinessAddress, BusinessDescription,
                    BusinessEmail, BusinessVertical, BusinessWebsites,
                    IsActive, CreatedAt, UpdatedAt, CreatedBy
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
                [
                    $id,
                    $data['CustomerId'],
                    $data['PhoneNumber'],
                    $data['PhoneNumberId'],
                    $data['AccessToken'] ?? null,
                    $data['BusinessName'],
                    $data['BusinessAddress'] ?? null,
                    $data['BusinessDescription'] ?? null,
                    $data['BusinessEmail'] ?? null,
                    $data['BusinessVertical'] ?? null,
                    $websites,
                    $now,
                    $now,
                    $data['CreatedBy'] ?? null,
                ],
            );

            return [
                'success' => true,
                'id' => $id,
                'created' => true,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'id' => null,
                'created' => false,
                'error' => 'Error al guardar configuración: ' . $exception->getMessage(),
            ];
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
