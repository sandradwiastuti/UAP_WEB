<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Transactions';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$transaction_id = $_GET['id'] ?? null;

$error = '';
$success = '';

$stmt_acc_check = $pdo->prepare("SELECT id FROM accounts WHERE user_id = ? LIMIT 1");
$stmt_acc_check->execute([$user_id]);
$user_has_account = $stmt_acc_check->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_has_account) {
    $amount = str_replace('.', '', $_POST['amount']);

    $type = $_POST['type'];
    $category_id = $_POST['category_id'];
    $transaction_date = $_POST['transaction_date'];
    $description = $_POST['description'];
    $account_id = $_POST['account_id'];

    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, account_id, category_id, type, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $account_id, $category_id, $type, $amount, $transaction_date, $description]);
    } elseif ($action == 'edit' && $transaction_id) {
        $stmt = $pdo->prepare("UPDATE transactions SET account_id = ?, type = ?, amount = ?, category_id = ?, transaction_date = ?, description = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $type, $amount, $category_id, $transaction_date, $description, $transaction_id, $user_id]);
    }
    header("Location: transactions.php");
    exit();
}

if ($action == 'delete' && $transaction_id) {
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    header("Location: transactions.php");
    exit();
}

include '../includes/header.php';

if ($action == 'add' || $action == 'edit') {
    if (!$user_has_account) {
        echo '<div class="alert alert-warning"><strong>Action Required:</strong> You must have at least one account to manage transactions. Please <a href="accounts.php?action=add" class="alert-link">add an account</a> first.</div>';
    } else {
        $stmt_cat = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? OR user_id = 1 ORDER BY name");
        $stmt_cat->execute([$user_id]);
        $all_categories = $stmt_cat->fetchAll();
        
        $stmt_accounts = $pdo->prepare("SELECT id, name FROM accounts WHERE user_id = ? ORDER BY name");
        $stmt_accounts->execute([$user_id]);
        $user_accounts = $stmt_accounts->fetchAll();

        $current_tx = null;
        if ($action == 'edit' && $transaction_id) {
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$transaction_id, $user_id]);
            $current_tx = $stmt->fetch();
        }
