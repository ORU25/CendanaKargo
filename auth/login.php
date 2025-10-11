<?php 
 include '../config/database.php';
?>

<?php
   include '../templates/header.php';
?>

<div class="justify-content-center">
    <h2>Login</h2>
    <form action="" method="post" class="">
        <label for="username">Username: </label>
        <input type="text" name="username" id="username" required>
        <br>
        <label for="password">Password: </label>
        <input type="password" name="password" id="password" required>
        <br>
        <button type="submit">Login</button>
    </form>
</div>

<?php
    include '../templates/footer.php';
?>