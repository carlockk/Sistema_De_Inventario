<?php
session_start();
include 'config.php';

// Función para verificar si el usuario es un administrador con todos los privilegios
function check_super_admin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        header('Location: index.php');
        exit;
    }
}

// Descomentar esta función después de crear el primer usuario
check_super_admin(); // Solo super administradores pueden acceder

// Manejo de creación de nuevos usuarios
if (isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role']; // 'admin' o 'limited_admin'

    // Verifica si el nombre de usuario ya existe
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $error = "El nombre de usuario ya existe.";
    } else {
        // Inserta el nuevo usuario
        $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        $stmt->execute([$username, $password, $role]);
        $success = "Usuario creado exitosamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Administrar Usuarios</title>
</head>
<body>
    <h1>Crear Nuevo Usuario Administrador</h1>
    <a href="index.php">Volver al inventario</a>

    <?php if (isset($success)): ?>
        <p style="color: green;"><?= $success ?></p>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?= $error ?></p>
    <?php endif; ?>

    <form method="post" action="admin_users.php">
        <label>Nombre de usuario:</label>
        <input type="text" name="username" required><br>

        <label>Contraseña:</label>
        <input type="password" name="password" required><br>

        <label>Rol:</label>
        <select name="role" required>
            <option value="limited_admin">Admin (crear, sin editar/borrar)</option>
            <option value="admin">Admin (todos los privilegios)</option>
        </select><br>

        <button type="submit" name="create_user">Crear Usuario</button>
    </form>
</body>
</html>
