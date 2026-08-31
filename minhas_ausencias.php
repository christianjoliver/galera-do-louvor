<?php
require_once 'config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_absence') {
        $date   = $_POST['date'];
        $reason = $_POST['reason'];
        $stmt = $pdo->prepare("INSERT INTO absences (user_id, date, reason) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $date, $reason]);
        header('Location: minhas_ausencias.php');
        exit;
    }
    if ($_POST['action'] === 'delete_absence') {
        $absence_id = $_POST['absence_id'];
        $stmt = $pdo->prepare("DELETE FROM absences WHERE id = ? AND user_id = ?");
        $stmt->execute([$absence_id, $_SESSION['user_id']]);
        header('Location: minhas_ausencias.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM absences WHERE user_id = ? ORDER BY date ASC");
$stmt->execute([$_SESSION['user_id']]);
$absences = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1>Minhas Ausências</h1>
        <button class="btn btn-small" onclick="openModal('modalAddAbsence')">+ Informar Ausência</button>
    </div>

    <div class="card">
        <p class="text-muted" style="margin-bottom: 16px;">
            Informe os dias que você não estará disponível. O sistema de escala automática irá ignorar seu nome nessas datas.
        </p>

        <?php if (count($absences) > 0): ?>
            <div class="absence-list">
                <?php foreach ($absences as $abs): ?>
                    <div class="absence-item">
                        <div class="absence-info">
                            <strong><?= date('d/m/Y', strtotime($abs['date'])) ?></strong>
                            <?php if ($abs['reason']): ?>
                                <span class="text-muted"><?= htmlspecialchars($abs['reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete_absence">
                            <input type="hidden" name="absence_id" value="<?= $abs['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Remover</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Você não possui ausências informadas.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="modalAddAbsence" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Informar Ausência</h2>
            <button class="close-modal" onclick="closeModal('modalAddAbsence')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_absence">
            <div class="form-group">
                <label>Data</label>
                <input type="date" name="date" required>
            </div>
            <div class="form-group">
                <label>Motivo (Opcional)</label>
                <input type="text" name="reason" placeholder="Ex: Viagem, Trabalho...">
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 8px;">Salvar</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