?>
    <div id="transaction-form-container" data-categories='<?= json_encode($all_categories) ?>' data-current-category="<?= $current_tx['category_id'] ?? '' ?>">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="mb-0"><?= ucfirst($action) ?> Transaction</h3>
            </div>
            <div class="card-body">
                <form action="transactions.php?action=<?= $action ?><?= $transaction_id ? '&id='.$transaction_id : '' ?>" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="account_id" class="form-label">Account</label>
                            <select name="account_id" id="account_id" class="form-select" required>
                                <?php foreach($user_accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" <?= ($current_tx && $current_tx['account_id'] == $account['id']) ? 'selected' : '' ?>><?= htmlspecialchars($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select name="type" id="type" class="form-select" required>
                                <option value="expense" <?= ($current_tx && $current_tx['type'] == 'expense') ? 'selected' : '' ?>>Expense</option>
                                <option value="income" <?= ($current_tx && $current_tx['type'] == 'income') ? 'selected' : '' ?>>Income</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">Select transaction type first</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="text" inputmode="numeric" class="form-control" name="amount" id="amount" value="<?= htmlspecialchars($current_tx['amount'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="transaction_date" class="form-label">Date</label>
                            <input type="date" class="form-control" name="transaction_date" id="transaction_date" value="<?= htmlspecialchars($current_tx['transaction_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="1"><?= htmlspecialchars($current_tx['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-add-transaction">Save Transaction</button>
                    <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
<?php
    } 
} else { 
    $valid_sort_columns = [
        'date' => 't.transaction_date',
        'account' => 'a.name',
        'category' => 'c.name',
        'type' => 't.type',
        'amount' => 't.amount'
    ];
    $sort_by = $_GET['sort_by'] ?? 'date';
    $sort_dir = $_GET['sort_dir'] ?? 'DESC';

    if (!array_key_exists($sort_by, $valid_sort_columns)) {
        $sort_by = 'date';
    }
    if (!in_array(strtoupper($sort_dir), ['ASC', 'DESC'])) {
        $sort_dir = 'DESC';
    }
    $order_by_clause = $valid_sort_columns[$sort_by] . ' ' . $sort_dir;

    $records_per_page = 20;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) {
        $current_page = 1;
    }

    $filter_type = $_GET['filter_type'] ?? 'all'; // 'all', 'expense', 'income'
    $filter_category_id = $_GET['filter_category_id'] ?? '';
    $filter_amount_operator = $_GET['filter_amount_operator'] ?? ''; // 'less_equal', 'more_equal'
    $filter_amount_value = $_GET['filter_amount_value'] ?? '';

    $where_clauses = ["t.user_id = ?"];
    $sql_params = [$user_id];

    if ($filter_type != 'all') {
        $where_clauses[] = "t.type = ?";
        $sql_params[] = $filter_type;
    }

    if (!empty($filter_category_id)) {
        $where_clauses[] = "t.category_id = ?";
        $sql_params[] = $filter_category_id;
    }

    if (!empty($filter_amount_operator) && is_numeric($filter_amount_value)) {
        if ($filter_amount_operator == 'less_equal') {
            $where_clauses[] = "t.amount <= ?";
        } elseif ($filter_amount_operator == 'more_equal') {
            $where_clauses[] = "t.amount >= ?";
        }
        $sql_params[] = $filter_amount_value;
    }

    $where_condition = implode(' AND ', $where_clauses);

    $stmt_filter_cat = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? OR user_id = 1 ORDER BY name");
    $stmt_filter_cat->execute([$user_id]);
    $all_filter_categories = $stmt_filter_cat->fetchAll();

    $sql_total_transactions = "
        SELECT COUNT(*) AS total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        JOIN accounts a ON t.account_id = a.id
        WHERE {$where_condition}
    ";
    $stmt_total_transactions = $pdo->prepare($sql_total_transactions);
    $stmt_total_transactions->execute($sql_params);
    $total_transactions = $stmt_total_transactions->fetchColumn();
    $total_pages = ceil($total_transactions / $records_per_page);

    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    } elseif ($total_pages == 0) {
        $current_page = 1;
    }
    $offset = ($current_page - 1) * $records_per_page;

    $sql_transactions = "
        SELECT t.id, t.transaction_date, t.type, t.amount, t.description, c.name as category_name, a.name as account_name
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        JOIN accounts a ON t.account_id = a.id
        WHERE {$where_condition}
        ORDER BY {$order_by_clause}
        LIMIT ? OFFSET ?
    ";
    $stmt_transactions = $pdo->prepare($sql_transactions);
    $stmt_transactions->execute(array_merge($sql_params, [$records_per_page, $offset]));
    $transactions = $stmt_transactions->fetchAll();
