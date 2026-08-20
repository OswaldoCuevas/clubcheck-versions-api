<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../enums/WhatsAppEvent.php';

use App\Enums\WhatsAppEvent;
use Core\Model;

class WhatsAppTemplateModel extends Model
{
    private string $templatesTable = 'CustomerWhatsAppTemplates';
    private string $eventsTable = 'CustomerWhatsAppTemplateEvents';
    private string $variablesTable = 'WhatsAppTemplateVariables';

    protected function initialize()
    {
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function getVariables(): array
    {
        return $this->db->fetchAll("SELECT * FROM {$this->variablesTable} WHERE IsActive = 1 ORDER BY SortOrder ASC, Name ASC");
    }

    public function getAllWithEvents(): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, e.EventKey
             FROM {$this->templatesTable} t
             LEFT JOIN {$this->eventsTable} e ON e.TemplateId = t.Id AND e.IsActive = 1
             ORDER BY t.CreatedAt DESC"
        );
    }

    public function findActiveForEvent(string $customerId, WhatsAppEvent $event): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT t.*, e.EventKey
             FROM {$this->eventsTable} e
             INNER JOIN {$this->templatesTable} t ON t.Id = e.TemplateId
             WHERE e.CustomerId = ? AND e.EventKey = ? AND e.IsActive = 1 AND t.IsActive = 1
             LIMIT 1",
            [$customerId, $event->value]
        );

        if (!$row) {
            return null;
        }

        $row['Components'] = $row['ComponentsJson'] ? json_decode($row['ComponentsJson'], true) : [];
        return $row;
    }

    public function createTemplate(array $data): array
    {
        if (empty($data['CustomerId']) || empty($data['TemplateName'])) {
            return ['success' => false, 'error' => 'CustomerId y TemplateName son requeridos'];
        }

        $id = $this->uuid();
        $components = $data['ComponentsJson'] ?? '[]';
        if (is_array($components)) {
            $components = json_encode($components, JSON_UNESCAPED_UNICODE);
        }

        $this->db->execute_query(
            "INSERT INTO {$this->templatesTable}
             (Id, CustomerId, TemplateName, LanguageCode, Description, ComponentsJson, IsActive, CreatedAt, UpdatedAt, CreatedBy)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), ?)",
            [
                $id,
                $data['CustomerId'],
                $data['TemplateName'],
                $data['LanguageCode'] ?? 'es_MX',
                $data['Description'] ?? null,
                $components,
                $data['CreatedBy'] ?? null,
            ]
        );

        if (!empty($data['EventKey'])) {
            $event = WhatsAppEvent::tryFrom($data['EventKey']);
            if ($event) {
                $this->assignEvent($data['CustomerId'], $id, $event, $data['CreatedBy'] ?? null);
            }
        }

        return ['success' => true, 'id' => $id, 'error' => null];
    }

    public function assignEvent(string $customerId, string $templateId, WhatsAppEvent $event, ?string $updatedBy = null): void
    {
        $this->db->execute_query(
            "UPDATE {$this->eventsTable} SET IsActive = 0, UpdatedAt = NOW(), UpdatedBy = ?
             WHERE CustomerId = ? AND EventKey = ?",
            [$updatedBy, $customerId, $event->value]
        );

        $this->db->execute_query(
            "INSERT INTO {$this->eventsTable}
             (Id, CustomerId, EventKey, TemplateId, IsActive, CreatedAt, UpdatedAt, CreatedBy)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?)",
            [$this->uuid(), $customerId, $event->value, $templateId, $updatedBy]
        );
    }

    public function deleteTemplate(string $id): array
    {
        $this->db->execute_query("UPDATE {$this->templatesTable} SET IsActive = 0, UpdatedAt = NOW() WHERE Id = ?", [$id]);
        $this->db->execute_query("UPDATE {$this->eventsTable} SET IsActive = 0, UpdatedAt = NOW() WHERE TemplateId = ?", [$id]);
        return ['success' => true, 'error' => null];
    }
}
