<?php
require_once 'config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    requireAdmin();
    $date = $_POST['date'];
    $description = $_POST['description'];
    $stmt = $pdo->prepare("INSERT INTO events (date, description) VALUES (?, ?)");
    $stmt->execute([$date, $description]);
    header('Location: dias.php');
    exit;
}

include 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM events ORDER BY date DESC");
$events = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1>Dias (Eventos)</h1>
        <?php if (isAdmin()): ?>
            <button class="btn btn-small" onclick="openModal('modalAddEvent')">+ Novo Dia</button>
        <?php endif; ?>
    </div>

    <div class="card">
        <?php if (count($events) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><strong><?= date('d/m/Y', strtotime($event['date'])) ?></strong></td>
                                <td><?= htmlspecialchars($event['description']) ?></td>
                                <td>
                                    <a href="dia_detalhes.php?id=<?= $event['id'] ?>" class="action-link">Ver →</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhum dia cadastrado.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (isAdmin()): ?>
<div id="modalAddEvent" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Novo Dia</h2>
            <button class="close-modal" onclick="closeModal('modalAddEvent')">&times;</button>
        </div>
        <form method="POST" action="dias.php">
            <input type="hidden" name="action" value="add_event">
            <div class="form-group">
                <label>Data</label>
                <input type="date" name="date" required>
            </div>
            <div class="form-group">
                <label>Descrição</label>
                <input type="text" name="description" placeholder="Ex: Culto de Domingo" required>
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 8px;">Criar Dia</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
