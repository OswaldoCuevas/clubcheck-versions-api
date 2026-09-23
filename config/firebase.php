<?php

return [
    'project_id' => getenv('FIREBASE_PROJECT_ID') ?: '',
    // Ruta absoluta a la clave JSON de una cuenta de servicio. Mantener fuera del directorio público.
    'service_account_path' => getenv('FIREBASE_SERVICE_ACCOUNT_PATH') ?: '',
];
