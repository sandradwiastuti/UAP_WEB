<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Accounts';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';
$account_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $initial_balance = str_replace('.', '', $_POST['initial_balance'] ?? '0');

    if (empty($name)) {
        $error = 'Nama akun wajib diisi.';
    } else {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance, currency) VALUES (?, ?, ?, 'IDR')");
            $stmt->execute([$user_id, $name, $initial_balance]);
            $_SESSION['success_message'] = "Akun berhasil ditambahkan.";
        } elseif ($action == 'edit' && $account_id) {
            $stmt = $pdo->prepare("UPDATE accounts SET name = ?, initial_balance = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $initial_balance, $account_id, $user_id]);
            $_SESSION['success_message'] = "Akun berhasil diperbarui.";
        }
        header("Location: accounts.php");
        exit();
    }
}

if ($action == 'delete' && $account_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $user_id]);
        $_SESSION['success_message'] = "Akun berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Tidak dapat menghapus akun karena memiliki transaksi terkait.";
    }
    header("Location: accounts.php");
    exit();
}

include '../includes/header.php';

?>
    <style>
        .account-card {
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .account-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .account-card .card-header {
            background-color: #dc3545; /* Red header */
            color: white;
            font-weight: bold;
            border-top-left-radius: calc(0.5rem - 1px);
            border-top-right-radius: calc(0.5rem - 1px);
        }
        .account-card .card-title {
            font-size: 1.25rem;
            margin-bottom: 0;
        }
        .account-card .card-body {
            background-color: #ffffff; /* White body */
        }
        .account-card .card-footer {
            background-color: #f8f9fa; /* Light grey footer */
            border-top: 1px solid #e0e0e0;
        }
        .account-card .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .account-card .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        .account-card .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-add-account {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-add-account:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #fff;
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
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="dashboard-title">Manage Accounts</h1>
        <button type="button" class="btn btn-primary btn-add-account" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="fas fa-plus-circle me-2"></i>Tambah Akun Baru
        </button>
    </div>

    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="row">
        <?php
        $sql = "
            SELECT
                a.id,
                a.name,
                a.initial_balance,
                a.currency,
                COALESCE(t_summary.total_income, 0) as total_income,
                COALESCE(t_summary.total_expense, 0) as total_expense
            FROM
                accounts a
            LEFT JOIN
                (
                    SELECT
                        account_id,
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
                    FROM transactions
                    GROUP BY account_id
                ) AS t_summary ON a.id = t_summary.account_id
            WHERE
                a.user_id = ?
            ORDER BY
                a.name;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $accounts = $stmt->fetchAll();

        if (count($accounts) > 0) :
            foreach ($accounts as $account) :
                $current_balance = $account['initial_balance'] + $account['total_income'] - $account['total_expense'];
        ?>
                <div class="col-md-4 mb-4">
                    <div class="card account-card h-100 shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title"><?= htmlspecialchars($account['name']) ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text mb-1"><strong>Saldo Awal:</strong> <?= htmlspecialchars($account['currency']) ?> <?= number_format($account['initial_balance'], 0, ',', '.') ?></p>
                            <p class="card-text mb-1"><strong>Total Pemasukan:</strong> <?= htmlspecialchars($account['currency']) ?> <?= number_format($account['total_income'], 0, ',', '.') ?></p>
                            <p class="card-text mb-1"><strong>Total Pengeluaran:</strong> <?= htmlspecialchars($account['currency']) ?> <?= number_format($account['total_expense'], 0, ',', '.') ?></p>
                            <h6 class="card-subtitle mt-3">Saldo Saat Ini: <strong><?= htmlspecialchars($account['currency']) ?> <?= number_format($current_balance, 0, ',', '.') ?></strong></h6>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-warning me-2" data-bs-toggle="modal" data-bs-target="#editAccountModal" 
                                data-id="<?= $account['id'] ?>" 
                                data-name="<?= htmlspecialchars($account['name']) ?>" 
                                data-initial-balance="<?= htmlspecialchars($account['initial_balance']) ?>">
                                <i class="fas fa-edit me-1"></i>Ubah
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $account['id'] ?>">
                                <i class="fas fa-trash me-1"></i>Hapus
                            </button>
                        </div>
                    </div>
                </div>
            <?php
            endforeach;
        else :
            ?>
            <div class="col-12">
                <div class="alert alert-info text-center" role="alert">
                    Tidak ada akun ditemukan. Klik 'Tambah Akun Baru' untuk memulai.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccountModalLabel">Tambah Akun Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="accounts.php?action=add" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_account_name" class="form-label">Nama Akun</label>
                            <input type="text" class="form-control" id="add_account_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_initial_balance" class="form-label">Saldo Awal (IDR)</label>
                            <input type="text" inputmode="numeric" class="form-control" id="add_initial_balance" name="initial_balance" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccountModalLabel">Ubah Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAccountForm" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="modal_account_name" class="form-label">Nama Akun</label>
                            <input type="text" class="form-control" id="modal_account_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="modal_initial_balance" class="form-label">Saldo Awal (IDR)</label>
                            <input type="text" inputmode="numeric" class="form-control" id="modal_initial_balance" name="initial_balance" required>
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

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin menghapus akun ini? Tindakan ini tidak dapat dibatalkan dan akan memengaruhi transaksi terkait.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" id="deleteAccountLink" class="btn btn-danger">Hapus</a>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const initialBalanceInput = document.getElementById('initial_balance');
    const addInitialBalanceInput = document.getElementById('add_initial_balance');
    const modalInitialBalanceInput = document.getElementById('modal_initial_balance');

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

    if (initialBalanceInput) {
        formatRupiah(initialBalanceInput);
    }
    if (addInitialBalanceInput) {
        formatRupiah(addInitialBalanceInput);
    }
    if (modalInitialBalanceInput) {
        formatRupiah(modalInitialBalanceInput);
    }

    const editAccountModal = document.getElementById('editAccountModal');
    if (editAccountModal) {
        editAccountModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const accountId = button.getAttribute('data-id');
            const accountName = button.getAttribute('data-name');
            const initialBalance = button.getAttribute('data-initial-balance');

            const modalTitle = editAccountModal.querySelector('.modal-title');
            const modalAccountNameInput = editAccountModal.querySelector('#modal_account_name');
            const modalInitialBalanceInput = editAccountModal.querySelector('#modal_initial_balance');
            const editForm = editAccountModal.querySelector('#editAccountForm');

            modalTitle.textContent = 'Ubah Akun: ' + accountName;
            modalAccountNameInput.value = accountName;
            modalInitialBalanceInput.value = new Intl.NumberFormat('id-ID').format(initialBalance.replace(/\D/g, ''));
            editForm.action = 'accounts.php?action=edit&id=' + accountId;
        });
    }

    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const accountId = button.getAttribute('data-id');
            const deleteLink = deleteConfirmModal.querySelector('#deleteAccountLink');
            deleteLink.href = 'accounts.php?action=delete&id=' + accountId;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>