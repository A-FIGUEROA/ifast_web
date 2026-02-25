<?php
/**
 * WIDGET FLOTANTE DE CONTROL DE HORARIO
 * Se incluye en todas las páginas para usuarios SUPERVISOR, VENTAS, ASESOR
 */

// Solo mostrar el widget para usuarios que no sean ADMIN
$tipo_usuario = obtenerTipoUsuario();
if (!in_array($tipo_usuario, ['SUPERVISOR', 'VENTAS', 'ASESOR'])) {
    return;
}
?>

<!-- WIDGET FLOTANTE DE CONTROL DE HORARIO -->
<div id="timeWidget" class="time-widget collapsed">
    <!-- Cabecera del widget (siempre visible) -->
    <div class="widget-header" onclick="toggleWidget()">
        <div class="status-indicator" id="statusIndicator"></div>
        <span class="status-text" id="statusText">DESCONECTADO</span>
        <button class="widget-toggle" id="widgetToggleBtn">
            <box-icon name='chevron-up' color='white' size='20px'></box-icon>
        </button>
    </div>

    <!-- Cuerpo del widget (expandible/colapsable) -->
    <div class="widget-body" id="widgetBody">
        <!-- Cronómetro principal -->
        <div class="time-display">
            <div class="time-label">⏱️ Tiempo trabajado hoy</div>
            <div class="time-value" id="tiempoTrabajado">00:00:00</div>
        </div>

        <!-- Tiempo de refrigerio -->
        <div class="time-display small">
            <div class="time-label">☕ Refrigerio</div>
            <div class="time-value-small" id="tiempoRefrigerio">00:00:00</div>
        </div>

        <hr class="widget-divider">

        <!-- Botones de acción -->
        <div class="widget-actions" id="widgetActions">
            <!-- Botón Iniciar Jornada (solo cuando está desconectado) -->
            <button class="btn-widget btn-start" id="btnIniciar" onclick="cambiarEstado('CONECTADO')" style="display: none;">
                <box-icon name='play-circle' color='white' size='18px'></box-icon>
                Iniciar Jornada
            </button>

            <!-- Botón Ir a Refrigerio (solo cuando está conectado) -->
            <button class="btn-widget btn-refrigerio" id="btnRefrigerio" onclick="cambiarEstado('REFRIGERIO')" style="display: none;">
                <box-icon name='coffee' color='white' size='18px'></box-icon>
                Ir a Refrigerio
            </button>

            <!-- Botón Volver al Trabajo (solo cuando está en refrigerio) -->
            <button class="btn-widget btn-volver" id="btnVolver" onclick="cambiarEstado('CONECTADO')" style="display: none;">
                <box-icon name='briefcase' color='white' size='18px'></box-icon>
                Volver al Trabajo
            </button>

            <!-- Botón Finalizar (cuando está conectado o en refrigerio) -->
            <button class="btn-widget btn-finalizar" id="btnFinalizar" onclick="cambiarEstado('DESCONECTADO')" style="display: none;">
                <box-icon name='stop-circle' color='white' size='18px'></box-icon>
                Finalizar Jornada
            </button>
        </div>

        <!-- Resumen del día -->
        <div class="widget-summary" id="widgetSummary">
            <small>📅 <span id="fechaActual"><?php echo date('d/m/Y'); ?></span></small>
            <small id="horaInicio" style="display: none;">🕐 Inicio: <span id="horaInicioText">--:--</span></small>
        </div>
    </div>
</div>

<!-- Estilos del Widget -->
<link rel="stylesheet" href="<?php echo $base_path ?? '/ifast_web/'; ?>assets/css/time_widget.css">

<!-- JavaScript del Widget -->
<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
<script src="<?php echo $base_path ?? '/ifast_web/'; ?>assets/js/time_widget.js"></script>
