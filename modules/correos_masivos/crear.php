<?php
// modules/correos_masivos/crear.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn     = $database->getConnection();

$tipo_usuario   = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Si viene un ID, cargamos borrador para editar
$campana     = null;
$adjuntos    = [];
$destinatarios = [];
$id_editar   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_editar > 0) {
    $stmt = $conn->prepare("SELECT * FROM campanas_correo WHERE id = :id AND estado = 'BORRADOR'");
    $stmt->bindParam(':id', $id_editar);
    $stmt->execute();
    $campana = $stmt->fetch();

    if ($campana) {
        $stmt2 = $conn->prepare("SELECT email, nombre FROM campana_destinatarios WHERE campana_id = :id");
        $stmt2->bindParam(':id', $id_editar);
        $stmt2->execute();
        $destinatarios = $stmt2->fetchAll();

        $stmt3 = $conn->prepare("SELECT * FROM campana_adjuntos WHERE campana_id = :id");
        $stmt3->bindParam(':id', $id_editar);
        $stmt3->execute();
        $adjuntos = $stmt3->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $campana ? 'Editar Borrador' : 'Nueva Campaña'; ?> - iFast</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f5f7fa; }
        .container { display:flex; min-height:100vh; }
        .main-content { flex:1; margin-left:260px; }
        .header { background:white; padding:20px 30px; box-shadow:0 2px 10px rgba(0,0,0,0.05);
                  display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:1.8rem; color:#2c3e50; font-weight:600; }
        .header-right { display:flex; align-items:center; gap:20px; }
        .user-info { display:flex; align-items:center; gap:15px; }
        .user-avatar { width:45px; height:45px; border-radius:50%;
                       background:linear-gradient(135deg,#FDC500 0%,#FFD500 100%);
                       display:flex; align-items:center; justify-content:center;
                       color:white; font-weight:600; font-size:1.1rem; }
        .user-details { text-align:right; }
        .user-name { font-weight:600; color:#2c3e50; font-size:.95rem; }
        .user-role { font-size:.8rem; color:#7f8c8d; display:inline-block;
                     padding:2px 8px; background:#ecf0f1; border-radius:10px; margin-top:2px; }
        .btn-logout { padding:10px 20px; background:#e74c3c; color:white; border:none;
                      border-radius:8px; cursor:pointer; font-weight:600; transition:all .3s; }
        .btn-logout:hover { background:#c0392b; transform:translateY(-2px); }
        .content  { padding:30px; }
        .card { background:white; border-radius:15px; padding:28px;
                box-shadow:0 5px 20px rgba(0,0,0,.08); margin-bottom:24px; }
        .card-title { font-size:1.2rem; font-weight:700; color:#2c3e50;
                      margin-bottom:20px; padding-bottom:12px;
                      border-bottom:2px solid #f5f7fa; display:flex; align-items:center; gap:8px; }
        label { display:block; font-weight:600; color:#2c3e50; margin-bottom:6px; font-size:.9rem; }
        input[type=text], input[type=email], textarea, select {
            width:100%; padding:11px 14px; border:2px solid #e0e0e0; border-radius:8px;
            font-size:.95rem; font-family:inherit; transition:border-color .2s; }
        input:focus, textarea:focus, select:focus { outline:none; border-color:#00509D; }
        .form-group { margin-bottom:20px; }
        .row2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        /* Destinatarios */
        #lista-destinatarios { max-height:220px; overflow-y:auto;
                               border:2px solid #e0e0e0; border-radius:8px;
                               padding:10px; margin-bottom:12px; background:#fafafa; }
        .dest-tag { display:inline-flex; align-items:center; gap:6px;
                    background:#e8f4fd; color:#00509D; border-radius:20px;
                    padding:5px 12px; font-size:.85rem; margin:3px; }
        .dest-tag .rm { cursor:pointer; color:#e74c3c; font-weight:700;
                        border:none; background:none; font-size:1rem; padding:0 2px; }
        .dest-add-row { display:flex; gap:10px; }
        .dest-add-row input { flex:1; }
        /* Tabs editor */
        .tabs { display:flex; gap:0; margin-bottom:0; }
        .tab-btn { padding:10px 22px; border:2px solid #e0e0e0; background:#f8f9fa;
                   cursor:pointer; font-weight:600; color:#7f8c8d; border-bottom:none;
                   border-radius:8px 8px 0 0; font-size:.9rem; }
        .tab-btn.active { background:white; color:#00509D; border-color:#00509D;
                          border-bottom-color:white; position:relative; top:2px; }
        .tab-panel { border:2px solid #00509D; border-radius:0 8px 8px 8px; padding:0; }
        .editor-toolbar { display:flex; gap:4px; padding:10px 12px; background:#f8f9fa;
                          border-bottom:1px solid #e0e0e0; flex-wrap:wrap; }
        .tbtn { padding:5px 10px; border:1px solid #ddd; background:white; border-radius:5px;
                cursor:pointer; font-size:.85rem; color:#2c3e50; transition:all .15s; }
        .tbtn:hover { background:#00509D; color:white; border-color:#00509D; }
        #editor-html { width:100%; min-height:240px; padding:15px;
                       font-family:inherit; font-size:.95rem; border:none;
                       outline:none; resize:vertical; line-height:1.6; }
        #editor-texto { width:100%; min-height:240px; padding:15px;
                        font-family:monospace; font-size:.9rem; border:none;
                        outline:none; resize:vertical; display:none; }
        /* Adjuntos */
        .drop-zone { border:2px dashed #00509D; border-radius:10px; padding:30px;
                     text-align:center; cursor:pointer; transition:all .2s;
                     background:#f0f7ff; color:#00509D; }
        .drop-zone:hover, .drop-zone.dragover { background:#e0f0ff; border-style:solid; }
        .drop-zone .icon { font-size:2.5rem; margin-bottom:8px; }
        .adjunto-item { display:flex; align-items:center; justify-content:space-between;
                        padding:10px 15px; background:#f8f9fa; border-radius:8px; margin-top:8px; }
        .adjunto-info { display:flex; align-items:center; gap:10px; }
        .adjunto-icon { font-size:1.4rem; }
        .adjunto-nombre { font-weight:600; color:#2c3e50; font-size:.9rem; }
        .adjunto-size { color:#7f8c8d; font-size:.8rem; }
        /* Botones acción */
        .btn { padding:12px 24px; border:none; border-radius:8px; cursor:pointer;
               font-weight:600; text-decoration:none; display:inline-block;
               transition:all .3s; font-size:.95rem; }
        .btn-primary { background:linear-gradient(135deg,#00296B 0%,#00509D 100%); color:white; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,41,107,.3); }
        .btn-success { background:#27ae60; color:white; }
        .btn-success:hover { background:#219a52; transform:translateY(-2px); }
        .btn-secondary { background:#95a5a6; color:white; }
        .btn-warning { background:#FDC500; color:white; }
        .action-bar { display:flex; gap:12px; flex-wrap:wrap; padding-top:10px; }
        /* Modal preview */
        .modal-overlay { display:none; position:fixed; top:0;left:0;width:100%;height:100%;
                         background:rgba(0,0,0,.6); z-index:9999;
                         align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:white; border-radius:15px; padding:30px;
                     max-width:680px; width:95%; max-height:90vh; overflow-y:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center;
                        margin-bottom:20px; }
        .modal-header h3 { color:#2c3e50; font-size:1.2rem; }
        .btn-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#7f8c8d; }
        .preview-meta { background:#f8f9fa; border-radius:8px; padding:14px;
                        margin-bottom:16px; font-size:.88rem; }
        .preview-meta div { margin-bottom:5px; }
        .preview-meta span { font-weight:600; color:#2c3e50; }
        .preview-body { border:1px solid #e0e0e0; border-radius:8px; padding:20px;
                        min-height:200px; }
        .pegar-modal { display:none; position:fixed; top:0;left:0;width:100%;height:100%;
                       background:rgba(0,0,0,.5); z-index:9998; align-items:center; justify-content:center; }
        .pegar-modal.open { display:flex; }
        .pegar-box { background:white; border-radius:15px; padding:28px; max-width:500px; width:90%; }
        .pegar-box h3 { color:#2c3e50; margin-bottom:14px; }
        .pegar-box textarea { width:100%; height:160px; padding:12px; border:2px solid #e0e0e0;
                              border-radius:8px; font-family:monospace; resize:vertical; font-size:.88rem; }
        .pegar-box p { color:#7f8c8d; font-size:.83rem; margin-bottom:10px; }
        .alert { padding:13px; border-radius:8px; margin-bottom:16px; }
        .alert-error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        @media(max-width:768px){
            .main-content { margin-left:0; }
            .row2 { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once '../../includes/header.php'; ?>
        </header>

        <div class="content">
            <div id="alerta-form" class="alert alert-error" style="display:none;"></div>

            <form id="form-campana" enctype="multipart/form-data">
                <input type="hidden" name="id_editar" value="<?php echo $id_editar; ?>">

                <!-- Datos básicos -->
                <div class="card">
                    <div class="card-title">📋 Datos de la Campaña</div>
                    <div class="row2">
                        <div class="form-group">
                            <label for="nombre_campana">Nombre interno de campaña *</label>
                            <input type="text" id="nombre_campana" name="nombre_campana"
                                placeholder="Ej: Promo Marzo 2026"
                                value="<?php echo htmlspecialchars($campana['nombre_campana'] ?? ''); ?>"
                                maxlength="120">
                        </div>
                        <div class="form-group">
                            <label for="asunto">Asunto del correo *</label>
                            <input type="text" id="asunto" name="asunto"
                                placeholder="Ej: Nuevas tarifas disponibles"
                                value="<?php echo htmlspecialchars($campana['asunto'] ?? ''); ?>"
                                maxlength="220">
                        </div>
                    </div>
                </div>

                <!-- Destinatarios -->
                <div class="card">
                    <div class="card-title">👥 Destinatarios</div>
                    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                        <button type="button" onclick="abrirPegarModal()" class="btn btn-secondary" style="padding:9px 18px;font-size:.88rem;">
                            📋 Pegar lista de emails
                        </button>
                        <label class="btn btn-secondary" style="padding:9px 18px;font-size:.88rem;cursor:pointer;">
                            📂 Importar desde Excel
                            <input type="file" id="archivo-excel-dest" accept=".xlsx,.xls,.csv" style="display:none">
                        </label>
                    </div>
                    <div id="lista-destinatarios">
                        <?php foreach ($destinatarios as $d): ?>
                        <span class="dest-tag" data-email="<?php echo htmlspecialchars($d['email']); ?>">
                            <?php echo htmlspecialchars($d['email']); ?>
                            <button type="button" class="rm" onclick="eliminarDest(this)">×</button>
                        </span>
                        <?php endforeach; ?>
                        <span id="placeholder-dest" style="color:#aaa;font-size:.88rem;<?php echo !empty($destinatarios)?'display:none':'' ?>">
                            Los emails aparecerán aquí...
                        </span>
                    </div>
                    <div class="dest-add-row">
                        <input type="email" id="nuevo-email" placeholder="correo@ejemplo.com">
                        <input type="text"  id="nuevo-nombre" placeholder="Nombre (opcional)" style="max-width:200px;">
                        <button type="button" onclick="agregarDest()" class="btn btn-primary" style="padding:10px 18px;font-size:.9rem;">+ Agregar</button>
                    </div>
                    <div style="margin-top:10px;color:#7f8c8d;font-size:.85rem;">
                        📊 Total: <strong id="total-dest">0</strong> destinatario(s)
                    </div>
                    <!-- Campo oculto con lista JSON -->
                    <input type="hidden" id="destinatarios-json" name="destinatarios_json" value="[]">
                </div>

                <!-- Editor de contenido -->
                <div class="card">
                    <div class="card-title">✍️ Contenido del Correo</div>
                    <div class="tabs">
                        <button type="button" class="tab-btn active" onclick="switchTab('html')">Texto Rico (HTML)</button>
                        <button type="button" class="tab-btn" onclick="switchTab('texto')">Solo Texto</button>
                    </div>
                    <div class="tab-panel">
                        <div id="panel-html">
                            <div class="editor-toolbar">
                                <button type="button" class="tbtn" onclick="fmt('bold')" title="Negrita"><b>B</b></button>
                                <button type="button" class="tbtn" onclick="fmt('italic')" title="Cursiva"><i>I</i></button>
                                <button type="button" class="tbtn" onclick="fmt('underline')" title="Subrayado"><u>U</u></button>
                                <button type="button" class="tbtn" onclick="insertHeading(2)" title="Título">H1</button>
                                <button type="button" class="tbtn" onclick="insertHeading(3)" title="Subtítulo">H2</button>
                                <button type="button" class="tbtn" onclick="fmt('insertUnorderedList')" title="Lista">• Lista</button>
                                <button type="button" class="tbtn" onclick="insertLink()" title="Enlace">🔗 Link</button>
                                <button type="button" class="tbtn" onclick="insertImgUrl()" title="Imagen por URL">🖼 Img URL</button>
                                <button type="button" class="tbtn" onclick="fmt('justifyLeft')" title="Izquierda">⬅</button>
                                <button type="button" class="tbtn" onclick="fmt('justifyCenter')" title="Centrar">↔</button>
                                <button type="button" class="tbtn" onclick="fmt('justifyRight')" title="Derecha">➡</button>
                                <input type="color" id="color-picker" value="#000000" title="Color de texto"
                                    style="width:30px;height:28px;padding:0;border:1px solid #ddd;border-radius:4px;cursor:pointer;">
                            </div>
                            <div id="editor-html" contenteditable="true"><?php echo $campana['cuerpo_html'] ?? ''; ?></div>
                        </div>
                        <div id="panel-texto" style="display:none">
                            <textarea id="editor-texto" name="cuerpo_texto"
                                placeholder="Versión de solo texto del correo..."><?php echo htmlspecialchars($campana['cuerpo_texto'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <input type="hidden" id="cuerpo-html-hidden" name="cuerpo_html">
                </div>

                <!-- Adjuntos -->
                <div class="card">
                    <div class="card-title">📎 Archivos Adjuntos</div>
                    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('input-adjuntos').click()">
                        <div class="icon">📂</div>
                        <div style="font-weight:600;margin-bottom:4px;">Arrastra archivos aquí o haz click para seleccionar</div>
                        <div style="font-size:.85rem;opacity:.8;">PDF, Word, Excel, Imágenes — máx. 10 MB por archivo</div>
                    </div>
                    <input type="file" id="input-adjuntos" name="adjuntos[]" multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.ppt,.pptx"
                        style="display:none">
                    <div id="lista-adjuntos">
                        <?php foreach ($adjuntos as $adj): ?>
                        <div class="adjunto-item" data-id="<?php echo $adj['id']; ?>">
                            <div class="adjunto-info">
                                <span class="adjunto-icon"><?php
                                    $ext = strtolower(pathinfo($adj['nombre_archivo'], PATHINFO_EXTENSION));
                                    $iconos = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','xls'=>'📊','xlsx'=>'📊',
                                               'jpg'=>'🖼','jpeg'=>'🖼','png'=>'🖼','gif'=>'🖼'];
                                    echo $iconos[$ext] ?? '📎';
                                ?></span>
                                <div>
                                    <div class="adjunto-nombre"><?php echo htmlspecialchars($adj['nombre_archivo']); ?></div>
                                    <div class="adjunto-size"><?php echo round($adj['tamanio']/1024, 1); ?> KB</div>
                                </div>
                            </div>
                            <button type="button" onclick="eliminarAdjuntoDB(<?php echo $adj['id']; ?>,this)"
                                style="background:#e74c3c;color:white;border:none;border-radius:6px;
                                       padding:6px 12px;cursor:pointer;font-size:.85rem;">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:8px;color:#7f8c8d;font-size:.82rem;" id="info-adjuntos"></div>
                </div>

                <!-- Barra de acciones -->
                <div class="card" style="padding:20px 28px;">
                    <div class="action-bar">
                        <button type="button" onclick="guardarBorrador()" class="btn btn-secondary">
                            💾 Guardar Borrador
                        </button>
                        <button type="button" onclick="abrirPreview()" class="btn btn-warning">
                            👁 Vista Previa
                        </button>
                        <button type="button" onclick="confirmarEnvio()" class="btn btn-success">
                            📤 Enviar Campaña
                        </button>
                        <a href="index.php" class="btn btn-secondary">← Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pegar Lista -->
    <div class="pegar-modal" id="modalPegar">
        <div class="pegar-box">
            <h3>📋 Pegar lista de emails</h3>
            <p>Pega los emails separados por comas, punto y coma o saltos de línea:</p>
            <textarea id="txt-pegar" placeholder="juan@ejemplo.com&#10;maria@empresa.com&#10;pedro@correo.pe"></textarea>
            <div style="display:flex;gap:10px;margin-top:14px;">
                <button onclick="cerrarPegarModal()" class="btn btn-secondary" style="padding:10px 18px;">Cancelar</button>
                <button onclick="procesarPegado()" class="btn btn-primary" style="padding:10px 18px;">Agregar emails</button>
            </div>
        </div>
    </div>

    <!-- Modal Vista Previa -->
    <div class="modal-overlay" id="modalPreview">
        <div class="modal-box">
            <div class="modal-header">
                <h3>👁 Vista Previa del Correo</h3>
                <button class="btn-close" onclick="cerrarPreview()">✕</button>
            </div>
            <div class="preview-meta">
                <div><span>De:</span> ventas@ifast.com.pe</div>
                <div><span>Para:</span> <span id="prev-para">destinatario@ejemplo.com</span></div>
                <div><span>Asunto:</span> <span id="prev-asunto">—</span></div>
            </div>
            <div class="preview-body">
                <div style="background:linear-gradient(135deg,#00296b,#00509d);color:white;padding:18px;text-align:center;border-radius:8px 8px 0 0;margin:-20px -20px 15px -20px;">
                    <strong style="font-size:1.1rem;">IFAST - International Courier</strong>
                </div>
                <div id="prev-body"></div>
                <div id="prev-adjuntos" style="margin-top:15px;padding-top:12px;border-top:1px solid #eee;font-size:.85rem;color:#555;"></div>
                <div style="background:#2c3e50;color:white;padding:12px;text-align:center;font-size:.8rem;border-radius:0 0 8px 8px;margin:15px -20px -20px -20px;">
                    IFAST · ventas@ifast.com.pe
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
                <button onclick="cerrarPreview()" class="btn btn-secondary">Cerrar</button>
                <button onclick="cerrarPreview();confirmarEnvio();" class="btn btn-success">Confirmar y Enviar →</button>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Envío -->
    <div class="modal-overlay" id="modalConfirmar">
        <div class="modal-box" style="max-width:420px;text-align:center;">
            <div style="font-size:3rem;margin-bottom:12px;">📤</div>
            <h3 style="color:#2c3e50;margin-bottom:10px;">¿Listo para enviar?</h3>
            <p style="color:#7f8c8d;margin-bottom:8px;">Se enviará <strong id="conf-total">0</strong> correos individuales.</p>
            <p style="color:#7f8c8d;font-size:.88rem;margin-bottom:24px;">Cada destinatario recibirá su propio correo personalizado.</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="document.getElementById('modalConfirmar').classList.remove('open')" class="btn btn-secondary">Cancelar</button>
                <button onclick="ejecutarEnvio()" class="btn btn-success">Sí, enviar ahora</button>
            </div>
        </div>
    </div>

    <!-- Modal Progreso de Envío -->
    <div class="modal-overlay" id="modalProgreso">
        <div class="modal-box" style="max-width:480px;">
            <h3 style="color:#2c3e50;margin-bottom:20px;">📤 Enviando campaña...</h3>
            <p style="color:#555;margin-bottom:6px;">Campaña: <strong id="prog-nombre">—</strong></p>
            <div style="background:#e0e0e0;border-radius:20px;height:20px;margin:16px 0;overflow:hidden;">
                <div id="barra-progreso" style="height:100%;background:linear-gradient(90deg,#00296B,#00509D);
                     border-radius:20px;transition:width .4s;width:0%"></div>
            </div>
            <p id="prog-pct" style="text-align:center;font-size:1.1rem;font-weight:700;color:#00509D;margin-bottom:16px;">0%</p>
            <div style="display:flex;gap:30px;justify-content:center;margin-bottom:16px;">
                <div style="text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:#27ae60;" id="prog-enviados">0</div>
                    <div style="font-size:.8rem;color:#7f8c8d;">✅ Enviados</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:#e74c3c;" id="prog-fallidos">0</div>
                    <div style="font-size:.8rem;color:#7f8c8d;">❌ Fallidos</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:#3498db;" id="prog-pendientes">0</div>
                    <div style="font-size:.8rem;color:#7f8c8d;">⏳ Pendientes</div>
                </div>
            </div>
            <p id="prog-actual" style="color:#7f8c8d;font-size:.85rem;text-align:center;min-height:20px;"></p>
            <p style="color:#f39c12;font-size:.82rem;text-align:center;margin-top:16px;">
                ⚠ El envío continúa aunque cierres esta ventana
            </p>
            <div id="prog-acciones" style="display:none;text-align:center;margin-top:18px;">
                <a id="prog-link-ver" href="#" class="btn btn-primary">Ver detalle completo →</a>
            </div>
        </div>
    </div>

    <script>
    // ── Destinatarios ─────────────────────────────────────────────
    let destinatarios = <?php echo json_encode(array_map(fn($d)=>['email'=>$d['email'],'nombre'=>$d['nombre']??''], $destinatarios)); ?>;

    function renderDests() {
        const cont = document.getElementById('lista-destinatarios');
        const ph   = document.getElementById('placeholder-dest');
        cont.querySelectorAll('.dest-tag').forEach(e => e.remove());
        destinatarios.forEach((d, i) => {
            const t = document.createElement('span');
            t.className = 'dest-tag';
            t.dataset.email = d.email;
            t.innerHTML = `${d.email} <button type="button" class="rm" onclick="eliminarDestIdx(${i})">×</button>`;
            cont.insertBefore(t, ph);
        });
        ph.style.display = destinatarios.length === 0 ? 'inline' : 'none';
        document.getElementById('total-dest').textContent = destinatarios.length;
        document.getElementById('destinatarios-json').value = JSON.stringify(destinatarios);
    }

    function agregarDest() {
        const email  = document.getElementById('nuevo-email').value.trim().toLowerCase();
        const nombre = document.getElementById('nuevo-nombre').value.trim();
        if (!email) return;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { alert('Email inválido: ' + email); return; }
        if (destinatarios.some(d => d.email === email)) { alert('Email ya agregado'); return; }
        destinatarios.push({ email, nombre });
        document.getElementById('nuevo-email').value  = '';
        document.getElementById('nuevo-nombre').value = '';
        renderDests();
    }

    function eliminarDestIdx(idx) {
        destinatarios.splice(idx, 1);
        renderDests();
    }

    document.getElementById('nuevo-email').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); agregarDest(); }
    });

    // Importar Excel para destinatarios
    document.getElementById('archivo-excel-dest').addEventListener('change', function() {
        if (!this.files.length) return;
        const fd = new FormData();
        fd.append('archivo_excel', this.files[0]);
        fd.append('accion', 'importar_emails');
        fetch('enviar.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.emails) {
                    let added = 0;
                    data.emails.forEach(e => {
                        const em = e.trim().toLowerCase();
                        if (em && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em) && !destinatarios.some(d=>d.email===em)) {
                            destinatarios.push({ email: em, nombre: '' });
                            added++;
                        }
                    });
                    renderDests();
                    alert(`Se importaron ${added} email(s) desde el archivo.`);
                } else {
                    alert(data.mensaje || 'Error al importar archivo');
                }
            });
        this.value = '';
    });

    // ── Modal pegar lista ──────────────────────────────────────────
    function abrirPegarModal()  { document.getElementById('modalPegar').classList.add('open'); }
    function cerrarPegarModal() { document.getElementById('modalPegar').classList.remove('open'); }
    function procesarPegado() {
        const txt   = document.getElementById('txt-pegar').value;
        const parts = txt.split(/[\n,;]+/).map(s => s.trim().toLowerCase()).filter(Boolean);
        let added = 0, invalidos = 0;
        parts.forEach(em => {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { invalidos++; return; }
            if (destinatarios.some(d => d.email === em)) return;
            destinatarios.push({ email: em, nombre: '' });
            added++;
        });
        renderDests();
        cerrarPegarModal();
        document.getElementById('txt-pegar').value = '';
        let msg = `Se agregaron ${added} email(s).`;
        if (invalidos > 0) msg += ` ${invalidos} inválido(s) omitido(s).`;
        alert(msg);
    }

    // ── Editor ────────────────────────────────────────────────────
    function fmt(cmd)    { document.getElementById('editor-html').focus(); document.execCommand(cmd, false, null); }
    function insertHeading(n) {
        document.getElementById('editor-html').focus();
        document.execCommand('formatBlock', false, 'h' + n);
    }
    function insertLink() {
        const url = prompt('URL del enlace:');
        if (url) { document.getElementById('editor-html').focus(); document.execCommand('createLink', false, url); }
    }
    function insertImgUrl() {
        const url = prompt('URL de la imagen:');
        if (url) { document.getElementById('editor-html').focus(); document.execCommand('insertImage', false, url); }
    }
    document.getElementById('color-picker').addEventListener('input', function() {
        document.getElementById('editor-html').focus();
        document.execCommand('foreColor', false, this.value);
    });

    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('panel-html').style.display   = tab === 'html'  ? '' : 'none';
        document.getElementById('panel-texto').style.display  = tab === 'texto' ? '' : 'none';
        document.getElementById('editor-texto').style.display = tab === 'texto' ? '' : 'none';
    }

    // ── Adjuntos ──────────────────────────────────────────────────
    const iconoExt = { pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
                       jpg:'🖼', jpeg:'🖼', png:'🖼', gif:'🖼', ppt:'📊', pptx:'📊' };
    let archivosNuevos = new DataTransfer();

    function actualizarInputArchivos() {
        document.getElementById('input-adjuntos').files = archivosNuevos.files;
        const n = archivosNuevos.files.length;
        document.getElementById('info-adjuntos').textContent = n > 0 ? `${n} archivo(s) nuevo(s) seleccionado(s)` : '';
    }

    function renderAdjunto(file) {
        const ext  = file.name.split('.').pop().toLowerCase();
        const icono = iconoExt[ext] || '📎';
        const kb   = (file.size / 1024).toFixed(1);
        const div  = document.createElement('div');
        div.className = 'adjunto-item';
        div.dataset.filename = file.name;
        div.innerHTML = `
            <div class="adjunto-info">
                <span class="adjunto-icon">${icono}</span>
                <div>
                    <div class="adjunto-nombre">${file.name}</div>
                    <div class="adjunto-size">${kb} KB</div>
                </div>
            </div>
            <button type="button" onclick="eliminarAdjuntoNuevo('${file.name}',this)"
                style="background:#e74c3c;color:white;border:none;border-radius:6px;
                       padding:6px 12px;cursor:pointer;font-size:.85rem;">✕</button>`;
        document.getElementById('lista-adjuntos').appendChild(div);
    }

    document.getElementById('input-adjuntos').addEventListener('change', function() {
        Array.from(this.files).forEach(f => {
            if (f.size > 10 * 1024 * 1024) { alert(`"${f.name}" supera 10 MB`); return; }
            archivosNuevos.items.add(f);
            renderAdjunto(f);
        });
        actualizarInputArchivos();
    });

    const dz = document.getElementById('drop-zone');
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('dragover');
        Array.from(e.dataTransfer.files).forEach(f => {
            if (f.size > 10 * 1024 * 1024) { alert(`"${f.name}" supera 10 MB`); return; }
            archivosNuevos.items.add(f);
            renderAdjunto(f);
        });
        actualizarInputArchivos();
    });

    function eliminarAdjuntoNuevo(nombre, btn) {
        const newDT = new DataTransfer();
        Array.from(archivosNuevos.files).forEach(f => { if (f.name !== nombre) newDT.items.add(f); });
        archivosNuevos = newDT;
        actualizarInputArchivos();
        btn.closest('.adjunto-item').remove();
    }

    function eliminarAdjuntoDB(id, btn) {
        if (!confirm('¿Eliminar este adjunto?')) return;
        fetch('enviar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=eliminar_adjunto&id_adjunto=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) btn.closest('.adjunto-item').remove();
            else alert(d.mensaje);
        });
    }

    // ── Guardar / Enviar ──────────────────────────────────────────
    function prepararFormData(accion) {
        document.getElementById('cuerpo-html-hidden').value = document.getElementById('editor-html').innerHTML;
        document.getElementById('destinatarios-json').value = JSON.stringify(destinatarios);
        const fd = new FormData(document.getElementById('form-campana'));
        fd.append('accion', accion);
        return fd;
    }

    function validar() {
        const nombre = document.getElementById('nombre_campana').value.trim();
        const asunto = document.getElementById('asunto').value.trim();
        const html   = document.getElementById('editor-html').innerHTML.trim();
        const alerta = document.getElementById('alerta-form');
        if (!nombre) { alerta.textContent='El nombre de la campaña es obligatorio.'; alerta.style.display='block'; return false; }
        if (!asunto) { alerta.textContent='El asunto del correo es obligatorio.'; alerta.style.display='block'; return false; }
        if (!html || html === '<br>') { alerta.textContent='El contenido del correo no puede estar vacío.'; alerta.style.display='block'; return false; }
        alerta.style.display = 'none';
        return true;
    }

    function guardarBorrador() {
        if (!validar()) return;
        const fd = prepararFormData('guardar_borrador');
        fetch('enviar.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('Borrador guardado correctamente.');
                    if (d.id) window.location.href = 'crear.php?id=' + d.id;
                } else alert(d.mensaje || 'Error al guardar');
            });
    }

    function abrirPreview() {
        if (!validar()) return;
        document.getElementById('prev-asunto').textContent =
            document.getElementById('asunto').value || '—';
        document.getElementById('prev-para').textContent =
            destinatarios.length > 0 ? destinatarios[0].email : 'destinatario@ejemplo.com';
        document.getElementById('prev-body').innerHTML =
            document.getElementById('editor-html').innerHTML;

        const adj  = document.querySelectorAll('#lista-adjuntos .adjunto-item');
        const cont = document.getElementById('prev-adjuntos');
        if (adj.length > 0) {
            cont.innerHTML = '<strong>Adjuntos:</strong><br>' +
                Array.from(adj).map(a => '📎 ' + a.querySelector('.adjunto-nombre').textContent).join('<br>');
        } else cont.innerHTML = '';
        document.getElementById('modalPreview').classList.add('open');
    }
    function cerrarPreview() { document.getElementById('modalPreview').classList.remove('open'); }

    function confirmarEnvio() {
        if (!validar()) return;
        if (destinatarios.length === 0) {
            alert('Debe agregar al menos un destinatario.');
            return;
        }
        document.getElementById('conf-total').textContent = destinatarios.length;
        document.getElementById('modalConfirmar').classList.add('open');
    }

    let campanaIdActual = null;
    let pollingInterval = null;

    function ejecutarEnvio() {
        document.getElementById('modalConfirmar').classList.remove('open');
        const fd = prepararFormData('enviar');
        document.getElementById('prog-nombre').textContent = document.getElementById('nombre_campana').value;
        document.getElementById('modalProgreso').classList.add('open');

        fetch('enviar.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => {
                if (d.success && d.campana_id) {
                    campanaIdActual = d.campana_id;
                    document.getElementById('prog-link-ver').href = 'ver.php?id=' + campanaIdActual;
                    iniciarPolling();
                } else {
                    alert(d.mensaje || 'Error al iniciar el envío');
                    document.getElementById('modalProgreso').classList.remove('open');
                }
            });
    }

    function iniciarPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => {
            fetch('obtener_estado.php?id=' + campanaIdActual)
                .then(r => r.json())
                .then(d => {
                    const total = d.total || 1;
                    const pct   = Math.round(((d.enviados + d.fallidos) / total) * 100);
                    document.getElementById('barra-progreso').style.width = pct + '%';
                    document.getElementById('prog-pct').textContent        = pct + '%';
                    document.getElementById('prog-enviados').textContent   = d.enviados;
                    document.getElementById('prog-fallidos').textContent   = d.fallidos;
                    document.getElementById('prog-pendientes').textContent = d.pendientes;
                    if (d.actual) document.getElementById('prog-actual').textContent = 'Enviando a: ' + d.actual + '...';

                    if (d.estado === 'COMPLETADO' || d.estado === 'ERROR') {
                        clearInterval(pollingInterval);
                        document.getElementById('prog-actual').textContent = d.estado === 'COMPLETADO' ? '✅ Envío completado' : '⚠ Envío finalizado con errores';
                        document.getElementById('prog-acciones').style.display = 'block';
                    }
                });
        }, 2000);
    }

    renderDests();
    </script>
</body>
</html>
