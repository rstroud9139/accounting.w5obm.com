<?php
require_once __DIR__ . '/bootstrap.php';

$route = $_GET['route'] ?? 'dashboard';

switch ($route) {
    case 'dashboard':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/DashboardController.php';
        (new DashboardController())->index();
        break;
    case 'accounts':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/AccountsController.php';
        (new AccountsController())->index();
        break;
    case 'account_register':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/AccountsController.php';
        (new AccountsController())->register();
        break;
    case 'account_register_export_csv':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/AccountsController.php';
        (new AccountsController())->register_export_csv();
        break;
    case 'transactions':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/TransactionsController.php';
        (new TransactionsController())->index();
        break;
    case 'transactions_export_csv':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/TransactionsController.php';
        (new TransactionsController())->export_csv();
        break;
    case 'transaction_new':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/TransactionsController.php';
        (new TransactionsController())->new();
        break;
    case 'transaction_create':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/TransactionsController.php';
        (new TransactionsController())->create();
        break;
    case 'reconciliation':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ReconciliationController.php';
        (new ReconciliationController())->index();
        break;
    case 'reconciliation_review':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ReconciliationController.php';
        (new ReconciliationController())->review();
        break;
    case 'reconciliation_commit':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ReconciliationController.php';
        (new ReconciliationController())->commit();
        break;
    case 'reconciliation_view':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ReconciliationController.php';
        (new ReconciliationController())->view();
        break;
    case 'reconciliation_export_csv':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ReconciliationController.php';
        (new ReconciliationController())->export_csv();
        break;
    case 'batch_reports':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/BatchReportsController.php';
        (new BatchReportsController())->index();
        break;
    case 'batch_reports_run':
        requirePermission('accounting_view');
        require_once __DIR__ . '/controllers/BatchReportsController.php';
        (new BatchReportsController())->run();
        break;
    case 'import':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ImportController.php';
        (new ImportController())->index();
        break;
    case 'import_upload':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ImportController.php';
        (new ImportController())->upload();
        break;
    case 'import_commit':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ImportController.php';
        (new ImportController())->commit();
        break;
    case 'import_last':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/ImportController.php';
        (new ImportController())->last();
        break;
    case 'category_map':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/CategoryMappingController.php';
        (new CategoryMappingController())->index();
        break;
    case 'category_map_save':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/CategoryMappingController.php';
        (new CategoryMappingController())->save();
        break;
    case 'category_map_save_inline':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/CategoryMappingController.php';
        (new CategoryMappingController())->saveInline();
        break;
    case 'migrations':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/MigrationController.php';
        (new MigrationController())->index();
        break;
    case 'migrations_run':
        requirePermission('accounting_manage');
        require_once __DIR__ . '/controllers/MigrationController.php';
        (new MigrationController())->run();
        break;
    default:
        http_response_code(404);
        echo 'Route not found';
}
