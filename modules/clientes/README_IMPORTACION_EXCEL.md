# 📊 Sistema de Importación Masiva de Clientes desde Excel

## ✅ IMPLEMENTACIÓN COMPLETADA

Se ha implementado completamente la funcionalidad de importación masiva de clientes desde archivos Excel (.xlsx, .xls).

---

## 📋 ARCHIVOS CREADOS

### **Archivos Nuevos:**

1. **`cargar_excel.php`**
   - Interfaz de usuario para carga de archivos Excel
   - Drag & drop para archivos
   - Vista previa antes de importar
   - Validación de archivos (.xlsx, .xls)
   - Instrucciones paso a paso
   - Botón para descargar plantilla

2. **`procesar_excel.php`**
   - Backend para procesamiento de archivos Excel
   - Usa PhpOffice\PhpSpreadsheet para leer Excel
   - Validación completa de datos
   - Modo PREVIEW (primeros 10 registros)
   - Modo IMPORTACIÓN (inserción masiva)
   - Detección de duplicados
   - Manejo de transacciones

3. **`descargar_plantilla.php`**
   - Genera archivo Excel de plantilla
   - Incluye encabezados correctos
   - Datos de ejemplo (3 filas)
   - Hoja de instrucciones detalladas
   - Estilos profesionales

4. **`README_IMPORTACION_EXCEL.md`**
   - Este archivo de documentación

### **Archivos Modificados:**

1. **`index.php`**
   - Botón "📊 Importar Excel" en la cabecera
   - Estilo CSS para botón de Excel
   - Solo visible para ADMIN y VENTAS

---

## 🚀 CÓMO USAR

### **Paso 1: Descargar Plantilla**

1. Ir a: `modules/clientes/index.php`
2. Clic en botón **"📊 Importar Excel"**
3. Clic en **"📥 Descargar Plantilla Excel"**
4. Se descarga archivo: `PLANTILLA_CLIENTES_IFAST.xlsx`

### **Paso 2: Completar Datos en Excel**

Abrir la plantilla y completar los campos:

**Campos Obligatorios:**
- `TIPO_DOC`: DNI o RUC
- `DOCUMENTO`: 8 dígitos (DNI) o 11 dígitos (RUC)
- `NOMBRE_RAZON_SOCIAL`: Nombre completo o razón social
- `EMAIL`: Correo electrónico válido
- `CELULAR`: Número de celular
- `DIRECCION`: Dirección completa
- `DISTRITO`: Distrito
- `PROVINCIA`: Provincia
- `DEPARTAMENTO`: Departamento

**Campos Opcionales:**
- `APELLIDO`: Apellidos (opcional)
- `TELIF`: Teléfono fijo (opcional)

**IMPORTANTE:**
- Eliminar las filas de ejemplo antes de importar
- No modificar los nombres de las columnas
- Los documentos duplicados serán detectados y rechazados

### **Paso 3: Importar Archivo**

1. Volver a `cargar_excel.php`
2. Arrastrar el archivo Excel a la zona de carga O hacer clic y seleccionar
3. Esperar la **Vista Previa**:
   - Muestra primeros 10 registros válidos
   - Muestra primeros 10 registros con errores
   - Muestra estadísticas (total, válidos, errores)
4. Revisar los datos
5. Si todo está correcto, clic en **"✅ Confirmar Importación"**
6. Se importan los registros válidos
7. Redirección automática a `index.php`

---

## 🔧 CARACTERÍSTICAS

### **Validaciones Implementadas:**

✅ **Tipo de Documento:**
- Solo acepta "DNI" o "RUC"
- Case insensitive (DNI = dni = Dni)

✅ **Documento:**
- DNI: Exactamente 8 dígitos numéricos
- RUC: Exactamente 11 dígitos numéricos
- Detección de duplicados en BD

✅ **Email:**
- Formato válido de email
- Obligatorio

✅ **Otros Campos:**
- Nombre/Razón Social: Obligatorio
- Celular: Obligatorio
- Dirección: Obligatoria
- Distrito, Provincia, Departamento: Obligatorios

### **Interfaz de Usuario:**

✅ Drag & Drop para archivos
✅ Validación en cliente (solo .xlsx, .xls)
✅ Spinner de carga
✅ Tabla de vista previa con formato profesional
✅ Diferenciación visual entre registros válidos y errores
✅ Botones de confirmación/cancelación
✅ Mensajes de éxito/error
✅ Instrucciones paso a paso

### **Backend:**

✅ Uso de PhpOffice\PhpSpreadsheet
✅ Validación de extensiones de archivo
✅ Límite de tamaño (10MB)
✅ Detección automática de columnas
✅ Mapeo flexible de headers
✅ Transacciones de BD con rollback
✅ Logging de errores
✅ JSON responses para AJAX

---

## 📊 FLUJO COMPLETO

```
1. Usuario descarga plantilla
   └─> Archivo Excel con headers e instrucciones

2. Usuario completa datos en Excel
   └─> Guarda archivo

3. Usuario sube archivo en cargar_excel.php
   └─> Drag & drop o click para seleccionar

4. Sistema procesa en MODO PREVIEW
   └─> Lee Excel
   └─> Valida cada fila
   └─> Clasifica: válidos vs errores
   └─> Retorna primeros 10 de cada categoría

5. Usuario revisa preview
   └─> Ve registros válidos (verde)
   └─> Ve registros con errores (rojo)
   └─> Ve estadísticas

6. Usuario confirma importación
   └─> Sistema procesa en MODO IMPORTACIÓN
   └─> Inicia transacción
   └─> Inserta registros válidos uno por uno
   └─> Commit si todo OK, rollback si hay error

7. Sistema muestra resultado
   └─> "Se importaron X clientes exitosamente"
   └─> Redirecciona a index.php

8. Usuario ve nuevos clientes en listado
```

