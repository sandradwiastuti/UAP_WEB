<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Accounts';
$user_id = $_SESSION['user_id'];

// Default action is to list accounts
$action = $_GET['action'] ?? 'list';
$account_id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle POST requests for adding/editing accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $initial_balance = $_POST['initial_balance'] ?? 0;

    if (empty($name)) {
        $error = 'Account name is required.';
    } else {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance, currency) VALUES (?, ?, ?, 'IDR')");
            $stmt->execute([$user_id, $name, $initial_balance]);
        } elseif ($action == 'edit' && $account_id) {
            $stmt = $pdo->prepare("UPDATE accounts SET name = ?, initial_balance = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $initial_balance, $account_id, $user_id]);
        }
        header("Location: accounts.php");
        exit();
    }
}

// Handle delete requests
if ($action == 'delete' && $account_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $user_id]);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Cannot delete account because it has existing transactions.";
    }
    header("Location: accounts.php");
    exit();
}

include '../includes/header.php';

// Display form for adding or editing
if ($action == 'add' || $action == 'edit') :
    $current_account = null;
    if ($action == 'edit' && $account_id) {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $user_id]);
        $current_account = $stmt->fetch();
        if (!$current_account) {
            die('Error: Account not found.');
        }
    }
?>
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0"><?= ucfirst($action) ?> Account</h3>
        </div>
        <div class="card-body">
            <form action="accounts.php?action=<?= $action ?><?= $account_id ? '&id=' . $account_id : '' ?>" method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">Account Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($current_account['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="initial_balance" class="form-label">Initial Balance (IDR)</label>
                    <input type="number" step="1" class="form-control" id="initial_balance" name="initial_balance" value="<?= htmlspecialchars($current_account['initial_balance'] ?? '0') ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Account</button>
                <a href="accounts.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

<?php else : // Default list view ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="dashboard-title">Manage Accounts</h1>
        <a href="accounts.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Add New Account</a>
    </div>

    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Initial Balance</th>
                        <th scope="col">Current Balance</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
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
                            <tr>
                                <td><?= htmlspecialchars($account['name']) ?></td>
                                <td><?= htmlspecialchars($account['currency']) ?> <?= number_format($account['initial_balance'], 0, ',', '.') ?></td>
                                <td><strong><?= htmlspecialchars($account['currency']) ?> <?= number_format($current_balance, 0, ',', '.') ?></strong></td>
                                <td class="text-end">
                                    <a href="accounts.php?action=edit&id=<?= $account['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="accounts.php?action=delete&id=<?= $account['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this account? This cannot be undone.')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </a>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                    else :
                        ?>
                        <tr>
                            <td colspan="4" class="text-center">No accounts found. Click 'Add New Account' to get started.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>