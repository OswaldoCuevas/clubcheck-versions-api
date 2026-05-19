<?php

/**
 * Generador de par de claves RSA para el sistema de licencias de ClubCheck
 *
 * EJECUCIÓN:
 *   php utils/generate_license_keys.php
 *
 * Genera un par de claves RSA-2048 y muestra las instrucciones para
 * agregarlas al archivo .env.
 *
 * REGLAS DE SEGURIDAD:
 *  - La CLAVE PRIVADA solo debe existir en el servidor (.env, nunca en Git).
 *  - La CLAVE PÚBLICA puede distribuirse con el software cliente.
 *  - Nunca regeneres las claves si ya hay licencias emitidas
 *    (las licencias antiguas dejarán de verificarse).
 */

$config = [
    'bits'             => 2048,
    'digest_alg'       => 'sha256',
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

echo "\n";
echo "==============================================\n";
echo "  ClubCheck - Generador de Claves de Licencia\n";
echo "==============================================\n\n";

// Generar par de claves
$keyPair = openssl_pkey_new($config);
if (!$keyPair) {
    fwrite(STDERR, "ERROR: No se pudo generar el par de claves.\n");
    fwrite(STDERR, "Verifica que la extensión OpenSSL esté habilitada en PHP.\n");
    exit(1);
}

// Extraer clave privada
$privateKeyPem = '';
openssl_pkey_export($keyPair, $privateKeyPem);

// Extraer clave pública
$keyDetails   = openssl_pkey_get_details($keyPair);
$publicKeyPem = $keyDetails['key'];

// Convertir a formato .env: reemplazar saltos de línea con \n literal
$privateKeyEnv = str_replace("\n", '\n', trim($privateKeyPem));
$publicKeyEnv  = str_replace("\n", '\n', trim($publicKeyPem));

echo "Par de claves RSA-2048 generado exitosamente.\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PASO 1: Agrega estas líneas a tu archivo .env\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "LICENSE_PRIVATE_KEY=\"{$privateKeyEnv}\"\n";
echo "LICENSE_PUBLIC_KEY=\"{$publicKeyEnv}\"\n";

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PASO 2: Clave pública para el cliente\n";
echo "  Guarda esto en tu software cliente para\n";
echo "  verificar licencias (NO incluir la privada).\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo $publicKeyPem;

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ADVERTENCIAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ⚠ NUNCA subas LICENSE_PRIVATE_KEY a Git.\n";
echo "  ⚠ NUNCA compartas la clave privada.\n";
echo "  ⚠ Si regeneras las claves, todas las licencias\n";
echo "    existentes dejarán de ser válidas.\n\n";

// Guardar también en archivos locales (opcional, para tener respaldo)
$sslDir = __DIR__ . '/../ssl/';
if (is_dir($sslDir)) {
    $privateFile = $sslDir . 'license_private.pem';
    $publicFile  = $sslDir . 'license_public.pem';

    if (file_exists($privateFile) || file_exists($publicFile)) {
        echo "  ⚠ Ya existen archivos en ssl/. No se sobreescribieron.\n";
        echo "    Borra ssl/license_private.pem y ssl/license_public.pem\n";
        echo "    si quieres regenerar.\n\n";
    } else {
        file_put_contents($privateFile, $privateKeyPem);
        file_put_contents($publicFile, $publicKeyPem);
        chmod($privateFile, 0600); // Solo lectura para el dueño
        echo "  ✔ Claves guardadas en:\n";
        echo "      ssl/license_private.pem  (NO subir a Git)\n";
        echo "      ssl/license_public.pem\n\n";
        echo "  Agrega a .gitignore:\n";
        echo "      ssl/license_private.pem\n\n";
    }
}

echo "Listo.\n\n";
