-- Script SQL para agregar columnas CLIENTE y ASESOR a la tabla guias_masivas
-- Ejecutar este script en phpMyAdmin o en tu gestor de base de datos

-- Agregar columna CLIENTE después de CONSIGNATARIO
ALTER TABLE `guias_masivas`
ADD COLUMN `cliente` VARCHAR(255) NULL AFTER `consignatario`;

-- Agregar columna ASESOR al final (antes de metodo_ingreso)
ALTER TABLE `guias_masivas`
ADD COLUMN `asesor` VARCHAR(255) NULL AFTER `fecha_embarque`;

-- Verificar que se agregaron correctamente
-- DESCRIBE guias_masivas;
