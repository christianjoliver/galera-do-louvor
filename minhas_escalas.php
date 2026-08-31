<?php
require_once 'config/database.php';
requireLogin();
include 'includes/header.php';

// Fetch events where this user is scheduled
$stmt = $pdo->prepare("
    SELECT e.id, e.date, e.description, r.name as role_name 
    FROM events e
    JOIN event_user_roles eur ON e.id = eur.event_id
    JOIN roles r ON r.id = eur.role_id
    WHERE eur.user_id = ?
    ORDER BY e.date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$escalas = $stmt->fetchAll();

// Separate upcoming and past events
$today = date('Y-m-d');
$upcoming = array_filter($escalas, fn($e) => $e['date'] >= $today);
$past = array_filter($escalas, fn($e) => $e['date'] < $today);
?>

<div class="flex-between mb-4">
    <h1>Minhas Escalas</h1>
</div>

<div class="card mb-4">
    <h2 style="color: var(--primary-color); margin-bottom: 16px;">Próximas Escalas</h2>
    <?php if (count($upcoming) > 0): ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Minha Função</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $esc): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($esc['date'])) ?></strong></td>
                            <td><?= htmlspecialchars($esc['description']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($esc['role_name']) ?></span></td>
                            <td>
                                <a href="dia_detalhes.php?id=<?= $esc['id'] ?>" class="btn-secondary btn-small">Ver Repertório</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color: var(--text-secondary);">Você não está escalado para nenhum evento futuro.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="color: var(--text-secondary); margin-bottom: 16px;">Histórico (Eventos Passados)</h2>
    <?php if (count($past) > 0): ?>
        <div class="table-container">
            <table class="data-table" style="opacity: 0.7;">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Função</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($past as $esc): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($esc['date'])) ?></td>
                            <td><?= htmlspecialchars($esc['description']) ?></td>
                            <td><?= htmlspecialchars($esc['role_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color: var(--text-secondary);">Nenhum histórico encontrado.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
