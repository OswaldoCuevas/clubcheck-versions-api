<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Catálogo único de errores controlados que una API puede devolver.
 *
 * El nombre del case explica el error dentro de PHP y value es el código
 * estable que reciben los clientes HTTP.
 */
enum ApiErrorCode: string
{
    case ApiError = 'API_ERROR';
    case ValidationError = 'VALIDATION_ERROR';
    case Unauthorized = 'UNAUTHORIZED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case RouteNotFound = 'ROUTE_NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case ServiceError = 'SERVICE_ERROR';
    case InternalError = 'INTERNAL_ERROR';

    case TokenMissing = 'TOKEN_MISSING';
    case TokenExpired = 'TOKEN_EXPIRED';
    case TokenInvalid = 'TOKEN_INVALID';
    case TokenTypeInvalid = 'TOKEN_TYPE_INVALID';
    case TokenIncomplete = 'TOKEN_INCOMPLETE';
    case TokenNotCurrent = 'TOKEN_NOT_CURRENT';
    case MachineTokenMismatch = 'MACHINE_TOKEN_MISMATCH';
    case CustomerNotFound = 'CUSTOMER_NOT_FOUND';
    case CustomerInactive = 'CUSTOMER_INACTIVE';
    case InvalidJsonPayload = 'INVALID_JSON_PAYLOAD';
    case DesktopSyncValidationFailed = 'DESKTOP_SYNC_VALIDATION_FAILED';
    case CustomerSessionValidationFailed = 'CUSTOMER_SESSION_VALIDATION_FAILED';
    case CustomerSessionMetadataTooLarge = 'CUSTOMER_SESSION_METADATA_TOO_LARGE';
    case CustomerSessionNotFound = 'CUSTOMER_SESSION_NOT_FOUND';
    case CustomerValidationFailed = 'CUSTOMER_VALIDATION_FAILED';
    case CustomerResourceNotFound = 'CUSTOMER_RESOURCE_NOT_FOUND';
    case CustomerConflict = 'CUSTOMER_CONFLICT';
    case CustomerTooManyAttempts = 'CUSTOMER_TOO_MANY_ATTEMPTS';
    case CustomerOperationFailed = 'CUSTOMER_OPERATION_FAILED';

    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case PlanLimitExceeded = 'PLAN_LIMIT_EXCEEDED';
    case SubscriptionExists = 'SUBSCRIPTION_EXISTS';
    case WhatsAppUnavailable = 'WHATSAPP_UNAVAILABLE';
    case WhatsAppValidationFailed = 'WHATSAPP_VALIDATION_FAILED';
    case WhatsAppProfileNotFound = 'WHATSAPP_PROFILE_NOT_FOUND';
    case PushNotificationValidationFailed = 'PUSH_NOTIFICATION_VALIDATION_FAILED';
    case PermissionRequestValidationFailed = 'PERMISSION_REQUEST_VALIDATION_FAILED';
    case DesktopUserProfileValidationFailed = 'DESKTOP_USER_PROFILE_VALIDATION_FAILED';
    case DesktopUserProfileNotFound = 'DESKTOP_USER_PROFILE_NOT_FOUND';
    case NotificationValidationFailed = 'NOTIFICATION_VALIDATION_FAILED';
    case NotificationNotFound = 'NOTIFICATION_NOT_FOUND';
    case UserPhotoInvalid = 'USER_PHOTO_INVALID';
    case UserPhotoTooLarge = 'USER_PHOTO_TOO_LARGE';
    case UserPhotoUnsupported = 'USER_PHOTO_UNSUPPORTED';
    case UserPhotoNotFound = 'USER_PHOTO_NOT_FOUND';
    case UserPhotoConflict = 'USER_PHOTO_CONFLICT';

    case AnnouncementNotFound = 'ANNOUNCEMENT_NOT_FOUND';
    case AnnouncementValidationFailed = 'ANNOUNCEMENT_VALIDATION_FAILED';
    case AnnouncementSaveFailed = 'ANNOUNCEMENT_SAVE_FAILED';
    case AnnouncementImageMissing = 'ANNOUNCEMENT_IMAGE_MISSING';
    case AnnouncementImageUnsupportedFormat = 'ANNOUNCEMENT_IMAGE_UNSUPPORTED_FORMAT';
    case AnnouncementImageTooLarge = 'ANNOUNCEMENT_IMAGE_TOO_LARGE';
    case AnnouncementImageDirectoryCreationFailed = 'ANNOUNCEMENT_IMAGE_DIRECTORY_CREATION_FAILED';
    case AnnouncementImageStorageFailed = 'ANNOUNCEMENT_IMAGE_STORAGE_FAILED';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Unauthorized,
            self::TokenMissing,
            self::TokenExpired,
            self::TokenInvalid,
            self::TokenTypeInvalid,
            self::TokenIncomplete,
            self::TokenNotCurrent,
            self::MachineTokenMismatch,
            self::CustomerNotFound,
            self::CustomerInactive,
            self::InvalidCredentials => 401,

            self::Forbidden,
            self::PlanLimitExceeded => 403,

            self::NotFound,
            self::RouteNotFound,
            self::CustomerSessionNotFound,
            self::CustomerResourceNotFound,
            self::AnnouncementNotFound => 404,
            self::DesktopUserProfileNotFound => 404,
            self::NotificationNotFound => 404,
            self::UserPhotoNotFound => 404,
            self::WhatsAppProfileNotFound => 404,

