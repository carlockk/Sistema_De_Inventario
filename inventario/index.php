<?php
session_start();
include 'config.php';

// Función para verificar si el usuario está logueado
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?action=login');
        exit;
    }
}

// Manejo del login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

// Manejo del logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Agregar o editar productos
if (isset($_POST['save_product'])) {
    check_login();
    $name = $_POST['name'];
    $quantity = floatval($_POST['quantity']); // Convertir a float para evitar errores con decimales
    $unit = $_POST['unit'];
    $unit_price = floatval($_POST['unit_price']); // Convertir a float para evitar errores con decimales
    $observation = $_POST['observation'];
    $image_path = null;

    // Manejo de la subida de imágenes
    if (!empty($_FILES['image']['tmp_name'])) {
        // Validar el tipo de archivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['image']['type'], $allowed_types)) {
            // Renombrar el archivo para evitar conflictos
            $image_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid() . '.' . $image_extension;
            $image_path = 'uploads/' . $image_name;
            move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
        } else {
            $error = 'Tipo de archivo no permitido. Solo se permiten JPEG, PNG y GIF.';
        }
    }

    if (!isset($error)) {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Editar producto existente
            $stmt = $pdo->prepare('UPDATE products SET name = ?, quantity = ?, unit = ?, unit_price = ?, observation = ?, image_path = ? WHERE id = ?');
            $stmt->execute([$name, $quantity, $unit, $unit_price, $observation, $image_path, $_POST['id']]);
        } else {
            // Agregar nuevo producto
            $stmt = $pdo->prepare('INSERT INTO products (name, quantity, unit, unit_price, observation, image_path) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $quantity, $unit, $unit_price, $observation, $image_path]);
        }

        header('Location: index.php');
        exit;
    }
}

// Eliminar producto
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_login();
    // Obtener la imagen del producto para eliminar el archivo
    $stmt = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch();

    // Eliminar la imagen del servidor si existe
    if ($product && $product['image_path'] && file_exists($product['image_path'])) {
        unlink($product['image_path']);
    }

    // Eliminar el producto de la base de datos
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$_GET['id']]);
    header('Location: index.php');
    exit;
}

