# Instrucciones para Aplicar las Mejoras

## Resumen de Cambios

Se ha implementado la funcionalidad para **subir archivos Setup (instaladores EXE) junto con archivos EXE** en el gestor de versiones de ClubCheck. Ambos archivos son ahora obligatorios al subir una nueva versión.

### Características Implementadas

1. ✅ **Subida obligatoria de archivos EXE y Setup**
   - Al subir una nueva versión, ahora se requieren ambos archivos EXE
   - Archivo principal: ClubCheck-X.X.X.X.exe
   - Archivo setup/instalador: ClubCheckSetup-X.X.X.X.exe
   - Validación automática de extensiones (.exe para ambos)
   - Backup automático de archivos existentes al reemplazar

2. ✅ **Ruta pública de descarga del Setup**
   - URL: `/api/download-setup`
   - Sin restricciones de autenticación
   - Cualquier usuario puede descargar el archivo Setup

3. ✅ **Base de datos actualizada**
   - Nuevos campos: `SetupUrl`, `SetupSha256`, `SetupFileSize`
   - Información completa de ambos archivos

4. ✅ **API actualizada**
   - Endpoint `/api/version`: Incluye información del Setup
   - Endpoint `/api/check-update`: Incluye URLs y checksums del Setup
   - Endpoint `/api/download-setup`: Descarga pública del Setup

---

## Pasos para Aplicar los Cambios

### 1. Ejecutar la Migración de Base de Datos

Ejecuta el siguiente comando SQL en tu base de datos MySQL:

```bash
mysql -u tu_usuario -p clubcheck < database/migrations/001_add_zip_fields_to_app_versions.sql
```

O desde phpMyAdmin / Adminer:
- Abre el archivo: `database/migrations/001_add_zip_fields_to_app_versions.sql`
- Copia y pega el contenido en el editor SQL
- Ejecuta la consulta

**Contenido de la migración:**
```sql
USE `clubcheck`;

ALTER TABLE `AppVersions` 
ADD COLUMN `ZipUrl` TEXT NULL AFTER `Sha256`,
ADD COLUMN `ZipSha256` CHAR(64) NULL AFTER `ZipUrl`,
ADD COLUMN `ZipFileSize` BIGINT UNSIGNED NULL AFTER `ZipSha256`;
```

### 2. Verificar los Cambios

Después de aplicar la migración, verifica que la tabla se actualizó correctamente:

```sql
DESCRIBE AppVersions;
```

Deberías ver las nuevas columnas:
- `ZipUrl` (TEXT)
- `ZipSha256` (CHAR(64))
- `ZipFileSize` (BIGINT UNSIGNED)

### 3. Probar la Funcionalidad

1. **Accede al gestor**: `http://tu-servidor/clubcheck/`
2. **Inicia sesión** con una cuenta que tenga permisos de subida
3. **Sube una nueva versión**:
   - Completa el campo de versión (ej: 1.2.3.4)
   - Selecciona el archivo .exe
   - Selecciona el archivo .zip *(ahora obligatorio)*
   - Agrega notas de versión (opcional)
   - Haz clic en "Subir Nueva Versión (EXE + ZIP)"

4. **Verifica las descargas públicas**:
   - EXE: `http://tu-servidor/clubcheck/api/download`
   - ZIP: `http://tu-servidor/clubcheck/api/download-zip` *(nueva ruta pública)*

5. **Verifica la API**:
   - `http://tu-servidor/clubcheck/api/version` (debería mostrar información del ZIP)
   - `http://tu-servidor/clubcheck/api/check-update` (incluye URLs del ZIP)

---

## Archivos Modificados

### Base de Datos
- ✅ `database/migrations/001_add_zip_fields_to_app_versions.sql` *(nuevo)*

### Configuración
- ✅ `config/app.php` - Permite extensiones .zip

### Modelos
- ✅ `app/Models/VersionModel.php` - Maneja campos ZIP

### Controladores
- ✅ `app/Controllers/HomeController.php` - Procesa subida de ZIP
- ✅ `app/Controllers/ApiController.php` - Añade endpoint downloadZip()

### Vistas
- ✅ `app/Views/home/index.php` - Campo de subida de ZIP + visualización

### Rutas
- ✅ `routes/web.php` - Nueva ruta `/api/download-zip`

---

## Notas Importantes

### Compatibilidad hacia atrás
- Las versiones antiguas sin ZIP seguirán funcionando
- El campo ZIP es obligatorio solo para **nuevas subidas**
- Las versiones existentes en la BD sin ZIP mostrarán valores vacíos

### Seguridad
- El archivo ZIP es de **descarga pública** (sin autenticación)
- El archivo EXE mantiene su descarga pública existente
- La subida de archivos requiere autenticación y permisos

### Tamaño de archivos
- Los límites de subida están configurados en `config/app.php`
- Límite actual: 500MB
- Puedes ajustarlo modificando `max_upload_size`

---

## Solución de Problemas

### Error: "Columna no encontrada"
**Causa**: La migración no se ejecutó correctamente.
**Solución**: Ejecuta manualmente el script SQL de migración.

### Error al subir: "El archivo ZIP es obligatorio"
**Causa**: El formulario ahora requiere ambos archivos.
**Solución**: Asegúrate de seleccionar tanto el .exe como el .zip antes de subir.

### Error 404 al descargar ZIP
**Causa**: No hay archivo ZIP en el servidor o la ruta no existe.
**Solución**: 
- Verifica que el archivo exista en `/uploads/ClubCheck-{version}.zip`
- Verifica que la ruta `/api/download-zip` esté configurada en `routes/web.php`

### El ZIP no se muestra en la información de versión
**Causa**: La versión fue subida antes de aplicar estos cambios.
**Solución**: Sube una nueva versión con ambos archivos (EXE + ZIP).

---

## Próximos Pasos

1. Aplicar la migración de base de datos
2. Probar subida de nueva versión con EXE + ZIP
3. Verificar descargas públicas funcionando
4. Actualizar aplicaciones cliente para usar el nuevo endpoint de ZIP

---

## Contacto y Soporte

Si encuentras algún problema, verifica:
- Logs del servidor: `storage/logs/`
- Permisos de escritura en carpeta `uploads/`
- Configuración de PHP (`upload_max_filesize`, `post_max_size`)

¡Listo! El gestor ahora soporta archivos ZIP obligatorios con descarga pública. 🎉
