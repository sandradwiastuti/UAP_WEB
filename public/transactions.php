<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Transactions';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$transaction_id = $_GET['id'] ?? null;

// Check if user has at least one account BEFORE handling any actions
$stmt_acc_check = $pdo->prepare("SELECT id FROM accounts WHERE user_id = ? LIMIT 1");
$stmt_acc_check->execute([$user_id]);
$user_has_account = $stmt_acc_check->fetch();

// Handle POST requests for adding/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_has_account) {
    // Sanitize the amount input by removing dots
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

// Handle delete request
if ($action == 'delete' && $transaction_id) {
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    header("Location: transactions.php");
    exit();
}

include '../includes/header.php';

// If in add/edit mode, check for accounts first.
if ($action == 'add' || $action == 'edit') {
    if (!$user_has_account) {
        echo '<div class="alert alert-warning"><strong>Action Required:</strong> You must have at least one account to manage transactions. Please <a href="accounts.php?action=add" class="alert-link">add an account</a> first.</div>';
    } else {
        // Fetch categories
        $stmt_cat = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? OR user_id = 1 ORDER BY name");
        $stmt_cat->execute([$user_id]);
        $all_categories = $stmt_cat->fetchAll();
        
        // Fetch user's accounts
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
        <h3><?= ucfirst($action) ?> Transaction</h3>
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
            <button type="submit" class="btn btn-primary">Save Transaction</button>
            <a href="transactions.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
<?php
    } 
} else { 
?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Transactions</h1>
        <a href="transactions.php?action=add" class="btn btn-primary">Add New</a>
    </div>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Account</th>
                <th>Category</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->prepare("
                SELECT t.id, t.transaction_date, t.type, t.amount, t.description, c.name as category_name, a.name as account_name
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                JOIN accounts a ON t.account_id = a.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC, t.id DESC
            ");
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll();
             if (count($transactions) > 0) :
                foreach ($transactions as $row) :
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                    <td><?= htmlspecialchars($row['account_name']) ?></td>
                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                    <td>
                        <span class="badge <?= $row['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                            <?= ucfirst($row['type']) ?>
                        </span>
                    </td>
                    <td class="text-end"><?= number_format($row['amount'], 0, ',', '.') ?></td>
                    <td>
                        <a href="transactions.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="transactions.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php
                endforeach;
            else :
            ?>
                 <tr>
                    <td colspan="6" class="text-center">No transactions yet. <a href="transactions.php?action=add">Add one now</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php
} 
include '../includes/footer.php';
?>