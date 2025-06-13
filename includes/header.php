<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - Dompetku' : 'Dompetku' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .navbar-custom {
            background: linear-gradient(to right, #dc3545, #8a0000);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-custom .navbar-brand {
            font-family: 'Georgia', serif;
            font-size: 1.8rem;
            font-weight: bold;
            color: #ffffff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            transition: color 0.3s ease;
        }

        .navbar-custom .navbar-brand:hover {
            color: #f8d7da;
        }

        .navbar-custom .nav-link {
            color: #ffffff;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            color: #f8d7da;
        }

        .navbar-custom .nav-link::after {
            content: '';
            position: absolute;
            width: 0%;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: #f8d7da;
            transition: width 0.3s ease;
        }

        .navbar-custom .nav-link:hover::after,
        .navbar-custom .nav-link.active::after {
            width: 100%;
        }

        .navbar-custom .dropdown-menu {
            background-color: #ffffff; /* White background for dropdown */
            border: 1px solid #e0e0e0; /* Light grey border */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
        }

        .navbar-custom .dropdown-item {
            color: #333333; /* Darker text for items */
            font-weight: 400;
            transition: background-color 0.2s ease, color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.5rem 1rem;
        }

        .navbar-custom .dropdown-item:hover {
            background-color: #f8d7da; /* Very light red on hover */
            color: #dc3545; /* Red text on hover */
        }

        .navbar-custom .dropdown-item .fas {
            color: #6c757d; /* Default icon color */
            transition: color 0.2s ease;
        }

        .navbar-custom .dropdown-item:hover .fas {
            color: #dc3545; /* Red icon on hover */
        }

        .navbar-custom .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5);
        }

        .navbar-custom .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .navbar-custom .nav-link.dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-custom .profile-icon {
            width: 32px;
            height: 32px;
            background-color: #ffffff; /* White background for profile icon */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            font-weight: bold;
            color: #dc3545; /* Red text for profile icon */
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">Dompetku</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'transactions.php') ? 'active' : '' ?>" href="transactions.php">Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'active' : '' ?>" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'accounts.php') ? 'active' : '' ?>" href="accounts.php">Accounts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : '' ?>" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="profile-icon">
                                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
                            </span>
                            <?= htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUser">
                            <li>
                                <a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">