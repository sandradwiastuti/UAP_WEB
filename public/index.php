<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Dashboard';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Mengambil data ringkasan transaksi
$stmt_transactions = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions 
    WHERE user_id = ?
");
$stmt_transactions->execute([$user_id]);
$summary_transactions = $stmt_transactions->fetch();

// Mengambil data ringkasan saldo awal dari semua akun
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

// Logika baru untuk saldo saat ini
$current_balance = $total_initial_balance + $total_income - $total_expense;

?>

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
    <a href="transactions.php?action=add" class="btn btn-danger me-2"><i class="fas fa-plus-circle me-2"></i>Tambah Transaksi Baru</a>
    <a href="categories.php" class="btn btn-secondary me-2"><i class="fas fa-tags me-2"></i>Kelola Kategori</a>
    <a href="reports.php" class="btn btn-info"><i class="fas fa-chart-pie me-2"></i>Lihat Laporan</a>
</div>

<?php include '../includes/footer.php'; ?>