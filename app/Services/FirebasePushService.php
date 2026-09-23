<?php

namespace App\Services;

/** Envío de notificaciones mediante Firebase Cloud Messaging HTTP v1. */
class FirebasePushService
{
    private string $projectId;
    private string $credentialsPath;
    private ?string $accessToken = null;

    public function __construct(?array $config = null)
    {
        $config ??= require __DIR__ . '/../../config/firebase.php';
        $this->projectId = (string) ($config['project_id'] ?? '');
        $this->credentialsPath = (string) ($config['service_account_path'] ?? '');
    }

    public function send(string $registrationToken, string $title, string $body, array $data = [], array $webOptions = []): array
    {
        if ($this->projectId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $this->projectId)) {
            throw new \RuntimeException('FIREBASE_PROJECT_ID no está configurado correctamente.');
        }

        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \InvalidArgumentException('Los datos de FCM deben ser pares de texto.');
            }
        }

        $message = [
            'token' => $registrationToken,
            'notification' => ['title' => $title, 'body' => $body],
        ];
        if ($data !== []) {
            $message['data'] = $data;
        }
        if (!empty($webOptions['imageUrl'])) {
            $message['notification']['image'] = $webOptions['imageUrl'];
        }
        if (!empty($webOptions['iconUrl']) || !empty($webOptions['imageUrl'])) {
            $message['webpush']['notification'] = ['title' => $title, 'body' => $body];
            if (!empty($webOptions['iconUrl'])) {
                $message['webpush']['notification']['icon'] = $webOptions['iconUrl'];
            }
            if (!empty($webOptions['imageUrl'])) {
                $message['webpush']['notification']['image'] = $webOptions['imageUrl'];
            }
        }
        if (!empty($webOptions['link'])) {
            $message['webpush']['fcm_options']['link'] = $webOptions['link'];
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->projectId) . '/messages:send';
        $response = $this->request($url, [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ], json_encode(['message' => $message], JSON_THROW_ON_ERROR));

        // Firebase puede incluir el token rechazado en un mensaje de error.
        $safeBody = str_replace($registrationToken, '[REDACTED]', $response['body']);
        $payload = json_decode($safeBody, true);
        if (!is_array($payload)) {
            $payload = ['raw' => $safeBody];
        }
        $code = $payload['error']['details'][0]['errorCode'] ?? $payload['error']['status'] ?? null;

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'messageId' => $payload['name'] ?? null,
            'errorCode' => $code,
            'httpStatus' => $response['status'],
            'firebaseResponse' => $payload,
        ];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        if ($this->credentialsPath === '' || !is_readable($this->credentialsPath)) {
            throw new \RuntimeException('FIREBASE_SERVICE_ACCOUNT_PATH no apunta a un archivo legible.');
        }

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);
        if (!is_array($credentials) || ($credentials['type'] ?? null) !== 'service_account'
            || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new \RuntimeException('La cuenta de servicio de Firebase no es válida.');
        }

        $now = time();
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claims;

        if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No fue posible firmar la autenticación de Firebase.');
        }

        $response = $this->request('https://oauth2.googleapis.com/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned . '.' . $this->base64url($signature),
        ]));
        $payload = json_decode($response['body'], true) ?: [];
        if ($response['status'] !== 200 || empty($payload['access_token'])) {
            throw new \RuntimeException('No fue posible autenticar con Firebase (HTTP ' . $response['status'] . ').');
        }

        return $this->accessToken = $payload['access_token'];
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function request(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('No fue posible iniciar la conexión con Firebase.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException('Error de conexión con Firebase: ' . $error);
        }

        return ['status' => $status, 'body' => $result];
    }
}
