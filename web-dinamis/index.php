<?php
/**
 * index.php — Sistem Manajemen Data Mahasiswa
 * CRUD lengkap menggunakan PHP Native + PDO + MariaDB
 *
 * Routes (GET ?action=):
 *   (default) → list semua mahasiswa
 *   create     → form tambah mahasiswa
 *   edit       → form edit mahasiswa (?id=N)
 *   delete     → hapus mahasiswa (?id=N) [POST]
 *   login      → halaman login
 *   logout     → proses logout
 *
 * Routes (POST ?action=):
 *   store      → simpan mahasiswa baru
 *   update     → update data mahasiswa
 */

session_start();
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

// ─────────────────────────────────────────────────────────────
// AUTH: Proteksi semua route kecuali login
// ─────────────────────────────────────────────────────────────
$publicActions = ['login', 'do_login'];

if (!isset($_SESSION['user_id']) && !in_array($action, $publicActions)) {
    redirect('?action=login');
}

// ─────────────────────────────────────────────────────────────
// ROUTING — Handle semua aksi
// ─────────────────────────────────────────────────────────────

/** LOGIN — tampilkan form */
if ($action === 'login') {
    $error = $_SESSION['flash']['message'] ?? null;
    unset($_SESSION['flash']);
    renderLogin($error);
    exit;
}

/** LOGIN — proses POST */
if ($action === 'do_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        redirect('?action=login', 'Username dan password wajib diisi.', 'error');
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, username, password, nama, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama']      = $user['nama'];
            $_SESSION['role']      = $user['role'];
            redirect('?action=list', 'Selamat datang, ' . $user['nama'] . '!');
        } else {
            redirect('?action=login', 'Username atau password salah.', 'error');
        }
    } catch (PDOException $e) {
        redirect('?action=login', 'Error koneksi database.', 'error');
    }
    exit;
}

/** LOGOUT */
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

/** LIST — Tampilkan semua mahasiswa dengan search & pagination */
if ($action === 'list') {
    $search   = trim($_GET['search'] ?? '');
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = 8;
    $offset   = ($page - 1) * $perPage;

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    try {
        $db = getDB();

        $whereClause = $search
            ? "WHERE nim LIKE :search OR nama LIKE :search OR jurusan LIKE :search"
            : "";

        // Hitung total untuk pagination
        $countStmt = $db->prepare("SELECT COUNT(*) FROM mahasiswa $whereClause");
        if ($search) $countStmt->bindValue(':search', "%$search%");
        $countStmt->execute();
        $total     = (int)$countStmt->fetchColumn();
        $totalPage = (int)ceil($total / $perPage);

        // Ambil data halaman ini
        $stmt = $db->prepare(
            "SELECT * FROM mahasiswa $whereClause ORDER BY angkatan DESC, nama ASC LIMIT :limit OFFSET :offset"
        );
        if ($search) $stmt->bindValue(':search', "%$search%");
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $rows = []; $total = 0; $totalPage = 1;
    }

    renderList($rows, $total, $page, $totalPage, $search, $flash);
    exit;
}

/** CREATE — tampilkan form tambah */
if ($action === 'create') {
    $errors = $_SESSION['form_errors'] ?? [];
    $old    = $_SESSION['form_old']    ?? [];
    unset($_SESSION['form_errors'], $_SESSION['form_old']);
    renderForm('create', [], $errors, $old);
    exit;
}

/** STORE — simpan mahasiswa baru (POST) */
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = sanitizeInput($_POST);
    $errors = validateMahasiswa($data);

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_old']    = $data;
        redirect('?action=create');
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO mahasiswa (nim, nama, jurusan, angkatan, email, no_telp, ipk)
            VALUES (:nim, :nama, :jurusan, :angkatan, :email, :no_telp, :ipk)
        ");
        $stmt->execute([
            ':nim'      => $data['nim'],
            ':nama'     => $data['nama'],
            ':jurusan'  => $data['jurusan'],
            ':angkatan' => $data['angkatan'],
            ':email'    => $data['email']   ?: null,
            ':no_telp'  => $data['no_telp'] ?: null,
            ':ipk'      => $data['ipk']     ?: null,
        ]);
        redirect('?action=list', 'Mahasiswa berhasil ditambahkan.');
    } catch (PDOException $e) {
        // Duplicate entry
        if ($e->getCode() === '23000') {
            $_SESSION['form_errors'] = ['nim' => 'NIM sudah terdaftar.'];
            $_SESSION['form_old']    = $data;
            redirect('?action=create');
        }
        redirect('?action=list', 'Gagal menyimpan data: ' . $e->getMessage(), 'error');
    }
    exit;
}

