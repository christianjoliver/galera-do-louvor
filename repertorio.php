<?php
require_once 'config/database.php';
requireLogin();

// Add song
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_song') {
        $stmt = $pdo->prepare("INSERT INTO songs (title, type, tone, video_url, chord_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['type'], $_POST['tone'], $_POST['video_url'], $_POST['chord_url']]);
        header('Location: repertorio.php?msg=added');
        exit;
    }

    if ($_POST['action'] === 'edit_song') {
        $stmt = $pdo->prepare("UPDATE songs SET title=?, type=?, tone=?, video_url=?, chord_url=? WHERE id=?");
        $stmt->execute([$_POST['title'], $_POST['type'], $_POST['tone'], $_POST['video_url'], $_POST['chord_url'], $_POST['song_id']]);
        header('Location: repertorio.php?msg=edited');
        exit;
    }

    if ($_POST['action'] === 'delete_song') {
        $stmt = $pdo->prepare("DELETE FROM songs WHERE id=?");
        $stmt->execute([$_POST['song_id']]);
        header('Location: repertorio.php?msg=deleted');
        exit;
    }
}

include 'includes/header.php';

$songs    = $pdo->query("SELECT * FROM songs ORDER BY title ASC")->fetchAll();
$hinos    = array_filter($songs, fn($s) => $s['type'] === 'hino');
$canticos = array_filter($songs, fn($s) => $s['type'] === 'cantico');

function songList($list, $isAdmin) {
    if (count($list) === 0) {
        echo '<p class="text-muted">Nenhuma música cadastrada.</p>';
        return;
    }
    echo '<div class="song-list">';
    foreach ($list as $song) {
        $id        = $song['id'];
        $title     = htmlspecialchars($song['title']);
        $typeLabel = $song['type'] === 'cantico' ? 'Cântico' : 'Hino';
        $chord     = htmlspecialchars($song['chord_url'] ?? '');
        $video     = htmlspecialchars($song['video_url'] ?? '');

        echo '<div class="song-item">';
        echo '<div class="song-title-group">';
        echo '<span class="song-title">' . $title . '</span>';
        if ($song['tone']) echo '<span class="song-tone">Tom: ' . htmlspecialchars($song['tone']) . '</span>';
        echo '</div>';
        echo '<div class="song-links">';
        if ($song['chord_url']) echo '<a href="' . $chord . '" target="_blank" class="link-btn">Cifra</a>';
        if ($song['video_url']) echo '<a href="' . $video . '" target="_blank" class="link-btn">Vídeo</a>';
        if ($isAdmin) {
            echo '<button class="link-btn link-btn-edit" onclick=\'openEditModal(' . $id . ',' . json_encode($song['title']) . ',' . json_encode($song['type']) . ',' . json_encode($song['tone']) . ',' . json_encode($song['chord_url']) . ',' . json_encode($song['video_url']) . ')\'>Editar</button>';
            echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Excluir ' . $title . '?\'">';
            echo '<input type="hidden" name="action" value="delete_song">';
            echo '<input type="hidden" name="song_id" value="' . $id . '">';
            echo '<button type="submit" class="link-btn link-btn-danger">Remover</button>';
            echo '</form>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}
?>

<div class="page-content">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php
            $msgs = ['added' => 'Música adicionada!', 'edited' => 'Música atualizada!', 'deleted' => 'Música removida!'];
            echo $msgs[$_GET['msg']] ?? '';
            ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Repertório</h1>
        <button class="btn btn-small" onclick="openModal('modalAddSong')">+ Nova Música</button>
    </div>

    <div class="card">
        <h2 style="color: var(--primary-color); margin-bottom: 16px;">Hinos</h2>
        <?php songList($hinos, isAdmin()); ?>
    </div>

    <div class="card">
        <h2 style="color: var(--primary-color); margin-bottom: 16px;">Cânticos</h2>
        <?php songList($canticos, isAdmin()); ?>
    </div>
</div>

<!-- Modal Adicionar -->
<div id="modalAddSong" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nova Música</h2>
            <button class="close-modal" onclick="closeModal('modalAddSong')">&times;</button>
        </div>
        <form method="POST" action="repertorio.php">
            <input type="hidden" name="action" value="add_song">
            <div class="form-group">
                <label>Título</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select name="type" required>
                    <option value="hino">Hino</option>
                    <option value="cantico">Cântico</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tom (Ex: D, Em, G#)</label>
                <input type="text" name="tone" placeholder="Ex: C, D, Em, F#..." maxlength="10">
            </div>
            <div class="form-group">
                <label>Link da Cifra</label>
                <input type="url" name="chord_url" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Link do Vídeo</label>
                <input type="url" name="video_url" placeholder="https://youtube.com/...">
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 8px;">Salvar</button>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div id="modalEditSong" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Música</h2>
            <button class="close-modal" onclick="closeModal('modalEditSong')">&times;</button>
        </div>
        <form method="POST" action="repertorio.php">
            <input type="hidden" name="action" value="edit_song">
            <input type="hidden" name="song_id" id="edit_song_id">
            <div class="form-group">
                <label>Título</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select name="type" id="edit_type" required>
                    <option value="hino">Hino</option>
                    <option value="cantico">Cântico</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tom (Ex: D, Em, G#)</label>
                <input type="text" name="tone" id="edit_tone" placeholder="Ex: C, D, Em, F#..." maxlength="10">
            </div>
            <div class="form-group">
                <label>Link da Cifra</label>
                <input type="url" name="chord_url" id="edit_chord_url" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Link do Vídeo</label>
                <input type="url" name="video_url" id="edit_video_url" placeholder="https://youtube.com/...">
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 8px;">Salvar Alterações</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, title, type, tone, chord, video) {
    document.getElementById('edit_song_id').value   = id;
    document.getElementById('edit_title').value     = title;
    document.getElementById('edit_type').value      = type;
    document.getElementById('edit_tone').value      = tone || '';
    document.getElementById('edit_chord_url').value = chord || '';
    document.getElementById('edit_video_url').value = video || '';
    openModal('modalEditSong');
}
</script>

<?php include 'includes/footer.php'; ?>
