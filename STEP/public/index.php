<?php
session_start();
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STEP - Task Management</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <main class="container index-container-wrapper">

        <div class="card index-hero-card">
            
            <!-- Čistý obal iba s reálnym logom -->
            <div class="site-logo">
                <a href="index.php" class="logo-link">
                    <img src="logo.png" alt="STEP Logo" class="custom-logo">
                </a>
            </div>

            <span class="index-badge">Tímový projekt & Task Manager</span>

            <p class="index-lead">
                Moderná aplikácia na efektívnu správu projektov a úloh. 
                Vytvárajte tímy, prideľujte zodpovednosti a sledujte priebeh práce v reálnom čase.
            </p>

            <div class="index-actions-grid">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn">Prejsť na Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn">Prihlásenie</a>
                    <a href="register.php" class="btn btn-secondary">Registrácia</a>
                <?php endif; ?>
                <a href="teams.php" class="btn btn-secondary">Zoznam tímov</a>
            </div>

            <div class="index-info-box">
                <p>
                    <strong>💡 Užitočná info:</strong> Pri registrácii je vám automaticky priradená rola študenta. Rolu učiteľa priraďuje administrátor systému.
                </p>
            </div>
        </div>

    </main>

    <footer class="site-footer">
        STEP – Tímový projekt &copy; <?php echo date('Y'); ?>
    </footer>

</body>

</html>