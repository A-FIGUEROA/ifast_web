<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Solo ADMIN y SUPERVISOR pueden cargar Excel
requierePermiso(['ADMIN', 'SUPERVISOR']);

$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Guías desde Excel</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
        }

        /* HEADER */
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FDC500 0%, #FFD500 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #7f8c8d;
            display: inline-block;
            padding: 2px 8px;
            background: #ecf0f1;
            border-radius: 10px;
            margin-top: 2px;
        }

        .btn-logout {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .content {
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
        }

        .card-title {
            font-size: 1.5rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upload-zone {
            border: 3px dashed #00509D;
            border-radius: 15px;
            padding: 50px 30px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-zone:hover {
            background: #e9ecef;
            border-color: #00296B;
        }

        .upload-zone.dragover {
            background: #e7f3ff;
            border-color: #00296B;
        }

        .upload-icon {
            font-size: 4rem;
            color: #00509D;
            margin-bottom: 15px;
        }

        .upload-zone h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .upload-zone p {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .file-input {
            display: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 41, 107, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #00509D;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #00296B;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .step {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .step h4 {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .step p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .preview-section {
            display: none;
            margin-top: 20px;
        }

        .preview-section.show {
            display: block;
        }

        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .file-info i {
            font-size: 2.5rem;
            color: #28a745;
        }

        .file-details h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .file-details p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .table-container {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.85rem;
            position: sticky;
            top: 0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
            font-size: 0.85rem;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #00509D;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Estilos para reporte de asignaciones */
        .report-summary {
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .stat-box {
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-box.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }

        .stat-box.warning {
            border-left-color: #ffc107;
            background: #fffbf0;
        }

        .stat-box.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .stat-box h4 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #666;
        }

        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-box.success .number { color: #28a745; }
        .stat-box.warning .number { color: #f57c00; }
        .stat-box.error .number { color: #dc3545; }

        .stat-box button {
            margin-top: 10px;
            padding: 8px 15px;
            border: none;
            background: #00509D;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .stat-box button:hover {
            background: #00296B;
        }

        /* Modal para detalles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-close {
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }

        .modal-close:hover {
            color: #333;
        }

        .detail-list {
            list-style: none;
            padding: 0;
        }

        .detail-item {
            padding: 12px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #00509D;
        }

        .detail-item strong {
            color: #00296B;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .steps {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <h1>Carga Masiva Excel</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($nombre_usuario, 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $nombre_usuario; ?></div>
                        <span class="user-role"><?php echo $tipo_usuario; ?></span>
                    </div>
                </div>
                <a href="../../logout.php" class="btn-logout">
                    <box-icon name='log-out' color='white' size='20px'></box-icon>
                </a>
            </div>
        </header>

        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class='bx bx-upload'></i>
                        Carga Masiva desde Excel
                    </h2>
                </div>

                <div class="info-box">
                    <h4><i class='bx bx-info-circle'></i> Instrucciones:</h4>
                    <ul>
                        <li>El archivo debe estar en formato Excel (.xlsx o .xls)</li>
                        <li>La primera fila debe contener los encabezados</li>
                        <li>Las columnas deben ser: <strong># GUIA | CONSIGNATARIO | DESCRIPCION | PCS | PESO MANIF. KG | VALOR FOB US$ | FECHA DE EMBARQUE</strong></li>
                        <li>Descarga la plantilla para ver el formato correcto</li>
                        <li>Los números de guía duplicados serán omitidos</li>
                    </ul>
                </div>

                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h4>Descargar Plantilla</h4>
                        <p>Descarga el archivo Excel de ejemplo</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h4>Llenar Datos</h4>
                        <p>Completa el Excel con tu información</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h4>Subir Archivo</h4>
                        <p>Arrastra o selecciona tu archivo</p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <a href="descargar_plantilla.php" class="btn btn-success">
                        <i class='bx bx-download'></i>
                        Descargar Plantilla Excel
                    </a>
                </div>

                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="upload-zone" id="uploadZone">
                        <i class='bx bx-cloud-upload upload-icon'></i>
                        <h3>Arrastra tu archivo Excel aquí</h3>
                        <p>o haz clic para seleccionar</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                            <i class='bx bx-file'></i>
                            Seleccionar Archivo
                        </button>
                        <input
                            type="file"
                            id="fileInput"
                            name="archivo"
                            class="file-input"
                            accept=".xlsx,.xls"
                        >
                    </div>
                </form>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Procesando archivo Excel, por favor espere...</p>
                </div>

                <div class="preview-section" id="previewSection">
                    <div class="file-info" id="fileInfo"></div>

                    <!-- REPORTE DE ASIGNACIONES -->
                    <div class="report-summary" id="reportSummary"></div>

                    <h3 style="margin-bottom: 15px;">Vista Previa (Primeras 10 filas)</h3>
                    <div class="table-container" id="previewTable"></div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="btnConfirmar">
                            <i class='bx bx-check'></i>
                            Confirmar e Importar
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnCancelar">
                            <i class='bx bx-x'></i>
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- MODAL PARA DETALLES -->
                <div class="modal" id="modalDetalles">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="modalTitle">Detalles</h3>
                            <span class="modal-close" onclick="cerrarModal()">&times;</span>
                        </div>
                        <div id="modalBody"></div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i>
                    Volver al Listado
                </a>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const loading = document.getElementById('loading');
        const previewSection = document.getElementById('previewSection');
        let currentFormData = null;

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileUpload();
            }
        });

        // File input change
        fileInput.addEventListener('change', handleFileUpload);

        function handleFileUpload() {
            const file = fileInput.files[0];
            if (!file) return;

            // Validar extensión
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'xlsx' && ext !== 'xls') {
                alert('Por favor selecciona un archivo Excel (.xlsx o .xls)');
                return;
            }

            // Mostrar loading
            loading.classList.add('show');
            previewSection.classList.remove('show');

            // Preparar FormData
            currentFormData = new FormData();
            currentFormData.append('archivo', file);
            currentFormData.append('preview', '1');

            // Enviar archivo para preview
            fetch('procesar_excel.php', {
                method: 'POST',
                body: currentFormData
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('show');

                if (data.success) {
                    mostrarPreview(data);
                } else {
                    alert('Error: ' + data.mensaje);
                }
            })
            .catch(error => {
                loading.classList.remove('show');
                alert('Error al procesar el archivo: ' + error);
            });
        }

        // Variable global para datos del reporte
        let reportData = null;

        function mostrarPreview(data) {
            reportData = data; // Guardar para modales

            // Mostrar información del archivo
            document.getElementById('fileInfo').innerHTML = `
                <i class='bx bxs-file-doc'></i>
                <div class="file-details">
                    <h4>${data.nombre_archivo}</h4>
                    <p>${data.total_registros} registros encontrados | ${data.registros_validos} válidos | ${data.registros_error} con errores</p>
                </div>
            `;

            // Generar reporte de asignaciones
            let reportHTML = '';

            // Asignaciones automáticas
            if (data.asignaciones_automaticas && data.asignaciones_automaticas.total > 0) {
                reportHTML += `
                    <div class="stat-box success">
                        <h4>Asignaciones Automáticas</h4>
                        <div class="number">${data.asignaciones_automaticas.total}</div>
                        <p>Guías asignadas automáticamente a clientes</p>
                        <button onclick="verDetalle('asignadas')">Ver Detalle</button>
                    </div>
                `;
            }

            // Sin asignar
            if (data.sin_asignar && data.sin_asignar.total > 0) {
                reportHTML += `
                    <div class="stat-box warning">
                        <h4>Sin Asignar</h4>
                        <div class="number">${data.sin_asignar.total}</div>
                        <p>Guías que requieren asignación manual</p>
                        <button onclick="verDetalle('sin_asignar')">Ver Detalle</button>
                    </div>
                `;
            }

            // Errores/omitidos
            if (data.registros_error > 0) {
                reportHTML += `
                    <div class="stat-box error">
                        <h4>Omitidos</h4>
                        <div class="number">${data.registros_error}</div>
                        <p>Registros con errores (duplicados, etc)</p>
                        <button onclick="verDetalle('errores')">Ver Detalle</button>
                    </div>
                `;
            }

            document.getElementById('reportSummary').innerHTML = reportHTML;

            // Crear tabla de preview
            let tableHTML = '<table><thead><tr>';
            tableHTML += '<th>N° Guía</th><th>Consignatario</th><th>RUC/DNI</th><th>Descripción</th><th>PCS</th><th>Peso (kg)</th><th>Valor FOB</th><th>Fecha Embarque</th>';
            tableHTML += '</tr></thead><tbody>';

            data.preview.forEach(row => {
                tableHTML += '<tr>';
                tableHTML += `<td>${row.nro_guia}</td>`;
                tableHTML += `<td>${row.consignatario}</td>`;
                tableHTML += `<td>${row.documento_cliente || '-'}</td>`;
                tableHTML += `<td>${row.descripcion || '-'}</td>`;
                tableHTML += `<td>${row.pcs}</td>`;
                tableHTML += `<td>${row.peso_kg}</td>`;
                tableHTML += `<td>$${row.valor_fob_usd}</td>`;
                tableHTML += `<td>${row.fecha_embarque || '-'}</td>`;
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table>';
            document.getElementById('previewTable').innerHTML = tableHTML;

            previewSection.classList.add('show');
        }

        // Función para mostrar detalles en modal
        function verDetalle(tipo) {
            const modal = document.getElementById('modalDetalles');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');

            let content = '';

            if (tipo === 'asignadas') {
                modalTitle.textContent = `Asignaciones Automáticas (${reportData.asignaciones_automaticas.total})`;
                content = '<ul class="detail-list">';
                reportData.asignaciones_automaticas.detalles.forEach(item => {
                    content += `
                        <li class="detail-item">
                            <strong>Guía:</strong> ${item.nro_guia}<br>
                            <strong>Cliente:</strong> ${item.cliente_nombre}<br>
                            <strong>Documento:</strong> ${item.documento}
                        </li>
                    `;
                });
                content += '</ul>';
            } else if (tipo === 'sin_asignar') {
                modalTitle.textContent = `Sin Asignar (${reportData.sin_asignar.total})`;
                content = '<ul class="detail-list">';
                reportData.sin_asignar.detalles.forEach(item => {
                    content += `
                        <li class="detail-item">
                            <strong>Guía:</strong> ${item.nro_guia}<br>
                            <strong>Documento buscado:</strong> ${item.documento_buscado || 'N/A'}<br>
                            <strong>Razón:</strong> ${item.razon}
                        </li>
                    `;
                });
                content += '</ul>';
            } else if (tipo === 'errores') {
                modalTitle.textContent = `Registros Omitidos (${reportData.registros_error})`;
                content = '<ul class="detail-list">';
                reportData.errores.forEach(item => {
                    content += `
                        <li class="detail-item">
                            <strong>Fila:</strong> ${item.fila}<br>
                            <strong>Error:</strong> ${item.error}
                        </li>
                    `;
                });
                content += '</ul>';
            }

            modalBody.innerHTML = content;
            modal.classList.add('show');
        }

        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalDetalles').classList.remove('show');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalDetalles').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Confirmar importación
        document.getElementById('btnConfirmar').addEventListener('click', () => {
            if (!currentFormData) return;

            if (!confirm('¿Está seguro de importar estos datos?')) return;

            loading.classList.add('show');
            previewSection.classList.remove('show');

            // Cambiar flag de preview a importar
            currentFormData.set('preview', '0');

            fetch('procesar_excel.php', {
                method: 'POST',
                body: currentFormData
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('show');

                if (data.success) {
                    alert(`Importación exitosa!\n${data.registros_importados} registros importados\n${data.registros_omitidos} registros omitidos`);
                    window.location.href = 'index.php?success=importado';
                } else {
                    alert('Error: ' + data.mensaje);
                }
            })
            .catch(error => {
                loading.classList.remove('show');
                alert('Error al importar: ' + error);
            });
        });

        // Cancelar
        document.getElementById('btnCancelar').addEventListener('click', () => {
            previewSection.classList.remove('show');
            fileInput.value = '';
            currentFormData = null;
        });
    </script>
</body>
</html>
