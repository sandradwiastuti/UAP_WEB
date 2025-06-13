<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Reports';
$user_id = $_SESSION['user_id'];

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

$valid_sort_columns = [
    'date' => 't.transaction_date',
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
$offset = ($current_page - 1) * $records_per_page;

$sql_params = [$user_id, $date_from, $date_to];
$sql_date_condition = "t.transaction_date BETWEEN ? AND ?";

$sql_total_transactions = "
    SELECT COUNT(*) AS total
    FROM transactions t
    WHERE t.user_id = ? AND {$sql_date_condition}
";
$stmt_total_transactions = $pdo->prepare($sql_total_transactions);
$stmt_total_transactions->execute($sql_params);
$total_transactions = $stmt_total_transactions->fetchColumn();
$total_pages = ceil($total_transactions / $records_per_page);

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $records_per_page;
} elseif ($total_pages == 0) {
    $current_page = 1;
    $offset = 0;
}

$sql_transactions = "
    SELECT t.transaction_date, t.type, t.amount, t.description, c.name as category_name, a.name as account_name
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = ? AND {$sql_date_condition}
    ORDER BY {$order_by_clause}
    LIMIT ? OFFSET ?
";
$stmt_transactions = $pdo->prepare($sql_transactions);
$stmt_transactions->execute(array_merge($sql_params, [$records_per_page, $offset]));
$transactions = $stmt_transactions->fetchAll();

$sql_summary_income = "
    SELECT SUM(t.amount)
    FROM transactions t
    WHERE t.user_id = ? AND t.type = 'income' AND {$sql_date_condition}
";
$stmt_summary_income = $pdo->prepare($sql_summary_income);
$stmt_summary_income->execute($sql_params);
$total_income = $stmt_summary_income->fetchColumn() ?: 0;

$sql_summary_expense = "
    SELECT SUM(t.amount)
    FROM transactions t
    WHERE t.user_id = ? AND t.type = 'expense' AND {$sql_date_condition}
";
$stmt_summary_expense = $pdo->prepare($sql_summary_expense);
$stmt_summary_expense->execute($sql_params);
$total_expense = $stmt_summary_expense->fetchColumn() ?: 0;

$sql_expense_chart = "
    SELECT c.name as category_name, SUM(t.amount) as total_amount
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND {$sql_date_condition}
    GROUP BY c.name
    ORDER BY total_amount DESC
";
$stmt_expense_chart = $pdo->prepare($sql_expense_chart);
$stmt_expense_chart->execute($sql_params);
$expense_chart_data_raw = $stmt_expense_chart->fetchAll();

$expense_chart_labels = [];
$expense_chart_values = [];

if ($total_expense > 0) {
    foreach ($expense_chart_data_raw as $row) {
        $percentage = ($row['total_amount'] / $total_expense) * 100;
        $expense_chart_labels[] = htmlspecialchars($row['category_name']) . ' (' . number_format($percentage, 1) . '%)';
        $expense_chart_values[] = $row['total_amount'];
    }
} else {
    $expense_chart_labels = ['No Expenses'];
    $expense_chart_values = [1];
}

$sql_income_chart = "
    SELECT c.name as category_name, SUM(t.amount) as total_amount
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'income' AND {$sql_date_condition}
    GROUP BY c.name
    ORDER BY total_amount DESC
";
$stmt_income_chart = $pdo->prepare($sql_income_chart);
$stmt_income_chart->execute($sql_params);
$income_chart_data_raw = $stmt_income_chart->fetchAll();

$income_chart_labels = [];
$income_chart_values = [];

if ($total_income > 0) {
    foreach ($income_chart_data_raw as $row) {
        $percentage = ($row['total_amount'] / $total_income) * 100;
        $income_chart_labels[] = htmlspecialchars($row['category_name']) . ' (' . number_format($percentage, 1) . '%)';
        $income_chart_values[] = $row['total_amount'];
    }
} else {
    $income_chart_labels = ['No Income'];
    $income_chart_values = [1];
}

$report_period_label = "from <strong>" . htmlspecialchars(date('d M Y', strtotime($date_from))) . "</strong> to <strong>" . htmlspecialchars(date('d M Y', strtotime($date_to))) . "</strong>";

include '../includes/header.php';
?>

