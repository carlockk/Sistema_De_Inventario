<?php
session_start();
include 'config.php'; // Conexión a la base de datos

// Función para verificar si el usuario está logueado
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php?action=login');
        exit;
    }
}

check_login(); // Verificamos que el usuario esté logueado

// Obtener los productos del inventario
$stmt = $pdo->prepare('SELECT * FROM products');
$stmt->execute();
$products = $stmt->fetchAll();

// Si se realiza una entrada
if (isset($_POST['entrada'])) {
    $product_id = $_POST['product_id'];
    $quantity = floatval($_POST['quantity']); // Asegurarnos de que sea un número válido

    // Actualizar la cantidad en el inventario
    $stmt = $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?');
    $stmt->execute([$quantity, $product_id]);

    header('Location: entradas.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entradas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Entradas de Productos</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Cantidad Actual</th>
                <th>Añadir Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?= $product['id'] ?></td>
                <td><?= $product['name'] ?></td>
                <td><?= $product['quantity'] ?></td>
                <td>
                    <form method="POST" action="entradas.php">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="number" name="quantity" step="0.01" placeholder="Cantidad a añadir" required>
                        <button type="submit" name="entrada">Añadir</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="index.php">Volver al inventario</a>
</body>
</html>