?>
    <style>
        .btn-add-transaction {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-add-transaction:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #fff;
        }

        .table-transactions .table-light {
            background-color: #f8d7da;
            background: linear-gradient(to right, #ffffff, #f8d7da);
        }

        .table-transactions .table-light th {
            color: #842029;
            border-color: #dc3545;
        }

        .table-transactions tbody tr {
            transition: background-color 0.3s ease;
        }

        .table-transactions tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .table-transactions tbody tr:nth-child(even) {
            background-color: #ffeaea;
        }

        .table-transactions tbody tr:hover {
            background-color: #f5c2c7;
        }

        .table-transactions .badge.bg-success {
            background-color: #28a745 !important;
        }

        .table-transactions .badge.bg-danger {
            background-color: #dc3545 !important;
        }

        .table-transactions .text-success {
            color: #28a745 !important;
        }

        .table-transactions .text-danger {
            color: #dc3545 !important;
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

        .filter-card .card-header {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
        }

        .filter-card .btn-filter {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        .filter-card .btn-filter:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .filter-card .btn-reset {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #fff;
        }
        .filter-card .btn-reset:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Transactions</h1>
        <a href="transactions.php?action=add" class="btn btn-primary btn-add-transaction">Add New</a>
    </div>

    <div class="row">
        <div class="col-lg-9">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-transactions">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'date', 'sort_dir' => ($sort_by === 'date' && $sort_dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1])) ?>" class="text-decoration-none text-dark">
                                            Date
                                            <?php if ($sort_by === 'date') : ?>
                                                <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-nowrap">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'account', 'sort_dir' => ($sort_by === 'account' && $sort_dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1])) ?>" class="text-decoration-none text-dark">
                                            Account
                                            <?php if ($sort_by === 'account') : ?>
                                                <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-nowrap">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'category', 'sort_dir' => ($sort_by === 'category' && $sort_dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1])) ?>" class="text-decoration-none text-dark">
                                            Category
                                            <?php if ($sort_by === 'category') : ?>
                                                <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-nowrap">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'type', 'sort_dir' => ($sort_by === 'type' && $sort_dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1])) ?>" class="text-decoration-none text-dark">
                                            Type
                                            <?php if ($sort_by === 'type') : ?>
                                                <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-end text-nowrap">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'amount', 'sort_dir' => ($sort_by === 'amount' && $sort_dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1])) ?>" class="text-decoration-none text-dark">
                                            Amount
                                            <?php if ($sort_by === 'amount') : ?>
                                                <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($transactions) > 0) : ?>
                                    <?php foreach ($transactions as $row) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                            <td><?= htmlspecialchars($row['account_name']) ?></td>
                                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?= $row['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= ucfirst($row['type']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?= number_format($row['amount'], 0, ',', '.') ?></td>
                                            <td class="text-end">
                                                <a href="transactions.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                                <a href="transactions.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No transactions found matching your criteria. <a href="transactions.php?action=add">Add one now</a>.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                    <?php
                    $pagination_params = $_GET;
                    unset($pagination_params['page']);
                    ?>
                    <nav class="mb-2 mb-md-0">
                        <ul class="pagination mb-0">
                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $current_page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($pagination_params, ['page' => $current_page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <form method="GET" action="transactions.php" class="d-flex align-items-center">
                        <?php
                        $form_params = $_GET;
                        unset($form_params['page']);
                        foreach ($form_params as $key => $value) :
                        ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endforeach; ?>
                        <label for="jump_page" class="form-label me-2 mb-0">Go to page:</label>
                        <input type="number" id="jump_page" name="page" class="form-control text-center me-2" style="width: 80px;" min="1" max="<?= max(1, $total_pages) ?>" value="<?= $current_page ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">Go</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card shadow-sm filter-card">
                <div class="card-header">
                    <h5 class="mb-0">Filter Transactions</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="transactions.php">
                        <div class="mb-3">
                            <label class="form-label">Show Type:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_type" id="filter_type_all" value="all" <?= ($filter_type == 'all') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filter_type_all">All</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_type" id="filter_type_expense" value="expense" <?= ($filter_type == 'expense') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filter_type_expense">Expenses Only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_type" id="filter_type_income" value="income" <?= ($filter_type == 'income') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filter_type_income">Income Only</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="filter_category_id" class="form-label">By Category:</label>
                            <select name="filter_category_id" id="filter_category_id" class="form-select">
                                <option value="">Select a category</option>
                                <optgroup label="Income Categories">
                                    <?php foreach ($all_filter_categories as $cat) : ?>
                                        <?php if ($cat['type'] == 'income') : ?>
                                            <option value="<?= $cat['id'] ?>" <?= ($filter_category_id == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Expense Categories">
                                    <?php foreach ($all_filter_categories as $cat) : ?>
                                        <?php if ($cat['type'] == 'expense') : ?>
                                            <option value="<?= $cat['id'] ?>" <?= ($filter_category_id == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">By Amount:</label>
                            <div class="input-group mb-2">
                                <select class="form-select" name="filter_amount_operator" id="filter_amount_operator" style="max-width: 130px;">
                                    <option value="">Operator</option>
                                    <option value="less_equal" <?= ($filter_amount_operator == 'less_equal') ? 'selected' : '' ?>>&lt;=</option>
                                    <option value="more_equal" <?= ($filter_amount_operator == 'more_equal') ? 'selected' : '' ?>>&gt;=</option>
                                </select>
                                <input type="number" name="filter_amount_value" id="filter_amount_value" class="form-control" placeholder="Amount" value="<?= htmlspecialchars($filter_amount_value) ?>">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-filter">Apply Filters</button>
                            <a href="transactions.php" class="btn btn-reset">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
} 
include '../includes/footer.php';
?>