# 📧 Sistema de Envío de Correos - Módulo de Facturación

## ✅ IMPLEMENTACIÓN COMPLETADA

Se ha implementado completamente la funcionalidad de envío de documentos (Facturas, Boletas, Recibos) por correo electrónico.

---

## 📋 ARCHIVOS CREADOS/MODIFICADOS

### **Archivos Nuevos:**

1. **`ALTER_TABLE_envio_correo.sql`**
   - Script SQL para agregar campos de control de envío
   - Campos: `estado_envio`, `correo_enviado_a`, `fecha_envio`

2. **`funciones_pdf.php`**
   - Funciones reutilizables para generar PDFs
   - `generarHTMLDocumento()` - Genera el HTML del documento
   - `generarYGuardarPDF()` - Genera y guarda el PDF en el servidor

3. **`enviar_correo.php`**
   - Backend para enviar documentos por correo
   - Usa PHPMailer con SMTP Gmail
   - Adjunta PDF del documento e imagen (opcional)

4. **`README_ENVIO_CORREOS.md`**
   - Este archivo de documentación

### **Archivos Modificados:**

1. **`crear.php`**
   - Ahora genera y guarda el PDF físicamente al crear el documento
   - Include de `funciones_pdf.php`
   - Llama a `generarYGuardarPDF()` después del INSERT

2. **`editar.php`**
   - Regenera el PDF al editar el documento
   - Include de `funciones_pdf.php`
   - Llama a `generarYGuardarPDF()` después del UPDATE

3. **`index.php`**
   - Botón "📧" para enviar por correo en cada fila
   - Modal para capturar destinatarios y mensaje
   - Badge de estado (PENDIENTE/ENVIADO)
   - JavaScript para manejar el envío

---

## 🚀 PASOS PARA ACTIVAR

### **1. Ejecutar el Script SQL**

```sql
-- Ejecutar en phpMyAdmin o tu gestor de BD
-- Archivo: modules/facturacion/ALTER_TABLE_envio_correo.sql

ALTER TABLE `documentos_facturacion`
ADD COLUMN `estado_envio` ENUM('PENDIENTE', 'ENVIADO') DEFAULT 'PENDIENTE',
ADD COLUMN `correo_enviado_a` TEXT NULL,
ADD COLUMN `fecha_envio` DATETIME NULL;
```

### **2. Verificar directorio de uploads**

Asegúrate de que existe el directorio:
```
uploads/facturas/
```

Si no existe, créalo con permisos de escritura (0777).

### **3. Listo para usar**

- Los nuevos documentos generarán automáticamente su PDF
- Los documentos editados regenerarán su PDF
- El botón 📧 estará disponible en cada documento

---

## 🔧 CARACTERÍSTICAS

### **Generación de PDF:**
- ✅ Se genera al **crear** el documento
- ✅ Se regenera al **editar** el documento
- ✅ Se guarda físicamente en `uploads/facturas/`
- ✅ Nombre del archivo: `[NUMERO] [CLIENTE].pdf`

### **Envío por Correo:**
- ✅ Múltiples destinatarios (separados por comas)
- ✅ Mensaje personalizado opcional
- ✅ Adjunta PDF del documento automáticamente
- ✅ Adjunta imagen si existe (opcional)
- ✅ Asunto personalizado con número y cliente
- ✅ Cuerpo HTML profesional
- ✅ Actualiza estado en BD

### **Estados:**
- **PENDIENTE** ⏳ - No se ha enviado por correo
- **ENVIADO** ✅ - Se envió al menos una vez

---

## 📨 FORMATO DEL CORREO

### **Asunto:**
```
Documento [TIPO] [NUMERO] - [CLIENTE] - IFAST
```
Ejemplo: `Documento FACTURA FV-00001 - Juan Pérez - IFAST`

### **Cuerpo:**
- Header con logo IFAST
- Saludo personalizado al cliente
- Información del documento (número, tipo, fecha)
- Total destacado
- Mensaje personalizado (si existe)
- Footer con datos de contacto

### **Adjuntos:**
1. PDF del documento (siempre)
2. Imagen adjunta (si existe y usuario lo solicita)

---

## 🔐 CONFIGURACIÓN SMTP

El sistema usa la siguiente configuración de Gmail:

```php
Host: smtp.gmail.com
Port: 587
Usuario: ventasifast2@gmail.com
Password: hbld olsj vghe ofvs (App Password)
Encryption: STARTTLS
```

**Nota:** Esta configuración está en `enviar_correo.php`

---

## 📊 FLUJO COMPLETO

```
1. Usuario crea/edita documento
   └─> PDF se genera y guarda en servidor

2. Usuario hace clic en botón 📧
   └─> Modal se abre

3. Usuario completa formulario:
   - Correos destino (precargado con email del cliente)
   - Mensaje personalizado (opcional)
   - Incluir imagen adjunta (opcional, solo si existe)

4. Usuario envía formulario
   └─> AJAX POST a enviar_correo.php

5. Backend:
   - Valida datos
   - Prepara correo con PHPMailer
   - Adjunta PDF e imagen (si aplica)
   - Envía correo
   - Actualiza BD (estado_envio, correo_enviado_a, fecha_envio)

6. Usuario recibe confirmación
   └─> Página se recarga mostrando nuevo estado
```

---

## ⚠️ DOCUMENTOS ANTIGUOS

Los documentos creados **ANTES** de esta implementación:
- **NO tienen PDF físico guardado**
- Se puede solucionar:
  1. **Opción A:** Editarlos (regenerará el PDF)
  2. **Opción B:** Ejecutar script de regeneración (por crear)

---

## 🆚 DIFERENCIAS CON EMBARQUES

| Característica | Embarques | Facturación |
|----------------|-----------|-------------|
| Archivos adjuntos | Múltiples (trackings + manuales) | 1 PDF + 1 imagen (opcional) |
| Asunto | Consignatario | Tipo + Número + Cliente |
| Destinatario | Conti Express | Cliente + adicionales |
| Actualización estado | `estado_envio` | `estado_envio` |

---

## ✅ TESTING

Para probar la funcionalidad:

1. **Crear un documento:**
   - Ve a "Crear Documento"
   - Completa los datos
   - Guarda
   - Verifica que se creó el PDF en `uploads/facturas/`

2. **Enviar por correo:**
   - En el listado, clic en 📧
   - Completa correos destino
   - (Opcional) Agrega mensaje
   - Envía
   - Verifica que llegó el correo
   - Verifica que el estado cambió a "ENVIADO"

3. **Editar documento:**
   - Edita cualquier campo
   - Guarda
   - Verifica que el PDF se regeneró

---

## 📞 SOPORTE

Si hay algún problema:
1. Verificar que el script SQL se ejecutó correctamente
2. Verificar permisos del directorio `uploads/facturas/`
3. Verificar configuración SMTP en `enviar_correo.php`
4. Verificar logs del servidor para errores de PHPMailer

---

**Desarrollado:** 2026-01-04
**Sistema:** IFAST - International Courier Service S.A.C.
