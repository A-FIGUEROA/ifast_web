/**
 * WIDGET DE CONTROL DE HORARIO - JAVASCRIPT
 * Maneja toda la lógica del widget flotante
 */

class TimeWidget {
    constructor() {
        this.estado = 'DESCONECTADO';
        this.tiempoInicio = null;
        this.tiempoTrabajado = 0;
        this.tiempoRefrigerio = 0;
        this.intervalo = null;
        this.basePath = '/ifast_web/';

        this.init();
    }

    /**
     * Inicializa el widget
     */
    init() {
        console.log('🕐 Inicializando Widget de Control de Horario...');
        this.cargarEstadoActual();
        this.iniciarActualizacionAutomatica();
    }

    /**
     * Carga el estado actual del usuario desde el servidor
     */
    async cargarEstadoActual() {
        try {
            const response = await fetch(this.basePath + 'api/control_horario/obtener_estado.php');
            const data = await response.json();

            if (data.success) {
                this.estado = data.estado;
                this.tiempoInicio = data.hora_inicio;
                this.tiempoTrabajado = data.tiempo_trabajado;
                this.tiempoRefrigerio = data.tiempo_refrigerio;

                this.actualizarUI();

                if (this.estado === 'CONECTADO' || this.estado === 'REFRIGERIO') {
                    this.iniciarCronometro();
                }

                console.log('✅ Estado cargado:', this.estado);
            }
        } catch (error) {
            console.error('❌ Error al cargar estado:', error);
        }
    }

