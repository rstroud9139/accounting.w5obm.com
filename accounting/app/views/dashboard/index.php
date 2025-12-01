<div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between shadow-sm border-0">
    <div class="me-md-3">
        <strong>Need the full Accounting Dashboard?</strong>
        <span class="ms-md-2 text-muted">Jump back to the legacy dashboard for cash, assets, and quick-post tools.</span>
    </div>
    <div class="mt-3 mt-md-0">
        <a class="btn btn-primary" href="/accounting/dashboard.php">
            <i class="fas fa-chart-line me-1"></i>Open Dashboard
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Year-to-Date Income</h5>
                <div class="display-6 text-success">$<?= number_format($totalIncome, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Year-to-Date Expenses</h5>
                <div class="display-6 text-danger">$<?= number_format($totalExpense, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Net</h5>
                <?php $net = ($totalIncome - $totalExpense); ?>
                <div class="display-6 <?= $net >= 0 ? 'text-primary' : 'text-warning' ?>">$<?= number_format($net, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Income vs Expense (Last 12 Months)</h5>
                <a class="btn btn-sm btn-outline-primary" href="<?= route('transactions') ?>">View Transactions</a>
            </div>
            <div class="card-body">
                <canvas id="line12"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Income by Category (YTD)</h5>
                <a class="btn btn-sm btn-outline-success" href="/accounting/reports/income_report.php?generate=1">Income Report</a>
            </div>
            <div class="card-body">
                <canvas id="pieYTD"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Fetch data endpoints for charts
    (async function() {
        let labels = [],
            income = [],
            expense = [];
        try {
            const r = await fetch('/accounting/app/api/metrics_income_expense_12mo.php', {
                credentials: 'same-origin'
            });
            if (r.ok) {
                const js = await r.json();
                labels = js.labels || [];
                income = js.income || [];
                expense = js.expense || [];
            }
        } catch (e) {
            /* ignore */
        }

        if (!labels.length) {
            labels = Array.from({
                length: 12
            }, (_, i) => new Date(0, i).toLocaleString('en', {
                month: 'short'
            }));
        }

        new Chart(document.getElementById('line12'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                        label: 'Income',
                        data: income,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.15)',
                        tension: .3
                    },
                    {
                        label: 'Expense',
                        data: expense,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220,38,38,0.15)',
                        tension: .3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        const ctxPie = document.getElementById('pieYTD');
        let pieLabels = [],
            pieValues = [];
        try {
            const y = new Date().getFullYear();
            const r2 = await fetch('/accounting/app/api/metrics_ytd_income_by_category.php?year=' + y, {
                credentials: 'same-origin'
            });
            if (r2.ok) {
                const js2 = await r2.json();
                pieLabels = js2.labels || [];
                pieValues = js2.values || [];
            }
        } catch (e) {
            /* ignore */
        }
        if (!pieLabels.length) {
            pieLabels = ['Donations', 'Dues', 'Events', 'Grants', 'Other'];
            pieValues = [6200, 1800, 900, 1500, 600];
        }

        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieValues,
                    backgroundColor: ['#22c55e', '#3b82f6', '#a855f7', '#f97316', '#eab308']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    })();
</script>