<?php
// Sesión para CSRF y flash messages
session_start();

require_once __DIR__ . '/db.php';

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

$nombre = '';
$correo = '';
$telefono = '';
$mensaje = '';
$origen = isset($_GET['from']) ? trim($_GET['from']) : 'web_form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido. Intenta de nuevo.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $mensaje = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';
    $origen = isset($_POST['origen']) ? trim($_POST['origen']) : $origen;

    if ($nombre === '' || $correo === '' || $mensaje === '') {
        $_SESSION['flash_error'] = 'Por favor completa los campos requeridos (nombre, correo y mensaje).';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if (strlen($mensaje) > 5000) {
        $_SESSION['flash_error'] = 'El mensaje es demasiado largo.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO contactos (nombre, correo, telefono, mensaje, origen, creado_en) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('sssss', $nombre, $correo, $telefono, $mensaje, $origen);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Tu mensaje ha sido enviado. ¡Gracias por contactarnos!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['flash_error'] = 'Error al enviar el mensaje. Intenta de nuevo más tarde.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Error interno al preparar la consulta.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contacto - FLY.N.C</title>
   <link rel="stylesheet" href="../static/css/estilos.css"/>
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
  

  <main class="contacto-fondo">
    <section class="form-rectangular" data-aos="zoom-in">
      <h2>Contáctanos</h2>
      <p>¿Tienes dudas o quieres vivir la experiencia <strong>FLY.N.C</strong>?  
        Escríbenos y te responderemos lo antes posible.
      </p>

      <?php if ($success_msg): ?>
        <div class="mensaje-exito" style="background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin-bottom:12px;">
          <?php echo htmlspecialchars($success_msg); ?>
        </div>
      <?php endif; ?>

      <?php if ($error_msg): ?>
        <div class="mensaje-error" style="background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;margin-bottom:12px;">
          <?php echo htmlspecialchars($error_msg); ?>
        </div>
      <?php endif; ?>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="contacto-formulario">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen); ?>" />
        <div class="campo">
          <label for="nombre">Nombre completo</label>
          <input type="text" id="nombre" name="nombre" placeholder="Tu nombre completo" required value="<?php echo isset($nombre) ? htmlspecialchars($nombre) : ''; ?>" />
        </div>

        <div class="campo">
          <label for="correo">Correo electrónico</label>
          <input type="email" id="correo" name="correo" placeholder="Tu correo electrónico" required value="<?php echo isset($correo) ? htmlspecialchars($correo) : ''; ?>" />
        </div>

        <div class="campo">
          <label for="telefono">Teléfono (opcional)</label>
          <input type="text" id="telefono" name="telefono" placeholder="Tu teléfono" value="<?php echo isset($telefono) ? htmlspecialchars($telefono) : ''; ?>" />
        </div>

        <div class="campo">
          <label for="mensaje">Mensaje</label>
          <textarea id="mensaje" name="mensaje" placeholder="Escribe tu mensaje aquí..." required><?php echo isset($mensaje) ? htmlspecialchars($mensaje) : ''; ?></textarea>
        </div>

        <button type="submit"><i class="fas fa-paper-plane"></i> Enviar mensaje</button>
      </form>

      <div class="info-contacto" data-aos="fade-up">
        <p><i class="fas fa-phone"></i> +57 312 729 6703</p>
        <p><i class="fas fa-envelope"></i> santysanoro@gmail.com</p>
        <p><i class="fas fa-map-marker-alt"></i> Cartago, Valle del Cauca, Colombia</p>
      </div>
    </section>
  </main>

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


  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 1000, once: true });
  </script>
</body>
</html>

