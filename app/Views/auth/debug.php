<?php
echo "<h1>Debug Login</h1>";
echo "<p>AuthController funcionando</p>";
echo "<p>Variables disponibles:</p>";
echo "<pre>";
var_dump(get_defined_vars());
echo "</pre>";
?>
<html>
<body>
    <h2>Formulario de Login Simple</h2>
    <form method="POST">
        <p>Usuario: <input type="text" name="username"></p>
        <p>Password: <input type="password" name="password"></p>
        <p><input type="submit" value="Login"></p>
    </form>
</body>
</html>
