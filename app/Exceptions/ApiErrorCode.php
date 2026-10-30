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

    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case PlanLimitExceeded = 'PLAN_LIMIT_EXCEEDED';
    case SubscriptionExists = 'SUBSCRIPTION_EXISTS';
    case WhatsAppUnavailable = 'WHATSAPP_UNAVAILABLE';

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
            self::RouteNotFound => 404,

            self::Conflict,
            self::SubscriptionExists => 409,

            self::AnnouncementImageMissing,
            self::AnnouncementImageUnsupportedFormat,
            self::AnnouncementImageTooLarge => 422,

            self::ServiceError,
            self::WhatsAppUnavailable => 502,

            self::InternalError,
            self::AnnouncementImageDirectoryCreationFailed,
            self::AnnouncementImageStorageFailed => 500,

            self::ApiError,
            self::ValidationError => 400,
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
            self::InvalidCredentials => 'Credenciales inválidas',
            self::PlanLimitExceeded => 'El plan no permite esta operación',
            self::SubscriptionExists => 'Ya existe una suscripción activa',
            self::WhatsAppUnavailable => 'Servicio de WhatsApp no disponible',
            self::AnnouncementImageMissing => 'Imagen no proporcionada',
            self::AnnouncementImageUnsupportedFormat => 'Formato de imagen no soportado',
            self::AnnouncementImageTooLarge => 'La imagen no puede superar 5 MB',
            self::AnnouncementImageDirectoryCreationFailed => 'No se pudo crear el directorio de imagenes',
            self::AnnouncementImageStorageFailed => 'No se pudo guardar la imagen',
        };
    }
}
