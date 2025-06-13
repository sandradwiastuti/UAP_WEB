<?php
require '../includes/auth_check.php';
require '../config/database.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<div class="alert alert-danger" role="alert">Kesalahan: Tidak dapat terhubung ke database. Mohon periksa konfigurasi database Anda.</div>');
}

$page_title = 'Dashboard';
$user_id = $_SESSION['user_id'];

$stmt_transactions = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE user_id = ?
");
$stmt_transactions->execute([$user_id]);
$summary_transactions = $stmt_transactions->fetch();

$stmt_accounts = $pdo->prepare("
    SELECT COALESCE(SUM(initial_balance), 0) as total_initial_balance
    FROM accounts
    WHERE user_id = ?
");
$stmt_accounts->execute([$user_id]);
$summary_accounts = $stmt_accounts->fetch();

$total_income = $summary_transactions['total_income'];
$total_expense = $summary_transactions['total_expense'];
$total_initial_balance = $summary_accounts['total_initial_balance'];

$current_balance = $total_initial_balance + $total_income - $total_expense;

$goal_filter_start_date = $_GET['goal_filter_start_date'] ?? date('Y-m-01', strtotime('-6 months'));
$goal_filter_end_date = $_GET['goal_filter_end_date'] ?? date('Y-m-d', strtotime('+6 months'));
$filter_status = $_GET['filter_status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_goal') {
        $new_goal_start_date = $_POST['new_goal_start_date'];
        $new_goal_end_date = $_POST['new_goal_end_date'];
        $new_income_goal = str_replace('.', '', $_POST['income_goal']);
        $new_expense_goal = str_replace('.', '', $_POST['expense_goal']);

        try {
            $stmt_insert_goal = $pdo->prepare("INSERT INTO goals (user_id, start_date, end_date, income_goal, expense_goal, status) VALUES (?, ?, ?, ?, ?, 'in progress')");
            $stmt_insert_goal->execute([$user_id, $new_goal_start_date, $new_goal_end_date, $new_income_goal, $new_expense_goal]);
            $_SESSION['success_message'] = "Tujuan berhasil ditambahkan!";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $_SESSION['error_message'] = "Tujuan untuk periode tanggal tersebut sudah ada.";
            } else {
                $_SESSION['error_message'] = "Terjadi kesalahan saat menambahkan tujuan: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit_goal') {
        $goal_id = $_POST['goal_id'];
        $edit_start_date = $_POST['edit_start_date'];
        $edit_end_date = $_POST['edit_end_date'];
        $edit_income_goal = str_replace('.', '', $_POST['edit_income_goal']);
        $edit_expense_goal = str_replace('.', '', $_POST['edit_expense_goal']);

        try {
            $stmt_update_goal = $pdo->prepare("UPDATE goals SET start_date = ?, end_date = ?, income_goal = ?, expense_goal = ? WHERE id = ? AND user_id = ?");
            $stmt_update_goal->execute([$edit_start_date, $edit_end_date, $edit_income_goal, $edit_expense_goal, $goal_id, $user_id]);
            $_SESSION['success_message'] = "Tujuan berhasil diperbarui!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Terjadi kesalahan saat memperbarui tujuan: " . $e->getMessage();
        }
    }
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_goal' && isset($_GET['id'])) {
    $goal_id = $_GET['id'];
    try {
        $stmt_delete_goal = $pdo->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
        $stmt_delete_goal->execute([$goal_id, $user_id]);
        $_SESSION['success_message'] = "Tujuan berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan saat menghapus tujuan: " . $e->getMessage();
    }
    header("Location: index.php");
    exit();
}

$current_full_date = date('Y-m-d');
$stmt_goals_to_check = $pdo->prepare("SELECT id, start_date, end_date, income_goal, expense_goal FROM goals WHERE user_id = ? AND end_date < ? AND status = 'in progress'");
$stmt_goals_to_check->execute([$user_id, $current_full_date]);
$goals_to_check = $stmt_goals_to_check->fetchAll();

foreach ($goals_to_check as $goal) {
    $goal_period_start = $goal['start_date'];
    $goal_period_end = $goal['end_date'];

    $stmt_actual_performance = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as actual_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as actual_expense
        FROM transactions
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    ");
    $stmt_actual_performance->execute([$user_id, $goal_period_start, $goal_period_end]);
    $actual_performance = $stmt_actual_performance->fetch();

    $actual_income = $actual_performance['actual_income'];
    $actual_expense = $actual_performance['actual_expense'];

    $is_income_achieved = ($actual_income >= $goal['income_goal']);
    $is_expense_controlled = ($actual_expense <= $goal['expense_goal']);

    $new_status = '';
    if ($is_income_achieved && $is_expense_controlled) {
        $new_status = 'berhasil';
    } else {
        $new_status = 'gagal';
    }

    if ($new_status != 'in progress') {
        $stmt_update_goal_status = $pdo->prepare("UPDATE goals SET status = ? WHERE id = ?");
        $stmt_update_goal_status->execute([$new_status, $goal['id']]);
    }
}

$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

$sql_goals_base = "SELECT id, start_date, end_date, income_goal, expense_goal, status FROM goals WHERE user_id = ? AND start_date BETWEEN ? AND ?";
$goal_params_base = [$user_id, $goal_filter_start_date, $goal_filter_end_date];

if ($filter_status != 'all') {
    $sql_goals_base .= " AND status = ?";
    $goal_params_base[] = $filter_status;
}

$stmt_total_goals = $pdo->prepare("SELECT COUNT(*) FROM (" . $sql_goals_base . ") AS filtered_goals");
$stmt_total_goals->execute($goal_params_base);
$total_goals = $stmt_total_goals->fetchColumn();
$total_pages = ceil($total_goals / $records_per_page);

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
} elseif ($total_pages == 0) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$sql_goals = $sql_goals_base . " ORDER BY start_date ASC LIMIT ? OFFSET ?";
$stmt_goals = $pdo->prepare($sql_goals);
$stmt_goals->execute(array_merge($goal_params_base, [$records_per_page, $offset]));
$goals = $stmt_goals->fetchAll();

