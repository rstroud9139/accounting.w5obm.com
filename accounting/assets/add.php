 <!-- /accounting/assets/add.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/asset_controller.php';

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

     <div class="container mt-5">
         <div class="d-flex align-items-center mb-4">
             <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
             <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
             <h2 class="ms-3">Add Asset</h2>
         </div>

         <div class="card shadow">
             <div class="card-header">
                 <h3>Add New Asset</h3>
             </div>
             <div class="card-body">
                 <?php if (isset($error_message)): ?>
                     <div class="alert alert-danger"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                 <form action="add.php" method="POST">
                     <div class="mb-3">
                         <label for="name" class="form-label">Asset Name</label>
                         <input type="text" id="name" name="name" class="form-control" required>
                     </div>
                     <div class="mb-3">
                         <label for="value" class="form-label">Value</label>
                         <input type="number" step="0.01" id="value" name="value" class="form-control" required>
                     </div>
                     <div class="mb-3">
                         <label for="acquisition_date" class="form-label">Acquisition Date</label>
                         <input type="date" id="acquisition_date" name="acquisition_date" class="form-control" required>
                     </div>
                     <div class="mb-3">
                         <label for="depreciation_rate" class="form-label">Annual Depreciation Rate (%)</label>
                         <input type="number" step="0.01" id="depreciation_rate" name="depreciation_rate" class="form-control">
                     </div>
                     <div class="mb-3">
                         <label for="description" class="form-label">Description</label>
                         <textarea id="description" name="description" class="form-control"></textarea>
                     </div>
                     <button type="submit" class="btn btn-success">Add Asset</button>
                     <a href="list.php" class="btn btn-secondary">Cancel</a>
                 </form>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>