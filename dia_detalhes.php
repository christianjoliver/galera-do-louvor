<?php
require_once 'config/database.php';
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: dias.php');
    exit;
}
$event_id = (int)$_GET['id'];

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die("Evento não encontrado.");
}
$event_date = $event['date'];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireAdmin(); // Only admin can modify details and generate schedule
    
    if ($_POST['action'] === 'add_song_to_event') {
        $song_id = $_POST['song_id'];
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO event_songs (event_id, song_id) VALUES (?, ?)");
        $stmt->execute([$event_id, $song_id]);
    }
    
    if ($_POST['action'] === 'remove_song_from_event') {
        $song_id = $_POST['song_id'];
        $stmt = $pdo->prepare("DELETE FROM event_songs WHERE event_id = ? AND song_id = ?");
        $stmt->execute([$event_id, $song_id]);
    }

    if ($_POST['action'] === 'add_role') {
        $role_id = $_POST['role_id'];
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO event_user_roles (event_id, role_id, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$event_id, $role_id, $user_id]);
    }

    if ($_POST['action'] === 'remove_role') {
        $role_id = $_POST['role_id'];
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM event_user_roles WHERE event_id = ? AND role_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $role_id, $user_id]);
    }

    if ($_POST['action'] === 'auto_schedule') {
        // Auto-schedule logic
        // 1. Clear current schedule for this event
        $pdo->prepare("DELETE FROM event_user_roles WHERE event_id = ?")->execute([$event_id]);
        
        // 2. Determine required roles. For now, assume we want 1 of each role, or user specifies required. 
        // Let's just try to fill all default roles once.
        $roles_stmt = $pdo->query("SELECT * FROM roles");
        $all_roles = $roles_stmt->fetchAll();

        // Track who is already assigned so we don't assign the same person twice if possible
        $assigned_user_ids = [];

        foreach ($all_roles as $r) {
            $role_id = $r['id'];
            
            // Find users who have this role AND are not absent on $event_date
            $available_stmt = $pdo->prepare("
                SELECT u.id FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                WHERE ur.role_id = ? 
                AND u.id NOT IN (
                    SELECT user_id FROM absences WHERE date = ?
                )
            ");
            $available_stmt->execute([$role_id, $event_date]);
            $candidates = $available_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $fresh_candidates = array_diff($candidates, $assigned_user_ids);
            
            if (!empty($fresh_candidates)) {
                $chosen = $fresh_candidates[array_rand($fresh_candidates)];
            } else {
                $chosen = null;
            }

            if ($chosen) {
                $pdo->prepare("INSERT INTO event_user_roles (event_id, role_id, user_id) VALUES (?, ?, ?)")->execute([$event_id, $role_id, $chosen]);
                $assigned_user_ids[] = $chosen;
            }
        }
    }
    
    header("Location: dia_detalhes.php?id=$event_id");
    exit;
}

