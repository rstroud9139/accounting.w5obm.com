 <!-- /accounting/ledger/list.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/ledger_controller.php';

    // Validate session
    validate_session();

    // Get status message if any
    $status = $_GET['status'] ?? null;

    // Fetch all ledger accounts
    $accounts = fetch_all_ledger_accounts();
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Ledger Accounts</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

     <div class="container mt-5">
         <div class="d-flex align-items-center mb-4">
             <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
             <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
             <h2 class="ms-3">Ledger Accounts</h2>
         </div>

         <?php if ($status === 'success'): ?>
             <div class="alert alert-success">Ledger account added successfully!</div>
         <?php elseif ($status === 'updated'): ?>
             <div class="alert alert-success">Ledger account updated successfully!</div>
         <?php elseif ($status === 'deleted'): ?>
             <div class="alert alert-success">Ledger account deleted successfully!</div>
         <?php elseif ($status === 'error'): ?>
             <div class="alert alert-danger">An error occurred. Please try again.</div>
         <?php elseif ($status === 'in_use'): ?>
             <div class="alert alert-warning">This account has transactions associated with it. Please reassign them first.</div>
         <?php elseif ($status === 'reassign_error'): ?>
             <div class="alert alert-danger">Failed to reassign transactions. Please try again.</div>
         <?php endif; ?>

         <div class="card shadow">
             <div class="card-header">
                 <h3>Ledger Account List</h3>
                 <a href="add.php" class="btn btn-success float-end">Add New Ledger Account</a>
             </div>
             <div class="card-body">
                 <table class="table table-striped">
                     <thead>
                         <tr>
                             <th>ID</th>
                             <th>Name</th>
                             <th>Description</th>
                             <th>Category</th>
                             <th>Actions</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($accounts as $account): ?>
                             <tr>
                                 <td><?php echo $account['id']; ?></td>
                                 <td><?php echo htmlspecialchars($account['name']); ?></td>
                                 <td><?php echo htmlspecialchars($account['description']); ?></td>
                                 <td><?php echo htmlspecialchars($account['category_name']); ?></td>
                                 <td>
                                     <a href="edit.php?id=<?php echo $account['id']; ?>" class="btn btn-primary btn-sm">Edit</a>

                                     <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $account['id']; ?>">
                                         Delete
                                     </button>

                                     <!-- Delete Modal -->
                                     <div class="modal fade" id="deleteModal<?php echo $account['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                         <div class="modal-dialog">
                                             <div class="modal-content">
                                                 <div class="modal-header">
                                                     <h5 class="modal-title" id="deleteModalLabel">Delete Ledger Account</h5>
                                                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                 </div>
                                                 <div class="modal-body">
                                                     <p>Are you sure you want to delete this ledger account?</p>

                                                     <?php if (is_ledger_account_in_use($account['id'])): ?>
                                                         <div class="alert alert-warning">
                                                             <p>This account has transactions associated with it. You must reassign these transactions to another account.</p>
                                                         </div>

                                                         <form action="delete.php" method="POST">
                                                             <input type="hidden" name="id" value="<?php echo $account['id']; ?>">

                                                             <div class="mb-3">
                                                                 <label for="new_account_id" class="form-label">Reassign Transactions To:</label>
                                                                 <-- /accounting/ledger/list.php (continued)
                                                                     <select name="new_account_id" id="new_account_id" class="form-control" required>
                                                                     <option value="">Select an account</option>
                                                                     <?php foreach ($accounts as $other_account): ?>
                                                                         <?php if ($other_account['id'] != $account['id']): ?>
                                                                             <option value="<?php echo $other_account['id']; ?>">
                                                                                 <?php echo htmlspecialchars($other_account['name']); ?>
                                                                             </option>
                                                                         <?php endif; ?>
                                                                     <?php endforeach; ?>
                                                                     </select>
                                                             </div>

                                                             <div class="mt-3">
                                                                 <button type="submit" class="btn btn-danger">Delete and Reassign</button>
                                                                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                             </div>
                                                         </form>
                                                     <?php else: ?>
                                                         <form action="delete.php" method="POST">
                                                             <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                                                             <div class="mt-3">
                                                                 <button type="submit" class="btn btn-danger">Delete</button>
                                                                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                             </div>
                                                         </form>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>