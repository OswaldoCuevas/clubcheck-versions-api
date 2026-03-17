<?php

namespace App\Services;

require_once __DIR__ . '/../Models/EmailCodeModel.php';
require_once __DIR__ . '/../Models/EmailTypeModel.php';
require_once __DIR__ . '/../enums/EmailType.php';

use Models\EmailCodeModel;
use Models\EmailTypeModel;
use App\Enums\EmailType;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Servicio de envío de correos electrónicos
 * 
 * Maneja el envío de emails mediante SMTP utilizando PHPMailer
 * y el registro de códigos de verificación
 */
class EmailService
{
    private array $config;
    private EmailCodeModel $emailCodeModel;
    private EmailTypeModel $emailTypeModel;
    private ?PHPMailer $mailer = null;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->emailCodeModel = new EmailCodeModel();
        $this->emailTypeModel = new EmailTypeModel();
    }

    /**
     * Carga la configuración de email desde variables de entorno
     */
    private function loadConfig(): array
    {
        return [
            'host' => trim(getenv('MAIL_HOST') ?: 'smtp.gmail.com'),
            'port' => (int) trim(getenv('MAIL_PORT') ?: '587'),
            'enableSsl' => filter_var(trim(getenv('MAIL_ENABLE_SSL') ?: 'true'), FILTER_VALIDATE_BOOLEAN),
            'fromAddress' => trim(getenv('MAIL_FROM_ADDRESS') ?: ''),
            'fromName' => trim(getenv('MAIL_FROM_NAME') ?: 'ClubCheck'),
            'username' => trim(getenv('MAIL_USER') ?: ''),
            'password' => trim(getenv('MAIL_PASSWORD') ?: ''),
        ];
    }

    /**
     * Verifica si el servicio está configurado correctamente
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['host'])
            && !empty($this->config['fromAddress'])
            && !empty($this->config['username'])
            && !empty($this->config['password']);
    }

    /**
     * Obtiene o crea la instancia de PHPMailer
     */
    private function getMailer(): PHPMailer
    {
        if ($this->mailer === null) {
            $this->mailer = new PHPMailer(true);
            $this->configureMailer($this->mailer);
        }
        return $this->mailer;
    }

    /**
     * Configura la instancia de PHPMailer
     */
    private function configureMailer(PHPMailer $mailer): void
    {
        // Configuración del servidor
        $mailer->isSMTP();
        $mailer->Host = $this->config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->config['username'];
        $mailer->Password = $this->config['password'];
        $mailer->Port = $this->config['port'];
        
        if ($this->config['enableSsl']) {
            $mailer->SMTPSecure = $this->config['port'] == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Configuración del remitente
        $mailer->setFrom($this->config['fromAddress'], $this->config['fromName']);

        // Configuración de charset y formato
        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);
    }

    /**
     * Estructura estándar de resultado
     */
    public static function createResult(
        bool $success,
        ?string $message = null,
        ?string $emailCodeId = null,
        ?string $code = null,
        array $data = []
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'emailCodeId' => $emailCodeId,
            'code' => $code,
            'data' => $data,
        ];
    }

    // ==================== MÉTODOS DE ENVÍO ====================

    /**
     * Envía un correo electrónico genérico
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        ?string $emailTypeCode = 'NORMAL',
        array $options = []
    ): array {
        if (!$this->isConfigured()) {
            return self::createResult(false, 'El servicio de email no está configurado');
        }

        try {
            // Obtener tipo de email
            $emailType = $this->emailTypeModel->findByCode($emailTypeCode);
            if (!$emailType) {
                return self::createResult(false, "Tipo de email no encontrado: {$emailTypeCode}");
            }

            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->clearReplyTos();

            // Configurar destinatario
            $mailer->addAddress($to, $options['toName'] ?? '');

            // Configurar Reply-To si se especifica
            if (!empty($options['replyTo'])) {
                $mailer->addReplyTo($options['replyTo'], $options['replyToName'] ?? '');
            }

            // Configurar CC si se especifica
            if (!empty($options['cc'])) {
                foreach ((array) $options['cc'] as $cc) {
                    $mailer->addCC($cc);
                }
            }

            // Configurar BCC si se especifica
            if (!empty($options['bcc'])) {
                foreach ((array) $options['bcc'] as $bcc) {
                    $mailer->addBCC($bcc);
                }
            }

            // Configurar contenido
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            // Enviar
            $mailer->send();

            // Registrar el envío (sin código de verificación)
            $emailCodeId = $this->emailCodeModel->create([
                'email' => $to,
                'code' => 'N/A',
                'emailTypeId' => $emailType['Id'],
                'subject' => $subject,
                'body' => $body,
                'customerApiId' => $options['customerApiId'] ?? null,
                'adminId' => $options['adminId'] ?? null,
                'userId' => $options['userId'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);

            return self::createResult(true, 'Correo enviado exitosamente', $emailCodeId);

        } catch (PHPMailerException $e) {
            $this->log("Error enviando email: " . $e->getMessage(), 'error');
            return self::createResult(false, 'Error al enviar el correo: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->log("Error inesperado enviando email: " . $e->getMessage(), 'error');
            return self::createResult(false, 'Error inesperado: ' . $e->getMessage());
        }
    }

    /**
     * Envía un correo de restablecimiento de contraseña
     */
    public function sendPasswordReset(
        string $to,
        array $options = []
    ): array {
        if (!$this->isConfigured()) {
            return self::createResult(false, 'El servicio de email no está configurado');
        }

        $emailType = $this->emailTypeModel->findByCode('PASSWORD_RESET');
        if (!$emailType) {
            return self::createResult(false, 'Tipo de email PASSWORD_RESET no encontrado');
        }

        // Verificar rate limiting
        if (!$this->emailCodeModel->canSendNewCode($to, $emailType['Id'], 3, 15)) {
            return self::createResult(false, 'Has solicitado demasiados códigos. Espera 15 minutos.');
        }

        // Invalidar códigos anteriores
        $this->emailCodeModel->invalidatePreviousCodes($to, $emailType['Id']);

        // Generar código
        $code = $this->emailCodeModel->generateCode(6, true);

        // Calcular expiración
        $expirationMinutes = $emailType['CodeExpirationMinutes'] ?? 15;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expirationMinutes} minutes"));

        // Construir cuerpo del correo
        $subject = $options['subject'] ?? 'Restablecer tu contraseña - ClubCheck';
        $body = $options['body'] ?? $this->getPasswordResetTemplate($code, $expirationMinutes, $options);

        try {
            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($to, $options['toName'] ?? '');
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = "Tu código de verificación es: {$code}. Expira en {$expirationMinutes} minutos.";

            $mailer->send();

            // Registrar el envío con código
            $emailCodeId = $this->emailCodeModel->create([
                'email' => $to,
                'code' => $code,
                'emailTypeId' => $emailType['Id'],
                'subject' => $subject,
                'body' => $body,
                'expiresAt' => $expiresAt,
                'customerApiId' => $options['customerApiId'] ?? null,
                'adminId' => $options['adminId'] ?? null,
                'userId' => $options['userId'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);

            return self::createResult(
                true,
                'Código de restablecimiento enviado',
                $emailCodeId,
                $code, // Solo para debug en desarrollo
                ['expiresAt' => $expiresAt, 'expirationMinutes' => $expirationMinutes]
            );

        } catch (PHPMailerException $e) {
            $this->log("Error enviando email de password reset: " . $e->getMessage(), 'error');
            return self::createResult(false, 'Error al enviar el correo');
        }
    }

    /**
     * Envía un correo de confirmación de email
     */
    public function sendEmailConfirmation(
        string $to,
        array $options = []
    ): array {
        if (!$this->isConfigured()) {
            return self::createResult(false, 'El servicio de email no está configurado');
        }

        $emailType = $this->emailTypeModel->findByCode('EMAIL_CONFIRMATION');
        if (!$emailType) {
            return self::createResult(false, 'Tipo de email EMAIL_CONFIRMATION no encontrado');
        }

        // Verificar rate limiting
        if (!$this->emailCodeModel->canSendNewCode($to, $emailType['Id'], 5, 60)) {
            return self::createResult(false, 'Has solicitado demasiados códigos. Espera 1 hora.');
        }

        // Invalidar códigos anteriores
        $this->emailCodeModel->invalidatePreviousCodes($to, $emailType['Id']);

        // Generar código
        $code = $this->emailCodeModel->generateCode(6, true);

        // Calcular expiración
        $expirationMinutes = $emailType['CodeExpirationMinutes'] ?? 60;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expirationMinutes} minutes"));

        // Construir cuerpo del correo
        $subject = $options['subject'] ?? 'Confirma tu correo electrónico - ClubCheck';
        $body = $options['body'] ?? $this->getEmailConfirmationTemplate($code, $expirationMinutes, $options);

        try {
            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($to, $options['toName'] ?? '');
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = "Tu código de confirmación es: {$code}. Expira en {$expirationMinutes} minutos.";

            $mailer->send();

            // Registrar el envío con código
            $emailCodeId = $this->emailCodeModel->create([
                'email' => $to,
                'code' => $code,
                'emailTypeId' => $emailType['Id'],
                'subject' => $subject,
                'body' => $body,
                'expiresAt' => $expiresAt,
                'customerApiId' => $options['customerApiId'] ?? null,
                'adminId' => $options['adminId'] ?? null,
                'userId' => $options['userId'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);

            return self::createResult(
                true,
                'Código de confirmación enviado',
                $emailCodeId,
                $code,
                ['expiresAt' => $expiresAt, 'expirationMinutes' => $expirationMinutes]
            );

        } catch (PHPMailerException $e) {
            $this->log("Error enviando email de confirmación: " . $e->getMessage(), 'error');
            return self::createResult(false, 'Error al enviar el correo');
        }
    }

    /**
     * Envía un correo de bienvenida
     */
    public function sendWelcome(
        string $to,
        array $options = []
    ): array {
        $subject = $options['subject'] ?? '¡Bienvenido a ClubCheck!';
        $body = $options['body'] ?? $this->getWelcomeTemplate($options);

        return $this->send($to, $subject, $body, 'WELCOME', $options);
    }

    /**
     * Envía una notificación
     */
    public function sendNotification(
        string $to,
        string $subject,
        string $body,
        array $options = []
    ): array {
        return $this->send($to, $subject, $body, 'NOTIFICATION', $options);
    }

    // ==================== VERIFICACIÓN DE CÓDIGOS ====================

    /**
     * Verifica un código de verificación
     */
    public function verifyCode(
        string $email,
        string $code,
        string $emailTypeCode
    ): array {
        $emailType = $this->emailTypeModel->findByCode($emailTypeCode);
        if (!$emailType) {
            return [
                'valid' => false,
                'message' => 'Tipo de email no encontrado',
            ];
        }

        // Buscar código válido
        $emailCode = $this->emailCodeModel->findValidCode($email, $code, $emailType['Id']);

        if (!$emailCode) {
            return [
                'valid' => false,
                'message' => 'Código inválido o expirado',
            ];
        }

        // Verificar intentos máximos
        $maxAttempts = $emailCode['TypeMaxAttempts'] ?? $emailType['MaxAttempts'] ?? 3;
        if ($emailCode['Attempts'] >= $maxAttempts) {
            return [
                'valid' => false,
                'message' => 'Número máximo de intentos excedido',
            ];
        }

        // Incrementar intentos
        $this->emailCodeModel->incrementAttempts($emailCode['Id']);

        // Verificar código
        if ($emailCode['Code'] === $code) {
            // Marcar como usado
            $this->emailCodeModel->markAsUsed($emailCode['Id']);

            return [
                'valid' => true,
                'message' => 'Código verificado correctamente',
                'emailCodeId' => $emailCode['Id'],
                'data' => [
                    'email' => $emailCode['Email'],
                    'type' => $emailCode['TypeCode'],
                    'customerApiId' => $emailCode['CustomerApiId'],
                    'adminId' => $emailCode['AdminId'],
                    'userId' => $emailCode['UserId'],
                ],
            ];
        }

        $attemptsRemaining = $maxAttempts - ($emailCode['Attempts'] + 1);
        return [
            'valid' => false,
            'message' => "Código incorrecto. Te quedan {$attemptsRemaining} intentos.",
            'attemptsRemaining' => $attemptsRemaining,
        ];
    }

    /**
     * Verifica código de restablecimiento de contraseña
     */
    public function verifyPasswordResetCode(string $email, string $code): array
    {
        return $this->verifyCode($email, $code, 'PASSWORD_RESET');
    }

    /**
     * Verifica código de confirmación de email
     */
    public function verifyEmailConfirmationCode(string $email, string $code): array
    {
        return $this->verifyCode($email, $code, 'EMAIL_CONFIRMATION');
    }

    // ==================== TEMPLATES HTML ====================

    /**
     * Template para correo de restablecimiento de contraseña
     */
    private function getPasswordResetTemplate(string $code, int $expirationMinutes, array $options = []): string
    {
        $userName = $options['userName'] ?? 'Usuario';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">🔐 Restablecer Contraseña</h1>
    </div>
    
    <div style="background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hola <strong>{$userName}</strong>,</p>
        
        <p style="font-size: 16px;">Has solicitado restablecer tu contraseña. Usa el siguiente código de verificación:</p>
        
        <div style="background-color: #f8f9fa; border: 2px dashed #667eea; border-radius: 10px; padding: 20px; text-align: center; margin: 25px 0;">
            <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #667eea;">{$code}</span>
        </div>
        
        <p style="font-size: 14px; color: #666;">
            ⏰ Este código expira en <strong>{$expirationMinutes} minutos</strong>.
        </p>
        
        <p style="font-size: 14px; color: #666;">
            Si no solicitaste restablecer tu contraseña, puedes ignorar este correo.
        </p>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 25px 0;">
        
        <p style="font-size: 12px; color: #999; text-align: center;">
            Este correo fue enviado por ClubCheck.<br>
            Por favor, no respondas a este mensaje.
        </p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template para correo de confirmación de email
     */
    private function getEmailConfirmationTemplate(string $code, int $expirationMinutes, array $options = []): string
    {
        $userName = $options['userName'] ?? 'Usuario';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirma tu correo</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">✉️ Confirma tu Correo</h1>
    </div>
    
    <div style="background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hola <strong>{$userName}</strong>,</p>
        
        <p style="font-size: 16px;">Gracias por registrarte. Para completar tu registro, confirma tu dirección de correo electrónico con el siguiente código:</p>
        
        <div style="background-color: #f8f9fa; border: 2px dashed #11998e; border-radius: 10px; padding: 20px; text-align: center; margin: 25px 0;">
            <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #11998e;">{$code}</span>
        </div>
        
        <p style="font-size: 14px; color: #666;">
            ⏰ Este código expira en <strong>{$expirationMinutes} minutos</strong>.
        </p>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 25px 0;">
        
        <p style="font-size: 12px; color: #999; text-align: center;">
            Este correo fue enviado por ClubCheck.<br>
            Por favor, no respondas a este mensaje.
        </p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template para correo de bienvenida
     */
    private function getWelcomeTemplate(array $options = []): string
    {
        $userName = $options['userName'] ?? 'Usuario';
        $companyName = $options['companyName'] ?? 'ClubCheck';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Bienvenido!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">🎉 ¡Bienvenido a {$companyName}!</h1>
    </div>
    
    <div style="background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hola <strong>{$userName}</strong>,</p>
        
        <p style="font-size: 16px;">¡Gracias por unirte a nosotros! Estamos emocionados de tenerte como parte de nuestra comunidad.</p>
        
        <p style="font-size: 16px;">Con tu cuenta podrás:</p>
        
        <ul style="font-size: 14px; color: #666;">
            <li>Gestionar tus membresías y suscripciones</li>
            <li>Revisar tu historial de asistencias</li>
            <li>Recibir notificaciones importantes</li>
            <li>Y mucho más...</li>
        </ul>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 25px 0;">
        
        <p style="font-size: 12px; color: #999; text-align: center;">
            Este correo fue enviado por {$companyName}.<br>
            Por favor, no respondas a este mensaje.
        </p>
    </div>
</body>
</html>
HTML;
    }

    // ==================== UTILIDADES ====================

    /**
     * Obtiene el historial de emails enviados
     */
    public function getHistory(string $email, int $limit = 50): array
    {
        return $this->emailCodeModel->getHistoryByEmail($email, $limit);
    }

    /**
     * Obtiene estadísticas de envíos
     */
    public function getStats(): array
    {
        return $this->emailCodeModel->getStatsByType();
    }

    /**
     * Obtiene los tipos de email disponibles
     */
    public function getEmailTypes(): array
    {
        return $this->emailTypeModel->getActiveTypes();
    }

    /**
     * Limpia códigos expirados
     */
    public function cleanupExpired(): int
    {
        return $this->emailCodeModel->cleanupExpiredCodes();
    }

    /**
     * Log de actividades
     */
    private function log(string $message, string $level = 'info'): void
    {
        if (function_exists('logger')) {
            logger($message, $level);
        } else {
            error_log("[{$level}] EmailService: {$message}");
        }
    }
}
