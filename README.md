# ABM Mesas - Sistema de Reservas

Aplicación web en PHP + MySQL para gestión de mesas y reservas de un restaurante.

## Enunciado

1. ABM de Mesas (Ubicación [A, B, C, D], Numero de mesa, cantidad de personas)
2. Login/registro de usuarios
3. Solicitud de reserva, fecha, hora [L-V de 10 a 24, sábado de 22 a 2AM, domingo de 12 a 16], cantidad de personas. Se pueden unir mesas en la misma Ubicación. Usar cache en memoria de la disponibilidad por ubicación. La duración de las reservas es por default por 2 horas y la ubicación la debe definir el sistema (por orden) y se puede reservar hasta 15 minutos antes. Máximo 3 mesas por reserva
4. Listado por fecha, que muestre las reservas por ubicación incluyendo que mesas en una sola consulta SQL optima

Consigna: Hacer el punto 3 y 4.

Tiempo de entrega : Lunes 24 Agosto 2026

## Instalación

### Requisitos

- PHP 8.x con extensión PDO MySQL
- MySQL o MariaDB
- Servidor web (Apache/Nginx) apuntando a la raíz del proyecto, o `php -S` para pruebas

### Pasos

1. Clonar el repositorio y entrar a la carpeta:

    ```bash
    cd abm-mesas
    ```

2. Crear la base de datos importando el esquema (crea la base `abm_mesas`, tablas y datos de ejemplo):

    ```bash
    mysql -u root -p < sql/schema.sql
    ```

   También se puede importar desde phpMyAdmin / MySQL Workbench.

3. Configurar credenciales en `config/db.php`:

    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'abm_mesas');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    ```

4. Levantar el servidor (o usar el virtual host que ya tengas):

    ```bash
    php -S localhost:8000
    ```

5. Entrar a `http://localhost:8000` y loguearse.

### Usuario de ejemplo

| Email             | Password  |
|-------------------|-----------|
| admin@resto.com   | admin123  |

## Estructura

```
auth/          Login, registro, logout y helpers de sesión
config/        Conexión a la base y helpers compartidos
css/           Estilos (responsive)
mesas/         ABM de mesas
reservas/      Alta, disponibilidad y cancelación de reservas
listados/      Reservas por fecha
sql/           schema.sql (instalación desde cero) y migraciones incrementales
```

## Decisiones técnicas

- **Seguridad**: prepared statements en todas las consultas (PDO con `EMULATE_PREPARES => false`), `password_hash`/`password_verify`, casteo de enteros, whitelist para ubicaciones, `htmlspecialchars` en toda salida HTML, `session_regenerate_id` al iniciar sesión.
- **Cache de disponibilidad**: por sesión en memoria (`$_SESSION['dispo_*']`), clave fecha+hora; se invalida al crear o cancelar reservas.
- **Ubicación automática**: el sistema asigna la primera ubicación disponible en orden (A → B → C → D).
- **Reglas de negocio** validadas tanto en cliente como en servidor: horarios por día, reserva hasta 15 min antes de iniciar o del cierre, máximo 3 mesas por reserva, mesas solo de la misma ubicación, capacidad total según mesas.
- **Denormalización**: `reservas.cantidad_mesas` y `reservas.mesas_reservadas` guardan snapshot de las mesas al momento de reservar.
- **Responsive**: en mobile las tablas se muestran como tarjetas apiladas sin scroll horizontal.
