<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_completo = $_POST['nombre_completo'];
    $telefono = $_POST['telefono'];
    $correo_electronico = $_POST['correo_electronico'];
    $cantidad_personas = $_POST['cantidad_personas'];
    $fecha = $_POST['fecha'];
    $pago_reserva = $_POST['pago_reserva'];
    // peso_aprox may come as a single field or as an array peso_persona[]
    $peso_aprox = isset($_POST['peso_aprox']) ? $_POST['peso_aprox'] : '';
    $peso_persona = isset($_POST['peso_persona']) ? $_POST['peso_persona'] : null;

    // Validaciones básicas
    $errores = [];
    
    if (empty($nombre_completo)) {
        $errores[] = "El nombre completo es obligatorio";
    }
    
    if (empty($telefono)) {
        $errores[] = "El teléfono es obligatorio";
    }
    
    if (empty($correo_electronico) || !filter_var($correo_electronico, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido";
    }
    
    if (empty($cantidad_personas) || !is_numeric($cantidad_personas)) {
        $errores[] = "La cantidad de personas debe ser un número válido";
    }
    
    if (empty($fecha)) {
        $errores[] = "La fecha es obligatoria";
    }
    
    if (empty($pago_reserva) || !is_numeric($pago_reserva)) {
        $errores[] = "El pago de reserva debe ser un número válido";
    }
    
    // Validate weight: either individual weights (array) or single peso_aprox
    if (!empty($peso_persona) && is_array($peso_persona)) {
        $pesos_clean = [];
        foreach ($peso_persona as $idx => $p) {
            $p_clean = str_replace(',', '.', trim($p));
            if ($p_clean === '') {
                $errores[] = "El peso de la persona " . ($idx+1) . " es obligatorio";
                continue;
            }
            if (!is_numeric($p_clean)) {
                $errores[] = "El peso de la persona " . ($idx+1) . " debe ser un número válido";
                continue;
            }
            $p_val = floatval($p_clean);
            if ($p_val > 120) {
                $errores[] = "El peso de la persona " . ($idx+1) . " no puede superar 120 kg";
                continue;
            }
            // store normalized value
            $pesos_clean[] = $p_val;
        }
        // store as comma-separated for DB
        if (empty($errores)) {
            $peso_aprox = implode(',', $pesos_clean);
        }
    } else {
        if (empty($peso_aprox)) {
            $errores[] = "El peso aproximado es obligatorio";
        } else {
            // Normalizar decimales y validar numérico y límite de 120 kg
            $peso_aprox_clean = str_replace(',', '.', $peso_aprox);
            if (!is_numeric($peso_aprox_clean)) {
                $errores[] = "El peso aproximado debe ser un número válido";
            } else {
                $peso_val = floatval($peso_aprox_clean);
                if ($peso_val > 120) {
                    $errores[] = "El peso no puede superar 120 kg";
                }
                // normalize for storage
                $peso_aprox = $peso_val;
            }
        }
    }

    if (empty($errores)) {
        // Validar y formatear la fecha
        if (!empty($fecha)) {
            $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
            if ($fecha_obj === false) {
                $errores[] = "Formato de fecha inválido";
            } else {
                $fecha_mysql = $fecha_obj->format('Y-m-d');
            }
        } else {
            $errores[] = "La fecha es requerida";
        }

        if (empty($errores)) {
            try {
                $sql = "INSERT INTO reservas (nombre_completo, telefono, correo_electronico, cantidad_personas, fecha, pago_reserva, peso_aprox, creado_en) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssisss", 
                    $nombre_completo,
                    $telefono,
                    $correo_electronico,
                    $cantidad_personas,
                    $fecha_mysql,
                    $pago_reserva,
                    $peso_aprox
                );

                if ($stmt->execute()) {
                    $_SESSION['mensaje'] = "Reserva realizada con éxito";
                    header("Location: reservas.php");
                    exit();
                } else {
                    $errores[] = "Error al realizar la reserva";
                }
            } catch (Exception $e) {
                $errores[] = "Error en el sistema: " . $e->getMessage();
            }
        }
    }
}
// Obtener parámetros de tipo/precio (si vienen por GET al llegar desde la página de servicios)
$tipo_vuelo = isset($_GET['tipo']) ? htmlspecialchars($_GET['tipo']) : (isset($_POST['tipo_vuelo']) ? htmlspecialchars($_POST['tipo_vuelo']) : '');
$precio_preset = isset($_GET['precio']) ? (int)$_GET['precio'] : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva - FLY.N.C</title>
    <link rel="stylesheet" href="../static/css/estilos.css">
    <style>
        .campo select {
            width: 100%;
            padding: 0.8rem;
            border: 1.8px solid #004d40;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.3s;
            background-color: #f8f9ff;
            color: #333;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23004d40' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.8rem center;
            background-size: 1.2em;
            cursor: pointer;
        }

        .campo select:focus {
            border-color: #009688;
            outline: none;
            box-shadow: 0 0 7px rgba(0, 150, 136, 0.3);
        }

        .campo select option {
            padding: 0.8rem;
            font-size: 1rem;
            color: #333;
        }
    </style>
    <script>
        // Precios según tipo de vuelo y cantidad de personas
            const precios = {
                turista: {
                    1: 250000,
                    2: 480000,
                    3: 690000,
                    4: 880000,
                    5: 1100000  // 5 personas (incluye la 6ta gratis)
                },
                express: {
                    1: 200000,
                    2: 380000,
                    3: 540000,
                    4: 680000,
                    5: 800000
                },
                extremo: {
                    1: 300000,
                    2: 580000,
                    3: 840000,
                    4: 1080000,
                    5: 1300000
                }
            };

        function actualizarPrecio() {
            const cantidadPersonas = document.getElementById('cantidad_personas').value;
            const tipoVuelo = document.querySelector('input[name="tipo_vuelo"]').value;
            const precio = precios[tipoVuelo] ? precios[tipoVuelo][cantidadPersonas] || 0 : 0;
            const reserva = Math.round(precio * 0.15);
            const restante = precio - reserva;
            
            // Actualizar el texto informativo del precio
            const infoPrecio = document.getElementById('info_precio');
            if (cantidadPersonas) {
                let mensajePrecio = `Precio para ${cantidadPersonas} persona${cantidadPersonas > 1 ? 's' : ''}: $${precio.toLocaleString('es-CO')} COP`;
                if (parseInt(cantidadPersonas) === 5 && tipoVuelo === 'turista') {
                    mensajePrecio += ' (incluye una persona adicional GRATIS)';
                }
                infoPrecio.textContent = mensajePrecio;
                // actualizar resumen y campo de reserva
                const precioTotalEl = document.getElementById('precio_total');
                const reservaEl = document.getElementById('reserva_display');   
                const restanteEl = document.getElementById('restante_display');
                const pagoReservaInput = document.getElementById('pago_reserva');
                if (precioTotalEl) precioTotalEl.textContent = precio.toLocaleString('es-CO');
                if (reservaEl) reservaEl.textContent = reserva.toLocaleString('es-CO');
                if (restanteEl) restanteEl.textContent = restante.toLocaleString('es-CO');
                if (pagoReservaInput) pagoReservaInput.value = reserva;
                // info personas
                const infoPersonas = document.getElementById('info_personas');
                if (parseInt(cantidadPersonas) === 5) {
                    if (infoPersonas) {
                        infoPersonas.textContent = '¡6ta persona GRATIS!';
                        infoPersonas.style.color = '#004d40';
                        infoPersonas.style.fontWeight = 'bold';
                    }
                } else if (infoPersonas) {
                    infoPersonas.textContent = '';
                }
            } else {
                infoPrecio.textContent = '';
            }
        }

        // Renderiza inputs de peso según la cantidad de personas seleccionada
        function renderPesoInputs(count) {
            const container = document.getElementById('pesos_container');
            if (!container) return;
            container.innerHTML = '';
            for (let i = 1; i <= count; i++) {
                const wrapper = document.createElement('div');
                wrapper.style.marginBottom = '8px';

                const label = document.createElement('label');
                label.style.display = 'block';
                label.style.marginBottom = '6px';
                label.style.fontWeight = '600';
                const inputId = 'peso_persona_' + i;
                label.htmlFor = inputId;
                label.textContent = 'Peso persona ' + i + ':';

                const input = document.createElement('input');
                input.type = 'number';
                input.id = inputId;
                input.name = 'peso_persona[]';
                input.className = 'peso_individual';
                input.step = '0.1';
                input.min = '0';
                input.max = '120';
                input.required = true;
                input.placeholder = 'Ej: 70';
                input.oninput = function() { checkPesoIndividual(this); };

                const small = document.createElement('small');
                small.style.color = '#c62828';
                small.style.display = 'none';
                small.textContent = 'El peso no puede superar 120 kg.';

                wrapper.appendChild(label);
                wrapper.appendChild(input);
                wrapper.appendChild(small);
                container.appendChild(wrapper);
            }
        }

        // Chequea un input individual de peso
        function checkPesoIndividual(el) {
            const val = el.value;
            const small = el.nextElementSibling;
            if (val !== '' && !isNaN(val) && Number(val) > 120) {
                if (small) small.style.display = 'block';
                el.setCustomValidity('El peso no puede superar 120 kg');
            } else {
                if (small) small.style.display = 'none';
                el.setCustomValidity('');
            }
        }

        // Inicializar: si hay un select de cantidad, asociar y renderizar si ya hay valor
        window.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('cantidad_personas');
            if (sel) {
                // si ya hay un valor seleccionado (por GET/POST), renderizar
                const val = parseInt(sel.value) || 0;
                const container = document.getElementById('pesos_container');
                // si no hay inputs ya renderizados por el servidor, renderizar dinámicamente
                if (val > 0 && container && container.children.length === 0) renderPesoInputs(val);
                sel.addEventListener('change', function() {
                    const n = parseInt(this.value) || 0;
                    if (n > 0) renderPesoInputs(n);
                    // actualizar precio también
                    actualizarPrecio();
                });
            }
        });

        // Valida el peso en el cliente: no permitir > 120 kg
        function checkPesoMax() {
            const input = document.getElementById('peso_aprox');
            const err = document.getElementById('peso_error');
            if (!input) return;
            const val = input.value;
            if (val !== '' && !isNaN(val) && Number(val) > 120) {
                err.style.display = 'block';
                input.setCustomValidity('El peso no puede superar 120 kg');
            } else {
                err.style.display = 'none';
                input.setCustomValidity('');
            }
        }

        
    </script>
