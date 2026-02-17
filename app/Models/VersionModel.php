<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class VersionModel extends Model
{
    /**
     * Obtener la versión más reciente de la aplicación
     */
    public function getLatestVersion()
    {
        $sql = "SELECT `Id`, `Name`, `Url`, `Sha256`, `IsMandatory`, `ReleaseNotes`, `UploadDate` 
                FROM `AppVersions` 
                ORDER BY `UploadDate` DESC, `Id` DESC 
                LIMIT 1";
        
        $result = $this->db->query($sql);
        
        if ($result && $row = $result->fetch_assoc()) {
            return [
                'latestVersion' => $row['Name'],
                'url' => $row['Url'],
                'sha256' => $row['Sha256'],
                'mandatory' => (bool) $row['IsMandatory'],
                'releaseNotes' => $row['ReleaseNotes'],
                'uploadDate' => $row['UploadDate'],
                'timestamp' => $row['UploadDate']
            ];
        }
        
        // Si no hay versión, retornar valores por defecto
        return [
            'latestVersion' => '0.0.0.0',
            'url' => '',
            'sha256' => '',
            'mandatory' => false,
            'releaseNotes' => '',
            'uploadDate' => null,
            'timestamp' => null
        ];
    }
    
    /**
     * Guardar o actualizar una versión
     */
    public function saveVersion($version, $url, $sha256, $isMandatory, $releaseNotes, $uploadDate)
    {
        // Escapar valores
        $name = $this->db->escape_string($version);
        $url = $this->db->escape_string($url);
        $sha256 = $this->db->escape_string($sha256);
        $isMandatory = $isMandatory ? 1 : 0;
        $releaseNotes = $this->db->escape_string($releaseNotes);
        $uploadDate = $this->db->escape_string($uploadDate);
        
        // Insertar o actualizar si la versión ya existe
        $sql = "INSERT INTO `AppVersions` 
                (`Name`, `Url`, `Sha256`, `IsMandatory`, `ReleaseNotes`, `UploadDate`) 
                VALUES 
                ('$name', '$url', '$sha256', $isMandatory, '$releaseNotes', '$uploadDate')
                ON DUPLICATE KEY UPDATE
                `Url` = VALUES(`Url`),
                `Sha256` = VALUES(`Sha256`),
                `IsMandatory` = VALUES(`IsMandatory`),
                `ReleaseNotes` = VALUES(`ReleaseNotes`),
                `UploadDate` = VALUES(`UploadDate`)";
        
        return $this->db->query($sql);
    }
    
    /**
     * Verificar si una versión existe
     */
    public function versionExists($version)
    {
        $name = $this->db->escape_string($version);
        $sql = "SELECT `Id` FROM `AppVersions` WHERE `Name` = '$name' LIMIT 1";
        $result = $this->db->query($sql);
        
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Obtener una versión específica
     */
    public function getVersion($version)
    {
        $name = $this->db->escape_string($version);
        $sql = "SELECT `Id`, `Name`, `Url`, `Sha256`, `IsMandatory`, `ReleaseNotes`, `UploadDate` 
                FROM `AppVersions` 
                WHERE `Name` = '$name' 
                LIMIT 1";
        
        $result = $this->db->query($sql);
        
        if ($result && $row = $result->fetch_assoc()) {
            return [
                'id' => $row['Id'],
                'latestVersion' => $row['Name'],
                'url' => $row['Url'],
                'sha256' => $row['Sha256'],
                'mandatory' => (bool) $row['IsMandatory'],
                'releaseNotes' => $row['ReleaseNotes'],
                'uploadDate' => $row['UploadDate'],
                'timestamp' => $row['UploadDate']
            ];
        }
        
        return null;
    }
}
