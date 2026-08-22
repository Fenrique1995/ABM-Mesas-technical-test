<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db     = getDB();
$mesas  = $db->query('SELECT * FROM mesas ORDER BY ubicacion, numero')->fetchAll();
$error  = $_GET['error'] ?? '';
$exito  = $_GET['exito'] ?? '';

// Horarios por día
$horarios = [
    0 => ['ini' => '12:00', 'fin' => '16:00'], // Domingo
    1 => ['ini' => '10:00', 'fin' => '24:00'], // Lunes
    2 => ['ini' => '10:00', 'fin' => '24:00'], // Martes
    3 => ['ini' => '10:00', 'fin' => '24:00'], // Miércoles
    4 => ['ini' => '10:00', 'fin' => '24:00'], // Jueves
    5 => ['ini' => '10:00', 'fin' => '24:00'], // Viernes
    6 => ['ini' => '22:00', 'fin' => '02:00'], // Sábado
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Reserva</title>
    <link rel="stylesheet" href="/css/estilo.css?v=6">
</head>
<body>
    <div class="container">
        <nav class="nav">
            <span class="nav-brand">Resto</span>
            <div class="nav-links">
                <a href="/mesas/index.php">Mesas</a>
                <a href="/reservas/nueva.php">Nueva Reserva</a>
                <a href="/listados/por_fecha.php">Listados</a>
                <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                <a href="/auth/logout.php">Salir</a>
            </div>
        </nav>

        <h1>Nueva Reserva</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <form method="POST" action="guardar.php" id="formReserva">
            <label for="fecha">Fecha</label>
            <input type="date" id="fecha" name="fecha" required min="<?= date('Y-m-d') ?>">

            <label for="hora">Hora de inicio</label>
            <select id="hora" name="hora" required>
                <option value="">Seleccioná fecha primero</option>
            </select>

            <label for="cantidad">Cantidad de personas</label>
            <input type="number" id="cantidad" name="cantidad" min="1" max="30" required value="2">

            <label for="ubicacion">Ubicación (definida por el sistema según disponibilidad)</label>
            <select id="ubicacion" name="ubicacion" required>
                <option value="">Elegí fecha y hora primero</option>
            </select>

            <div id="mesasDisponibles" class="mesas-grid">
                <p class="text-muted">Seleccioná fecha y hora para ver las mesas disponibles.</p>
            </div>

            <input type="hidden" name="mesas_seleccionadas" id="mesasSeleccionadas" value="">
            <p><strong>Máximo 3 mesas, misma ubicación.</strong></p>

            <button type="submit" class="btn btn-primary">Reservar</button>
            <a href="/mesas/index.php" class="btn">Cancelar</a>
        </form>
    </div>

    <script>
    const horarios = <?= json_encode($horarios) ?>;
    const fechaInput = document.getElementById('fecha');
    const horaSelect = document.getElementById('hora');
    const ubicacionSelect = document.getElementById('ubicacion');
    const mesasDiv = document.getElementById('mesasDisponibles');
    const mesasSeleccionadas = document.getElementById('mesasSeleccionadas');
    let seleccionadas = [];
    let mesasData = [];

    fechaInput.addEventListener('change', function() {
        // Parsear localmente: new Date('YYYY-MM-DD') usa UTC y en UTC-3
        // devuelve el día anterior
        const [y, m, d] = this.value.split('-').map(Number);
        const dia = new Date(y, m - 1, d).getDay();
        const horario = horarios[dia];
        horaSelect.innerHTML = '';

        let horas = [];
        const iniH = parseInt(horario.ini.split(':')[0]);
        const finH = parseInt(horario.fin.split(':')[0]);
        const duracion = 2;

        if (dia === 6) {
            // Sábado: 22 a 02
            for (let h = iniH; h <= 23; h++) {
                if (h + duracion <= finH + 24) horas.push(h);
            }
            for (let h = 0; h < finH; h++) {
                if (h + duracion <= finH) horas.push(h);
            }
        } else {
            for (let h = iniH; h + duracion <= finH; h++) horas.push(h);
        }

        // Si la fecha es hoy, ocultar horas pasadas o con menos de 15 min
        const ahora = new Date();
        const esHoy = y === ahora.getFullYear() && m - 1 === ahora.getMonth() && d === ahora.getDate();
        const limite = ahora.getHours() * 60 + ahora.getMinutes() + 15;
        horas = horas.filter(h => {
            let mins = h * 60;
            if (esHoy && dia === 6 && h < 6) mins += 1440;
            return !esHoy || mins >= limite;
        });

        if (horas.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'No hay horarios disponibles para hoy';
            horaSelect.appendChild(opt);
            ubicacionSelect.innerHTML = '<option value="">Elegí otra fecha</option>';
            return;
        }

        horas.forEach(h => {
            const opt = document.createElement('option');
            opt.value = String(h).padStart(2, '0') + ':00';
            opt.textContent = String(h).padStart(2, '0') + ':00';
            horaSelect.appendChild(opt);
        });

        horaSelect.dispatchEvent(new Event('change'));
    });

    horaSelect.addEventListener('change', cargarDisponibilidad);
    ubicacionSelect.addEventListener('change', cargarMesas);

    async function pedirJSON(url) {
        const resp = await fetch(url);
        if (!resp.ok) throw new Error('Error del servidor (' + resp.status + ')');
        return await resp.json();
    }

    async function cargarDisponibilidad() {
        const fecha = fechaInput.value;
        const hora = horaSelect.value;
        if (!fecha || !hora) return;

        let data;
        try {
            data = await pedirJSON('disponibilidad.php?fecha=' + fecha + '&hora=' + hora);
        } catch (e) {
            ubicacionSelect.innerHTML = '<option value="">Error al consultar disponibilidad</option>';
            mesasDiv.innerHTML = '<p class="text-muted">Error al consultar disponibilidad: ' + e.message + '</p>';
            return;
        }

        ubicacionSelect.innerHTML = '';
        if (data.ubicaciones && data.ubicaciones.length > 0) {
            data.ubicaciones.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.ubicacion;
                opt.textContent = u.ubicacion + ' (' + u.disponibles + ' mesas disponibles)';
                ubicacionSelect.appendChild(opt);
            });
            // El sistema define la ubicación por orden (A -> B -> C -> D)
            ubicacionSelect.selectedIndex = 0;
            ubicacionSelect.dispatchEvent(new Event('change'));
        } else {
            ubicacionSelect.innerHTML = '<option value="">No hay disponibilidad</option>';
            mesasDiv.innerHTML = '<p class="text-muted">No hay mesas disponibles para esta fecha/hora.</p>';
        }
    }

    async function cargarMesas() {
        const fecha = fechaInput.value;
        const hora = horaSelect.value;
        const ubicacion = ubicacionSelect.value;
        if (!fecha || !hora || !ubicacion) return;

        let data;
        try {
            data = await pedirJSON('disponibilidad.php?fecha=' + fecha + '&hora=' + hora + '&ubicacion=' + encodeURIComponent(ubicacion));
        } catch (e) {
            mesasData = [];
            mesasDiv.innerHTML = '<p class="text-muted">Error al cargar mesas: ' + e.message + '</p>';
            return;
        }

        seleccionadas = [];
        mesasSeleccionadas.value = '';
        mesasDiv.innerHTML = '';
        mesasData = data.mesas || [];

        renderMesas();
    }

    function renderMesas() {
        const visibles = mesasData;

        // Deseleccionar mesas que quedaron fuera del filtro
        seleccionadas = seleccionadas.filter(id => visibles.some(m => m.id === id));
        mesasSeleccionadas.value = seleccionadas.join(',');
        mesasDiv.innerHTML = '';

        if (visibles.length === 0) {
            mesasDiv.innerHTML = '<p class="text-muted">No hay mesas disponibles en esta ubicación.</p>';
            return;
        }

        visibles.forEach(m => {
            const card = document.createElement('div');
            card.className = 'mesa-card';
            card.dataset.id = m.id;
            if (seleccionadas.includes(m.id)) card.classList.add('selected');
            card.innerHTML = '<strong>Mesa #' + m.numero + '</strong><br>' +
                'Cap: ' + m.capacidad + ' personas<br>';
            card.addEventListener('click', () => toggleMesa(m.id, card));
            mesasDiv.appendChild(card);
        });
    }

    function toggleMesa(id, card) {
        const idx = seleccionadas.indexOf(id);
        if (idx >= 0) {
            seleccionadas.splice(idx, 1);
            card.classList.remove('selected');
        } else {
            if (seleccionadas.length >= 3) {
                alert('Máximo 3 mesas por reserva.');
                return;
            }
            seleccionadas.push(id);
            card.classList.add('selected');
        }
        mesasSeleccionadas.value = seleccionadas.join(',');
    }
    </script>
</body>
</html>
