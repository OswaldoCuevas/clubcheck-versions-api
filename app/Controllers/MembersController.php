<?php
namespace Controllers;

require_once __DIR__ . '/../Services/MembersService.php';

use App\Services\MembersService;
use App\Exceptions\MemberException;

/** Adaptador HTTP del dominio members; las rutas administrativas conservan identidad propia. */
class MembersController
{
    /** Expone identidad, membresía y actividad del socio seleccionado en el dashboard. */
    public function dashboard(): void { $this->run('dashboard', true); }

    /** Expone pagos acreditados paginados, aislados por el contexto JWT vigente. */
    public function payments(): void { $this->run('payments', true); }

    /** Actualiza la meta recurrente del socio seleccionado y devuelve progreso confirmado. */
    public function monthlyGoal(): void { $this->run('monthlyGoal', true); }

    /** Registra una cuenta global y solicita correo confirmado antes del acceso privado. */
    public function register(): void { $this->run('register'); }

    /** Confirma correo únicamente por POST y sin entregar JWT privado. */
    public function verifyEmail(): void { $this->run('verifyEmail'); }

    /** Reenvía confirmación con los límites persistentes del dominio. */
    public function resendVerification(): void { $this->run('resend'); }

    /** Inicia una sesión exclusiva members cuando el correo está confirmado. */
    public function login(): void { $this->run('login'); }

    /** Captura QR/código sin consumir la invitación. */
    public function invitationContext(): void { $this->run('invitationContext'); }

    /** Revoca todos los tokens derivados de la sesión actual. */
    public function logout(): void { $this->run('logout', true); }

    /** Recupera cuenta y continuidad pendiente, incluso con una relación bloqueada. */
    public function me(): void { $this->run('me', true); }

    /** Devuelve relaciones propias habilitadas y deshabilitadas para el selector. */
    public function links(): void { $this->run('links', true); }

    /** Selecciona contexto autorizado sin prolongar la sesión. */
    public function context(): void { $this->run('selectContext', true); }

    /** Canjea invitación en transacción sin transferir titularidad. */
    public function redeem(): void { $this->run('redeem', true); }

    /** Emite invitación solo con identidad individual y permiso explícito. */
    public function issue(): void { $this->run('issue'); }

    /** Lista cuentas locales únicamente para el dueño. */
    public function accounts(): void { $this->run('accounts'); }

    /** Deshabilita una relación del gimnasio autenticado conservando titularidad. */
    public function disable(string $linkId): void { $this->run('disable', false, $linkId); }

    /** Acredita al operador de escritorio además de validar su máquina en el servidor. */
    public function operatorSession(): void { $this->run('operatorSession'); }

    /** Lee Bearer sin aceptar tokens en query string ni registrarlos. */
    private function bearer(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer ([^\s]+)$/i', $header, $matches)) {
            throw new MemberException('Se requiere Bearer.', 401, [], 'SESSION_INVALID');
        }
        return $matches[1];
    }

    /** Valida JSON objeto y tamaño del cuerpo del contrato PascalCase. */
    private function body(): array
    {
        $input = file_get_contents('php://input', false, null, 0, 16385);
        if (strlen($input) > 16384 || !str_starts_with(ltrim($input), '{')) {
            throw new MemberException('Cuerpo JSON inválido.', 400, [], 'INVALID_INPUT');
        }
        $data = json_decode($input, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new MemberException('Cuerpo JSON inválido.', 400, [], 'INVALID_INPUT');
        }
        return $data;
    }

    /** Ejecuta autorización antes del negocio y entrega errores tipados sin detalles de persistencia. */
    private function run(string $action, bool $member = false, ?string $linkId = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        try {
            if (in_array($action, ['dashboard', 'payments', 'monthlyGoal'], true)) {
                require_once __DIR__ . '/../Services/MemberDashboardService.php';
                require_once __DIR__ . '/../../utils/database.php';
                $database = new \Database();
                $service = new MembersService($database);
                $identity = $service->authenticate($this->bearer());
                $resources = new \App\Services\MemberDashboardService($database, $service);
                $data = $action === 'monthlyGoal' ? $this->body() : [];
                $result = $action === 'monthlyGoal' ? $resources->monthlyGoal($identity, $data, $_GET) : $resources->$action($identity, $_GET);
            } else {
                $service = new MembersService();
                $data = in_array($action, ['me', 'links', 'accounts', 'logout'], true) ? [] : $this->body();
                if ($member) {
                    // Operaciones de recuperación global no exigen que siga habilitado el contexto seleccionado.
                    $identity = $service->authenticate($this->bearer(), false);
                    $result = match ($action) {
                        'me' => ['Account' => $service->accountView($identity['Account'])],
                        'links' => $service->links($identity['Account']['Id']),
                        'logout' => $service->logout($identity),
                        default => $service->$action($identity, $data),
                    };
                } elseif (in_array($action, ['issue', 'accounts', 'disable'], true)) {
                    $operator = $service->operator($this->bearer());
                    if ($action === 'issue') {
                        $requestId = $_SERVER['HTTP_REQUESTID'] ?? $_SERVER['HTTP_REQUEST_ID'] ?? $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
                        if ($requestId !== null) { $data['RequestId'] = $requestId; }
                    }
                    $result = match ($action) {
                        'accounts' => $service->accounts($operator, $_GET),
                        'disable' => $service->disable($operator, $linkId, $data),
                        default => $service->issue($operator, $data),
                    };
                } elseif ($action === 'operatorSession') {
                    require_once __DIR__ . '/../Services/CustomerJwtService.php';
                    $machine = (new \App\Services\CustomerJwtService())->validateCustomerToken($this->bearer());
                    if (!$machine['valid']) {
                        throw new MemberException('Máquina no autorizada.', 401, [], 'SESSION_INVALID');
                    }
                    $result = $service->operatorSession($machine['customerId'], $data);
                } else {
                    $result = $service->$action($data);
                }
            }
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (MemberException $e) {
            http_response_code($e->getStatusCode());
            echo json_encode($e->toArray(), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            // Nunca incluir SQL, datos de petición o secretos en respuestas/logs.
            http_response_code(503);
            echo json_encode(['Code' => 'TEMPORARILY_UNAVAILABLE', 'Message' => 'Servicio temporalmente no disponible.']);
        }
    }
}
