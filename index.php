<?php
require_once 'config/database.php';
requireLogin();
include 'includes/header.php';

// Fetch upcoming events (Dias)
$stmt = $pdo->query("SELECT * FROM events ORDER BY date ASC");
$events = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1>Próximos Dias</h1>
        <a href="dias.php" class="btn btn-small">Gerenciar Dias</a>
    </div>

    <div class="card">
        <?php if (count($events) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($event['date'])) ?></td>
                                <td><?= htmlspecialchars($event['description']) ?></td>
                                <td>
                                    <a href="dia_detalhes.php?id=<?= $event['id'] ?>" class="action-link">Ver Escala</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhum dia cadastrado ainda.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