/** EDIT — tampilkan form edit */
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) redirect('?action=list', 'ID tidak valid.', 'error');

    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM mahasiswa WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) redirect('?action=list', 'Data tidak ditemukan.', 'error');
    } catch (PDOException $e) {
        redirect('?action=list', 'Error database.', 'error');
    }

    $errors = $_SESSION['form_errors'] ?? [];
    $old    = $_SESSION['form_old']    ?? $row;
    unset($_SESSION['form_errors'], $_SESSION['form_old']);
    renderForm('edit', $row, $errors, $old);
    exit;
}

/** UPDATE — proses edit (POST) */
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $data   = sanitizeInput($_POST);
    $errors = validateMahasiswa($data, $id);

    if (!$id) redirect('?action=list', 'ID tidak valid.', 'error');

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_old']    = $data;
        redirect("?action=edit&id=$id");
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            UPDATE mahasiswa
            SET nim=:nim, nama=:nama, jurusan=:jurusan, angkatan=:angkatan,
                email=:email, no_telp=:no_telp, ipk=:ipk
            WHERE id=:id
        ");
        $stmt->execute([
            ':id'       => $id,
            ':nim'      => $data['nim'],
            ':nama'     => $data['nama'],
            ':jurusan'  => $data['jurusan'],
            ':angkatan' => $data['angkatan'],
            ':email'    => $data['email']   ?: null,
            ':no_telp'  => $data['no_telp'] ?: null,
            ':ipk'      => $data['ipk']     ?: null,
        ]);
        redirect('?action=list', 'Data mahasiswa berhasil diperbarui.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $_SESSION['form_errors'] = ['nim' => 'NIM sudah digunakan mahasiswa lain.'];
            $_SESSION['form_old']    = $data;
            redirect("?action=edit&id=$id");
        }
        redirect('?action=list', 'Gagal memperbarui data.', 'error');
    }
    exit;
}

/** DELETE — hapus mahasiswa (POST) */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) redirect('?action=list', 'ID tidak valid.', 'error');

    try {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM mahasiswa WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            redirect('?action=list', 'Mahasiswa berhasil dihapus.');
        } else {
            redirect('?action=list', 'Data tidak ditemukan.', 'error');
        }
    } catch (PDOException $e) {
        redirect('?action=list', 'Gagal menghapus data.', 'error');
    }
    exit;
}

// Fallback
redirect('?action=list');

// ─────────────────────────────────────────────────────────────
// HELPER FUNCTIONS
// ─────────────────────────────────────────────────────────────

function sanitizeInput(array $post): array {
    return [
        'nim'      => trim($post['nim']      ?? ''),
        'nama'     => trim($post['nama']     ?? ''),
        'jurusan'  => trim($post['jurusan']  ?? ''),
        'angkatan' => trim($post['angkatan'] ?? ''),
        'email'    => trim($post['email']    ?? ''),
        'no_telp'  => trim($post['no_telp']  ?? ''),
        'ipk'      => trim($post['ipk']      ?? ''),
    ];
}

function validateMahasiswa(array $data, int $excludeId = 0): array {
    $errors = [];
    if (empty($data['nim']))      $errors['nim']      = 'NIM wajib diisi.';
    if (empty($data['nama']))     $errors['nama']     = 'Nama wajib diisi.';
    if (empty($data['jurusan']))  $errors['jurusan']  = 'Jurusan wajib diisi.';
    if (empty($data['angkatan'])) $errors['angkatan'] = 'Angkatan wajib diisi.';
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }
    if (!empty($data['ipk'])) {
        $ipk = (float)$data['ipk'];
        if ($ipk < 0 || $ipk > 4.00) $errors['ipk'] = 'IPK harus antara 0.00 - 4.00.';
    }
    return $errors;
}

