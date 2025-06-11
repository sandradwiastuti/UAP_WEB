<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Reports';
$user_id = $_SESSION['user_id'];

// --- Data Fetching ---

// 1. Determine the filtering period (month and year)
// Default to the current month if not specified
$selected_month = $_GET['month'] ?? date('Y-m');
$year = date('Y', strtotime($selected_month));
$month_name = date('F', strtotime($selected_month));

// 2. Fetch all transactions for the selected month to calculate summaries and populate the table
$sql_transactions = "
    SELECT t.transaction_date, t.type, t.amount, t.description, c.name as category_name, a.name as account_name
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = ? AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    ORDER BY t.transaction_date ASC
";
$stmt_transactions = $pdo->prepare($sql_transactions);
$stmt_transactions->execute([$user_id, $selected_month]);
$transactions = $stmt_transactions->fetchAll();

// 3. Calculate summary metrics from the fetched transactions
$total_income = 0;
$total_expense = 0;
foreach ($transactions as $tx) {
    if ($tx['type'] === 'income') {
        $total_income += $tx['amount'];
    } else {
        $total_expense += $tx['amount'];
    }
}

// 4. Fetch data for the expense chart (expenses grouped by category)
$sql_chart = "
    SELECT c.name as category_name, SUM(t.amount) as total_amount
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    GROUP BY c.name
    ORDER BY total_amount DESC
";
$stmt_chart = $pdo->prepare($sql_chart);
$stmt_chart->execute([$user_id, $selected_month]);
$chart_data = $stmt_chart->fetchAll();

// 5. Prepare chart data for JavaScript
$chart_labels = [];
$chart_values = [];
foreach ($chart_data as $data) {
    $chart_labels[] = $data['category_name'];
    $chart_values[] = $data['total_amount'];
}


include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="dashboard-title">Financial Report</h1>
    <form method="GET" action="reports.php" class="d-flex align-items-center">
        <label for="month" class="form-label me-2 mb-0">Select Month:</label>
        <input type="month" id="month" name="month" class="form-control me-2" value="<?= htmlspecialchars($selected_month) ?>">
        <button type="submit" class="btn btn-primary">View</button>
    </form>
</div>

<h2 class="mb-4">Summary for <?= htmlspecialchars($month_name) . ' ' . htmlspecialchars($year) ?></h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card summary-card card-income">
            <div class="card-body">
                <h5 class="card-title">Income</h5>
                <p class="card-text">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card card-expense">
            <div class="card-body">
                <h5 class="card-title">Expense</h5>
                <p class="card-text">Rp <?= number_format($total_expense, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card card-balance">
            <div class="card-body">
                <h5 class="card-title">Net Flow</h5>
                <p class="card-text">Rp <?= number_format($total_income - $total_expense, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Expense Breakdown by Category</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if (!empty($chart_data)) : ?>
                    <canvas id="expenseChart"></canvas>
                <?php else : ?>
                    <p class="text-muted">No expense data for this period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
             <div class="card-header">
                <h5 class="mb-0">Transaction Details</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transactions)) : ?>
                                <?php foreach ($transactions as $transaction) : ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($transaction['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($transaction['category_name']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= $transaction['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= ucfirst($transaction['type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end <?= $transaction['type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                            <?= ($transaction['type'] == 'income' ? '+' : '-') ?> Rp <?= number_format($transaction['amount'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="text-center p-4">No transactions recorded for this period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartLabels = <?= json_encode($chart_labels) ?>;
    const chartValues = <?= json_encode($chart_values) ?>;

    if (chartLabels.length > 0) {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        const expenseChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Expenses',
                    data: chartValues,
                    backgroundColor: [
                        '#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f',
                        '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c',
                        '#689f38', '#afb42b', '#fbc02d', '#ffa000', '#f57c00'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>