foreach ($goals as &$goal) {
    $stmt_actual_performance = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as actual_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as actual_expense
        FROM transactions
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    ");
    $stmt_actual_performance->execute([$user_id, $goal['start_date'], $goal['end_date']]);
    $actual_data = $stmt_actual_performance->fetch();
    $goal['actual_income'] = $actual_data['actual_income'];
    $goal['actual_expense'] = $actual_data['actual_expense'];
}
unset($goal); 

$successful_goals = 0;
$failed_goals = 0;
$stmt_all_filtered_goals = $pdo->prepare($sql_goals_base . " ORDER BY start_date ASC");
$stmt_all_filtered_goals->execute($goal_params_base);
$all_filtered_goals_for_count = $stmt_all_filtered_goals->fetchAll();

foreach ($all_filtered_goals_for_count as $goal_count) {
    if ($goal_count['status'] == 'berhasil') {
        $successful_goals++;
    } elseif ($goal_count['status'] == 'gagal') {
        $failed_goals++;
    }
}

include '../includes/header.php';
?>

<style>
    .goal-table thead.table-light {
        background-color: #f8d7da;
        background: linear-gradient(to right, #ffffff, #f8d7da);
    }
    .goal-table thead.table-light th {
        color: #842029;
        border-color: #dc3545;
    }
    .goal-table tbody tr {
        transition: background-color 0.3s ease;
    }
    .goal-table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }
    .goal-table tbody tr:nth-child(even) {
        background-color: #ffeaea;
    }
    .goal-table tbody tr:hover {
        background-color: #f5c2c7;
    }

    .goal-table .status-berhasil {
        color: #28a745;
        font-weight: bold;
    }
    .goal-table .status-gagal {
        color: #dc3545;
        font-weight: bold;
    }
    .goal-table .status-in-progress {
        color: #0d6efd;
        font-weight: bold;
    }

    .actual-value-positive {
        color: #28a745;
    }
    .actual-value-negative {
        color: #dc3545;
    }

    .quick-actions .btn-square {
        padding: 8px 12px;
        min-width: 50px;
        height: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
        transition: transform 0.2s ease, background-color 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        text-align: center;
        text-decoration: none;
    }
    .quick-actions .btn-square:hover {
        transform: translateY(-2px);
        background-color: #c82333;
        border-color: #bd2130;
    }
    .quick-actions .btn-square i {
        font-size: 1.5rem;
        color: white;
        margin-bottom: 2px;
    }
    .quick-actions .btn-square span {
        font-size: 0.6rem;
        line-height: 1;
        color: white;
    }

    .modal-header {
        background-color: #dc3545;
        color: white;
        border-bottom: none;
    }
    .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    .modal-footer .btn-primary {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    .modal-footer .btn-primary:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }

    .pagination .page-link {
        color: #dc3545;
    }
    .pagination .page-item.active .page-link {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }
    .pagination .page-item.disabled .page-link {
        color: #6c757d;
    }
