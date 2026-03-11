<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';

use Core\Controller;
use Models\MessageSentModel;
use ApiHelper;

class MessageSentController extends Controller
{
    private MessageSentModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new MessageSentModel();
    }

    /**
     * GET /api/messages-sent?customerApiId=xxx[&limit=500&offset=0]
     * Retorna los mensajes enviados de un cliente.
     */
    public function index(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerApiId = trim($_GET['customerApiId'] ?? '');

        if ($customerApiId === '') {
            ApiHelper::respond(['error' => 'El parámetro customerApiId es obligatorio'], 422);
        }

        $limit  = max(1, min(1000, (int) ($_GET['limit']  ?? 500)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $records = $this->model->findByCustomer($customerApiId, $limit, $offset);

        ApiHelper::respond([
            'customerApiId' => $customerApiId,
            'data' => $records,
        ]);
    }

    /**
     * GET /api/messages-sent/:id
     * Retorna un registro por su Id.
     */
    public function show(string $id): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $record = $this->model->findById($id);

        if ($record === null) {
            ApiHelper::respond(['error' => 'Registro no encontrado'], 404);
        }

        ApiHelper::respond($record);
    }

    /**
     * POST /api/messages-sent
     * Crea un nuevo registro de mensaje enviado.
     * Body: { id, userId, customerApiId, phoneNumber, message, sentDay, sentHour, successful, errorMessage, sync }
     */
    public function store(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerApiId = trim($payload['customerApiId'] ?? '');

        if ($customerApiId === '') {
            ApiHelper::respond(['error' => 'El campo customerApiId es obligatorio'], 422);
        }

        $id = trim($payload['id'] ?? $payload['Id'] ?? '');
        if ($id === '') {
            ApiHelper::respond(['error' => 'El campo id es obligatorio'], 422);
        }

        $record   = $this->mapPayload($payload);
        $success  = $this->model->create($record);

        ApiHelper::respond(['id' => $id, 'success' => $success], $success ? 201 : 422);
    }

    /**
     * PUT /api/messages-sent/:id
     * Actualiza un registro existente.
     */
    public function update(string $id): void
    {
        ApiHelper::respondIfOptions();

        $payload = ApiHelper::getJsonBody();
        $record  = $this->mapPayload($payload);
        $success = $this->model->update($id, $record);

        ApiHelper::respond(['id' => $id, 'success' => $success], $success ? 200 : 422);
    }

    /**
     * DELETE /api/messages-sent/:id
     * Elimina un registro.
     */
    public function destroy(string $id): void
    {
        ApiHelper::respondIfOptions();

        $success = $this->model->delete($id);

        ApiHelper::respond(['id' => $id, 'success' => $success], $success ? 200 : 404);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mapPayload(array $payload): array
    {
        return [
            'Id'           => $payload['id']           ?? $payload['Id']           ?? null,
            'UserId'       => $payload['userId']       ?? $payload['UserId']       ?? null,
            'Username'     => $payload['username']     ?? $payload['Username']     ?? null,
            'CustomerApiId'=> $payload['customerApiId']?? $payload['CustomerApiId']?? null,
            'PhoneNumber'  => $payload['phoneNumber']  ?? $payload['PhoneNumber']  ?? null,
            'Message'      => $payload['message']      ?? $payload['Message']      ?? null,
            'SentDay'      => $payload['sentDay']      ?? $payload['SentDay']      ?? null,
            'SentHour'     => $payload['sentHour']     ?? $payload['SentHour']     ?? null,
            'Successful'   => $payload['successful']   ?? $payload['Successful']   ?? 0,
            'ErrorMessage' => $payload['errorMessage'] ?? $payload['ErrorMessage'] ?? null,
            'Sync'         => $payload['sync']         ?? $payload['Sync']         ?? 0,
        ];
    }
}
