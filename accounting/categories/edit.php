<?php
// /accounting/categories/edit.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../../include/premium_hero.php';

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

    // Fetch all categories for stats
    try {
        $all_categories = getAllCategories(['active' => true]);
    } catch (Exception $e) {
        $all_categories = [];
    }

    $total_categories = is_array($all_categories) ? count($all_categories) : 0;
    $same_type_count = 0;
    foreach ($all_categories as $cat) {
        if (($cat['type'] ?? '') === ($category['type'] ?? '')) {
            $same_type_count++;
        }
    }

    $categoryEditHeroChips = [
        'Type: ' . ($category['type'] ?? 'Unknown'),
        'Total categories: ' . number_format($total_categories),
        'Same type: ' . number_format($same_type_count),
    ];

    $categoryEditHeroHighlights = [
        [
            'label' => 'Category Name',
            'value' => htmlspecialchars($category['name'] ?? 'Unnamed'),
            'meta' => 'Current',
        ],
        [
            'label' => 'Category Type',
            'value' => htmlspecialchars($category['type'] ?? 'N/A'),
            'meta' => 'Classification',
        ],
        [
            'label' => 'Same Type',
            'value' => number_format($same_type_count),
            'meta' => 'Peer categories',
        ],
    ];

    $categoryEditHeroActions = [
        [
            'label' => 'Categories Home',
            'url' => '/accounting/categories/',
            'variant' => 'outline',
            'icon' => 'fa-list',
        ],
        [
            'label' => 'Add Category',
            'url' => '/accounting/categories/add.php',
            'variant' => 'outline',
            'icon' => 'fa-plus',
        ],
        [
            'label' => 'Accounting Dashboard',
            'url' => '/accounting/dashboard.php',
            'variant' => 'outline',
            'icon' => 'fa-arrow-left',
        ],
    ];

    $categoryEditHeroConfig = [
        'eyebrow' => 'Chart of Accounts',
        'title' => 'Edit Category',
        'subtitle' => 'Refine names, types, and nesting to keep the ledger sharp.',
        'chips' => $categoryEditHeroChips,
        'highlights' => $categoryEditHeroHighlights,
        'actions' => $categoryEditHeroActions,
        'theme' => 'sunset',
        'size' => 'compact',
        'media_mode' => 'none',
    ];

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

     <?php
     if (function_exists('displayToastMessage')) {
         displayToastMessage();
     }
     ?>

     <?php if (function_exists('renderPremiumHero')) {
         renderPremiumHero($categoryEditHeroConfig);
     } ?>

     <div class="page-container">
         <?php if (!function_exists('renderPremiumHero')): ?>
             <?php $fallbackLogo = accounting_logo_src_for(__DIR__); ?>
             <section class="hero hero-small mb-4">
                 <div class="hero-body py-3">
                     <div class="container-fluid">
                         <div class="row align-items-center">
                             <div class="col-md-2 d-none d-md-flex justify-content-center">
                                 <img src="<?= htmlspecialchars($fallbackLogo); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                             </div>
                             <div class="col-md-6 text-center text-md-start text-white">
                                 <h1 class="h4 mb-1">Edit Category</h1>
                                 <p class="mb-0 small">Update category details to keep your chart of accounts organized.</p>
                             </div>
                             <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                 <a href="/accounting/categories/" class="btn btn-outline-light btn-sm me-2">
                                     <i class="fas fa-list me-1"></i>Categories Home
                                 </a>
                                 <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                     <i class="fas fa-arrow-left me-1"></i>Dashboard
                                 </a>
                             </div>
                         </div>
                     </div>
                 </div>
             </section>
         <?php endif; ?>

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