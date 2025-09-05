<?php
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// Handle actions
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        // Check if user has any bookings before deleting
        $hasBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?", [$userId])['count'];
        if ($hasBookings > 0) {
            setFlashMessage('error', 'Cannot delete user with existing bookings');
        } else {
            executeQuery("DELETE FROM users WHERE id = ?", [$userId]);
            setFlashMessage('success', 'User deleted successfully');
        }
    }
    
    redirect('users.php');
}

// Handle add/edit user
if (isset($_POST['save_user'])) {
    $userId = $_POST['user_id'] ?? null;
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $location = sanitizeInput($_POST['location']);
    $language = $_POST['language'];
    $password = $_POST['password'];
    
    if (empty($name) || empty($phone)) {
        setFlashMessage('error', 'Name and phone are required');
    } else {
        if ($userId) {
            // Update existing user
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                executeQuery("UPDATE users SET name = ?, email = ?, phone = ?, location = ?, language = ?, password = ? WHERE id = ?", 
                           [$name, $email, $phone, $location, $language, $hashedPassword, $userId]);
            } else {
                executeQuery("UPDATE users SET name = ?, email = ?, phone = ?, location = ?, language = ? WHERE id = ?", 
                           [$name, $email, $phone, $location, $language, $userId]);
            }
            setFlashMessage('success', 'User updated successfully');
        } else {
            // Add new user
            if (empty($password)) {
                setFlashMessage('error', 'Password is required for new users');
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                executeQuery("INSERT INTO users (name, email, phone, location, language, password) VALUES (?, ?, ?, ?, ?, ?)", 
                           [$name, $email, $phone, $location, $language, $hashedPassword]);
                setFlashMessage('success', 'User added successfully');
            }
        }
        redirect('users.php');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$language = $_GET['language'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($location) {
    $whereConditions[] = "u.location = ?";
    $params[] = $location;
}

if ($language) {
    $whereConditions[] = "u.language = ?";
    $params[] = $language;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get users
$users = fetchAll("
    SELECT u.*, 
           (SELECT COUNT(*) FROM bookings WHERE customer_id = u.id) as total_bookings,
           (SELECT COUNT(*) FROM reviews WHERE customer_id = u.id) as total_reviews
    FROM users u
    $whereClause
    ORDER BY u.created_at DESC
", $params);

// Get locations for filter
$locations = fetchAll("SELECT DISTINCT location FROM users WHERE location IS NOT NULL AND location != '' ORDER BY location");

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    redirect('../index.php');
}

// Get user for editing
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editUser = fetchOne("SELECT * FROM users WHERE id = ?", [$_GET['edit']]);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Manage Users' : 'ব্যবহারকারী পরিচালনা'; ?> - S24 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .admin-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .floating-elements {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 150px;
            height: 150px;
            top: 5%;
            left: 5%;
            animation-delay: 0s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        .floating-element:nth-child(2) {
            width: 100px;
            height: 100px;
            top: 15%;
            right: 10%;
            animation-delay: 2s;
            background: linear-gradient(45deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
        }
        
        .floating-element:nth-child(3) {
            width: 120px;
            height: 120px;
            bottom: 15%;
            left: 10%;
            animation-delay: 4s;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2));
        }
        
        .floating-element:nth-child(4) {
            width: 80px;
            height: 80px;
            bottom: 5%;
            right: 15%;
            animation-delay: 1s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 4s ease-in-out infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .admin-card:hover::before {
            opacity: 1;
        }
        
        .admin-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            color: #000000;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
            background: linear-gradient(135deg, #d97706, #b45309);
        }
    </style>
</head>
<body class="admin-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    
    <!-- Header -->
    <header class="admin-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-users text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Manage Users' : 'ব্যবহারকারী পরিচালনা'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="providers.php" class="nav-link">
                        <i class="fas fa-user-check"></i>
                        <?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <!-- Page Header -->
        <div class="admin-card p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-users text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Manage Users' : 'ব্যবহারকারী পরিচালনা'; ?>
                    </h1>
                    <p class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total users and customer management' : 'মোট ব্যবহারকারী এবং গ্রাহক পরিচালনা'; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-purple-600"><?php echo count($users); ?></div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total Users' : 'মোট ব্যবহারকারী'; ?>
                    </div>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="openAddUserModal()" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i><?php echo $currentLang === 'en' ? 'Add User' : 'ব্যবহারকারী যোগ করুন'; ?>
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="admin-card p-4 mb-6 <?php echo $flash['type'] === 'success' ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'; ?>">
                <div class="flex items-center">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3 text-xl"></i>
                    <span class="<?php echo $flash['type'] === 'success' ? 'text-green-700' : 'text-red-700'; ?> font-medium">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Search' : 'অনুসন্ধান'; ?>
                    </label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $currentLang === 'en' ? 'Name, phone, email...' : 'নাম, ফোন, ইমেইল...'; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>
                    </label>
                    <select name="location" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Locations' : 'সব অবস্থান'; ?></option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo $currentLang === 'en' ? 'User Info' : 'ব্যবহারকারী তথ্য'; ?>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo $currentLang === 'en' ? 'Contact' : 'যোগাযোগ'; ?>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo $currentLang === 'en' ? 'Stats' : 'পরিসংখ্যান'; ?>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo $currentLang === 'en' ? 'Actions' : 'কর্ম'; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-users text-4xl mb-4 block"></i>
                                    <?php echo $currentLang === 'en' ? 'No users found' : 'কোনো ব্যবহারকারী পাওয়া যায়নি'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                                                    <i class="fas fa-user text-purple-600 text-xl"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo $currentLang === 'en' ? 'ID' : 'আইডি'; ?>: <?php echo $user['id']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-globe mr-1"></i><?php echo $user['language'] === 'en' ? 'English' : 'বাংলা'; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo $currentLang === 'en' ? 'Joined' : 'যোগদান'; ?>: <?php echo formatDate($user['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="space-y-1">
                                            <div><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($user['phone']); ?></div>
                                            <?php if ($user['email']): ?>
                                                <div><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($user['location']): ?>
                                                <div><i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($user['location']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="space-y-1">
                                            <div><i class="fas fa-calendar mr-2"></i><?php echo $user['total_bookings']; ?> <?php echo $currentLang === 'en' ? 'bookings' : 'বুকিং'; ?></div>
                                            <div><i class="fas fa-star mr-2"></i><?php echo $user['total_reviews']; ?> <?php echo $currentLang === 'en' ? 'reviews' : 'পর্যালোচনা'; ?></div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="?edit=<?php echo $user['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="text-red-600 hover:text-red-900"
                                                        onclick="return confirm('<?php echo $currentLang === 'en' ? 'Delete this user? This action cannot be undone.' : 'এই ব্যবহারকারী মুছে ফেলবেন? এই কর্মটি অপরিবর্তনীয়।'; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <?php echo $editUser ? ($currentLang === 'en' ? 'Edit User' : 'ব্যবহারকারী সম্পাদনা করুন') : ($currentLang === 'en' ? 'Add New User' : 'নতুন ব্যবহারকারী যোগ করুন'); ?>
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" value="<?php echo $editUser ? $editUser['id'] : ''; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Name' : 'নাম'; ?> *
                        </label>
                        <input type="text" name="name" value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Phone' : 'ফোন'; ?> *
                        </label>
                        <input type="text" name="phone" value="<?php echo $editUser ? htmlspecialchars($editUser['phone']) : ''; ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Email' : 'ইমেইল'; ?>
                        </label>
                        <input type="email" name="email" value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>
                        </label>
                        <input type="text" name="location" value="<?php echo $editUser ? htmlspecialchars($editUser['location']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Language' : 'ভাষা'; ?>
                        </label>
                        <select name="language" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="en" <?php echo ($editUser && $editUser['language'] === 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="bn" <?php echo ($editUser && $editUser['language'] === 'bn') ? 'selected' : ''; ?>>বাংলা</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Password' : 'পাসওয়ার্ড'; ?> <?php echo $editUser ? '(' . ($currentLang === 'en' ? 'leave blank to keep current' : 'বর্তমান রাখতে খালি রাখুন') . ')' : ''; ?>
                        </label>
                        <input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?>
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" name="save_user" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            <?php echo $currentLang === 'en' ? 'Save' : 'সংরক্ষণ করুন'; ?>
                        </button>
                        <button type="button" onclick="closeUserModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

    <script>
        function openAddUserModal() {
            document.getElementById('userModal').classList.remove('hidden');
        }
        
        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
        }
        
        // Close modal if clicking outside
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });
        
        // Auto-open modal if editing
        <?php if ($editUser): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openAddUserModal();
        });
        <?php endif; ?>
    </script>
</body>
</html> 