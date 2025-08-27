<?php
// Conexión a la base de datos
$serverName = "PA-S1-DATA\\UCQNDATA";
$connectionInfo = array("Database" => "recep_tec", "UID" => "sadumesm", "PWD" => "Dumes100%", "characterset" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

// Verificar si es una petición AJAX para DataTables
if (isset($_GET['draw'])) {
    handleDataTablesRequest($conn);
    exit;
}

// Obtener estadísticas generales para las tarjetas
function obtenerEstadisticas($conn) {
    $stats = array();
    
    // Total de usuarios
    $query = "SELECT COUNT(*) as total FROM firma_usuarios";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['total'] = $row['total'];
    }
    
    // Usuarios con imagen
    $query = "SELECT COUNT(*) as con_imagen FROM firma_usuarios WHERE RutaImagen IS NOT NULL AND RutaImagen != ''";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['con_imagen'] = $row['con_imagen'];
    }
    
    return $stats;
}

// Función para manejar las peticiones de DataTables
function handleDataTablesRequest($conn) {
    $draw = intval($_GET['draw']);
    $start = intval($_GET['start']);
    $length = intval($_GET['length']);
    $searchValue = $_GET['search']['value'] ?? '';
    
    // Columnas de la tabla
    $columns = array(
        0 => 'NomYApellCmp',
        1 => 'Identificacion',
        2 => 'RutaImagen',
        3 => 'RutaImagen', // Para la columna de ver firma
        4 => 'FechaRegistro'
    );
    
    // Construir consulta base
    $baseQuery = "FROM firma_usuarios";
    $whereClause = "";
    $params = array();
    
    // Aplicar filtro de búsqueda si existe
    if (!empty($searchValue)) {
        $whereClause = " WHERE (NomYApellCmp LIKE ? OR Identificacion LIKE ?)";
        $searchParam = '%' . $searchValue . '%';
        $params = array($searchParam, $searchParam);
    }
    
    // Ordenamiento
    $orderClause = "";
    if (isset($_GET['order'][0]['column']) && isset($_GET['order'][0]['dir'])) {
        $orderColumn = intval($_GET['order'][0]['column']);
        $orderDir = $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
        
        if (isset($columns[$orderColumn])) {
            // Para la columna de imagen (índice 2), ordenar por si tiene imagen o no
            if ($orderColumn == 2) {
                $orderClause = " ORDER BY CASE WHEN RutaImagen IS NOT NULL AND RutaImagen != '' THEN 1 ELSE 0 END " . $orderDir;
            } else {
                $orderClause = " ORDER BY " . $columns[$orderColumn] . " " . $orderDir;
            }
        }
    } else {
        $orderClause = " ORDER BY FechaRegistro DESC";
    }
    
    // Contar total de registros
    $totalQuery = "SELECT COUNT(*) as total " . $baseQuery . $whereClause;
    $totalStmt = sqlsrv_query($conn, $totalQuery, $params);
    $totalRecords = 0;
    if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    
    // Consulta principal con paginación
    $dataQuery = "SELECT NomYApellCmp, Identificacion, RutaImagen, FechaRegistro " . 
                 $baseQuery . $whereClause . $orderClause . 
                 " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $dataParams = array_merge($params, array($start, $length));
    $dataStmt = sqlsrv_query($conn, $dataQuery, $dataParams);
    
    $data = array();
    if ($dataStmt) {
        while ($row = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
            // Formatear la fecha
            $fechaFormateada = $row['FechaRegistro']->format('d/m/Y H:i:s');
            
            // Estado de imagen
            $tieneImagen = ($row['RutaImagen'] && trim($row['RutaImagen']) !== '');
            $estadoImagen = $tieneImagen ? 
                '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Sí</span>' :
                '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>No</span>';
            
            // Columna para ver firma
            $verFirma = '';
            if ($tieneImagen) {
                $rutaImagen = htmlspecialchars($row['RutaImagen']);
                $verFirma = '<button class="btn btn-primary btn-sm btn-ver-firma" data-imagen="' . $rutaImagen . '" title="Ver Firma">' .
                           '<i class="fas fa-eye me-1"></i>Ver Firma</button>';
            } else {
                $verFirma = '<span class="text-muted"><i class="fas fa-image-slash me-1"></i>Sin firma</span>';
            }
            
            // Botones de acción - CAMBIO AQUÍ: se añadió la clase 'action-btn-container' para el contenedor
            $acciones = '<div class="action-btn-container">' .
                       '<button class="btn btn-info btn-sm btn-modify action-btn-left" ' .
                       'data-id="' . htmlspecialchars($row['Identificacion']) . '" title="Modificar Usuario">' .
                       '<i class="fas fa-edit me-1"></i>Modificar</button>' .
                       '</div>';
            
            $data[] = array(
                '<div class="d-flex align-items-center"><i class="fas fa-user-circle me-2 text-primary"></i><span class="nombre-completo">' . 
                htmlspecialchars($row['NomYApellCmp']) . '</span></div>',
                '<span class="badge bg-light text-dark">' . htmlspecialchars($row['Identificacion']) . '</span>',
                $estadoImagen,
                $verFirma,
                '<i class="fas fa-clock me-2 text-muted"></i>' . $fechaFormateada,
                $acciones
            );
        }
    }
    
    // Preparar respuesta JSON para DataTables
    $response = array(
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords,
        "data" => $data
    );
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

$estadisticas = obtenerEstadisticas($conn);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión - Lista de Usuarios</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <link rel="shortcut icon" href="img/icono.ico" type="image/x-icon">
    <link rel="stylesheet" href="diseño/vista.css">

    <style>
        :root {
            --primary-color: #007BFF;
            --success-color: #218838;
            --info-color: #138496;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }

        body {
            background: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 1rem;
        }

        /* Loader Styles */
        .loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            z-index: 9999;
        }

        .svg-container {
            display: flex;
            gap: 60px;
            align-items: center;
        }

        .loader-wrapper svg polyline,
        .loader-wrapper svg path {
            fill: none;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* SVG RAYO */
        svg polyline#back {
            stroke: #ff4d5033;
        }

        svg polyline#front {
            stroke: #ff4d4f;
            stroke-dasharray: 300;
            stroke-dashoffset: 300;
            animation: dashAnim 2s linear infinite;
        }

        /* CORAZÓN */
        svg path#back-heart {
            stroke: #00b89433;
        }

        svg path#front-heart {
            stroke: #00b894;
            stroke-dasharray: 300;
            stroke-dashoffset: 300;
            animation: dashAnim 2s linear infinite;
        }

        @keyframes dashAnim {
            70% {
                opacity: 0.2;
            }
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Header Styles - Reducido */
        .page-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .page-header h1 {
            font-weight: 600;
            font-size: 2rem;
            margin: 0;
        }

        .page-header .subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Stats Cards - Reducidas */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .stats-card .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats-card .stats-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-card .stats-icon {
            font-size: 2rem;
            color: var(--primary-color);
            opacity: 0.7;
            margin-bottom: 0.5rem;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
            border-left: 4px solid var(--success-color);
        }

        .search-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        /* Custom Search Input */
        .search-input-container {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input-container .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 16px 60px 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
            text-align: left;
        }

        .search-input-container .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .search-input-container .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .search-input-container .search-icon:hover {
            color: var(--primary-color);
        }

        /* Table Container - Reducido */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--info-color);
            display: none; /* Oculto por defecto */
        }

        .table-container h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
        }

        /* Table Styles */
        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.05);
            font-size: 0.9rem;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem;
            font-size: 0.8rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        .table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        /* Mejora para mostrar nombres completos sin cortar */
        .nombre-completo {
            word-wrap: break-word;
            white-space: normal;
            max-width: none;
            display: inline-block;
            line-height: 1.3;
        }

        /* Ancho mínimo para la columna de nombres */
        .table td:first-child,
        .table th:first-child {
            min-width: 200px;
            width: auto;
        }

        @media (max-width: 768px) {
            .table td:first-child,
            .table th:first-child {
                min-width: 150px;
            }
        }

        /* ===== ESTILOS PARA BOTÓN DE ACCIÓN ESQUINADO A LA IZQUIERDA ===== */
        /* Contenedor de acciones posicionado a la izquierda dentro de la celda */
        .action-btn-container {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            width: 100%;
            padding: 0;
            position: relative;
            text-align: left;
        }

        /* Botón esquinado a la izquierda dentro de su celda */
        .action-btn-left {
            margin: 0;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        /* Asegurar que la celda de acciones tenga alineación correcta */
        .table tbody td:last-child {
            text-align: left !important;
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            vertical-align: middle;
        }

        .table thead th:last-child {
            text-align: left !important;
            padding-left: 0.75rem !important;
        }

        /* Responsive para el botón de acción */
        @media (max-width: 768px) {
            .action-btn-left {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .action-btn-left i {
                margin-right: 0.25rem;
            }

            .table tbody td:last-child {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
        }

        @media (max-width: 576px) {
            .action-btn-left {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
            
            .action-btn-left .me-1 {
                display: none; /* Ocultar icono en pantallas muy pequeñas para ahorrar espacio */
            }

            .table tbody td:last-child {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }
        }

        /* Asegurar que la columna de acciones tenga el ancho mínimo necesario */
        .table td:last-child,
        .table th:last-child {
            min-width: 120px;
            width: 120px;
        }

        @media (max-width: 768px) {
            .table td:last-child,
            .table th:last-child {
                min-width: 100px;
                width: 100px;
            }
        }

        @media (max-width: 576px) {
            .table td:last-child,
            .table th:last-child {
                min-width: 80px;
                width: 80px;
            }
        }
        /* ===== FIN DE ESTILOS PARA BOTÓN DE ACCIÓN ===== */

        /* Enhanced Modals - MEJORADOS CON BORDES PULIDOS */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 123, 255, 0.15);
            overflow: hidden;
            width: 78%;
            text-align: center;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
            position: relative;
        }

        .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-header h5 {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .modal-body {
            padding: 2rem;
            background: #fafbfc;
        }

        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        .btn-close {
            background: rgba(255,255,255,0.9);
            border-radius: 50%;
            opacity: 1;
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-close:hover {
            background: white;
            transform: scale(1.1);
        }

        /* Modal de modificar datos - MÁS GRANDE */
        .modal-lg-custom {
            max-width: 900px;
        }

        /* Modal de firma - Bordes pulidos */
        .modal-firma .modal-content {
            border-radius: 20px;
        }

        .modal-firma .modal-body {
            padding: 2.5rem;
            background: white;
        }

        .modal-firma .modal-body img {
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 3px solid #f8f9fa;
            transition: transform 0.3s ease;
        }

        .modal-firma .modal-body img:hover {
            transform: scale(1.02);
        }

        /* Form Controls */
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            text-align: center;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            background: white;
        }

        .form-label {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        /* Estilos para campos de solo lectura */
        .form-control[readonly] {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }

        /* Animation Classes */
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* DataTables Custom Styles */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-size: 0.875rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.375rem 0.75rem;
            margin-left: 2px;
            border-radius: 6px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
        }

        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
            margin-bottom: 1.5rem;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.75rem;
            }
            
            .stats-card .stats-number {
                font-size: 1.5rem;
            }
            
            .table-container {
                padding: 1rem;
            }

            .modal-lg-custom {
                max-width: 95%;
                margin: 1rem auto;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body id="contenidocompleto">
    <!-- Loader con SVGs -->
    <div class="loader-wrapper" id="loader">
        <div class="svg-container">
            <!-- SVG tipo rayo -->
            <svg width="120px" height="90px">
                <polyline points="0.157 44.954, 26 44.954, 41.843 90, 80 0, 92 44, 120 44" id="back"></polyline>
                <polyline points="0.157 44.954, 26 44.954, 41.843 90, 80 0, 92 44, 120 44" id="front"></polyline>
            </svg>

            <!-- SVG de corazón -->
            <svg width="120px" height="90px">
                <path id="back-heart" d="M60 80s-24-20-36-36C12 28 20 10 40 10c8 0 20 10 20 10s12-10 20-10c20 0 28 18 16 34-12 16-36 36-36 36z"></path>
                <path id="front-heart" d="M60 80s-24-20-36-36C12 28 20 10 40 10c8 0 20 10 20 10s12-10 20-10c20 0 28 18 16 34-12 16-36 36-36 36z"></path>
            </svg>
        </div>
    </div>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="page-header text-center fade-in-up">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center">
                <img src="img/icono.ico" alt="Icono" style="position: absolute;right: 93%;top: 40px;width: 4%;">
                <div>
                    <h1 class="mb-0"><i class="fas fa-users me-3"></i>Sistema de Gestión de Usuarios</h1>
                    <p class="subtitle mb-0">Panel de administración y control de usuarios registrados</p>
                </div>
            </div>
        </div>
    </div>

        <!-- Search Section -->
        <div class="search-section fade-in-up" style="animation-delay: 0.4s">
            <h4><i class="fas fa-search"></i> Búsqueda de Usuarios</h4>
            <div class="row">
                <div class="col-md-8">
                    <div class="search-input-container">
                        <input type="text" 
                               class="form-control" 
                               id="userSearch" 
                               placeholder="Buscar por nombre, apellido o identificación..."
                               autocomplete="off">
                        
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" id="clearSearch" class="btn btn-primary">
                            <i class="fas fa-times me-2"></i>Limpiar
                        </button>
                        <a href="index.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Salir
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Results Message (hidden by default) -->
        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h5>Ingresa un término de búsqueda</h5>
            <p>Escribe el nombre, apellido o identificación del usuario que deseas buscar.</p>
        </div>

        <!-- Table Section -->
        <div class="table-container fade-in-up" id="tableContainer" style="animation-delay: 0.5s">
            <h4><i class="fas fa-table"></i> Resultados de Búsqueda</h4>
            <div class="table-responsive">
                <table id="usuariosTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user me-2"></i>Nombres y Apellidos</th>
                            <th><i class="fas fa-id-card me-2"></i>Identificación</th>
                            <th><i class="fas fa-image me-2"></i>Imagen Cargada</th>
                            <th><i class="fas fa-signature me-2"></i>Ver Firma</th>
                            <th><i class="fas fa-calendar me-2"></i>Fecha Registro</th>
                            <th><i class="fas fa-cogs me-2"></i>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- DataTables manejará el contenido -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para modificar datos - MEJORADO Y MÁS GRANDE -->
    <div class="modal fade" id="modal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">
                        <i class="fas fa-user-edit me-2"></i>Modificar Usuario
                    </h5>
                </div>
                <form id="editForm" name="editForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="editNombre" class="form-label">
                                        <i class="fas fa-user me-2 text-primary"></i>Nombres y Apellidos
                                    </label>
                                    <input type="text" 
                                        class="form-control" 
                                        id="editNombre" 
                                        name="editNombre"
                                        readonly
                                        autocomplete="name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="editIdentificacion" class="form-label">
                                        <i class="fas fa-id-card me-2 text-primary"></i>Identificación
                                    </label>
                                    <input type="text" 
                                        class="form-control" 
                                        id="editIdentificacion" 
                                        name="editIdentificacion"
                                        readonly
                                        autocomplete="username">
                                </div>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="editClave" class="form-label">
                                        <i class="fas fa-lock me-2 text-primary"></i>Nueva Clave
                                    </label>
                                    <input type="password" 
                                        class="form-control" 
                                        id="editClave" 
                                        name="editClave"
                                        placeholder="Ingresa la nueva clave"
                                        autocomplete="new-password">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1 text-info"></i>
                                        Deja en blanco si no deseas cambiar la clave actual.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="editImagen" class="form-label">
                                        <i class="fas fa-image me-2 text-primary"></i>Subir Nueva Imagen
                                    </label>
                                    <input type="file" 
                                        class="form-control" 
                                        id="editImagen" 
                                        name="editImagen" 
                                        accept="image/*" 
                                        autocomplete="off">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1 text-info"></i>
                                        Formatos permitidos: JPG, PNG, JPEG. Tamaño máximo: 5MB.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="btnActualizar" name="btnActualizar">
                            <i class="fas fa-save me-2"></i>Actualizar Datos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Loader Script
        window.addEventListener('load', function () {
            const loader = document.getElementById('loader');
            const contenido = document.getElementById('contenidocompleto');

            setTimeout(() => {
                loader.style.display = 'none';
                contenido.style.display = 'block';
            }, 1500);
        });

        $(document).ready(function () {
            var modal = $('#modal');
            var currentUserData = {};
            var table = null;
            var searchTimeout;

            // Mostrar mensaje inicial
            $('#noResults').show();

            // Función de búsqueda personalizada
            function performSearch() {
                const searchTerm = $('#userSearch').val().trim();
                
                if (searchTerm.length === 0) {
                    // Si no hay término de búsqueda, ocultar tabla y mostrar mensaje
                    $('#tableContainer').hide();
                    $('#noResults').show();
                    if (table) {
                        table.destroy();
                        table = null;
                    }
                    return;
                }

                if (searchTerm.length < 2) {
                    return; // No buscar hasta tener al menos 2 caracteres
                }

                // Ocultar mensaje y mostrar tabla
                $('#noResults').hide();
                $('#tableContainer').show();

                // Inicializar DataTable si no existe
                if (!table) {
                    initializeDataTable();
                }
                
                // Aplicar búsqueda
                table.search(searchTerm).draw();
            }

            // Inicializar DataTable
            function initializeDataTable() {
                table = $('#usuariosTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "ajax": {
                        "url": window.location.pathname,
                        "type": "GET",
                        "error": function(xhr, error, thrown) {
                            console.error('Error loading data:', error);
                            showAlert('Error al cargar los datos. Por favor, recarga la página.', 'danger');
                        }
                    },
                    "columns": [
                        { "data": 0, "orderable": true },   // Nombres y Apellidos
                        { "data": 1, "orderable": true },   // Identificación
                        { "data": 2, "orderable": true },   // Imagen Cargada
                        { "data": 3, "orderable": false },  // Ver Firma
                        { "data": 4, "orderable": true },   // Fecha Registro
                        { "data": 5, "orderable": false }   // Acciones
                    ],
                    "pageLength": 15,
                    "lengthMenu": [[10, 15, 25, 50], [10, 15, 25, 50]],
                    "responsive": true,
                    "stateSave": false,
                    "columnDefs": [
                        { "orderable": false, "targets": [3, 5] }
                    ],
                    "language": {
                        "sProcessing": '<div class="d-flex align-items-center justify-content-center"><i class="fas fa-spinner fa-spin me-2"></i>Cargando datos...</div>',
                        "sLengthMenu": "Mostrar _MENU_ registros",
                        "sZeroRecords": '<div class="text-center py-4">' +
                                    '<i class="fas fa-users-slash fs-1 text-muted mb-3"></i>' +
                                    '<h5 class="text-muted">No se encontraron usuarios</h5>' +
                                    '<p class="text-muted">Intenta con otros términos de búsqueda</p></div>',
                        "sEmptyTable": "Ningún dato disponible en esta tabla",
                        "sInfo": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        "sInfoEmpty": "Mostrando 0 a 0 de 0 registros",
                        "sInfoFiltered": "(filtrado de _MAX_ registros totales)",
                        "sSearch": "Buscar:",
                        "sLoadingRecords": "Cargando...",
                        "oPaginate": {
                            "sFirst": "Primero",
                            "sLast": "Último",
                            "sNext": "Siguiente",
                            "sPrevious": "Anterior"
                        }
                    }
                });
            }

            // Búsqueda en tiempo real con debounce
            $('#userSearch').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 500); // Esperar 500ms después de que el usuario deje de escribir
            });

            // Búsqueda al hacer clic en el icono
            $('#searchBtn').on('click', function() {
                performSearch();
            });

            // Búsqueda al presionar Enter
            $('#userSearch').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    performSearch();
                }
            });

            // Limpiar búsqueda
            $('#clearSearch').on('click', function() {
                $('#userSearch').val('');
                $('#tableContainer').hide();
                $('#noResults').show();
                if (table) {
                    table.destroy();
                    table = null;
                }
            });

            // Event handlers para los botones de la tabla
            $(document).on('click', '.btn-modify', function() {
                var identificacion = $(this).data('id');
                var row = $(this).closest('tr');
                
                // Extraer el nombre del HTML (mejorado para capturar el nombre completo)
                var nombreCompleto = row.find('td:eq(0) .nombre-completo').text().trim();
                
                // Extraer la identificación del badge
                var idFromBadge = row.find('td:eq(1) .badge').text().trim();
                
                // Guardar datos del usuario actual
                currentUserData = {
                    nombre: nombreCompleto, 
                    identificacion: idFromBadge
                };
                
                // Llenar el modal
                $('#editNombre').val(currentUserData.nombre);
                $('#editIdentificacion').val(currentUserData.identificacion);
                $('#editClave').val('');
                $('#editImagen').val('');
                
                var modalInstance = new bootstrap.Modal(document.getElementById('modal'));
                modalInstance.show();
            });

            // Mostrar imagen de firma - MODAL MEJORADO
            $(document).on('click', '.btn-ver-firma', function() {
                var rutaImagen = $(this).data('imagen');
                var row = $(this).closest('tr');
                var nombreUsuario = row.find('td:eq(0) .nombre-completo').text().trim();
                
                // Crear modal mejorado para mostrar la imagen
                var modalHtml = `
                    <div class="modal fade modal-firma" id="modalFirma" tabindex="-1" aria-labelledby="modalFirmaLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalFirmaLabel">
                                        <i class="fas fa-signature me-2"></i>Firma Digital - ${nombreUsuario}
                                    </h5>
                                </div>
                                <div class="modal-body text-center">
                                    <div class="mb-3">
                                        <span class="badge bg-primary px-3 py-2">
                                            <i class="fas fa-user me-2"></i>${nombreUsuario}
                                        </span>
                                    </div>
                                    <div class="firma-container">
                                        <img src="${rutaImagen}" 
                                             class="img-fluid" 
                                             alt="Firma de ${nombreUsuario}" 
                                             style="max-height: 400px; max-width: 100%;">
                                    </div>
                                </div>
                                <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-2"></i>Cerrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remover modal anterior si existe
                $('#modalFirma').remove();
                
                // Agregar el modal al body
                $('body').append(modalHtml);
                
                // Mostrar el modal
                var modalInstance = new bootstrap.Modal(document.getElementById('modalFirma'));
                modalInstance.show();
                
                // Limpiar el modal cuando se cierre
                $('#modalFirma').on('hidden.bs.modal', function() {
                    $(this).remove();
                });
            });

            // Actualizar los datos en la base de datos
            $('#btnActualizar').on('click', function() {
                var btn = $(this);
                var originalHtml = btn.html();
                
                // Mostrar loading
                btn.html('<i class="fas fa-spinner fa-spin me-2"></i>Procesando...');
                btn.prop('disabled', true);
                
                var identificacion = $('#editIdentificacion').val();
                var clave = $('#editClave').val();
                var imagen = $('#editImagen')[0].files[0];

                // FormData para enviar datos y archivo
                var formData = new FormData();
                formData.append('Identificacion', identificacion);
                formData.append('clave', clave);
                if (imagen) {
                    formData.append('imagen', imagen);
                }

                $.ajax({
                    url: 'registrar_clave.php',
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,     
                    success: function(response) {
                        try {
                            response = JSON.parse(response);

                            if (response.success) {
                                // Cerrar modal
                                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('modal'));
                                modalInstance.hide();
                                
                                // Mostrar alerta de éxito
                                showAlert('¡Datos modificados exitosamente!', 'success');
                                
                                // Recargar la tabla si existe
                                if (table) {
                                    table.ajax.reload(null, false);
                                }
                            } else {
                                showAlert('Error al actualizar los datos: ' + response.message, 'danger');
                            }
                        } catch (e) {
                            showAlert('Error al procesar la respuesta del servidor', 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating data:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        showAlert('Error de conexión al actualizar los datos', 'danger');
                    },
                    complete: function() {
                        btn.html(originalHtml);
                        btn.prop('disabled', false);
                    }
                });
            });

            // Función para mostrar alertas
            function showAlert(message, type) {
                // Remover alertas anteriores
                $('.alert').fadeOut(function() {
                    $(this).remove();
                });
                
                // Crear elemento de alerta
                var alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger';
                var iconClass = type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle';
                
                var alertHtml = `
                    <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                        style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);" role="alert">
                        <i class="fas fa-${iconClass} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                $('body').append(alertHtml);
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    $('.alert').fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Mejorar la experiencia de los modales
            $('#modal').on('shown.bs.modal', function() {
                $(this).find('input[type="password"]:first').focus();
            });

            // Validación en tiempo real para contraseña
            $('#editClave').on('input', function() {
                var password = $(this).val();
                var parent = $(this).parent();
                
                // Remover indicador anterior
                parent.find('.password-strength').remove();
                
                if (password.length > 0) {
                    var strength = getPasswordStrength(password);
                    var strengthHtml = `
                        <div class="password-strength mt-1">
                            <small class="text-${strength.color}">
                                <i class="fas fa-${strength.icon} me-1"></i>
                                Fortaleza: ${strength.text}
                            </small>
                        </div>
                    `;
                    parent.append(strengthHtml);
                }
            });

            function getPasswordStrength(password) {
                if (password.length < 4) {
                    return { color: 'danger', icon: 'times', text: 'Muy débil' };
                } else if (password.length < 6) {
                    return { color: 'warning', icon: 'exclamation', text: 'Débil' };
                } else if (password.length < 8) {
                    return { color: 'info', icon: 'check', text: 'Moderada' };
                } else {
                    return { color: 'success', icon: 'check-circle', text: 'Fuerte' };
                }
            }
        });
    </script>
</body>
</html>