<style>
    .table-red-white .table-light {
        background-color: #f8d7da;
        background: linear-gradient(to right, #ffffff, #f8d7da);
    }

    .table-red-white .table-light th {
        color: #842029;
        border-color: #dc3545;
    }

    .table-red-white tbody tr {
        transition: background-color 0.3s ease;
    }

    .table-red-white tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .table-red-white tbody tr:nth-child(even) {
        background-color: #ffeaea;
    }

    .table-red-white tbody tr:hover {
        background-color: #f5c2c7;
    }

    .table-red-white .badge.bg-success {
        background-color: #28a745 !important;
    }

    .table-red-white .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .table-red-white .text-success {
        color: #28a745 !important;
    }

    .table-red-white .text-danger {
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

    .btn-view-report {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .btn-view-report:hover {
        background-color: #c82333;
        border-color: #bd2130;
        color: #fff;
    }

    .form-control[type="date"] {
        border-color: #dc3545;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="dashboard-title">Financial Report</h1>
    <form method="GET" action="reports.php" class="d-flex align-items-center">
        <label for="date_from" class="form-label me-2 mb-0 text-dark">From:</label>
        <input type="date" id="date_from" name="date_from" class="form-control me-2" value="<?= htmlspecialchars($date_from) ?>">
        
        <label for="date_to" class="form-label me-2 mb-0 text-dark">To:</label>
        <input type="date" id="date_to" name="date_to" class="form-control me-2" value="<?= htmlspecialchars($date_to) ?>">
        
        <button type="submit" class="btn btn-view-report">View Report</button>
    </form>
</div>

<h2 class="mb-4">Summary <?= $report_period_label ?></h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card summary-card card-income">
            <div class="card-body">
                <h5 class="card-title">Total Income</h5>
                <p class="card-text">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card card-expense">
            <div class="card-body">
                <h5 class="card-title">Total Expense</h5>
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
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Expense Breakdown by Category</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center" style="max-height: 400px;">
                <?php if (!empty($expense_chart_data_raw) && $total_expense > 0) : ?>
                    <canvas id="expenseChart" style="max-width: 100%; max-height: 100%;"></canvas>
                <?php else : ?>
                    <p class="text-muted">No expense data for this period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Income Breakdown by Category</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center" style="max-height: 400px;">
                <?php if (!empty($income_chart_data_raw) && $total_income > 0) : ?>
                    <canvas id="incomeChart" style="max-width: 100%; max-height: 100%;"></canvas>
                <?php else : ?>
                    <p class="text-muted">No income data for this period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm h-100">
             <div class="card-header">
                <h5 class="mb-0">All Transactions This Period</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-red-white">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">
                                    <a href="?<?=
                                        http_build_query(array_merge($_GET, [
                                            'sort_by' => 'date',
                                            'sort_dir' => ($sort_by === 'date' && $sort_dir === 'ASC') ? 'DESC' : 'ASC',
                                            'page' => 1
                                        ]))
                                    ?>" class="text-decoration-none text-dark">
                                        Date
                                        <?php if ($sort_by === 'date') : ?>
                                            <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="text-nowrap">
                                    <a href="?<?=
                                        http_build_query(array_merge($_GET, [
                                            'sort_by' => 'category',
                                            'sort_dir' => ($sort_by === 'category' && $sort_dir === 'ASC') ? 'DESC' : 'ASC',
                                            'page' => 1
                                        ]))
                                    ?>" class="text-decoration-none text-dark">
                                        Category
                                        <?php if ($sort_by === 'category') : ?>
                                            <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="text-nowrap">
                                    <a href="?<?=
                                        http_build_query(array_merge($_GET, [
                                            'sort_by' => 'type',
                                            'sort_dir' => ($sort_by === 'type' && $sort_dir === 'ASC') ? 'DESC' : 'ASC',
                                            'page' => 1
                                        ]))
                                    ?>" class="text-decoration-none text-dark">
                                        Type
                                        <?php if ($sort_by === 'type') : ?>
                                            <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="text-end text-nowrap">
                                    <a href="?<?=
                                        http_build_query(array_merge($_GET, [
                                            'sort_by' => 'amount',
                                            'sort_dir' => ($sort_by === 'amount' && $sort_dir === 'ASC') ? 'DESC' : 'ASC',
                                            'page' => 1
                                        ]))
                                    ?>" class="text-decoration-none text-dark">
                                        Amount
                                        <?php if ($sort_by === 'amount') : ?>
                                            <?= $sort_dir === 'ASC' ? ' &#9650;' : ' &#9660;' ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
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
                <form method="GET" action="reports.php" class="d-flex align-items-center">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const expenseChartLabels = <?= json_encode($expense_chart_labels) ?>;
    const expenseChartValues = <?= json_encode($expense_chart_values) ?>;
    const incomeChartLabels = <?= json_encode($income_chart_labels) ?>;
    const incomeChartValues = <?= json_encode($income_chart_values) ?>;

    const commonChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += 'Rp ' + context.formattedValue;
                        return label;
                    }
                }
            }
        }
    };

    const colors = [
        '#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f',
        '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c',
        '#689f38', '#afb42b', '#fbc02d', '#ffa000', '#f57c00'
    ];

    if (expenseChartLabels.length > 0 && expenseChartValues.length > 0 && !(expenseChartLabels.length === 1 && expenseChartLabels[0] === 'No Expenses')) {
        const ctxExpense = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctxExpense, {
            type: 'doughnut',
            data: {
                labels: expenseChartLabels,
                datasets: [{
                    label: 'Expenses',
                    data: expenseChartValues,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: commonChartOptions
        });
    }

    if (incomeChartLabels.length > 0 && incomeChartValues.length > 0 && !(incomeChartLabels.length === 1 && incomeChartLabels[0] === 'No Income')) {
        const ctxIncome = document.getElementById('incomeChart').getContext('2d');
        new Chart(ctxIncome, {
            type: 'doughnut',
            data: {
                labels: incomeChartLabels,
                datasets: [{
                    label: 'Income',
                    data: incomeChartValues,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: commonChartOptions
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>