</head>
<body>
    <nav>
        <div class="logo">FLY.N.C</div>
        <ul class="nav-links">
            <li><a href="../templates/index.html">Inicio</a></li>
            <li><a href="../templates/servicios.html">Servicios</a></li>
            <li><a href="contacto.php">Contacto</a></li>
            <li><a href="../templates/reservas.html">Reservas</a></li>
            <li><a href="../templates/gallery.html">Galeria</a></li>
        </ul>
    </nav>

    <div class="contacto-fondo">
        <div class="form-rectangular">
            <h2>Realizar Reserva</h2>
            <p>Complete el formulario para reservar su vuelo</p>

            <?php if (!empty($errores)): ?>
                <div class="errores">
                    <?php foreach ($errores as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje-exito">
                    <?php 
                        echo $_SESSION['mensaje'];
                        unset($_SESSION['mensaje']);
                    ?>
                </div>
            <?php endif; ?>


            <form method="POST" action="reservas.php<?php echo $tipo_vuelo ? '?tipo=' . urlencode($tipo_vuelo) . '&precio=' . urlencode($precio_preset) : ''; ?>" class="contacto-formulario">
                <input type="hidden" name="tipo_vuelo" value="<?php echo $tipo_vuelo; ?>">
                
                <div class="campo">
                    <label for="nombre_completo">Nombre Completo:</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required 
                           maxlength="150" value="<?php echo isset($_POST['nombre_completo']) ? htmlspecialchars($_POST['nombre_completo']) : ''; ?>">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono" required 
                           maxlength="50" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                </div>

                <div class="campo">
                    <label for="correo_electronico">Correo Electrónico:</label>
                    <input type="email" id="correo_electronico" name="correo_electronico" required 
                           maxlength="150" value="<?php echo isset($_POST['correo_electronico']) ? htmlspecialchars($_POST['correo_electronico']) : ''; ?>">
                </div>

                <div class="campo">
                    <label for="cantidad_personas">Cantidad de Personas:</label>
                    <select id="cantidad_personas" name="cantidad_personas" required onchange="actualizarPrecio()">
                        <option value="">Seleccione cantidad de personas</option>
                        <option value="1">1 persona</option>
                        <option value="2">2 personas</option>
                        <option value="3">3 personas</option>
                        <option value="4">4 personas</option>
                        <option value="5">5 personas</option>
                            </select>
                    <small style="display: block; margin-top: 5px; color: #666;" id="info_precio"></small>
                    <small id="info_personas" style="display: block; margin-top: 5px;"></small>
                </div>

                <div class="campo">
                    <label for="fecha">Fecha de Reserva:</label>
                    <input type="date" id="fecha" name="fecha" required 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo isset($_POST['fecha']) ? htmlspecialchars($_POST['fecha']) : ''; ?>">
                </div>

                <div class="campo">
                    <label for="pago_reserva">Pago de Reserva (15% del total):</label>
                    <input type="number" id="pago_reserva" name="pago_reserva" required step="1" min="0" readonly
                           value="<?php echo isset($_POST['pago_reserva']) ? htmlspecialchars($_POST['pago_reserva']) : $pago_reserva; ?>">
                    <small style="color: #666; margin-top: 5px; display: block;">Este es el 15% del valor total del vuelo que debe pagar para reservar.</small>
                </div>

                <div class="campo" id="pesos_section">
                    <label for="peso_aprox">Peso por persona (kg):</label>
                    <div id="pesos_container">
                        <?php
                        // Si se enviaron pesos individuales en POST, renderizarlos para mantener valores al recargar
                        if (!empty($_POST['peso_persona']) && is_array($_POST['peso_persona'])) {
                            foreach ($_POST['peso_persona'] as $i => $pv) {
                                $index = $i + 1;
                                $val = htmlspecialchars($pv);
                                $id = 'peso_persona_' . $index;
                                echo "<div style='margin-bottom:8px;'><label for=\"$id\" style=\"display:block; margin-bottom:6px; font-weight:600;\">Peso persona $index:</label><input id=\"$id\" type=\"number\" name=\"peso_persona[]\" class=\"peso_individual\" step=\"0.1\" min=\"0\" max=\"120\" required value=\"$val\" placeholder=\"Ej: 70\" oninput=\"checkPesoIndividual(this)\"> <small style=\"color:#c62828; display:none; margin-left:8px;\">Peso inválido</small></div>";
                            }
                        } else {
                            // fallback: show single peso_aprox input if no individual weights
                            $single = isset($_POST['peso_aprox']) ? htmlspecialchars($_POST['peso_aprox']) : '';
                            echo "<input type=\"number\" id=\"peso_aprox\" name=\"peso_aprox\" step=\"0.1\" min=\"0\" max=\"120\" value=\"$single\" placeholder=\"Ej: 70\" oninput=\"checkPesoMax()\">";
                        }
                        ?>
                    </div>
                    <small id="peso_error" style="color:#c62828; display:none; margin-top:5px;">El peso no puede superar 120 kg.</small>
                </div>

                <button type="submit">Realizar Reserva</button>

                <div style="margin-top: 20px; text-align: left; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p style="color: #004d40; font-weight: bold; margin-bottom: 10px;">Resumen de Pago:</p>
                    <p>Precio total del vuelo: $<span id="precio_total">0</span> COP</p>
                    <p>Pago de reserva (15%): $<span id="reserva_display">0</span> COP</p>
                    <p>Restante a pagar: $<span id="restante_display">0</span> COP</p>
                    <small style="display: block; margin-top: 10px; color: #666;">
                        El restante se deberá pagar el día del vuelo antes de iniciar la actividad.
                    </small>
                </div>
            </form>
        </div>
    </div>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-col">
      <h3>Información</h3>
      <ul>
        <li><a href="#">Términos de servicio</a></li>
        <li><a href="#">Política de privacidad</a></li>
        <li><a href="#">Preguntas frecuentes</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h3>Contacto</h3>
      <p><strong>Teléfono:</strong> +57 312 729 6703</p>
      <p><strong>Correo:</strong> santysanoro@gmail.com</p>
      <p><strong>Horario de atencion:</strong> Lunes - viernes - 7:00 AM a 7:00 PM</p>
      <p><strong>Ubicación:</strong> Cartago, Valle del Cauca</p>
    </div>

    <div class="footer-col">
      <h3>¿Quieres más información?</h3>
      <div class="newsletter">
        <a href="../php/contacto.php" class="newsletter-btn">Contáctanos</a>
      </div>
      <h4>Síguenos</h4>
      <div class="social-icons">
        <a href="#"><i class="fab fa-instagram"></i>
        <img src="../static/intagram.jpg" alt="ig"></a>

        <a href="#"><i class="fab fa-facebook"></i>
        <img src="../static/facebook.jpg" alt="face"></a>

        <a href="#"><i class="fab fa-tiktok"></i>
        <img src="../static/tiktok.jpg" alt="tiktok"></a>

      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <img src="../static/img/logo.png" alt="Logo FLY.N.C" class="footer-logo" />
    <p>© 2025 <strong>FLY.N.C</strong> — Vive la experiencia desde el cielo </p>
  </div>
</footer>

</body>
</html>