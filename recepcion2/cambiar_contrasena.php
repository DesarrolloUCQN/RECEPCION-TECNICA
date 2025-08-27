<?php
// Conexión a la base de datos
$serverName = "PA-S1-DATA\\UCQNDATA";
$connectionInfo = array("Database" => "recep_tec", "UID" => "sadumesm", "PWD" => "Dumes100%", "characterset" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibimos los datos
    $identificacion = $_POST['Identificacion'];
    $nuevaContrasena = $_POST['nuevaContrasena'];

    // Validación de datos
    if (empty($identificacion) || empty($nuevaContrasena)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos']);
        exit;
    }

    // Actualizar la contraseña en la base de datos (sin hash)
    $query = "UPDATE firma_usuarios SET clave = ? WHERE Identificacion = ?";
    $params = array($nuevaContrasena, $identificacion);

    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Contraseña actualizada exitosamente.']);
    }
}
?>