// Fetch Songs in Event
$stmt = $pdo->prepare("
    SELECT s.* FROM songs s
    JOIN event_songs es ON s.id = es.song_id
    WHERE es.event_id = ?
");
$stmt->execute([$event_id]);
$event_songs = $stmt->fetchAll();

$all_songs = $pdo->query("SELECT * FROM songs ORDER BY title ASC")->fetchAll();

// Fetch Roles in Event
$stmt = $pdo->prepare("
    SELECT r.name, eur.role_id, u.name as member_name, u.id as user_id
    FROM event_user_roles eur
    JOIN roles r ON r.id = eur.role_id
    JOIN users u ON u.id = eur.user_id
    WHERE eur.event_id = ?
    ORDER BY r.id ASC
");
$stmt->execute([$event_id]);
$event_roles = $stmt->fetchAll();

$all_roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$all_users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="mb-4">
    <a href="dias.php" class="action-link">&larr; Voltar para Dias</a>
    <h1 class="mt-4">Detalhes do Dia: <?= date('d/m/Y', strtotime($event['date'])) ?></h1>
    <p style="color: var(--text-secondary);"><?= htmlspecialchars($event['description']) ?></p>
</div>

<div class="responsive-grid">
    <!-- Repertório Column -->
    <div>
        <div class="card">
            <h2 style="color: var(--primary-color); margin-bottom: 14px;">Repertório do Dia</h2>
            <?php if (isAdmin()): ?>
                <div style="margin-bottom: 16px;">
                    <button class="btn btn-small btn-block" onclick="openModal('modalAddSongEvent')">+ Adicionar Música</button>
                </div>
            <?php endif; ?>
            
            <?php if (count($event_songs) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($event_songs as $song): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($song['title']) ?></strong> (<?= $song['type'] === 'cantico' ? 'Cântico' : 'Hino' ?>)
                                    </td>
                                    <td>
                                        <div class="song-actions">
                                            <?php if ($song['chord_url']): ?>
                                                <a href="<?= htmlspecialchars($song['chord_url']) ?>" target="_blank" class="link-btn">Cifra</a>
                                            <?php endif; ?>
                                            <?php if ($song['video_url']): ?>
                                                <a href="<?= htmlspecialchars($song['video_url']) ?>" target="_blank" class="link-btn">Vídeo</a>
                                            <?php endif; ?>
                                            <?php if (isAdmin()): ?>
                                                <form method="POST" style="margin: 0; width: 100%;">
                                                    <input type="hidden" name="action" value="remove_song_from_event">
                                                    <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                    <button type="submit" class="link-btn link-btn-danger" style="width: 100%;">Remover</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-secondary);">Nenhuma música adicionada a este dia.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Equipe Column -->
    <div>
        <div class="card">
            <h2 style="color: var(--primary-color); margin-bottom: 14px;">Equipe do Dia</h2>
            <?php if (isAdmin()): ?>
                <div class="btn-actions" style="margin-bottom: 16px;">
                    <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Reescrever a escala com sorteio automático. Continuar?');">
                        <input type="hidden" name="action" value="auto_schedule">
                        <button type="submit" class="btn btn-blue btn-small btn-block">Gerar Escala Automática</button>
                    </form>
                    <button class="btn btn-small" style="flex: 1;" onclick="openModal('modalAddRole')">+ Adicionar Membro</button>
                </div>
            <?php endif; ?>
            
            <?php if (count($event_roles) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Função</th>
                                <th>Membro</th>
                                <?php if (isAdmin()): ?>
                                    <th>Ação</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($event_roles as $role): ?>
                                <tr>
                                    <td><?= htmlspecialchars($role['name']) ?></td>
                                    <td><?= htmlspecialchars($role['member_name']) ?></td>
                                    <?php if (isAdmin()): ?>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="remove_role">
                                                <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $role['user_id'] ?>">
                                                <button type="submit" class="btn-secondary btn-small" style="color: red; border-color: red; padding: 2px 8px;">X</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-secondary);">Nenhum membro escalado.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Modal Add Song to Event -->
<div id="modalAddSongEvent" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('modalAddSongEvent')">&times;</span>
        <h2 class="mb-4">Adicionar Música ao Dia</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_song_to_event">
            <div class="form-group">
                <label>Selecione a Música</label>
                <select name="song_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($all_songs as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?> (<?= $s['type'] === 'cantico' ? 'Cântico' : 'Hino' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn w-100" style="width: 100%; margin-top: 16px;">Adicionar ao Repertório</button>
        </form>
    </div>
</div>

<!-- Modal Add Role -->
<div id="modalAddRole" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('modalAddRole')">&times;</span>
        <h2 class="mb-4">Escalar Membro Manualmente</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_role">
            <div class="form-group">
                <label>Função</label>
                <select name="role_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($all_roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Membro</label>
                <select name="user_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn w-100" style="width: 100%; margin-top: 16px;">Escalar Membro</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
