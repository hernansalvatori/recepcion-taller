<?php
// casos_api.php
header('Content-Type: application/json; charset=utf-8');

// Archivo donde se guardarán los casos
define('CASOS_FILE', __DIR__ . '/casos_data.json');
// Carpeta de imágenes (asegúrate que exista y tenga permisos de escritura)
define('CASOS_IMG_DIR', __DIR__ . '/img/casos');

// Crear carpeta de imágenes si no existe
if (!file_exists(CASOS_IMG_DIR)) {
    @mkdir(CASOS_IMG_DIR, 0775, true);
}

// Leer acción
$action = $_REQUEST['action'] ?? 'list';

// Función para cargar los casos desde el archivo JSON
function cargarCasos() {
    if (!file_exists(CASOS_FILE)) {
        return [];
    }
    $json = file_get_contents(CASOS_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

// Función para guardar los casos en el archivo JSON
function guardarCasos($casos) {
    $json = json_encode($casos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(CASOS_FILE, $json);
}

// Función para manejar subida de imagen
function subirImagen($campoName, $prefix = 'caso') {
    if (!isset($_FILES[$campoName]) || $_FILES[$campoName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $_FILES[$campoName]['tmp_name'];
    $origName = $_FILES[$campoName]['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // Extensiones permitidas
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas)) {
        return null;
    }

    $fileName = $prefix . '_' . uniqid() . '.' . $ext;
    $destPath = CASOS_IMG_DIR . '/' . $fileName;

    if (move_uploaded_file($tmpName, $destPath)) {
        // ruta relativa para el navegador
        return 'img/casos/' . $fileName;
    }

    return null;
}

// LISTAR CASOS
if ($action === 'list') {
    $casos = cargarCasos();
    echo json_encode([
        'ok' => true,
        'casos' => $casos
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// CREAR CASO
if ($action === 'create') {
    $casos = cargarCasos();

    $id = uniqid('caso_');

    $titulo   = $_POST['titulo']   ?? '';
    $tipo     = $_POST['tipo']     ?? '';
    $cliente  = $_POST['cliente']  ?? '';
    $fecha    = $_POST['fecha']    ?? '';
    $problema = $_POST['problema'] ?? '';
    $solucion = $_POST['solucion'] ?? '';
    $resultado= $_POST['resultado']?? '';

    // Subir imágenes
    $fotoAntes   = subirImagen('fotoAntes', 'antes');
    $fotoDespues = subirImagen('fotoDespues', 'despues');

    $nuevo = [
        'id'         => $id,
        'titulo'     => $titulo,
        'tipo'       => $tipo,
        'cliente'    => $cliente,
        'fecha'      => $fecha,
        'problema'   => $problema,
        'solucion'   => $solucion,
        'resultado'  => $resultado,
        'fotoAntes'  => $fotoAntes,
        'fotoDespues'=> $fotoDespues
    ];

    $casos[] = $nuevo;
    guardarCasos($casos);

    echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
    exit;
}

// ACTUALIZAR CASO
if ($action === 'update') {
    $casos = cargarCasos();
    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ID faltante'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $encontrado = false;

    foreach ($casos as &$caso) {
        if ($caso['id'] === $id) {
            $encontrado = true;

            $caso['titulo']    = $_POST['titulo']   ?? $caso['titulo'];
            $caso['tipo']      = $_POST['tipo']     ?? $caso['tipo'];
            $caso['cliente']   = $_POST['cliente']  ?? $caso['cliente'];
            $caso['fecha']     = $_POST['fecha']    ?? $caso['fecha'];
            $caso['problema']  = $_POST['problema'] ?? $caso['problema'];
            $caso['solucion']  = $_POST['solucion'] ?? $caso['solucion'];
            $caso['resultado'] = $_POST['resultado']?? $caso['resultado'];

            // Si se sube una nueva foto, la sustituimos
            $nuevaAntes = subirImagen('fotoAntes', 'antes');
            if ($nuevaAntes) {
                $caso['fotoAntes'] = $nuevaAntes;
            }

            $nuevaDespues = subirImagen('fotoDespues', 'despues');
            if ($nuevaDespues) {
                $caso['fotoDespues'] = $nuevaDespues;
            }

            break;
        }
    }

    if (!$encontrado) {
        echo json_encode(['ok' => false, 'error' => 'Caso no encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    guardarCasos($casos);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// BORRAR CASO
if ($action === 'delete') {
    $casos = cargarCasos();
    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ID faltante'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nuevoArray = [];
    $encontrado = false;

    foreach ($casos as $caso) {
        if ($caso['id'] === $id) {
            $encontrado = true;
            // Opcional: borrar también las imágenes del servidor
            if (!empty($caso['fotoAntes'])) {
                $path = __DIR__ . '/' . $caso['fotoAntes'];
                if (file_exists($path)) @unlink($path);
            }
            if (!empty($caso['fotoDespues'])) {
                $path = __DIR__ . '/' . $caso['fotoDespues'];
                if (file_exists($path)) @unlink($path);
            }
            continue;
        }
        $nuevoArray[] = $caso;
    }

    if (!$encontrado) {
        echo json_encode(['ok' => false, 'error' => 'Caso no encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    guardarCasos($nuevoArray);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// Si llega aquí, acción no válida
echo json_encode(['ok' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
