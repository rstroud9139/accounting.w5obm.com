 <!-- /accounting/categories/edit.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/category_controller.php';

    // Validate session
    validate_session();

    // Get category ID
    $id = $_GET['id'] ?? null;

    if (!$id) {
        header('Location: list.php?status=invalid_request');
        exit();
    }

    // Fetch category data
    $category = fetch_category_by_id($id);

    if (!$category) {
        header('Location: list.php?status=not_found');
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $type = $_POST['type'];

        if (update_category($id, $name, $description, $type)) {
            header('Location: list.php?status=updated');
            exit();
        } else {
            $error_message = "Failed to update category.";
        }
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Edit Category</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

     <div class="container mt-5">
         <div class="d-flex align-items-center mb-4">
             <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
             <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
             <h2 class="ms-3">Edit Category</h2>
         </div>

         <div class="card shadow">
             <div class="card-header">
                 <h3>Edit Category</h3>
             </div>
             <div class="card-body">
                 <?php if (isset($error_message)): ?>
                     <div class="alert alert-danger"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                 <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                     <div class="mb-3">
                         <label for="name" class="form-label">Category Name</label>
                         <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                     </div>
                     <div class="mb-3">
                         <label for="description" class="form-label">Description</label>
                         <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($category['description']); ?></textarea>
                     </div>
                     <div class="mb-3">
                         <label for="type" class="form-label">Type</label>
                         <select id="type" name="type" class="form-control" required>
                             <option value="Income" <?php echo $category['type'] === 'Income' ? 'selected' : ''; ?>>Income</option>
                             <option value="Expense" <?php echo $category['type'] === 'Expense' ? 'selected' : ''; ?>>Expense</option>
                             <option value="Asset" <?php echo $category['type'] === 'Asset' ? 'selected' : ''; ?>>Asset</option>
                             <option value="Liability" <?php echo $category['type'] === 'Liability' ? 'selected' : ''; ?>>Liability</option>
                             <option value="Equity" <?php echo $category['type'] === 'Equity' ? 'selected' : ''; ?>>Equity</option>
                             <option value="Operating" <?php echo $category['type'] === 'Operating' ? 'selected' : ''; ?>>Operating</option>
                             <option value="Investing" <?php echo $category['type'] === 'Investing' ? 'selected' : ''; ?>>Investing</option>
                             <option value="Financing" <?php echo $category['type'] === 'Financing' ? 'selected' : ''; ?>>Financing</option>
                         </select>
                     </div>
                     <button type="submit" class="btn btn-primary">Update Category</button>
                     <a href="list.php" class="btn btn-secondary">Cancel</a>
                 </form>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>