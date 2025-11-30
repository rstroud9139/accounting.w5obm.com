<?php
// /accounting/assets/add.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/assetController.php';
require_once __DIR__ . '/../../include/premium_hero.php';
require_once __DIR__ . '/../include/accounting_nav_helpers.php';

// Validate session
validate_session();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $value = $_POST['value'];
    $acquisition_date = $_POST['acquisition_date'];
    $depreciation_rate = $_POST['depreciation_rate'] ?? 0;
    $description = $_POST['description'];

    if (add_asset($name, $value, $acquisition_date, $depreciation_rate, $description)) {
        header('Location: list.php?status=success');
        exit();
    } else {
        $error_message = "Failed to add asset.";
    }
}
?>
<!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Add Asset</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
         <?php if (function_exists('renderPremiumHero')): ?>
             <?php renderPremiumHero([
                 'eyebrow' => 'Asset Operations',
                 'title' => 'Add Asset',
                 'subtitle' => 'Register new equipment, assign a value, and keep the audit trail clean.',
                'theme' => 'midnight',
                 'size' => 'compact',
                 'media_mode' => 'none',
                 'chips' => [
                     'Secure inventory entry',
                     'Includes depreciation fields'
                 ],
                 'actions' => [
                     [
                         'label' => 'View Assets',
                         'url' => '/accounting/assets/list.php',
                         'variant' => 'outline',
                         'icon' => 'fa-boxes'
                     ],
                     [
                         'label' => 'Back to Dashboard',
                         'url' => '/accounting/dashboard.php',
                         'variant' => 'outline',
                         'icon' => 'fa-arrow-left'
                     ]
                 ],
                 'highlights' => [
                     [
                         'label' => 'Workflow',
                         'value' => 'Step 1',
                         'meta' => 'Details + value'
                     ],
                     [
                         'label' => 'Ready For',
                         'value' => 'Board',
                         'meta' => 'Audit support'
                     ]
                 ],
             ]); ?>
         <?php else: ?>
             <section class="hero hero-small mb-4">
                 <div class="hero-body py-3">
                     <div class="container-fluid">
                         <div class="row align-items-center">
                             <div class="col-md-2 d-none d-md-flex justify-content-center">
                                 <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                                 <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                             </div>
                             <div class="col-md-6 text-center text-md-start text-white">
                                 <h1 class="h4 mb-1">Add Asset</h1>
                                 <p class="mb-0 small">Register club equipment with supporting detail.</p>
                             </div>
                             <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                 <a href="list.php" class="btn btn-outline-light btn-sm me-2">
                                     <i class="fas fa-arrow-left me-1"></i>Back to Assets
                                 </a>
                                 <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                     <i class="fas fa-home me-1"></i>Dashboard
                                 </a>
                             </div>
                         </div>
                     </div>
                 </div>
             </section>
         <?php endif; ?>

        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-3">
                    <?php if (function_exists('accounting_render_workspace_nav')): ?>
                        <?php accounting_render_workspace_nav('assets'); ?>
                    <?php endif; ?>
                </div>
                <div class="col-lg-9">
                    <div class="card shadow">
                        <div class="card-header">
                            <h3 class="mb-0">Add New Asset</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            <form action="add.php" method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="name" class="form-label">Asset Name</label>
                                    <input type="text" id="name" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="value" class="form-label">Value</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" id="value" name="value" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="acquisition_date" class="form-label">Acquisition Date</label>
                                    <input type="date" id="acquisition_date" name="acquisition_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="depreciation_rate" class="form-label">Annual Depreciation Rate (%)</label>
                                    <input type="number" step="0.01" id="depreciation_rate" name="depreciation_rate" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" class="form-control" rows="4" placeholder="Where the asset lives, readiness state, serial numbers..."></textarea>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Add Asset</button>
                                    <a href="list.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>