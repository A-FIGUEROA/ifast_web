-- =====================================================
-- Script para agregar campos de envío de correo
-- Tabla: documentos_facturacion
-- Fecha: 2026-01-04
-- =====================================================

-- Agregar campos de control de envío de correo
ALTER TABLE `documentos_facturacion`
ADD COLUMN `estado_envio` ENUM('PENDIENTE', 'ENVIADO') DEFAULT 'PENDIENTE' COMMENT 'Estado del envío por correo' AFTER `creado_en`,
ADD COLUMN `correo_enviado_a` TEXT NULL COMMENT 'Correos a los que se envió el documento' AFTER `estado_envio`,
ADD COLUMN `fecha_envio` DATETIME NULL COMMENT 'Fecha y hora del último envío' AFTER `correo_enviado_a`;

-- Verificar que los campos se agregaron correctamente
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'documentos_facturacion'
  AND COLUMN_NAME IN ('estado_envio', 'correo_enviado_a', 'fecha_envio');

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================
