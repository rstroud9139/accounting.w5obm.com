<?php
if (!function_exists('route')) {
	function route(string $name, array $params = []): string
	{
		$query = http_build_query(array_merge(['route' => $name], $params));
		return '/accounting/app/index.php?' . $query;
	}
}

$currentRoute = $currentRoute ?? ($_GET['route'] ?? 'dashboard');
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = $requestUri ? (parse_url($requestUri, PHP_URL_PATH) ?: '') : '';
$isLegacyDashboard = $requestPath === '/accounting/dashboard.php';
$reportsActive = strpos($requestUri, '/accounting/reports/') !== false;
$toolsRoutes = ['import', 'import_upload', 'import_commit', 'import_last', 'category_map', 'category_map_save', 'category_map_save_inline'];
$batchRoutes = ['batch_reports', 'batch_reports_run'];
$migrationRoutes = ['migrations', 'migrations_run'];
$dataRoutes = ['accounts', 'account_register', 'transactions', 'transaction_new', 'transaction_create', 'reconciliation', 'reconciliation_review', 'reconciliation_commit', 'reconciliation_view'];
$navIsActive = function ($routes) use ($currentRoute) {
	if (is_array($routes)) {
		return in_array($currentRoute, $routes, true);
	}
	return $currentRoute === $routes;
};
$dataMenuActive = $navIsActive($dataRoutes);
$toolsMenuActive = $navIsActive(array_merge($toolsRoutes, $batchRoutes, $migrationRoutes));
$dbLabel = $_ENV['LOCAL_ACC_DB_NAME'] ?? $_ENV['ACC_DB_NAME'] ?? 'accounting_w5obm';
?>
<nav class="accounting-nav navbar navbar-expand-lg navbar-dark no-print" role="navigation" aria-label="Accounting navigation">
	<div class="container-fluid">
		<a class="navbar-brand d-flex align-items-center" href="/accounting/dashboard.php">
			<img src="/accounting/images/badges/club_logo.png" alt="W5OBM logo" width="40" height="40" class="me-2 rounded shadow-sm">
			<span class="fw-semibold">W5OBM Accounting</span>
		</a>
		<button class="navbar-toggler accounting-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#accountingNav" aria-controls="accountingNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="accountingNav">
			<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				<li class="nav-item">
					<a class="nav-link <?= $isLegacyDashboard ? 'active' : ''; ?>" href="/accounting/dashboard.php">
						<i class="fas fa-chart-line me-1"></i>Dashboard
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= !$isLegacyDashboard && $navIsActive('dashboard') ? 'active' : ''; ?>" href="<?= route('dashboard'); ?>">
						<i class="fas fa-compass me-1"></i>Command Center
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $navIsActive('accounts') ? 'active' : ''; ?>" href="<?= route('accounts'); ?>">
						<i class="fas fa-book me-1"></i>Accounts
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $navIsActive(['account_register', 'account_register_export_csv']) ? 'active' : ''; ?>" href="<?= route('account_register'); ?>">
						<i class="fas fa-table me-1"></i>Ledger
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $navIsActive(['reconciliation', 'reconciliation_review', 'reconciliation_commit', 'reconciliation_view']) ? 'active' : ''; ?>" href="<?= route('reconciliation'); ?>">
						<i class="fas fa-scale-balanced me-1"></i>Reconcile
					</a>
				</li>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle <?= $dataMenuActive ? 'active' : ''; ?>" href="#" id="accountingDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-database me-1"></i>Data &amp; Tables
					</a>
					<ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="accountingDataDropdown">
						<li class="dropdown-header">Core Tables</li>
						<li><a class="dropdown-item" href="<?= route('accounts'); ?>">Chart of Accounts</a></li>
						<li><a class="dropdown-item" href="<?= route('transactions'); ?>">Journal Entries</a></li>
						<li><a class="dropdown-item" href="<?= route('account_register'); ?>">Account Registers</a></li>
						<li><a class="dropdown-item" href="<?= route('reconciliation'); ?>">Reconciliations</a></li>
						<li><a class="dropdown-item" href="/accounting/assets/list.php">Assets Workspace</a></li>
						<li>
							<hr class="dropdown-divider">
						</li>
						<li class="dropdown-header">Reference Tables</li>
						<li><a class="dropdown-item" href="<?= route('category_map'); ?>">Category Mapping</a></li>
						<li><a class="dropdown-item" href="/accounting/donations/index.php">Donations Workspace</a></li>
						<li><a class="dropdown-item" href="<?= route('batch_reports'); ?>">Batch Reports</a></li>
					</ul>
				</li>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle <?= $toolsMenuActive ? 'active' : ''; ?>" href="#" id="accountingToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-toolbox me-1"></i>Tools
					</a>
					<ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="accountingToolsDropdown">
						<li class="dropdown-header">Bank Imports</li>
						<li><a class="dropdown-item" href="<?= route('import'); ?>">Upload Statement</a></li>
						<li><a class="dropdown-item" href="<?= route('import_last'); ?>">Last Import</a></li>
						<li>
							<hr class="dropdown-divider">
						</li>
						<li class="dropdown-header">Automation</li>
						<li><a class="dropdown-item" href="<?= route('batch_reports'); ?>">Batch Reports</a></li>
						<li><a class="dropdown-item" href="<?= route('migrations'); ?>">Migrations</a></li>
					</ul>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $reportsActive ? 'active' : ''; ?>" href="/accounting/reports/reports_dashboard.php">
						<i class="fas fa-chart-pie me-1"></i>Reports
					</a>
				</li>
			</ul>
			<div class="nav-meta d-flex align-items-center flex-wrap gap-2">
				<span class="nav-pill">
					<i class="fas fa-database me-1"></i><?= htmlspecialchars($dbLabel, ENT_QUOTES, 'UTF-8'); ?>
				</span>
				<a class="btn btn-sm btn-outline-light" href="/accounting/app/index.php?route=admin">
					<i class="fas fa-shield-alt me-1"></i>Admin Console
				</a>
			</div>
		</div>
	</div>
</nav>