</style>

<h1 class="mb-4 dashboard-title">Dashboard Keuangan Anda</h1>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card summary-card card-income">
            <div class="card-body">
                <h5 class="card-title">Total Pemasukan</h5>
                <p class="card-text">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                <div class="card-icon">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card summary-card card-expense">
            <div class="card-body">
                <h5 class="card-title">Total Pengeluaran</h5>
                <p class="card-text">Rp <?= number_format($total_expense, 0, ',', '.') ?></p>
                <div class="card-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card summary-card card-balance">
            <div class="card-body">
                <h5 class="card-title">Saldo Saat Ini</h5>
                <p class="card-text">Rp <?= number_format($current_balance, 0, ',', '.') ?></p>
             <div class="card-icon">
                    <i class="fas fa-balance-scale"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 p-4 bg-white rounded shadow-sm quick-actions">
    <h3 class="mb-3">Aksi Cepat</h3>
    <div class="d-flex flex-wrap gap-3">
        <a href="transactions.php?action=add" class="btn btn-square" data-bs-toggle="tooltip" data-bs-placement="top" title="Tambah Transaksi Baru">
            <i class="fas fa-plus-circle"></i>
            <span>Tambah Transaksi</span>
        </a>
        <a href="categories.php" class="btn btn-square" data-bs-toggle="tooltip" data-bs-placement="top" title="Kelola Kategori">
            <i class="fas fa-tags"></i>
            <span>Kelola Kategori</span>
        </a>
        <a href="accounts.php" class="btn btn-square" data-bs-toggle="tooltip" data-bs-placement="top" title="Kelola Akun">
            <i class="fas fa-university"></i>
            <span>Kelola Akun</span>
        </a>
        <a href="reports.php" class="btn btn-square" data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat Laporan">
            <i class="fas fa-chart-pie"></i>
            <span>Lihat Laporan</span>
        </a>
        <button type="button" class="btn btn-square" data-bs-toggle="modal" data-bs-target="#addGoalModal" data-bs-placement="top" title="Tambah Tujuan Baru">
            <i class="fas fa-bullseye"></i>
            <span>Tambah Tujuan</span>
        </button>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white" style="background-color: #dc3545 !important;">
                <h5 class="mb-0">Tujuan Keuangan Anda</h5>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error_message'])) : ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['success_message'])) : ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <form method="GET" action="index.php" class="d-flex align-items-center mb-2 mb-md-0">
                        <label for="goal_filter_start_date" class="form-label me-2 mb-0">Dari:</label>
                        <input type="date" id="goal_filter_start_date" name="goal_filter_start_date" class="form-control me-2" value="<?= htmlspecialchars($goal_filter_start_date) ?>">
                        
                        <label for="goal_filter_end_date" class="form-label me-2 mb-0">Sampai:</label>
                        <input type="date" id="goal_filter_end_date" name="goal_filter_end_date" class="form-control me-2" value="<?= htmlspecialchars($goal_filter_end_date) ?>">
                        
                        <button type="submit" class="btn btn-sm btn-primary">Lihat</button>
                    </form>
                    <div class="text-end">
                        <span class="badge bg-success fs-6 me-2">Berhasil: <?= $successful_goals ?></span>
                        <span class="badge bg-danger fs-6">Gagal: <?= $failed_goals ?></span>
                    </div>
                </div>

                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <?php
                        $base_url_filter_params = array_filter([
                            'goal_filter_start_date' => $goal_filter_start_date,
                            'goal_filter_end_date' => $goal_filter_end_date
                        ]);
                    ?>
                    <a href="index.php?<?= http_build_query($base_url_filter_params) ?>" class="btn btn-sm <?= $filter_status == 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">Semua Status</a>
                    <a href="index.php?<?= http_build_query(array_merge($base_url_filter_params, ['filter_status' => 'berhasil'])) ?>" class="btn btn-sm <?= $filter_status == 'berhasil' ? 'btn-success' : 'btn-outline-success' ?>">Berhasil</a>
                    <a href="index.php?<?= http_build_query(array_merge($base_url_filter_params, ['filter_status' => 'gagal'])) ?>" class="btn btn-sm <?= $filter_status == 'gagal' ? 'btn-danger' : 'btn-outline-danger' ?>">Gagal</a>
                    <a href="index.php?<?= http_build_query(array_merge($base_url_filter_params, ['filter_status' => 'in progress'])) ?>" class="btn btn-sm <?= $filter_status == 'in progress' ? 'btn-info' : 'btn-outline-info' ?>">Dalam Proses</a>
                </div>


                <?php if (!empty($goals)) : ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 goal-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Periode Tujuan</th>
                                    <th class="text-end">Target Pemasukan</th>
                                    <th class="text-end">Pemasukan Aktual</th>
                                    <th class="text-end">Target Pengeluaran</th>
                                    <th class="text-end">Pengeluaran Aktual</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($goals as $goal) : ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($goal['start_date'])) ?> - <?= date('d M Y', strtotime($goal['end_date'])) ?></td>
                                        <td class="text-end">Rp <?= number_format($goal['income_goal'], 0, ',', '.') ?></td>
                                        <td class="text-end <?= ($goal['actual_income'] >= $goal['income_goal']) ? 'actual-value-positive' : 'actual-value-negative' ?>">
                                            Rp <?= number_format($goal['actual_income'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end">Rp <?= number_format($goal['expense_goal'], 0, ',', '.') ?></td>
                                        <td class="text-end <?= ($goal['actual_expense'] <= $goal['expense_goal']) ? 'actual-value-positive' : 'actual-value-negative' ?>">
                                            Rp <?= number_format($goal['actual_expense'], 0, ',', '.') ?>
                                        </td>
                                        <td class="status-<?= str_replace(' ', '-', $goal['status']) ?>">
                                            <?= ucfirst($goal['status']) ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-warning me-1" 
                                                data-bs-toggle="modal" data-bs-target="#editGoalModal" 
                                                data-id="<?= $goal['id'] ?>" 
                                                data-start-date="<?= htmlspecialchars($goal['start_date']) ?>" 
                                                data-end-date="<?= htmlspecialchars($goal['end_date']) ?>" 
                                                data-income-goal="<?= htmlspecialchars($goal['income_goal']) ?>" 
                                                data-expense-goal="<?= htmlspecialchars($goal['expense_goal']) ?>">
                                                <i class="fas fa-edit"></i> Ubah
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteGoalModal" 
                                                data-id="<?= $goal['id'] ?>">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                        <?php
                        $pagination_params = $_GET;
                        unset($pagination_params['page']);
                        ?>
                        <nav class="mb-2 mb-md-0">
                            <ul class="pagination mb-0">
                                <?php if ($current_page > 1) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $current_page - 1])) ?>">&lt;&lt;</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($current_page < $total_pages) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $current_page + 1])) ?>">&gt;&gt;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <form method="GET" action="index.php" class="d-flex align-items-center">
                            <?php
                            $form_params = $_GET;
                            unset($form_params['page']);
                            foreach ($form_params as $key => $value) :
                            ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>
                            <label for="jump_page" class="form-label me-2 mb-0">Lompat ke halaman:</label>
                            <input type="number" id="jump_page" name="page" class="form-control text-center me-2" style="width: 80px;" min="1" max="<?= max(1, $total_pages) ?>" value="<?= $current_page ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Go</button>
                        </form>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info text-center mt-3">
                        Belum ada tujuan yang ditemukan untuk periode ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addGoalModal" tabindex="-1" aria-labelledby="addGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGoalModalLabel">Tambah Tujuan Keuangan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="post">
                <input type="hidden" name="action" value="add_goal">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_goal_start_date" class="form-label">Tanggal Mulai Tujuan</label>
                        <input type="date" class="form-control" id="new_goal_start_date" name="new_goal_start_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_goal_end_date" class="form-label">Tanggal Akhir Tujuan</label>
                        <input type="date" class="form-control" id="new_goal_end_date" name="new_goal_end_date" value="<?= date('Y-m-t') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_income_goal" class="form-label">Target Pemasukan (IDR)</label>
                        <input type="text" inputmode="numeric" class="form-control" id="add_income_goal" name="income_goal" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_expense_goal" class="form-label">Target Pengeluaran (IDR)</label>
                        <input type="text" inputmode="numeric" class="form-control" id="add_expense_goal" name="expense_goal" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Tujuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editGoalModal" tabindex="-1" aria-labelledby="editGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGoalModalLabel">Ubah Tujuan Keuangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="post">
                <input type="hidden" name="action" value="edit_goal">
                <input type="hidden" name="goal_id" id="edit_goal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_start_date" class="form-label">Tanggal Mulai Tujuan</label>
                        <input type="date" class="form-control" id="edit_start_date" name="edit_start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_end_date" class="form-label">Tanggal Akhir Tujuan</label>
                        <input type="date" class="form-control" id="edit_end_date" name="edit_end_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_income_goal" class="form-label">Target Pemasukan (IDR)</label>
                        <input type="text" inputmode="numeric" class="form-control" id="edit_income_goal" name="edit_income_goal" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_expense_goal" class="form-label">Target Pengeluaran (IDR)</label>
                        <input type="text" inputmode="numeric" class="form-control" id="edit_expense_goal" name="edit_expense_goal" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteGoalModal" tabindex="-1" aria-labelledby="deleteGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGoalModalLabel">Konfirmasi Hapus Tujuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus tujuan ini? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="deleteGoalLink" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    function formatRupiah(inputElement) {
        inputElement.addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/\D/g, '');
            if (value === '') {
                e.target.value = '';
                return;
            }
            e.target.value = new Intl.NumberFormat('id-ID').format(value);
        });
        if (inputElement.value) {
            inputElement.value = new Intl.NumberFormat('id-ID').format(inputElement.value.replace(/\D/g, ''));
        }
    }

    const addIncomeGoalInput = document.getElementById('add_income_goal');
    const addExpenseGoalInput = document.getElementById('add_expense_goal');
    if (addIncomeGoalInput) formatRupiah(addIncomeGoalInput);
    if (addExpenseGoalInput) formatRupiah(addExpenseGoalInput);

    const editIncomeGoalInput = document.getElementById('edit_income_goal');
    const editExpenseGoalInput = document.getElementById('edit_expense_goal');
    if (editIncomeGoalInput) formatRupiah(editIncomeGoalInput);
    if (editExpenseGoalInput) formatRupiah(editExpenseGoalInput);


    const editGoalModal = document.getElementById('editGoalModal');
    if (editGoalModal) {
        editGoalModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const goalId = button.getAttribute('data-id');
            const startDate = button.getAttribute('data-start-date');
            const endDate = button.getAttribute('data-end-date');
            const incomeGoal = button.getAttribute('data-income-goal');
            const expenseGoal = button.getAttribute('data-expense-goal');

            const modalGoalId = editGoalModal.querySelector('#edit_goal_id');
            const modalStartDate = editGoalModal.querySelector('#edit_start_date');
            const modalEndDate = editGoalModal.querySelector('#edit_end_date');
            const modalIncomeGoal = editGoalModal.querySelector('#edit_income_goal');
            const modalExpenseGoal = editGoalModal.querySelector('#edit_expense_goal');
            const editForm = editGoalModal.querySelector('form');

            modalGoalId.value = goalId;
            modalStartDate.value = startDate;
            modalEndDate.value = endDate;
            modalIncomeGoal.value = new Intl.NumberFormat('id-ID').format(incomeGoal);
            modalExpenseGoal.value = new Intl.NumberFormat('id-ID').format(expenseGoal);
            
            editForm.action = 'index.php';
        });
    }

    const deleteGoalModal = document.getElementById('deleteGoalModal');
    if (deleteGoalModal) {
        deleteGoalModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const goalId = button.getAttribute('data-id');
            const deleteLink = deleteGoalModal.querySelector('#deleteGoalLink');
            
            deleteLink.href = 'index.php?action=delete_goal&id=' + goalId;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>