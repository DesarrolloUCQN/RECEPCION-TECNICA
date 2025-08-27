<?php
// Establecer la conexión con la base de datos
$serverName = "PA-S1-DATA\\UCQNDATA";
$connectionInfo = array("Database" => "recep_tec", "UID" => "sadumesm", "PWD" => "Dumes100%", "characterset" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

// Inicializar variables
$noMovimiento = null;
$tipoMovimiento = null;
$cedula = null;
$clave = null;

// Verificar si se ha enviado el número de movimiento y tipo de movimiento
if (isset($_GET['movimiento']) && isset($_GET['tipoMovimiento'])) {
    $noMovimiento = $_GET['movimiento'];
    $tipoMovimiento = $_GET['tipoMovimiento'];

    // Consulta para verificar si existen registros
    $query = "SELECT COUNT(*) FROM insumos WHERE [No. Movimiento] = ? AND [Tipo Movimiento] = ?";
    $params = array($noMovimiento, $tipoMovimiento);

    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        echo "Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true);
        exit;
    }

    $row = sqlsrv_fetch_array($stmt);
    $count = $row[0];

    if ($count > 0) {
        // Mostrar solo el formulario de validación sin la tabla de vista previa
        echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registros de Movimiento</title>
            <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
            <style>
                /* === ESTILOS BASE COMPACTOS CON FUENTE MÁS GRANDE === */
                body {
                    background-color: #f8f9fa;
                    font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.5;
                    font-size: 15px;
                    padding: 10px;
                    margin: 0;
                }

                /* === CONTENEDORES COMPACTOS === */
                .container {
                    max-width: 500px;
                    margin: 15px auto;
                    padding: 10px;
                }

                .card {
                    border-radius: 14px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    border: none;
                    overflow: hidden;
                    margin-bottom: 15px;
                    padding: 20px;
                }

                /* === BOTONES COMPACTOS CON FUENTE MÁS GRANDE === */
                .btn {
                    border-radius: 10px !important;
                    padding: 10px 20px;
                    font-size: 15px;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                }

                .btn-primary {
                    background: linear-gradient(135deg, #007fff, #0066cc);
                    border: none;
                    color: white !important;
                }

                .btn-primary:hover {
                    background: linear-gradient(135deg, #0066cc, #0055aa);
                    transform: translateY(-1px);
                    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
                }

                .btn-primary:active {
                    background: linear-gradient(135deg, #0055aa, #004488);
                    transform: translateY(0);
                }

                /* === FORMULARIOS COMPACTOS CON FUENTE MÁS GRANDE === */
                .form-control {
                    border-radius: 10px !important;
                    padding: 10px 15px;
                    border: 1px solid #ddd;
                    transition: all 0.2s ease;
                    height: 42px;
                    font-size: 15px;
                }

                .form-control:focus {
                    border-color: #007fff;
                    box-shadow: 0 0 0 2px rgba(0, 127, 255, 0.2);
                    outline: none;
                }

                .form-group {
                    margin-bottom: 18px;
                }

                label {
                    font-weight: 600;
                    margin-bottom: 8px;
                    display: block;
                    color: #555;
                    font-size: 15px;
                }

                h3 {
                    font-size: 18px;
                    margin-bottom: 20px;
                    color: #333;
                    font-weight: 600;
                }

                strong {
                    color: #0066cc;
                }

                /* === EFECTOS SUAVES === */
                * {
                    transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s, transform 0.2s;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h3 class="text-center mb-4">Registros para el número de movimiento: 
                    <strong>' . $noMovimiento . '</strong> y tipo de movimiento: <strong>' . $tipoMovimiento . '</strong></h3>
                    
                    <form action="aprobacion.php" method="POST" id="validationForm">
                        <input type="hidden" name="NoMovimiento" value="' . $noMovimiento . '">
                        <input type="hidden" name="tipoMovimiento" value="' . $tipoMovimiento . '">
                        <div class="form-group">
                            <label for="cedula">Cédula:</label>
                            <input type="text" id="cedula" name="cedula" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="clave">Contraseña:</label>
                            <input type="password" id="clave" name="clave" class="form-control" required>
                        </div>
                        <button type="submit" name="validar" class="btn btn-primary">
                            <i class="fas fa-check"></i> Validar
                        </button>
                    </form>
                </div>
            </div>
            <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        </body>
        </html>';
    } else {
        echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error</title>
            <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #f8f9fa;
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.5;
                    font-size: 15px;
                    padding: 10px;
                    margin: 0;
                }
                .container {
                    max-width: 500px;
                    margin: 15px auto;
                    padding: 10px;
                }
                .card {
                    border-radius: 14px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    border: none;
                    overflow: hidden;
                    margin-bottom: 15px;
                    padding: 20px;
                }
                strong {
                    color: #0066cc;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card p-4">
                    <p>No se encontraron registros para el número de movimiento <strong>' . $noMovimiento . '</strong> y tipo de movimiento <strong>' . $tipoMovimiento . '</strong>.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}

// El resto del código permanece igual (validación de credenciales y actualización)
if (isset($_POST['cedula']) && isset($_POST['clave']) && isset($_POST['NoMovimiento']) && isset($_POST['validar'])) {
    $cedula = $_POST['cedula'];
    $clave = $_POST['clave'];
    $noMovimiento = $_POST['NoMovimiento'];
    $tipoMovimiento = $_POST['tipoMovimiento'];

    $query = "SELECT * FROM firma_usuarios WHERE Identificacion = ? AND clave = ?";
    $params = array($cedula, $clave);

    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        echo "Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true);
        exit;
    }

    if (sqlsrv_has_rows($stmt)) {
        echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Aprobación</title>
            <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #f8f9fa;
                    font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.5;
                    font-size: 15px;
                    padding: 10px;
                    margin: 0;
                }
                .container {
                    max-width: 500px;
                    margin: 15px auto;
                    padding: 10px;
                }
                .card {
                    border-radius: 14px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    border: none;
                    overflow: hidden;
                    margin-bottom: 15px;
                    padding: 20px;
                }
                .btn {
                    border-radius: 10px !important;
                    padding: 10px 20px;
                    font-size: 15px;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                }
                .btn-approve {
                    background: linear-gradient(135deg, #007fff, #007fff);
                    border: none;
                    color: white !important;
                }
                .btn-approve:hover {
                    background: linear-gradient(135deg, #007fff, #007fff);
                    transform: translateY(-1px);
                    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
                }
                .btn-approve:active {
                    background: linear-gradient(135deg, #007fff, #007fff);
                    transform: translateY(0);
                }
                .form-group {
                    margin-bottom: 18px;
                }
                label {
                    font-weight: 600;
                    margin-bottom: 8px;
                    display: block;
                    color: #555;
                    font-size: 15px;
                }
                input[type="radio"] {
                    margin-right: 8px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <form action="aprobacion.php" method="POST">
                        <input type="hidden" name="NoMovimiento" value="' . $noMovimiento . '">
                        <input type="hidden" name="tipoMovimiento" value="' . $tipoMovimiento . '">
                        <input type="hidden" name="cedula" value="' . $cedula . '">
                        <input type="hidden" name="clave" value="' . $clave . '">
                        
                        <label for="aprobacion">Seleccione el estado de aprobación:</label><br>
                        <div class="form-group">
                            <input type="radio" id="aprobado" name="aprobacion" value="Aprobado" required>
                            <label for="aprobado">Aprobado</label>
                        </div>
                        <div class="form-group">
                            <input type="radio" id="noAprobado" name="aprobacion" value="No Aprobado" required>
                            <label for="noAprobado">No Aprobado</label>
                        </div>
                        
                        <button type="submit" name="actualizar" class="btn btn-approve">
                            <i class="fas fa-check-circle"></i> Registrar Aprobación
                        </button>
                    </form>
                </div>
            </div>
            <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        </body>
        </html>';
    } else {
        echo "
        <script>
            alert('Usuario o Contraseña incorrecto');
            window.location.href = 'vista.php';
            window.close();
        </script>";
    }
}

if (isset($_POST['aprobacion']) && isset($_POST['cedula']) && isset($_POST['NoMovimiento']) && isset($_POST['actualizar']) && isset($_POST['tipoMovimiento'])) {
    $aprobacion = $_POST['aprobacion'];
    $cedula = $_POST['cedula'];
    $noMovimiento = $_POST['NoMovimiento'];
    $tipoMovimiento = $_POST['tipoMovimiento'];

    $queryUpdate = "UPDATE insumos SET aprobacion = ?, validado = ? WHERE [No. Movimiento] = ? AND [Tipo Movimiento] = ?";
    $paramsUpdate = array($cedula, $aprobacion, $noMovimiento, $tipoMovimiento);

    $stmtUpdate = sqlsrv_query($conn, $queryUpdate, $paramsUpdate);

    if ($stmtUpdate === false) {
        echo "Error al ejecutar la actualización: " . print_r(sqlsrv_errors(), true);
        exit;
    }

    echo "
    <script>
        alert('La aprobación ha sido registrada correctamente para el número de movimiento.');
        window.location.href = 'vista.php';
        window.close();
    </script>";
}

sqlsrv_close($conn);
?>