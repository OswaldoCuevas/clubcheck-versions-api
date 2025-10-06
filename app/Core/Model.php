<?php

namespace Core;

class Model
{
    protected $data = [];
    protected $errors = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initialize();
    }
    
    /**
     * Método para inicialización personalizada
     */
    protected function initialize()
    {
        // Override en clases hijas si es necesario
    }
    
    /**
     * Validar datos
     */
    public function validate($data, $rules)
    {
        $this->errors = [];
        
        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);
            
            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($data[$field])) {
                    $this->errors[$field][] = "El campo {$field} es requerido";
                }
                
                if (strpos($r, 'pattern:') === 0 && !empty($data[$field])) {
                    $pattern = substr($r, 8);
                    if (!preg_match($pattern, $data[$field])) {
                        $this->errors[$field][] = "El formato del campo {$field} no es válido";
                    }
                }
                
                if (strpos($r, 'max:') === 0 && !empty($data[$field])) {
                    $max = (int)substr($r, 4);
                    if (strlen($data[$field]) > $max) {
                        $this->errors[$field][] = "El campo {$field} no puede tener más de {$max} caracteres";
                    }
                }
                
                if (strpos($r, 'min:') === 0 && !empty($data[$field])) {
                    $min = (int)substr($r, 4);
                    if (strlen($data[$field]) < $min) {
                        $this->errors[$field][] = "El campo {$field} debe tener al menos {$min} caracteres";
                    }
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Obtener errores de validación
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Verificar si hay errores
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    
    /**
     * Cargar datos desde archivo JSON
     */
    protected function loadJsonFile($filepath)
    {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $content = file_get_contents($filepath);
        
        // Verificar que el contenido no esté vacío
        if (empty(trim($content))) {
            return null; // Retornar null para contenido vacío
        }
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log del error para debug
            error_log("JSON Error en archivo $filepath: " . json_last_error_msg() . " - Contenido: " . substr($content, 0, 200));
            
            // En lugar de lanzar excepción, retornar null y permitir manejo graceful
            return null;
        }
        
        return $data;
    }
    
    /**
     * Guardar datos en archivo JSON
     */
    protected function saveJsonFile($filepath, $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Error al codificar JSON: " . json_last_error_msg());
        }
        
        $result = file_put_contents($filepath, $json, LOCK_EX);
        
        if ($result === false) {
            throw new \Exception("Error al escribir archivo: {$filepath}");
        }
        
        return true;
    }
    
    /**
     * Log de actividades
     */
    protected function log($message, $level = 'info')
    {
        logger($message, $level);
    }
}