            self::MethodNotAllowed => 405,

            self::Conflict,
            self::CustomerConflict,
            self::SubscriptionExists => 409,
            self::UserPhotoConflict => 409,

            self::CustomerTooManyAttempts => 429,

            self::AnnouncementImageMissing,
            self::AnnouncementImageUnsupportedFormat,
            self::AnnouncementImageTooLarge,
            self::AnnouncementValidationFailed,
            self::DesktopSyncValidationFailed,
            self::CustomerSessionValidationFailed,
            self::CustomerValidationFailed => 422,
            self::PushNotificationValidationFailed,
            self::PermissionRequestValidationFailed => 422,
            self::DesktopUserProfileValidationFailed => 422,
            self::NotificationValidationFailed => 422,
            self::WhatsAppValidationFailed => 422,

            self::CustomerSessionMetadataTooLarge => 413,
            self::UserPhotoTooLarge => 413,

            self::UserPhotoUnsupported => 415,

            self::ServiceError,
            self::WhatsAppUnavailable => 502,

            self::InternalError,
            self::CustomerOperationFailed,
            self::AnnouncementSaveFailed,
            self::AnnouncementImageDirectoryCreationFailed,
            self::AnnouncementImageStorageFailed => 500,

            self::ApiError,
            self::ValidationError,
            self::InvalidJsonPayload => 400,
            self::UserPhotoInvalid => 400,
        };
    }

    public function publicMessage(): string
    {
        return match ($this) {
            self::ApiError => 'Error en la solicitud',
            self::ValidationError => 'Error de validación',
            self::Unauthorized => 'No autorizado',
            self::Forbidden => 'Acceso prohibido',
            self::NotFound => 'Recurso no encontrado',
            self::RouteNotFound => 'Endpoint no encontrado',
            self::MethodNotAllowed => 'Método no permitido',
            self::Conflict => 'Conflicto con el recurso',
            self::ServiceError => 'Error en servicio externo',
            self::InternalError => 'Error interno del servidor',
            self::TokenMissing => 'Token no proporcionado',
            self::TokenExpired => 'Token expirado',
            self::TokenInvalid => 'Token inválido',
            self::TokenTypeInvalid => 'Tipo de token inválido',
            self::TokenIncomplete => 'Token incompleto',
            self::TokenNotCurrent => 'Token no es el actual',
            self::MachineTokenMismatch => 'Token de máquina no coincide',
            self::CustomerNotFound => 'Cliente no encontrado',
            self::CustomerInactive => 'Cliente inactivo',
            self::InvalidJsonPayload => 'Carga JSON inválida',
            self::DesktopSyncValidationFailed => 'Datos de sincronización inválidos',
            self::CustomerSessionValidationFailed => 'Datos de sesión inválidos',
            self::CustomerSessionMetadataTooLarge => 'Metadatos de sesión demasiado grandes',
            self::CustomerSessionNotFound => 'Sesión no encontrada',
            self::CustomerValidationFailed => 'Datos de cliente inválidos',
            self::CustomerResourceNotFound => 'Cliente no encontrado',
            self::CustomerConflict => 'Conflicto con el cliente',
            self::CustomerTooManyAttempts => 'Demasiados intentos',
            self::CustomerOperationFailed => 'No se pudo completar la operación del cliente',
            self::InvalidCredentials => 'Credenciales inválidas',
            self::PlanLimitExceeded => 'El plan no permite esta operación',
            self::SubscriptionExists => 'Ya existe una suscripción activa',
            self::WhatsAppUnavailable => 'Servicio de WhatsApp no disponible',
            self::WhatsAppValidationFailed => 'Datos de WhatsApp inválidos',
            self::WhatsAppProfileNotFound => 'Configuración de WhatsApp no encontrada',
            self::PushNotificationValidationFailed => 'Datos de notificación push inválidos',
            self::PermissionRequestValidationFailed => 'Datos de solicitud de permiso inválidos',
            self::DesktopUserProfileValidationFailed => 'Filtros de ficha de socio inválidos',
            self::DesktopUserProfileNotFound => 'Socio no accesible',
            self::NotificationValidationFailed => 'Datos de notificación inválidos',
            self::NotificationNotFound => 'Notificación no encontrada',
            self::UserPhotoInvalid => 'Fotografía inválida',
            self::UserPhotoTooLarge => 'La fotografía excede el límite permitido',
            self::UserPhotoUnsupported => 'Formato de fotografía no soportado',
            self::UserPhotoNotFound => 'Fotografía no accesible',
            self::UserPhotoConflict => 'Conflicto de versión de fotografía',
            self::AnnouncementNotFound => 'Anuncio no encontrado',
            self::AnnouncementValidationFailed => 'Datos de anuncio inválidos',
            self::AnnouncementSaveFailed => 'Error al guardar el anuncio',
            self::AnnouncementImageMissing => 'Imagen no proporcionada',
            self::AnnouncementImageUnsupportedFormat => 'Formato de imagen no soportado',
            self::AnnouncementImageTooLarge => 'La imagen no puede superar 5 MB',
            self::AnnouncementImageDirectoryCreationFailed => 'No se pudo crear el directorio de imagenes',
            self::AnnouncementImageStorageFailed => 'No se pudo guardar la imagen',
        };
    }
}
