<?php

/**
 * Helper functions para el sistema ClubCheck
 */

if (!function_exists('getAppFileName')) {
    /**
     * Genera el nombre del archivo ejecutable para una versión específica
     * @param string $version Versión del archivo (ej: 1.1.1.20)
     * @return string Nombre del archivo (ej: ClubCheck-1.1.1.20.exe)
     */
    function getAppFileName($version)
    {
        // Intentar cargar desde configuración
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            $appPattern = config('files.app_name_pattern', 'ClubCheck.exe');
            $appName = pathinfo($appPattern, PATHINFO_FILENAME);
        } catch (Exception $e) {
            // Fallback si no se puede cargar la configuración
            $appName = 'ClubCheck';
        }
        
        return "{$appName}-{$version}.exe";
    }
}

if (!function_exists('getAppFilePath')) {
    /**
     * Genera la ruta completa del archivo ejecutable
     * @param string $version Versión del archivo
     * @param string $baseDir Directorio base (opcional)
     * @return string Ruta completa del archivo
     */
    function getAppFilePath($version, $baseDir = null)
    {
        if ($baseDir === null) {
            $baseDir = __DIR__ . '/../../uploads/';
        }
        
        $fileName = getAppFileName($version);
        return rtrim($baseDir, '/') . '/' . $fileName;
    }
}

if (!function_exists('findExistingAppFile')) {
    /**
     * Busca archivos existentes con el nombre anterior (MyApp) o actual (ClubCheck)
     * @param string $version Versión a buscar
     * @param string $baseDir Directorio base (opcional)
     * @return string|null Ruta del archivo encontrado o null
     */
    function findExistingAppFile($version, $baseDir = null)
    {
        if ($baseDir === null) {
            $baseDir = __DIR__ . '/../../uploads/';
        }
        
        // Posibles nombres de archivos (compatibilidad con nombres anteriores)
        $possibleNames = [
            "ClubCheck-{$version}.exe",  // Nuevo formato
            "MyApp-{$version}.exe",      // Formato anterior
        ];
        
        foreach ($possibleNames as $fileName) {
            $filePath = rtrim($baseDir, '/') . '/' . $fileName;
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        
        return null;
    }
}
?>