// ─────────────────────────────────────────────────────────────
// VIEW FUNCTIONS — Template Rendering
// ─────────────────────────────────────────────────────────────

function renderLayout(string $title, string $content, string $activeNav = 'list'): void {
    $appName  = APP_NAME;
    $userName = e($_SESSION['nama'] ?? 'Guest');
    $userRole = e($_SESSION['role'] ?? '');
    $flash    = ''; // Flash dihandle per-halaman
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?> — <?= e($appName) ?></title>
  <style>
    :root{--bg:#f8fafc;--surface:#ffffff;--border:#e2e8f0;--accent:#3b82f6;--accent-hover:#2563eb;--danger:#ef4444;--success:#10b981;--warning:#f59e0b;--text:#1e293b;--muted:#64748b;--radius:10px;--shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;line-height:1.6;min-height:100vh;display:flex;flex-direction:column}
    a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}

    /* Navbar */
    .navbar{background:#1e293b;color:#fff;padding:0 24px;display:flex;justify-content:space-between;align-items:center;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.2)}
    .navbar-brand{font-size:1rem;font-weight:700;color:#fff;letter-spacing:-.02em}
    .navbar-brand span{color:#60a5fa}
    .navbar-right{display:flex;align-items:center;gap:16px;font-size:.85rem}
    .navbar-user{color:#94a3b8}.navbar-user strong{color:#e2e8f0}
    .btn-logout{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#e2e8f0;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:.82rem;transition:background .15s}
    .btn-logout:hover{background:rgba(255,255,255,.2)}

    /* Layout */
    .main-content{flex:1;max-width:1100px;margin:32px auto;padding:0 24px;width:100%}

    /* Page Header */
    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px}
    .page-title{font-size:1.5rem;font-weight:700;color:var(--text);letter-spacing:-.03em}
    .page-subtitle{font-size:.85rem;color:var(--muted);margin-top:3px}

    /* Cards */
    .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
    .card-body{padding:24px}

    /* Alert / Flash */
    .alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;font-size:.88rem;display:flex;align-items:center;gap:10px}
    .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    .alert-warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e}

    /* Buttons */
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer;border:none;transition:all .15s;text-decoration:none;font-family:inherit}
    .btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-hover);text-decoration:none}
    .btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}.btn-secondary:hover{background:var(--bg);text-decoration:none}
    .btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#dc2626;text-decoration:none}
    .btn-sm{padding:5px 12px;font-size:.8rem}
    .btn-warning{background:var(--warning);color:#fff}.btn-warning:hover{background:#d97706}

    /* Table */
    .table-wrapper{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border)}
    table{width:100%;border-collapse:collapse;font-size:.875rem}
    thead{background:#f1f5f9}
    th{padding:11px 14px;text-align:left;font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
    td{padding:12px 14px;border-top:1px solid var(--border);vertical-align:middle}
    tbody tr:hover{background:#f8fafc}
    .td-actions{display:flex;gap:6px}

    /* Badge */
    .badge{display:inline-block;padding:2px 10px;border-radius:100px;font-size:.75rem;font-weight:500}
    .badge-blue{background:#eff6ff;color:#1d4ed8}
    .badge-green{background:#f0fdf4;color:#166534}
    .badge-gray{background:#f1f5f9;color:#475569}

    /* Search bar */
    .search-bar{display:flex;gap:10px;margin-bottom:20px}
    .search-input{flex:1;padding:9px 14px;border:1px solid var(--border);border-radius:8px;font-size:.875rem;outline:none;font-family:inherit}
    .search-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.1)}

    /* Pagination */
    .pagination{display:flex;gap:6px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap}
    .page-btn{padding:6px 12px;border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:.82rem;text-decoration:none;transition:all .15s}
    .page-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent);text-decoration:none}
    .page-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
    .page-btn.disabled{color:var(--muted);pointer-events:none;opacity:.5}

    /* Form */
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    @media(max-width:640px){.form-grid{grid-template-columns:1fr}}
    .form-group{display:flex;flex-direction:column;gap:6px}
    .form-group.full{grid-column:1/-1}
    label{font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase}
    .form-control{padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-size:.9rem;outline:none;font-family:inherit;transition:border-color .15s}
    .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.1)}
    .form-control.is-invalid{border-color:var(--danger)}
    .invalid-feedback{font-size:.8rem;color:var(--danger);margin-top:2px}
    .form-hint{font-size:.78rem;color:var(--muted)}
    .form-actions{display:flex;gap:10px;margin-top:8px}

    /* Stats */
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow)}
    .stat-label{font-size:.75rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em}
    .stat-value{font-size:1.8rem;font-weight:700;color:var(--text);margin-top:4px;letter-spacing:-.03em}

    /* IPK color coding */
    .ipk-high{color:#059669;font-weight:600}.ipk-mid{color:#d97706;font-weight:600}.ipk-low{color:#dc2626;font-weight:600}

    /* Empty state */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
    .empty-state .icon{font-size:3rem;margin-bottom:12px;opacity:.4}

    footer{text-align:center;padding:24px;color:var(--muted);font-size:.8rem;border-top:1px solid var(--border)}
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">📚 <span><?= e($appName) ?></span></div>
    <div class="navbar-right">
      <span class="navbar-user">Login sebagai <strong><?= $userName ?></strong> <span style="background:rgba(255,255,255,.1);padding:2px 8px;border-radius:4px;font-size:.75rem"><?= $userRole ?></span></span>
      <form method="POST" action="?action=logout" style="margin:0">
        <button type="submit" class="btn-logout">↩ Logout</button>
      </form>
    </div>
  </nav>

  <div class="main-content">
    <?= $content ?>
  </div>

  <footer>© 2025 <?= e($appName) ?> — UAS Administrasi Server &amp; Cloud Computing | PHP <?= phpversion() ?> + MariaDB</footer>
</body>
</html>
<?php
}

