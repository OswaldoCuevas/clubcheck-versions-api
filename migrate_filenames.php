<?php
/**
 * Script para migrar archivos de MyApp-x.x.x.x.exe a ClubCheck-x.x.x.x.exe
 * Ejecutar: php migrate_filenames.php
 */

require_once __DIR__ . '/app/Helpers/FileHelper.php';

echo "=== MIGRACIÓN DE NOMBRES DE ARCHIVOS ===\n\n";

$uploadsDir = __DIR__ . '/uploads/';
$backupDir = __DIR__ . '/uploads/backup_migration_' . date('Y-m-d_H-i-s') . '/';

if (!is_dir($uploadsDir)) {
    echo "❌ Error: Directorio uploads no existe: $uploadsDir\n";
    exit(1);
}

// Crear directorio de backup
if (!mkdir($backupDir, 0755, true)) {
    echo "❌ Error: No se pudo crear directorio de backup: $backupDir\n";
    exit(1);
}

echo "✅ Directorio de backup creado: $backupDir\n\n";

// Buscar archivos con formato MyApp-x.x.x.x.exe
$files = glob($uploadsDir . 'MyApp-*.exe');

if (empty($files)) {
    echo "ℹ️ No se encontraron archivos MyApp-*.exe para migrar\n";
    exit(0);
}

echo "Archivos encontrados para migrar:\n";
foreach ($files as $file) {
    echo "  - " . basename($file) . "\n";
}
echo "\n";

$migratedCount = 0;
$errorCount = 0;

foreach ($files as $oldFilePath) {
    $oldFileName = basename($oldFilePath);
    
    // Extraer versión del nombre anterior
    if (preg_match('/MyApp-(\d+\.\d+\.\d+\.\d+)\.exe$/', $oldFileName, $matches)) {
        $version = $matches[1];
        $newFileName = getAppFileName($version);
        $newFilePath = $uploadsDir . $newFileName;
        
        echo "Migrando: $oldFileName → $newFileName\n";
        
        // Crear backup del archivo original
        $backupFilePath = $backupDir . $oldFileName;
        if (copy($oldFilePath, $backupFilePath)) {
            echo "  ✅ Backup creado: " . basename($backupFilePath) . "\n";
            
            // Renombrar archivo
            if (rename($oldFilePath, $newFilePath)) {
                echo "  ✅ Archivo migrado exitosamente\n";
                $migratedCount++;
            } else {
                echo "  ❌ Error al renombrar archivo\n";
                $errorCount++;
            }
        } else {
            echo "  ❌ Error al crear backup\n";
            $errorCount++;
        }
    } else {
        echo "❌ No se pudo extraer versión de: $oldFileName\n";
        $errorCount++;
    }
    
    echo "\n";
}

// Migrar también archivos de backup
$backupFiles = glob($uploadsDir . 'backup_*_MyApp-*.exe');
if (!empty($backupFiles)) {
    echo "=== MIGRANDO ARCHIVOS DE BACKUP ===\n\n";
    
    foreach ($backupFiles as $oldBackupPath) {
        $oldBackupName = basename($oldBackupPath);
        
        // Cambiar MyApp por ClubCheck en el nombre de backup
        $newBackupName = str_replace('MyApp-', 'ClubCheck-', $oldBackupName);
        $newBackupPath = $uploadsDir . $newBackupName;
        
        echo "Migrando backup: $oldBackupName → $newBackupName\n";
        
        if (rename($oldBackupPath, $newBackupPath)) {
            echo "  ✅ Backup migrado exitosamente\n";
            $migratedCount++;
        } else {
            echo "  ❌ Error al renombrar backup\n";
            $errorCount++;
        }
        
        echo "\n";
    }
}

// Actualizar version.json si es necesario
$versionFile = __DIR__ . '/version.json';
if (file_exists($versionFile)) {
    $versionData = json_decode(file_get_contents($versionFile), true);
    
    if ($versionData && isset($versionData['url'])) {
        $oldUrl = $versionData['url'];
        
        // Si la URL contiene MyApp, actualizarla
        if (strpos($oldUrl, 'MyApp-') !== false) {
            $newUrl = str_replace('MyApp-', 'ClubCheck-', $oldUrl);
            $versionData['url'] = $newUrl;
            
            // Crear backup del archivo JSON
            copy($versionFile, $backupDir . 'version.json');
            
            // Actualizar archivo JSON
            if (file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                echo "✅ Archivo version.json actualizado\n";
                echo "  URL anterior: $oldUrl\n";
                echo "  URL nueva: $newUrl\n\n";
            } else {
                echo "❌ Error al actualizar version.json\n\n";
                $errorCount++;
            }
        } else {
            echo "ℹ️ version.json no requiere actualización\n\n";
        }
    }
}

echo "=== RESUMEN DE MIGRACIÓN ===\n";
echo "Archivos migrados exitosamente: $migratedCount\n";
echo "Errores encontrados: $errorCount\n";
echo "Backup ubicado en: $backupDir\n";

if ($migratedCount > 0) {
    echo "\n✅ Migración completada! Ahora todos los archivos usan el formato ClubCheck-x.x.x.x.exe\n";
}

if ($errorCount > 0) {
    echo "\n⚠️ Se encontraron errores durante la migración. Revisa los mensajes anteriores.\n";
}

echo "\nArchivos actuales en uploads/:\n";
$currentFiles = glob($uploadsDir . '*.exe');
foreach ($currentFiles as $file) {
    echo "  - " . basename($file) . "\n";
}
?>
