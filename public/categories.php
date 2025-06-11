<?php
require '../includes/auth_check.php';
require '../config/database.php';

$page_title = 'Categories';
$user_id = $_SESSION['user_id'];

// Default action is to list categories
$action = $_GET['action'] ?? 'list';
$category_id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle POST requests for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];

    if (empty($name) || empty($type)) {
        $error = 'Category name and type are required.';
    } else {
        if ($action == 'add') {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $name, $type]);
                $success = 'Category added successfully!';
            } catch (PDOException $e) {
                $error = 'An error occurred while adding the category.';
            }
        } elseif ($action == 'edit' && $category_id) {
            try {
                // Verify the category belongs to the user before updating
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $type, $category_id, $user_id]);
                $success = 'Category updated successfully!';
            } catch (PDOException $e) {
                $error = 'An error occurred while updating the category.';
            }
        }
        // Redirect to the list view after a short delay to show the message
        header("Location: categories.php");
        exit();
    }
}

// Handle delete requests
if ($action == 'delete' && $category_id) {
    // Verify the category belongs to the user before deleting
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$category_id, $user_id]);
    header("Location: categories.php");
    exit();
}

include '../includes/header.php';

// Display form for adding or editing
if ($action == 'add' || $action == 'edit') :
    $current_category = null;
    if ($action == 'edit' && $category_id) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$category_id, $user_id]);
        $current_category = $stmt->fetch();
        if (!$current_category) {
            // Category not found or doesn't belong to the user
            die('Error: Category not found.');
        }
    }
?>
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0"><?= ucfirst($action) ?> Category</h3>
        </div>
        <div class="card-body">
            <form action="categories.php?action=<?= $action ?><?= $category_id ? '&id=' . $category_id : '' ?>" method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($current_category['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Select a type</option>
                        <option value="income" <?= ($current_category && $current_category['type'] == 'income') ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= ($current_category && $current_category['type'] == 'expense') ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Category</button>
                <a href="categories.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

<?php else : // Default list view ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="dashboard-title">Manage Categories</h1>
        <a href="categories.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Add New Category</a>
    </div>
    
    <?php if ($error) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all categories for the current user
                    $stmt = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type, name");
                    $stmt->execute([$user_id]);
                    $categories = $stmt->fetchAll();

                    if (count($categories) > 0) :
                        foreach ($categories as $category) :
                    ?>
                            <tr>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td>
                                    <span class="badge rounded-pill <?= $category['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst($category['type']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="categories.php?action=edit&id=<?= $category['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="categories.php?action=delete&id=<?= $category['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category? This might affect existing transactions.')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </a>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                    else :
                        ?>
                        <tr>
                            <td colspan="3" class="text-center">No categories found. Click 'Add New Category' to start.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>