function renderList(array $rows, int $total, int $page, int $totalPage, string $search, ?array $flash): void {
    ob_start();
    $flashHtml = '';
    if ($flash) {
        $type = $flash['type'] === 'error' ? 'alert-error' : 'alert-success';
        $icon = $flash['type'] === 'error' ? '✕' : '✓';
        $flashHtml = "<div class=\"alert $type\"><span>$icon</span>" . e($flash['message']) . "</div>";
    }

    // Stats
    try {
        $db = getDB();
        $stats = $db->query("
            SELECT
                COUNT(*) as total,
                COUNT(DISTINCT jurusan) as jurusan_count,
                ROUND(AVG(ipk), 2) as avg_ipk,
                COUNT(DISTINCT angkatan) as angkatan_count
            FROM mahasiswa
        ")->fetch();
    } catch(PDOException $e) {
        $stats = ['total'=>0,'jurusan_count'=>0,'avg_ipk'=>0,'angkatan_count'=>0];
    }
?>
<div class="page-header">
  <div>
    <div class="page-title">Data Mahasiswa</div>
    <div class="page-subtitle">Kelola data mahasiswa terdaftar · Total <?= $total ?> mahasiswa ditemukan</div>
  </div>
  <a href="?action=create" class="btn btn-primary">＋ Tambah Mahasiswa</a>
</div>

<?= $flashHtml ?>

<div class="stats-row">
  <div class="stat-card"><div class="stat-label">Total Mahasiswa</div><div class="stat-value"><?= number_format($stats['total']) ?></div></div>
  <div class="stat-card"><div class="stat-label">Jurusan</div><div class="stat-value"><?= $stats['jurusan_count'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Rata-rata IPK</div><div class="stat-value"><?= number_format($stats['avg_ipk'] ?? 0, 2) ?></div></div>
  <div class="stat-card"><div class="stat-label">Angkatan</div><div class="stat-value"><?= $stats['angkatan_count'] ?></div></div>
</div>

<div class="card">
  <div class="card-body">
    <form method="GET" action="" class="search-bar">
      <input type="hidden" name="action" value="list" />
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari NIM, nama, atau jurusan..." class="search-input" />
      <button type="submit" class="btn btn-primary">🔍 Cari</button>
      <?php if($search): ?><a href="?action=list" class="btn btn-secondary">✕ Reset</a><?php endif; ?>
    </form>

    <?php if (empty($rows)): ?>
    <div class="empty-state"><div class="icon">📭</div><p>Tidak ada data mahasiswa<?= $search ? " untuk pencarian \"" . e($search) . "\"" : '' ?>.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th><th>NIM</th><th>Nama</th><th>Jurusan</th><th>Angkatan</th><th>IPK</th><th>Email</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $row):
            $ipk = $row['ipk'];
            $ipkClass = $ipk >= 3.5 ? 'ipk-high' : ($ipk >= 2.75 ? 'ipk-mid' : 'ipk-low');
        ?>
          <tr>
            <td style="color:var(--muted);font-size:.8rem"><?= (($page-1)*8)+$i+1 ?></td>
            <td><span class="badge badge-blue"><?= e($row['nim']) ?></span></td>
            <td style="font-weight:500"><?= e($row['nama']) ?></td>
            <td><?= e($row['jurusan']) ?></td>
            <td><span class="badge badge-gray"><?= e($row['angkatan']) ?></span></td>
            <td><span class="<?= $ipkClass ?>"><?= $ipk ? number_format($ipk, 2) : '—' ?></span></td>
            <td style="color:var(--muted);font-size:.82rem"><?= $row['email'] ? e($row['email']) : '—' ?></td>
            <td>
              <div class="td-actions">
                <a href="?action=edit&id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">✎ Edit</a>
                <form method="POST" action="?action=delete" style="margin:0"
                      onsubmit="return confirm('Hapus mahasiswa <?= e(addslashes($row['nama'])) ?>?')">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-sm">✕ Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPage > 1): ?>
    <div class="pagination">
      <a href="?action=list&page=<?= max(1,$page-1) ?>&search=<?= urlencode($search) ?>"
         class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
      <?php for ($p = 1; $p <= $totalPage; $p++): ?>
        <a href="?action=list&page=<?= $p ?>&search=<?= urlencode($search) ?>"
           class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="?action=list&page=<?= min($totalPage,$page+1) ?>&search=<?= urlencode($search) ?>"
         class="page-btn <?= $page >= $totalPage ? 'disabled' : '' ?>">Next →</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php
    $content = ob_get_clean();
    renderLayout('Data Mahasiswa', $content);
}

function renderForm(string $mode, array $row, array $errors, array $old): void {
    $isEdit   = $mode === 'edit';
    $title    = $isEdit ? 'Edit Mahasiswa' : 'Tambah Mahasiswa';
    $action   = $isEdit ? '?action=update' : '?action=store';
    $btnLabel = $isEdit ? '💾 Simpan Perubahan' : '＋ Tambah Mahasiswa';

    $v = fn(string $field, string $default = '') => e($old[$field] ?? $row[$field] ?? $default);
    $err = fn(string $field) => isset($errors[$field]) ? "<div class=\"invalid-feedback\">" . e($errors[$field]) . "</div>" : '';
    $inv = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';

    ob_start();
?>
<div class="page-header">
  <div>
    <div class="page-title"><?= $title ?></div>
    <div class="page-subtitle"><?= $isEdit ? 'Perbarui data mahasiswa' : 'Isi form untuk menambahkan data mahasiswa baru' ?></div>
  </div>
  <a href="?action=list" class="btn btn-secondary">← Kembali</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="POST" action="<?= $action ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group">
          <label>NIM *</label>
          <input type="text" name="nim" value="<?= $v('nim') ?>" class="form-control<?= $inv('nim') ?>"
                 placeholder="contoh: 2024001001" required maxlength="20" />
          <?= $err('nim') ?>
        </div>
        <div class="form-group">
          <label>Nama Lengkap *</label>
          <input type="text" name="nama" value="<?= $v('nama') ?>" class="form-control<?= $inv('nama') ?>"
                 placeholder="Nama sesuai KTP" required maxlength="100" />
          <?= $err('nama') ?>
        </div>
        <div class="form-group">
          <label>Jurusan *</label>
          <select name="jurusan" class="form-control<?= $inv('jurusan') ?>" required>
            <option value="">-- Pilih Jurusan --</option>
            <?php foreach(['Teknik Informatika','Sistem Informasi','Teknik Komputer','Manajemen Informatika'] as $j): ?>
              <option value="<?= e($j) ?>" <?= $v('jurusan') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $err('jurusan') ?>
        </div>
        <div class="form-group">
          <label>Angkatan *</label>
          <select name="angkatan" class="form-control<?= $inv('angkatan') ?>" required>
            <option value="">-- Pilih Angkatan --</option>
            <?php for ($y = date('Y'); $y >= 2018; $y--): ?>
              <option value="<?= $y ?>" <?= $v('angkatan') == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <?= $err('angkatan') ?>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= $v('email') ?>" class="form-control<?= $inv('email') ?>"
                 placeholder="mahasiswa@kampus.ac.id" maxlength="150" />
          <?= $err('email') ?>
        </div>
        <div class="form-group">
          <label>No. Telepon</label>
          <input type="text" name="no_telp" value="<?= $v('no_telp') ?>" class="form-control"
                 placeholder="08xxxxxxxxxx" maxlength="20" />
        </div>
        <div class="form-group">
          <label>IPK</label>
          <input type="number" name="ipk" value="<?= $v('ipk') ?>" class="form-control<?= $inv('ipk') ?>"
                 placeholder="3.75" step="0.01" min="0" max="4.00" />
          <?= $err('ipk') ?>
          <span class="form-hint">Skala 0.00 – 4.00</span>
        </div>
      </div>

      <div class="form-actions" style="margin-top:24px">
        <button type="submit" class="btn btn-primary"><?= $btnLabel ?></button>
        <a href="?action=list" class="btn btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php
    $content = ob_get_clean();
    renderLayout($title, $content);
}

function renderLogin(?string $error): void {
    $appName = APP_NAME;
    $errHtml = $error ? "<div class=\"alert alert-error\">✕ $error</div>" : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — <?= e($appName) ?></title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .card{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 25px 60px rgba(0,0,0,.4)}
    .brand{text-align:center;margin-bottom:28px}
    .brand-icon{font-size:2.5rem;margin-bottom:10px}
    .brand-name{font-size:1.2rem;font-weight:700;color:#1e293b;letter-spacing:-.02em}
    .brand-sub{font-size:.82rem;color:#64748b;margin-top:4px}
    .alert{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:.85rem}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    .form-group{margin-bottom:16px}
    label{display:block;font-size:.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
    .form-control{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;outline:none;transition:border-color .15s;font-family:inherit}
    .form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
    .btn{width:100%;padding:11px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:background .15s;margin-top:8px;font-family:inherit}
    .btn:hover{background:#2563eb}
    .hint{text-align:center;margin-top:20px;font-size:.78rem;color:#94a3b8;background:#f8fafc;padding:10px;border-radius:8px;border:1px solid #e2e8f0}
    .hint code{background:#e2e8f0;padding:1px 6px;border-radius:4px;font-size:.82rem}
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <div class="brand-icon">📚</div>
      <div class="brand-name"><?= e($appName) ?></div>
      <div class="brand-sub">UAS Administrasi Server &amp; Cloud Computing</div>
    </div>

    <?= $errHtml ?>

    <form method="POST" action="?action=do_login">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control" placeholder="Masukkan username" autocomplete="username" required autofocus />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Masukkan password" autocomplete="current-password" required />
      </div>
      <button type="submit" class="btn">→ Masuk</button>
    </form>

    <div class="hint">
      Default login: <code>admin</code> / <code>admin123</code>
    </div>
  </div>
</body>
</html>
<?php
}