---

## 📝 FORMATO DE PLANTILLA EXCEL

### **Encabezados (Fila 1):**

| TIPO_DOC | DOCUMENTO | NOMBRE_RAZON_SOCIAL | APELLIDO | EMAIL | TELIF | CELULAR | DIRECCION | DISTRITO | PROVINCIA | DEPARTAMENTO |
|----------|-----------|---------------------|----------|-------|-------|---------|-----------|----------|-----------|--------------|

### **Ejemplo de Datos (Fila 2):**

| DNI | 12345678 | Juan Pérez | García | juan.perez@email.com | 014567890 | 987654321 | Av. Ejemplo 123 | Miraflores | Lima | Lima |

---

## ⚠️ VALIDACIONES Y ERRORES

### **Errores Comunes:**

| Error | Causa | Solución |
|-------|-------|----------|
| "Tipo de documento inválido" | TIPO_DOC no es DNI ni RUC | Usar solo "DNI" o "RUC" |
| "DNI debe tener 8 dígitos" | Documento tiene más o menos de 8 dígitos | Corregir a 8 dígitos exactos |
| "RUC debe tener 11 dígitos" | Documento tiene más o menos de 11 dígitos | Corregir a 11 dígitos exactos |
| "Email no válido" | Formato de email incorrecto | Usar formato: usuario@dominio.com |
| "Documento ya existe en la base de datos" | El documento ya fue registrado | Eliminar fila duplicada del Excel |
| "Campo X es requerido" | Campo obligatorio está vacío | Completar el campo |

---

## 🎨 INTERFAZ

### **Zona de Carga:**
- Fondo con borde punteado azul
- Icono de nube con flecha
- Texto instructivo
- Animación en hover
- Drag & drop funcional

### **Vista Previa:**
- Tabla con scroll horizontal
- Headers con fondo azul
- Filas válidas con borde verde
- Filas con error con borde rojo
- Badge de estado (✅ VÁLIDO / ❌ ERROR)
- Tooltips con detalles de errores

### **Botones:**
- **Descargar Plantilla:** Azul (#2196F3)
- **Confirmar Importación:** Verde (#27ae60)
- **Cancelar:** Gris (#6c757d)

---

## 🔐 PERMISOS

Solo usuarios con permisos **ADMIN** o **VENTAS** pueden:
- Ver el botón "📊 Importar Excel"
- Acceder a `cargar_excel.php`
- Procesar archivos en `procesar_excel.php`

Los usuarios **SUPERVISOR** no pueden importar clientes.

---

## 📦 DEPENDENCIAS

### **PHP:**
- PhpOffice/PhpSpreadsheet (ya instalado en vendor)
- PDO para base de datos
- JSON para respuestas AJAX

### **JavaScript:**
- Fetch API para AJAX
- FormData para upload de archivos
- Event listeners para drag & drop

### **CSS:**
- Grid para layouts
- Flexbox para alineación
- Animaciones CSS3
- Responsive design

---

## 🧪 TESTING

### **Prueba 1: Descargar Plantilla**
1. Ir a `index.php`
2. Clic en "📊 Importar Excel"
3. Clic en "📥 Descargar Plantilla Excel"
4. Verificar que se descarga `PLANTILLA_CLIENTES_IFAST.xlsx`
5. Abrir archivo y verificar headers + ejemplos + instrucciones

### **Prueba 2: Importación Exitosa**
1. Completar plantilla con 5 clientes válidos
2. Eliminar filas de ejemplo
3. Guardar archivo
4. Subir en `cargar_excel.php`
5. Verificar preview muestra 5 registros válidos
6. Confirmar importación
7. Verificar mensaje de éxito
8. Ir a `index.php` y verificar que aparecen los 5 clientes

### **Prueba 3: Validación de Errores**
1. Completar plantilla con datos inválidos:
   - DNI con 7 dígitos
   - Email sin @
   - Campos vacíos
2. Subir archivo
3. Verificar que preview muestra errores
4. Verificar que no se puede confirmar si solo hay errores

### **Prueba 4: Duplicados**
1. Crear cliente manualmente con DNI 12345678
2. Importar Excel con mismo DNI
3. Verificar que se detecta como duplicado
4. Verificar que no se importa

---

## 📞 NOTAS TÉCNICAS

### **Límites:**
- Tamaño máximo de archivo: **10MB**
- Extensiones permitidas: **.xlsx, .xls**
- No hay límite de registros (recomendado máximo 1000)

### **Transacciones:**
- Se usa `beginTransaction()` antes de insertar
- Se hace `commit()` si todo OK
- Se hace `rollBack()` si hay error
- Cada registro se inserta individualmente

### **Logging:**
- Los errores de inserción se registran en error_log
- Los errores de usuario se retornan en JSON

### **Performance:**
- Preview solo retorna primeros 10 registros
- Importación procesa todos los registros válidos
- La validación en BD se hace antes de la transacción

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [x] Crear interfaz de carga (`cargar_excel.php`)
- [x] Crear procesador backend (`procesar_excel.php`)
- [x] Crear generador de plantilla (`descargar_plantilla.php`)
- [x] Agregar botón en `index.php`
- [x] Implementar drag & drop
- [x] Implementar validaciones
- [x] Implementar modo preview
- [x] Implementar modo importación
- [x] Implementar detección de duplicados
- [x] Implementar manejo de transacciones
- [x] Crear documentación

---

**Desarrollado:** 2026-01-05
**Sistema:** IFAST - International Courier Service S.A.C.
**Módulo:** Clientes - Importación Masiva Excel
