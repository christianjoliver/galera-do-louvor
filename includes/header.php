<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministério de Louvor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <!-- Overlay to close sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="logo">LouvorApp</span>
            <button class="sidebar-close" onclick="closeSidebar()" aria-label="Fechar menu">&times;</button>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="repertorio.php">Repertório</a></li>
                <li><a href="dias.php">Dias (Eventos)</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="membros.php">Membros</a></li>
                <?php endif; ?>
                <li><a href="minhas_escalas.php">Minhas Escalas</a></li>
                <li><a href="minhas_ausencias.php">Minhas Ausências</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <span class="sidebar-user"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
            <a href="logout.php" class="btn btn-small btn-secondary">Sair</a>
        </div>
    </aside>

    <!-- Top Bar -->
    <div class="topbar">
        <button class="hamburger" id="hamburgerBtn" onclick="openSidebar()" aria-label="Abrir menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <span class="topbar-title">LouvorApp</span>
    </div>

    <!-- Page Content -->
    <main class="main-content">
