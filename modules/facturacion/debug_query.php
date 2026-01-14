<?php
/**
 * Script de diagnóstico para debug de queries de facturación
 * ELIMINAR ESTE ARCHIVO DESPUÉS DE DIAGNOSTICAR EL PROBLEMA
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

// Simular parámetros
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$pagina = 1;
$offset = 0;
$registros_por_pagina = 15;

// Query base
$query_count = "SELECT COUNT(*) as total
                FROM documentos_facturacion df
                INNER JOIN clientes c ON df.cliente_id = c.id
                WHERE 1=1";

$query_select = "SELECT df.*, df.imagen_adjunta,
                        c.nombre_razon_social, c.apellido, c.documento, c.email,
                        u.nombre as usuario_nombre, u.apellido as usuario_apellido
                 FROM documentos_facturacion df
                 INNER JOIN clientes c ON df.cliente_id = c.id
                 LEFT JOIN usuarios u ON df.creado_por = u.id
                 WHERE 1=1";

$params = [];

// Aplicar búsqueda
if (!empty($buscar)) {
    $query_count .= " AND (df.numero_documento LIKE :buscar
                          OR c.nombre_razon_social LIKE :buscar
                          OR c.documento LIKE :buscar)";
    $query_select .= " AND (df.numero_documento LIKE :buscar
                           OR c.nombre_razon_social LIKE :buscar
                           OR c.documento LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

// Aplicar filtro por tipo
if (!empty($tipo_filtro)) {
    $query_count .= " AND df.tipo_documento = :tipo";
    $query_select .= " AND df.tipo_documento = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

// Completar query de selección
$query_select .= " ORDER BY df.creado_en DESC LIMIT :offset, :limit";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug de Queries - Facturación</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 8px;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .query-box {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .param-box {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
        .param-item {
            margin: 5px 0;
            padding: 5px;
            background: #2d2d30;
            border-left: 3px solid #4ec9b0;
        }
        .key {
            color: #9cdcfe;
            font-weight: bold;
        }
        .value {
            color: #ce9178;
        }
        .info {
            background: #1a3a52;
            border-left: 4px solid #569cd6;
            padding: 10px;
            margin: 10px 0;
        }
        .warning {
            background: #524a1a;
            border-left: 4px solid #d7ba7d;
            padding: 10px;
            margin: 10px 0;
        }
        .success {
            background: #1a4d1a;
            border-left: 4px solid #4ec9b0;
            padding: 10px;
            margin: 10px 0;
        }
        .error {
            background: #4d1a1a;
            border-left: 4px solid #f48771;
            padding: 10px;
            margin: 10px 0;
        }
        .test-form {
            background: #2d2d30;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .test-form input,
        .test-form select,
        .test-form button {
            padding: 10px;
            margin: 5px;
            border: 1px solid #3e3e42;
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 4px;
        }
        .test-form button {
            background: #569cd6;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }
        .test-form button:hover {
            background: #4a9cc5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table th,
        table td {
            padding: 10px;
            border: 1px solid #3e3e42;
            text-align: left;
        }
        table th {
            background: #2d2d30;
            color: #4ec9b0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug de Queries - Facturación</h1>

        <div class="warning">
            ⚠️ <strong>IMPORTANTE:</strong> Este archivo es solo para diagnóstico. ELIMÍNALO después de resolver el problema.
        </div>

        <div class="test-form">
            <h2>Probar Diferentes Escenarios</h2>
            <form method="GET">
                <label>Búsqueda:</label>
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Ej: FV-00001, Juan, etc.">

                <label>Filtro de Tipo:</label>
                <select name="tipo">
                    <option value="">-- Todos --</option>
                    <option value="FACTURA" <?php echo $tipo_filtro === 'FACTURA' ? 'selected' : ''; ?>>FACTURA</option>
                    <option value="BOLETA" <?php echo $tipo_filtro === 'BOLETA' ? 'selected' : ''; ?>>BOLETA</option>
                    <option value="RECIBO" <?php echo $tipo_filtro === 'RECIBO' ? 'selected' : ''; ?>>RECIBO</option>
                </select>

                <button type="submit">Probar</button>
            </form>
        </div>

        <h2>📋 Parámetros Actuales</h2>
        <table>
            <tr>
                <th>Variable</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>Búsqueda ($buscar)</td>
                <td><?php echo empty($buscar) ? '<em>vacío</em>' : htmlspecialchars($buscar); ?></td>
            </tr>
            <tr>
                <td>Filtro Tipo ($tipo_filtro)</td>
                <td><?php echo empty($tipo_filtro) ? '<em>vacío</em>' : htmlspecialchars($tipo_filtro); ?></td>
            </tr>
            <tr>
                <td>Offset</td>
                <td><?php echo $offset; ?></td>
            </tr>
            <tr>
                <td>Límite</td>
                <td><?php echo $registros_por_pagina; ?></td>
            </tr>
        </table>

        <h2>🔢 Query COUNT</h2>
        <div class="query-box">
            <?php echo htmlspecialchars($query_count); ?>
        </div>

        <h2>📊 Query SELECT</h2>
        <div class="query-box">
            <?php echo htmlspecialchars($query_select); ?>
        </div>

        <h2>🔑 Parámetros PDO ($params)</h2>
        <div class="param-box">
            <?php if (empty($params)): ?>
                <div class="info">ℹ️ No hay parámetros de búsqueda o filtro</div>
            <?php else: ?>
                <?php foreach ($params as $key => $value): ?>
                    <div class="param-item">
                        <span class="key"><?php echo htmlspecialchars($key); ?></span> =
                        <span class="value">"<?php echo htmlspecialchars($value); ?>"</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h2>✅ Validación de Parámetros</h2>
        <?php
        // Contar placeholders en cada query
        preg_match_all('/:\w+/', $query_count, $placeholders_count);
        preg_match_all('/:\w+/', $query_select, $placeholders_select);

        $placeholders_count_unique = array_unique($placeholders_count[0]);
        $placeholders_select_unique = array_unique($placeholders_select[0]);

        $params_keys = array_keys($params);

        // Verificar query COUNT
        $count_ok = true;
        foreach ($placeholders_count_unique as $placeholder) {
            if (!in_array($placeholder, $params_keys)) {
                $count_ok = false;
                echo "<div class='error'>❌ Query COUNT: Falta parámetro $placeholder</div>";
            }
        }
        if ($count_ok) {
            echo "<div class='success'>✅ Query COUNT: Todos los parámetros están presentes</div>";
        }

        // Verificar query SELECT (sin :offset y :limit que se agregan después)
        $select_placeholders_without_pagination = array_diff($placeholders_select_unique, [':offset', ':limit']);
        $select_ok = true;
        foreach ($select_placeholders_without_pagination as $placeholder) {
            if (!in_array($placeholder, $params_keys)) {
                $select_ok = false;
                echo "<div class='error'>❌ Query SELECT: Falta parámetro $placeholder</div>";
            }
        }
        if ($select_ok) {
            echo "<div class='success'>✅ Query SELECT: Todos los parámetros de búsqueda/filtro están presentes</div>";
        }

        // Verificar que :offset y :limit estén en la query SELECT
        if (in_array(':offset', $placeholders_select[0]) && in_array(':limit', $placeholders_select[0])) {
            echo "<div class='success'>✅ Query SELECT: Tiene placeholders :offset y :limit para paginación</div>";
        } else {
            echo "<div class='error'>❌ Query SELECT: Faltan placeholders :offset y/o :limit</div>";
        }
        ?>

        <h2>🧪 Ejecutar Queries</h2>
        <?php
        // Intentar ejecutar query COUNT
        try {
            $stmt = $conn->prepare($query_count);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $total = $stmt->fetch()['total'];
            echo "<div class='success'>✅ Query COUNT ejecutada exitosamente: $total registros</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Query COUNT falló: " . htmlspecialchars($e->getMessage()) . "</div>";
        }

        // Intentar ejecutar query SELECT
        try {
            $stmt = $conn->prepare($query_select);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll();
            echo "<div class='success'>✅ Query SELECT ejecutada exitosamente: " . count($results) . " registros obtenidos</div>";

            if (count($results) > 0) {
                echo "<h3>📄 Primeros Resultados:</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Tipo</th><th>Número</th><th>Cliente</th><th>Total</th></tr>";
                foreach (array_slice($results, 0, 5) as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['tipo_documento']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['numero_documento']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nombre_razon_social']) . "</td>";
                    echo "<td>$" . number_format($row['total'], 2) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Query SELECT falló: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='error'>Error Code: " . $e->getCode() . "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding: 20px; background: #2d2d30; border-radius: 5px;">
            <a href="index.php" style="color: #569cd6; text-decoration: none; font-weight: bold;">← Volver a Facturación</a>
        </div>
    </div>
</body>
</html>