    /**
     * Cambia el estado del usuario
     */
    async cambiarEstado(nuevoEstado) {
        // Confirmaciones
        if (nuevoEstado === 'DESCONECTADO') {
            if (!confirm('¿Está seguro de finalizar su jornada laboral?')) {
                return;
            }
        }

        // Mostrar loading
        this.mostrarLoading(true);

        try {
            const response = await fetch(this.basePath + 'api/control_horario/registrar_tiempo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    estado: nuevoEstado
                })
            });

            const data = await response.json();

            if (data.success) {
                this.estado = data.estado;
                this.tiempoTrabajado = data.tiempo_trabajado;
                this.tiempoRefrigerio = data.tiempo_refrigerio;
                this.tiempoInicio = data.hora_inicio;

                this.actualizarUI();
                this.mostrarNotificacion(data.mensaje, 'success');

                // Reiniciar cronómetro si es necesario
                if (this.estado === 'CONECTADO' || this.estado === 'REFRIGERIO') {
                    this.iniciarCronometro();
                } else {
                    this.detenerCronometro();
                }
            } else {
                this.mostrarNotificacion(data.mensaje, 'error');
            }
        } catch (error) {
            console.error('❌ Error al cambiar estado:', error);
            this.mostrarNotificacion('Error al cambiar el estado', 'error');
        } finally {
            this.mostrarLoading(false);
        }
    }

    /**
     * Inicia el cronómetro para actualizar tiempos en tiempo real
     */
    iniciarCronometro() {
        // Detener cronómetro anterior si existe
        this.detenerCronometro();

        // Actualizar cada segundo
        this.intervalo = setInterval(() => {
            this.actualizarCronometro();
        }, 1000);

        console.log('▶️ Cronómetro iniciado');
    }

    /**
     * Detiene el cronómetro
     */
    detenerCronometro() {
        if (this.intervalo) {
            clearInterval(this.intervalo);
            this.intervalo = null;
            console.log('⏸️ Cronómetro detenido');
        }
    }

    /**
     * Actualiza el cronómetro en tiempo real
     */
    actualizarCronometro() {
        if (this.estado === 'CONECTADO') {
            this.tiempoTrabajado += 1/60; // Incrementar en minutos
        } else if (this.estado === 'REFRIGERIO') {
            this.tiempoRefrigerio += 1/60;
        }

        this.mostrarTiempos();
    }

    /**
     * Actualiza la interfaz del widget
     */
    actualizarUI() {
        // Actualizar indicador de estado
        const indicador = document.getElementById('statusIndicator');
        const textoEstado = document.getElementById('statusText');

        if (indicador && textoEstado) {
            indicador.className = `status-indicator status-${this.estado.toLowerCase()}`;
            textoEstado.textContent = this.estado;
        }

        // Actualizar botones según el estado
        this.actualizarBotones();

        // Mostrar tiempos
        this.mostrarTiempos();

        // Mostrar hora de inicio si está conectado
        this.mostrarHoraInicio();
    }

    /**
     * Actualiza la visibilidad de los botones según el estado
     */
    actualizarBotones() {
        const btnIniciar = document.getElementById('btnIniciar');
        const btnRefrigerio = document.getElementById('btnRefrigerio');
        const btnVolver = document.getElementById('btnVolver');
        const btnFinalizar = document.getElementById('btnFinalizar');

        // Ocultar todos primero
        [btnIniciar, btnRefrigerio, btnVolver, btnFinalizar].forEach(btn => {
            if (btn) btn.style.display = 'none';
        });

        // Mostrar según estado
        switch (this.estado) {
            case 'DESCONECTADO':
                if (btnIniciar) btnIniciar.style.display = 'flex';
                break;

            case 'CONECTADO':
                if (btnRefrigerio) btnRefrigerio.style.display = 'flex';
                if (btnFinalizar) btnFinalizar.style.display = 'flex';
                break;

            case 'REFRIGERIO':
                if (btnVolver) btnVolver.style.display = 'flex';
                if (btnFinalizar) btnFinalizar.style.display = 'flex';
                break;
        }
    }

    /**
     * Muestra los tiempos en formato legible
     */
    mostrarTiempos() {
        const elemTrabajado = document.getElementById('tiempoTrabajado');
        const elemRefrigerio = document.getElementById('tiempoRefrigerio');

        if (elemTrabajado) {
            elemTrabajado.textContent = this.formatearTiempoCronometro(this.tiempoTrabajado);
        }

        if (elemRefrigerio) {
            elemRefrigerio.textContent = this.formatearTiempoCronometro(this.tiempoRefrigerio);
        }
    }

    /**
     * Muestra la hora de inicio si está conectado
     */
    mostrarHoraInicio() {
        const elemHoraInicio = document.getElementById('horaInicio');
        const elemHoraInicioText = document.getElementById('horaInicioText');

        if (this.tiempoInicio && elemHoraInicio && elemHoraInicioText) {
            const fecha = new Date(this.tiempoInicio);
            const horas = String(fecha.getHours()).padStart(2, '0');
            const minutos = String(fecha.getMinutes()).padStart(2, '0');

            elemHoraInicioText.textContent = `${horas}:${minutos}`;
            elemHoraInicio.style.display = 'block';
        } else if (elemHoraInicio) {
            elemHoraInicio.style.display = 'none';
        }
    }

    /**
     * Formatea minutos a formato HH:MM:SS
     */
    formatearTiempoCronometro(minutos) {
        const totalSegundos = Math.floor(minutos * 60);
        const horas = Math.floor(totalSegundos / 3600);
        const mins = Math.floor((totalSegundos % 3600) / 60);
        const segs = totalSegundos % 60;

        return `${String(horas).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(segs).padStart(2, '0')}`;
    }

    /**
     * Inicia actualización automática periódica del servidor
     */
    iniciarActualizacionAutomatica() {
        // Actualizar desde el servidor cada 5 minutos
        setInterval(() => {
            this.sincronizarConServidor();
        }, 5 * 60 * 1000); // 5 minutos
    }

    /**
     * Sincroniza los tiempos con el servidor
     */
    async sincronizarConServidor() {
        try {
            const response = await fetch(this.basePath + 'api/control_horario/obtener_tiempo.php');
            const data = await response.json();

            if (data.success) {
                this.tiempoTrabajado = data.tiempo_trabajado;
                this.tiempoRefrigerio = data.tiempo_refrigerio;
                this.estado = data.estado;

                console.log('🔄 Sincronizado con servidor');
            }
        } catch (error) {
            console.error('❌ Error al sincronizar:', error);
        }
    }

    /**
     * Muestra indicador de carga
     */
    mostrarLoading(mostrar) {
        const botones = document.querySelectorAll('.btn-widget');
        botones.forEach(btn => {
            if (mostrar) {
                btn.classList.add('loading');
                btn.disabled = true;
            } else {
                btn.classList.remove('loading');
                btn.disabled = false;
            }
        });
    }

    /**
     * Muestra notificación toast
     */
    mostrarNotificacion(mensaje, tipo = 'info') {
        // Crear elemento de notificación
        const notif = document.createElement('div');
        notif.className = `widget-notification ${tipo}`;
        notif.textContent = mensaje;
        notif.style.cssText = `
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: ${tipo === 'success' ? '#27ae60' : tipo === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideInUp 0.3s ease-out;
            font-size: 0.9rem;
            max-width: 300px;
        `;

        document.body.appendChild(notif);

        // Eliminar después de 3 segundos
        setTimeout(() => {
            notif.style.animation = 'slideInUp 0.3s ease-out reverse';
            setTimeout(() => {
                document.body.removeChild(notif);
            }, 300);
        }, 3000);
    }
}

/**
 * Función global para cambiar estado (llamada desde HTML)
 */
function cambiarEstado(nuevoEstado) {
    if (window.timeWidget) {
        window.timeWidget.cambiarEstado(nuevoEstado);
    }
}

/**
 * Función global para toggle del widget (llamada desde HTML)
 */
function toggleWidget() {
    const widget = document.getElementById('timeWidget');
    if (widget) {
        widget.classList.toggle('collapsed');
    }
}

/**
 * Inicializar widget al cargar la página
 */
document.addEventListener('DOMContentLoaded', function() {
    // Solo inicializar si el widget existe en la página
    if (document.getElementById('timeWidget')) {
        window.timeWidget = new TimeWidget();
        console.log('✅ Widget de Control de Horario inicializado');
    }
});
