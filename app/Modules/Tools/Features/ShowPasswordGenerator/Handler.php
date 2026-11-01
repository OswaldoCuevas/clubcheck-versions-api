<?php

declare(strict_types=1);

namespace App\Modules\Tools\Features\ShowPasswordGenerator;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Tools\Services\PasswordGenerator;

final readonly class Handler
{
    public function __construct(
        private ICurrentUserProvider $users,
        private PasswordGenerator $passwords,
    ) {
    }

    public function handle(Query $query): array
    {
        $message = '';
        $messageType = '';
        $generatedData = null;
        $action = $query->form['action'] ?? '';

        if ($action === 'hash_password') {
            $password = trim((string) ($query->form['password'] ?? ''));
            if ($password === '') {
                $message = 'La contraseña no puede estar vacía';
                $messageType = 'error';
            } else {
                $generatedData = [
                    'type' => 'hash',
                    'password' => $password,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                ];
                $message = 'Hash generado correctamente';
                $messageType = 'success';
            }
        } elseif ($action === 'generate_random') {
            $length = max(4, min(50, (int) ($query->form['length'] ?? 12)));
            $upper = isset($query->form['include_upper']);
            $lower = isset($query->form['include_lower']);
            $numbers = isset($query->form['include_numbers']);
            $symbols = isset($query->form['include_symbols']);

            if (!$upper && !$lower && !$numbers && !$symbols) {
                $message = 'Debe seleccionar al menos un tipo de carácter';
                $messageType = 'error';
            } else {
                $password = $this->passwords->random(
                    $length,
                    $upper,
                    $lower,
                    $numbers,
                    $symbols,
                );
                $generatedData = [
                    'type' => 'random',
                    'password' => $password,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'length' => $length,
                ];
                $message = 'Contraseña generada correctamente';
                $messageType = 'success';
            }
        }

        return [
            'currentUser' => $this->users->current(),
            'message' => $message,
            'messageType' => $messageType,
            'generatedData' => $generatedData,
            'title' => 'Generador de Contraseñas - ClubCheck',
        ];
    }
}
