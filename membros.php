<?php
require_once 'config/database.php';
requireAdmin();

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_user') {
        $name           = $_POST['name'];
        $username       = $_POST['username'];
        $password       = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role           = $_POST['role'];
        $roles_assigned = $_POST['roles'] ?? [];

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)")
                ->execute([$name, $username, $password, $role]);
            $user_id = $pdo->lastInsertId();

            if (!empty($roles_assigned)) {
                $rs = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($roles_assigned as $r_id) $rs->execute([$user_id, $r_id]);
            }
            $pdo->commit();
            header('Location: membros.php?msg=added');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erro ao cadastrar (nome de usuário já existe?).";
        }
    }

    if ($_POST['action'] === 'edit_user') {
        $user_id        = (int)$_POST['user_id'];
        $name           = $_POST['name'];
        $username       = $_POST['username'];
        $role           = $_POST['role'];
        $roles_assigned = $_POST['roles'] ?? [];

        try {
            $pdo->beginTransaction();

            // Update base info
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET name=?, username=?, password_hash=?, role=? WHERE id=?")
                    ->execute([$name, $username, $hash, $role, $user_id]);
            } else {
                $pdo->prepare("UPDATE users SET name=?, username=?, role=? WHERE id=?")
                    ->execute([$name, $username, $role, $user_id]);
            }

            // Replace roles
            $pdo->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$user_id]);
            if (!empty($roles_assigned)) {
                $rs = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($roles_assigned as $r_id) $rs->execute([$user_id, $r_id]);
            }

            $pdo->commit();
            header('Location: membros.php?msg=edited');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erro ao salvar alterações (nome de usuário já existe?).";
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $user_id = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$user_id]);
        header('Location: membros.php?msg=deleted');
        exit;
    }
}

$users     = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();
$all_roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Fetch roles per user
$user_roles     = [];
$user_role_ids  = [];
foreach ($pdo->query("SELECT ur.user_id, ur.role_id, r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id")->fetchAll() as $row) {
    $user_roles[$row['user_id']][]    = $row['name'];
    $user_role_ids[$row['user_id']][] = $row['role_id'];
}

include 'includes/header.php';
?>

<div class="page-content">

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php
        $msgs = ['added' => 'Membro cadastrado!', 'edited' => 'Membro atualizado!', 'deleted' => 'Membro removido!'];
        echo $msgs[$_GET['msg']] ?? '';
        ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

    <div class="page-header">
        <h1>Membros e Funções</h1>
    </div>
    
    <div style="margin-bottom: 20px;">
        <button class="btn" onclick="openModal('modalAddUser')">+ Novo Membro</button>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Nível</th>
                        <th>Funções</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $uroles = $user_roles[$u['id']] ?? []; ?>
                        <?php $urolids = json_encode($user_role_ids[$u['id']] ?? []); ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-primary">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Usuário</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $uroles ? implode(', ', array_map('htmlspecialchars', $uroles)) : '-' ?></td>
                            <td>
                                <div class="song-links">
                                    <button class="link-btn link-btn-edit"
                                        onclick='openEditUser(<?= $u["id"] ?>, <?= json_encode($u["name"]) ?>, <?= json_encode($u["username"]) ?>, <?= json_encode($u["role"]) ?>, <?= $urolids ?>)'>
                                        Editar
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir <?= htmlspecialchars($u['name']) ?>?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="link-btn link-btn-danger">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Adicionar -->
<div id="modalAddUser" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Novo Membro</h2>
            <button class="close-modal" onclick="closeModal('modalAddUser')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Nome de Usuário (Login)</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Nível de Acesso</label>
                <select name="role" required>
                    <option value="user">Usuário Comum</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label>Funções</label>
                <div class="checkbox-list">
                    <?php foreach ($all_roles as $r): ?>
                        <label>
                            <input type="checkbox" name="roles[]" value="<?= $r['id'] ?>">
                            <?= htmlspecialchars($r['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 12px;">Salvar Membro</button>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div id="modalEditUser" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Membro</h2>
            <button class="close-modal" onclick="closeModal('modalEditUser')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="eu_id">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="name" id="eu_name" required>
            </div>
            <div class="form-group">
                <label>Nome de Usuário (Login)</label>
                <input type="text" name="username" id="eu_username" required>
            </div>
            <div class="form-group">
                <label>Nova Senha <span style="color:var(--text-secondary); font-size:0.8rem;">(deixe em branco para não alterar)</span></label>
                <input type="password" name="password" id="eu_password" placeholder="Nova senha...">
            </div>
            <div class="form-group">
                <label>Nível de Acesso</label>
                <select name="role" id="eu_role" required>
                    <option value="user">Usuário Comum</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label>Funções</label>
                <div class="checkbox-list" id="eu_roles">
                    <?php foreach ($all_roles as $r): ?>
                        <label>
                            <input type="checkbox" class="eu_role_check" name="roles[]" value="<?= $r['id'] ?>">
                            <?= htmlspecialchars($r['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 12px;">Salvar Alterações</button>
        </form>
    </div>
</div>

<script>
function openEditUser(id, name, username, role, roleIds) {
    document.getElementById('eu_id').value       = id;
    document.getElementById('eu_name').value     = name;
    document.getElementById('eu_username').value = username;
    document.getElementById('eu_role').value     = role;
    document.getElementById('eu_password').value = '';

    // Reset and check current role checkboxes
    document.querySelectorAll('.eu_role_check').forEach(function(cb) {
        cb.checked = roleIds.indexOf(parseInt(cb.value)) !== -1;
    });

    openModal('modalEditUser');
}
</script>

<?php include 'includes/footer.php'; ?>