// Mostrar la interfaz de login si el usuario no está autenticado
if (isset($_GET['action']) && $_GET['action'] === 'login') {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <img src="assets/logo.png" alt="Logo"> <!-- Reemplaza 'assets/logo.png' con la ruta de tu imagen -->
    </header>
    <div class="container">
        <form method="post" action="?action=login">
            <h2>Iniciar Sesión</h2>
            <label>Usuario:</label>
            <input type="text" name="username" required>
            <label>Contraseña:</label>
            <input type="password" name="password" required>
            <button type="submit" name="login">Login</button>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// Mostrar el inventario solo si está logueado
check_login();

// Manejo de paginación
$limit = 30;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Obtener el total de productos
$stmt_total = $pdo->prepare('SELECT COUNT(*) as total FROM products');
$stmt_total->execute();
$total_products = $stmt_total->fetch()['total'];
$total_pages = ceil($total_products / $limit);

// Si la página solicitada excede el total de páginas, redirigir a la última página
if ($page > $total_pages && $total_pages > 0) {
    header("Location: ?page=$total_pages");
    exit;
}

$offset = ($page - 1) * $limit;

// Obtener los productos para la página actual
$stmt = $pdo->prepare('SELECT * FROM products LIMIT ? OFFSET ?');
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Si el usuario está editando un producto
$editing_product = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$_GET['id']]);
    $editing_product = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const viewListButton = document.getElementById('viewListButton');
            const addProductButton = document.getElementById('addProductButton');
            const productListSection = document.getElementById('productListSection');
            const addProductSection = document.getElementById('addProductSection');

            // Función para mostrar la lista de productos
            function showProductList() {
                productListSection.classList.add('active');
                addProductSection.classList.remove('active');
            }

            // Función para mostrar el formulario de agregar/editar producto
            function showAddProductForm() {
                productListSection.classList.remove('active');
                addProductSection.classList.add('active');
            }

            // Mostrar la lista de productos por defecto
            showProductList();

            viewListButton.addEventListener('click', function() {
                showProductList();
            });

            addProductButton.addEventListener('click', function() {
                showAddProductForm();
            });

            // Si hay una acción de edición, mostrar el formulario automáticamente
            <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
                showAddProductForm();
                // Opcional: desplazarse al formulario
                window.scrollTo({
                    top: addProductSection.offsetTop,
                    behavior: 'smooth'
                });
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <!-- Header -->
    <header>
        <img src="assets/logo.png" alt="Logo"> <!-- Reemplaza 'assets/logo.png' con la ruta de tu imagen -->
    </header>

    <!-- Navigation Menu -->
    <nav>
        <ul>
            <li><button id="viewListButton">Ver lista de insumos y mercaderías</button></li>
            <li><button id="addProductButton">Agregar productos</button></li>
             <li><a href="entradas.php">Agregar entradas</a></li>
             <li><a href="salidas.php">Agregar salidas</a></li>
            <li><a href="?action=logout" style="background-color: #000; color: white; padding: 10px 15px; border-radius: 4px;">Cerrar sesión</a></li>
        </ul>
    </nav>
    

    <div class="container">
        <!-- Sección de Lista de Productos -->
        <div id="productListSection" class="content-section">
            <h2>Lista de insumos y mercaderías</h2>
            <!-- Tabla de productos -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Cantidad</th>
                        <th>Unidad</th>
                        <th>Valor por Unidad</th>
                        <th>Valor Total</th>
                        <th>Observación</th>
                        <th>Imagen</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products): ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['id']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['quantity']) ?></td>
                            <td><?= htmlspecialchars($product['unit']) ?></td>
                            <td>$<?= number_format($product['unit_price'], 2) ?></td>
                            <td>$<?= number_format($product['quantity'] * $product['unit_price'], 2) ?></td>
                            <td><?= htmlspecialchars($product['observation']) ?></td>
                            <td>
                                <?php if ($product['image_path']): ?>
                                    <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="Imagen del producto">
                                <?php else: ?>
                                    No disponible
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?= $product['id'] ?>">Editar</a>
                                <a href="?action=delete&id=<?= $product['id'] ?>" onclick="return confirm('¿Estás seguro de eliminar este producto?');">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No hay productos disponibles.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">Anterior</a>
                <?php endif; ?>
                <span>Página <?= $page ?> de <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Siguiente</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección de Agregar/Editar Producto -->
        <div id="addProductSection" class="content-section">
            <form method="post" action="index.php" enctype="multipart/form-data">
                <h2><?= isset($editing_product) ? 'Editar Producto' : 'Agregar Producto' ?></h2>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editing_product['id'] ?? '') ?>">

                <label>Nombre del Producto:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($editing_product['name'] ?? '') ?>" required>

                <label>Cantidad:</label>
                <input type="number" name="quantity" step="any" value="<?= htmlspecialchars($editing_product['quantity'] ?? '') ?>" required>

                <label>Unidad:</label>
                <select name="unit" required>
                    <option value="litros" <?= isset($editing_product['unit']) && $editing_product['unit'] == 'litros' ? 'selected' : '' ?>>Litros</option>
                    <option value="kilos" <?= isset($editing_product['unit']) && $editing_product['unit'] == 'kilos' ? 'selected' : '' ?>>Kilos</option>
                    <option value="unidades" <?= isset($editing_product['unit']) && $editing_product['unit'] == 'unidades' ? 'selected' : '' ?>>Unidades</option>
                    <option value="pack" <?= isset($editing_product['unit']) && $editing_product['unit'] == 'pack' ? 'selected' : '' ?>>Pack</option>
                    <option value="bolsa" <?= isset($editing_product['unit']) && $editing_product['unit'] == 'bolsa' ? 'selected' : '' ?>>Bolsa</option>
                </select>

                <label>Precio por Unidad:</label>
                <input type="number" name="unit_price" step="any" value="<?= htmlspecialchars($editing_product['unit_price'] ?? '') ?>">

                <label>Observación:</label>
                <textarea name="observation"><?= htmlspecialchars($editing_product['observation'] ?? '') ?></textarea>

                <label>Imagen (opcional):</label>
                <input type="file" name="image">

                <button type="submit" name="save_product">Guardar</button>
                <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            </form>
        </div>
    </div>
</body>
</html>
