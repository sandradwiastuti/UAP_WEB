<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Categories';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';
$category_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];

    if (empty($name) || empty($type)) {
        $error = 'Nama kategori dan jenis wajib diisi.';
    } else {
        if ($action == 'add') {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $name, $type]);
                $_SESSION['success_message'] = 'Kategori berhasil ditambahkan!';
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Terjadi kesalahan saat menambahkan kategori.';
            }
        } elseif ($action == 'edit' && $category_id) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $type, $category_id, $user_id]);
                $_SESSION['success_message'] = 'Kategori berhasil diperbarui!';
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Terjadi kesalahan saat memperbarui kategori.';
            }
        }
        header("Location: categories.php");
        exit();
    }
}

if ($action == 'delete' && $category_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$category_id, $user_id]);
        $_SESSION['success_message'] = 'Kategori berhasil dihapus!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Tidak dapat menghapus kategori karena memiliki transaksi terkait.';
    }
    header("Location: categories.php");
    exit();
}

include '../includes/header.php';
?>
<style>
    .category-card {
        border: 1px solid #e0e0e0;
        border-radius: 0.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .category-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .category-card .card-header {
        background-color: #dc3545; /* Red header */
        color: white;
        font-weight: bold;
        border-top-left-radius: calc(0.5rem - 1px);
        border-top-right-radius: calc(0.5rem - 1px);
    }
    .category-card .card-title {
        font-size: 1.25rem;
        margin-bottom: 0;
    }
    .category-card .card-body {
        background-color: #ffffff; /* White body */
    }
    .category-card .card-footer {
        background-color: #f8f9fa; /* Light grey footer */
        border-top: 1px solid #e0e0e0;
    }
    .category-card .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
    }
    .category-card .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }
    .category-card .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }
    .btn-add-category {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .btn-add-category:hover {
        background-color: #c82333;
        border-color: #bd2130;
        color: #fff;
    }

    /* Modal styling to match theme */
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
    .table-light {
        background-color: #f8d7da !important;
        background: linear-gradient(to right, #ffffff, #f8d7da) !important;
    }

    .table-light th {
        color: #842029 !important;
        border-color: #dc3545 !important;
    }
    .table-hover tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .table-hover tbody tr:nth-child(even) {
        background-color: #ffeaea;
    }

    .table-hover tbody tr:hover {
        background-color: #f5c2c7;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="dashboard-title">Manage Categories</h1>
    <button type="button" class="btn btn-primary btn-add-category" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fas fa-plus-circle me-2"></i>Tambah Kategori Baru
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

<?php
$records_per_page = 5;

$income_sort_by = $_GET['income_sort_by'] ?? 'name';
$income_sort_dir = $_GET['income_sort_dir'] ?? 'ASC';
$income_page = isset($_GET['income_page']) && is_numeric($_GET['income_page']) ? (int)$_GET['income_page'] : 1;

$stmt_total_income = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ? AND type = 'income'");
$stmt_total_income->execute([$user_id]);
$total_income_categories = $stmt_total_income->fetchColumn();
$total_income_pages = ceil($total_income_categories / $records_per_page);
if ($income_page < 1) $income_page = 1;
if ($income_page > $total_income_pages && $total_income_pages > 0) $income_page = $total_income_pages;
$income_offset = ($income_page - 1) * $records_per_page;

$stmt_income = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? AND type = 'income' ORDER BY {$income_sort_by} {$income_sort_dir} LIMIT ? OFFSET ?");
$stmt_income->execute([$user_id, $records_per_page, $income_offset]);
$income_categories = $stmt_income->fetchAll();

$expense_sort_by = $_GET['expense_sort_by'] ?? 'name';
$expense_sort_dir = $_GET['expense_sort_dir'] ?? 'ASC';
$expense_page = isset($_GET['expense_page']) && is_numeric($_GET['expense_page']) ? (int)$_GET['expense_page'] : 1;

$stmt_total_expense = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ? AND type = 'expense'");
$stmt_total_expense->execute([$user_id]);
$total_expense_categories = $stmt_total_expense->fetchColumn();
$total_expense_pages = ceil($total_expense_categories / $records_per_page);
if ($expense_page < 1) $expense_page = 1;
if ($expense_page > $total_expense_pages && $total_expense_pages > 0) $expense_page = $total_expense_pages;
$expense_offset = ($expense_page - 1) * $records_per_page;

$stmt_expense = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY {$expense_sort_by} {$expense_sort_dir} LIMIT ? OFFSET ?");
$stmt_expense->execute([$user_id, $records_per_page, $expense_offset]);
$expense_categories = $stmt_expense->fetchAll();
?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Kategori Pemasukan</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-nowrap">
                                <a href="?action=list&income_sort_by=name&income_sort_dir=<?= ($income_sort_by === 'name' && $income_sort_dir === 'ASC') ? 'DESC' : 'ASC' ?>&income_page=1" class="text-decoration-none text-dark">
                                    Nama
                                    <?php if ($income_sort_by === 'name') : ?>
                                        <?= $income_sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($income_categories) > 0) : ?>
                            <?php foreach ($income_categories as $category) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($category['name']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                            data-id="<?= $category['id'] ?>" 
                                            data-name="<?= htmlspecialchars($category['name']) ?>" 
                                            data-type="income">
                                            <i class="fas fa-edit me-1"></i>Ubah
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $category['id'] ?>">
                                            <i class="fas fa-trash me-1"></i>Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2" class="text-center">Tidak ada kategori pemasukan ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                <nav class="mb-2 mb-md-0">
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $income_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?action=list&income_page=<?= $income_page - 1 ?>&income_sort_by=<?= $income_sort_by ?>&income_sort_dir=<?= $income_sort_dir ?>">Sebelumnya</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_income_pages; $i++) : ?>
                            <li class="page-item <?= $i == $income_page ? 'active' : '' ?>">
                                <a class="page-link" href="?action=list&income_page=<?= $i ?>&income_sort_by=<?= $income_sort_by ?>&income_sort_dir=<?= $income_sort_dir ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $income_page >= $total_income_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?action=list&income_page=<?= $income_page + 1 ?>&income_sort_by=<?= $income_sort_by ?>&income_sort_dir=<?= $income_sort_dir ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
                <form method="GET" action="categories.php" class="d-flex align-items-center">
                    <input type="hidden" name="action" value="list">
                    <input type="hidden" name="income_sort_by" value="<?= $income_sort_by ?>">
                    <input type="hidden" name="income_sort_dir" value="<?= $income_sort_dir ?>">
                    <label for="income_jump_page" class="form-label me-2 mb-0">Lompat ke halaman:</label>
                    <input type="number" id="income_jump_page" name="income_page" class="form-control text-center me-2" style="width: 80px;" min="1" max="<?= max(1, $total_income_pages) ?>" value="<?= $income_page ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">Go</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Kategori Pengeluaran</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-nowrap">
                                <a href="?action=list&expense_sort_by=name&expense_sort_dir=<?= ($expense_sort_by === 'name' && $expense_sort_dir === 'ASC') ? 'DESC' : 'ASC' ?>&expense_page=1" class="text-decoration-none text-dark">
                                    Nama
                                    <?php if ($expense_sort_by === 'name') : ?>
                                        <?= $expense_sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($expense_categories) > 0) : ?>
                            <?php foreach ($expense_categories as $category) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($category['name']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                            data-id="<?= $category['id'] ?>" 
                                            data-name="<?= htmlspecialchars($category['name']) ?>" 
                                            data-type="expense">
                                            <i class="fas fa-edit me-1"></i>Ubah
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $category['id'] ?>">
                                            <i class="fas fa-trash me-1"></i>Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2" class="text-center">Tidak ada kategori pengeluaran ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                <nav class="mb-2 mb-md-0">
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $expense_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?action=list&expense_page=<?= $expense_page - 1 ?>&expense_sort_by=<?= $expense_sort_by ?>&expense_sort_dir=<?= $expense_sort_dir ?>">Sebelumnya</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_expense_pages; $i++) : ?>
                            <li class="page-item <?= $i == $expense_page ? 'active' : '' ?>">
                                <a class="page-link" href="?action=list&expense_page=<?= $i ?>&expense_sort_by=<?= $expense_sort_by ?>&expense_sort_dir=<?= $expense_sort_dir ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $expense_page >= $total_expense_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?action=list&expense_page=<?= $expense_page + 1 ?>&expense_sort_by=<?= $expense_sort_by ?>&expense_sort_dir=<?= $expense_sort_dir ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
                <form method="GET" action="categories.php" class="d-flex align-items-center">
                    <input type="hidden" name="action" value="list">
                    <input type="hidden" name="expense_sort_by" value="<?= $expense_sort_by ?>">
                    <input type="hidden" name="expense_sort_dir" value="<?= $expense_sort_dir ?>">
                    <label for="expense_jump_page" class="form-label me-2 mb-0">Lompat ke halaman:</label>
                    <input type="number" id="expense_jump_page" name="expense_page" class="form-control text-center me-2" style="width: 80px;" min="1" max="<?= max(1, $total_expense_pages) ?>" value="<?= $expense_page ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">Go</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Tambah Kategori Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories.php?action=add" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_category_name" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="add_category_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_category_type" class="form-label">Jenis</label>
                        <select class="form-select" id="add_category_type" name="type" required>
                            <option value="" disabled selected hidden>Pilih jenis</option>
                            <option value="income">Pemasukan</option>
                            <option value="expense">Pengeluaran</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Ubah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_category_name" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="modal_category_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_category_type" class="form-label">Jenis</label>
                        <select class="form-select" id="modal_category_type" name="type" required>
                            <option value="" disabled selected hidden>Pilih jenis</option>
                            <option value="income">Pemasukan</option>
                            <option value="expense">Pengeluaran</option>
                        </select>
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
                Apakah Anda yakin ingin menghapus kategori ini? Tindakan ini tidak dapat dibatalkan dan mungkin memengaruhi transaksi terkait.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="deleteCategoryLink" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editCategoryModal = document.getElementById('editCategoryModal');
    if (editCategoryModal) {
        editCategoryModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const categoryId = button.getAttribute('data-id');
            const categoryName = button.getAttribute('data-name');
            const categoryType = button.getAttribute('data-type');

            const modalTitle = editCategoryModal.querySelector('.modal-title');
            const modalCategoryNameInput = editCategoryModal.querySelector('#modal_category_name');
            const modalCategoryTypeSelect = editCategoryModal.querySelector('#modal_category_type');
            const editForm = editCategoryModal.querySelector('#editCategoryForm');

            modalTitle.textContent = 'Ubah Kategori: ' + categoryName;
            modalCategoryNameInput.value = categoryName;
            modalCategoryTypeSelect.value = categoryType;
            editForm.action = 'categories.php?action=edit&id=' + categoryId;
        });
    }

    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const categoryId = button.getAttribute('data-id');
            const deleteLink = deleteConfirmModal.querySelector('#deleteCategoryLink');
            deleteLink.href = 'categories.php?action=delete&id=' + categoryId;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>