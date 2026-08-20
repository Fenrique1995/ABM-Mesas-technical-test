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
    <link rel="stylesheet" href="/css/estilo.css">
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

            <label for="ubicacion">Ubicación (automática según disponibilidad)</label>
            <select id="ubicacion" name="ubicacion" required>
                <option value="">Elegí fecha y hora primero</option>
            </select>

            <div id="mesasDisponibles" class="mesas-grid">
                <p class="text-muted">Seleccioná fecha, hora y ubicación para ver mesas disponibles.</p>
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

    fechaInput.addEventListener('change', function() {
        const fecha = new Date(this.value);
        const dia = fecha.getDay();
        const horario = horarios[dia];
        horaSelect.innerHTML = '';

        let horas = [];
        const iniH = parseInt(horario.ini.split(':')[0]);
        const finH = parseInt(horario.fin.split(':')[0]);

        if (dia === 6) {
            // Sábado: 22 a 02 
            for (let h = iniH; h <= 23; h++) horas.push(h);
            for (let h = 0; h < finH; h++) horas.push(h);
        } else {
            for (let h = iniH; h < finH; h++) horas.push(h);
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

    async function cargarDisponibilidad() {
        const fecha = fechaInput.value;
        const hora = horaSelect.value;
        if (!fecha || !hora) return;

        const resp = await fetch('disponibilidad.php?fecha=' + fecha + '&hora=' + hora);
        const data = await resp.json();

        ubicacionSelect.innerHTML = '';
        if (data.ubicaciones && data.ubicaciones.length > 0) {
            ubicacionSelect.innerHTML = '<option value="">Seleccioná ubicación</option>';
            data.ubicaciones.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.ubicacion;
                opt.textContent = u.ubicacion + ' (' + u.disponibles + ' mesas disponibles)';
                ubicacionSelect.appendChild(opt);
            });
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

        const resp = await fetch('disponibilidad.php?fecha=' + fecha + '&hora=' + hora + '&ubicacion=' + ubicacion);
        const data = await resp.json();

        seleccionadas = [];
        mesasSeleccionadas.value = '';
        mesasDiv.innerHTML = '';

        if (data.mesas && data.mesas.length > 0) {
            data.mesas.forEach(m => {
                const card = document.createElement('div');
                card.className = 'mesa-card';
                card.dataset.id = m.id;
                card.innerHTML = '<strong>Mesa #' + m.numero + '</strong><br>' +
                    'Cap: ' + m.capacidad + ' personas<br>' +
                    '<small>' + m.seccion + '</small>';
                card.addEventListener('click', () => toggleMesa(m.id, card));
                mesasDiv.appendChild(card);
            });
        } else {
            mesasDiv.innerHTML = '<p class="text-muted">No hay mesas disponibles en esta ubicación.</p>';
        }
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
