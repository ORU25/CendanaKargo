<?php
    $title = "Home - Cendana Kargo";
    include 'config/database.php';
    include 'templates/header.php';
?>
<?php if(isset($_GET['error']) && $_GET['error'] == 'unauthorized'){
    $type = "danger";
    $message = "You are not authorized to access that page. Please log in with appropriate credentials.";
    include 'components/alert.php';
}?>

<div class="justify-content-center text-center mt-5">
    <h1 class="">Welcome to Cendana Kargo</h1>
    <button><a href="auth/login">Login Admin</a></button>
</div>

<?php
    include 'templates/footer.php';
?>