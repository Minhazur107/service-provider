<?php
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// Handle actions
if (isset($_POST['action']) && isset($_POST['provider_id'])) {
    $providerId = (int)$_POST['provider_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        executeQuery("UPDATE service_providers SET verification_status = 'verified' WHERE id = ?", [$providerId]);
        setFlashMessage('success', 'Provider approved successfully');
    } elseif ($action === 'reject') {
        executeQuery("UPDATE service_providers SET verification_status = 'rejected' WHERE id = ?", [$providerId]);
        setFlashMessage('success', 'Provider rejected successfully');
    } elseif ($action === 'toggle_active') {
        executeQuery("UPDATE service_providers SET is_active = NOT is_active WHERE id = ?", [$providerId]);
        setFlashMessage('success', 'Provider status updated successfully');
    } elseif ($action === 'delete') {
        // Check if provider has any bookings before deleting
        $hasBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE provider_id = ?", [$providerId])['count'];
        if ($hasBookings > 0) {
            setFlashMessage('error', 'Cannot delete provider with existing bookings');
        } else {
            executeQuery("DELETE FROM service_providers WHERE id = ?", [$providerId]);
            setFlashMessage('success', 'Provider deleted successfully');
        }
    }
    
    redirect('providers.php');
}

// Handle add/edit provider
if (isset($_POST['save_provider'])) {
    $providerId = $_POST['provider_id'] ?? null;
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $categoryId = (int)$_POST['category_id'];
    $description = sanitizeInput($_POST['description']);
    $serviceAreas = sanitizeInput($_POST['service_areas']);
    $priceMin = $_POST['price_min'] ? (float)$_POST['price_min'] : null;
    $priceMax = $_POST['price_max'] ? (float)$_POST['price_max'] : null;
    $hourlyRate = $_POST['hourly_rate'] ? (float)$_POST['hourly_rate'] : null;
    $availabilityHours = sanitizeInput($_POST['availability_hours']);
    $verificationStatus = $_POST['verification_status'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($phone) || empty($categoryId)) {
        setFlashMessage('error', 'Name, phone, and category are required');
    } else {
        if ($providerId) {
            // Update existing provider
            executeQuery("UPDATE service_providers SET name = ?, phone = ?, email = ?, category_id = ?, description = ?, service_areas = ?, price_min = ?, price_max = ?, hourly_rate = ?, availability_hours = ?, verification_status = ?, is_active = ? WHERE id = ?", 
                       [$name, $phone, $email, $categoryId, $description, $serviceAreas, $priceMin, $priceMax, $hourlyRate, $availabilityHours, $verificationStatus, $isActive, $providerId]);
            setFlashMessage('success', 'Provider updated successfully');
        } else {
            // Add new provider
            executeQuery("INSERT INTO service_providers (name, phone, email, category_id, description, service_areas, price_min, price_max, hourly_rate, availability_hours, verification_status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                       [$name, $phone, $email, $categoryId, $description, $serviceAreas, $priceMin, $priceMax, $hourlyRate, $availabilityHours, $verificationStatus, $isActive]);
            setFlashMessage('success', 'Provider added successfully');
        }
        redirect('providers.php');
    }
}

// Get filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$verification = $_GET['verification'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($status) {
    $whereConditions[] = "sp.is_active = ?";
    $params[] = $status === 'active' ? 1 : 0;
}

if ($category) {
    $whereConditions[] = "sp.category_id = ?";
    $params[] = $category;
}

if ($verification) {
    $whereConditions[] = "sp.verification_status = ?";
    $params[] = $verification;
}

if ($search) {
    $whereConditions[] = "(sp.name LIKE ? OR sp.phone LIKE ? OR sp.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get providers
$providers = fetchAll("
    SELECT sp.*, sc.name as category_name, sc.name_bn as category_name_bn,
           (SELECT COUNT(*) FROM bookings WHERE provider_id = sp.id) as total_bookings,
           (SELECT COUNT(*) FROM reviews WHERE provider_id = sp.id AND status = 'approved') as total_reviews
    FROM service_providers sp
    LEFT JOIN service_categories sc ON sp.category_id = sc.id
    $whereClause
    ORDER BY sp.created_at DESC
", $params);

// Get categories for filter
$categories = fetchAll("SELECT id, name, name_bn FROM service_categories WHERE is_active = 1");

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    redirect('../index.php');
}

// Get provider for editing
$editProvider = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editProvider = fetchOne("SELECT * FROM service_providers WHERE id = ?", [$_GET['edit']]);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Manage Providers' : 'প্রদানকারী পরিচালনা'; ?> - S24 Admin</title>
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-verified {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .status-active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
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
                        <i class="fas fa-user-check text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Manage Service Providers' : 'সেবা প্রদানকারী পরিচালনা'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <?php echo $currentLang === 'en' ? 'Users' : 'ব্যবহারকারী'; ?>
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
                        <i class="fas fa-user-check text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Manage Service Providers' : 'সেবা প্রদানকারী পরিচালনা'; ?>
                    </h1>
                    <p class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total providers and pending verification management' : 'মোট প্রদানকারী এবং অপেক্ষমান যাচাইকরণ পরিচালনা'; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-purple-600"><?php echo count($providers); ?></div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total Providers' : 'মোট প্রদানকারী'; ?>
                    </div>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="openAddProviderModal()" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i><?php echo $currentLang === 'en' ? 'Add Provider' : 'প্রদানকারী যোগ করুন'; ?>
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
        <div class="admin-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-filter text-purple-600 mr-2"></i>
                <?php echo $currentLang === 'en' ? 'Filter Providers' : 'প্রদানকারী ফিল্টার করুন'; ?>
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
                        <?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?>
                    </label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Categories' : 'সব বিভাগ'; ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? $cat['name'] : $cat['name_bn']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?>
                    </label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Status' : 'সব অবস্থা'; ?></option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Active' : 'সক্রিয়'; ?>
                        </option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Inactive' : 'নিষ্ক্রিয়'; ?>
                        </option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Verification' : 'যাচাইকরণ'; ?>
                    </label>
                    <select name="verification" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All' : 'সব'; ?></option>
                        <option value="pending" <?php echo $verification === 'pending' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান'; ?>
                        </option>
                        <option value="verified" <?php echo $verification === 'verified' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত'; ?>
                        </option>
                        <option value="rejected" <?php echo $verification === 'rejected' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Rejected' : 'প্রত্যাখ্যান'; ?>
                        </option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Providers Table -->
        <div class="admin-card overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-list text-purple-600 mr-2"></i>
                    <?php echo $currentLang === 'en' ? 'Service Providers List' : 'সেবা প্রদানকারীদের তালিকা'; ?>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-purple-50 to-pink-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-user mr-2"></i><?php echo $currentLang === 'en' ? 'Provider' : 'প্রদানকারী'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-tag mr-2"></i><?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-phone mr-2"></i><?php echo $currentLang === 'en' ? 'Contact' : 'যোগাযোগ'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-chart-bar mr-2"></i><?php echo $currentLang === 'en' ? 'Stats' : 'পরিসংখ্যান'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-2"></i><?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-cogs mr-2"></i><?php echo $currentLang === 'en' ? 'Actions' : 'কর্ম'; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($providers)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-users text-4xl text-purple-500"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-700 mb-2">
                                        <?php echo $currentLang === 'en' ? 'No Providers Found' : 'কোনো প্রদানকারী পাওয়া যায়নি'; ?>
                                    </h3>
                                    <p class="text-gray-500">
                                        <?php echo $currentLang === 'en' ? 'Try adjusting your filters or add a new provider.' : 'আপনার ফিল্টারগুলি সামঞ্জস্য করুন বা নতুন প্রদানকারী যোগ করুন।'; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($providers as $provider): ?>
                                <tr class="hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 transition-all duration-300">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <?php if ($provider['profile_picture']): ?>
                                                    <img class="h-12 w-12 rounded-full object-cover border-2 border-purple-200" 
                                                         src="../uploads/<?php echo htmlspecialchars($provider['profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($provider['name']); ?>">
                                                <?php else: ?>
                                                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                                                        <i class="fas fa-user text-white text-xl"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($provider['name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-id-card mr-1"></i><?php echo $currentLang === 'en' ? 'ID' : 'আইডি'; ?>: <?php echo $provider['id']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-calendar-plus mr-1"></i><?php echo $currentLang === 'en' ? 'Joined' : 'যোগদান'; ?>: <?php echo formatDate($provider['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                            <i class="fas fa-tag mr-1"></i>
                                            <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="space-y-1">
                                            <div class="flex items-center">
                                                <i class="fas fa-phone text-purple-500 mr-2"></i>
                                                <span><?php echo htmlspecialchars($provider['phone']); ?></span>
                                            </div>
                                            <?php if ($provider['email']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-envelope text-purple-500 mr-2"></i>
                                                    <span><?php echo htmlspecialchars($provider['email']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($provider['service_areas']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>
                                                    <span><?php echo htmlspecialchars($provider['service_areas']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="space-y-2">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar text-blue-500 mr-2"></i>
                                                <span class="font-medium"><?php echo $provider['total_bookings']; ?></span>
                                                <span class="ml-1"><?php echo $currentLang === 'en' ? 'bookings' : 'বুকিং'; ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-star text-yellow-500 mr-2"></i>
                                                <span class="font-medium"><?php echo $provider['total_reviews']; ?></span>
                                                <span class="ml-1"><?php echo $currentLang === 'en' ? 'reviews' : 'পর্যালোচনা'; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-2">
                                            <span class="status-badge status-<?php echo $provider['verification_status']; ?>">
                                                <?php echo t($provider['verification_status']); ?>
                                            </span>
                                            <span class="status-badge status-<?php echo $provider['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $provider['is_active'] ? ($currentLang === 'en' ? 'Active' : 'সক্রিয়') : ($currentLang === 'en' ? 'Inactive' : 'নিষ্ক্রিয়'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="view_provider.php?id=<?php echo $provider['id']; ?>" 
                                               class="btn-success" title="<?php echo $currentLang === 'en' ? 'View Details' : 'বিস্তারিত দেখুন'; ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="?edit=<?php echo $provider['id']; ?>" 
                                               class="btn-warning" title="<?php echo $currentLang === 'en' ? 'Edit Provider' : 'প্রদানকারী সম্পাদনা করুন'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($provider['verification_status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-success" 
                                                            onclick="return confirm('<?php echo $currentLang === 'en' ? 'Approve this provider?' : 'এই প্রদানকারীকে অনুমোদন করবেন?'; ?>')"
                                                            title="<?php echo $currentLang === 'en' ? 'Approve Provider' : 'প্রদানকারী অনুমোদন করুন'; ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-danger"
                                                            onclick="return confirm('<?php echo $currentLang === 'en' ? 'Reject this provider?' : 'এই প্রদানকারীকে প্রত্যাখ্যান করবেন?'; ?>')"
                                                            title="<?php echo $currentLang === 'en' ? 'Reject Provider' : 'প্রদানকারী প্রত্যাখ্যান করুন'; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <button type="submit" class="<?php echo $provider['is_active'] ? 'btn-danger' : 'btn-success'; ?>"
                                                        onclick="return confirm('<?php echo $currentLang === 'en' ? 'Change provider status?' : 'প্রদানকারীর অবস্থা পরিবর্তন করবেন?'; ?>')"
                                                        title="<?php echo $currentLang === 'en' ? 'Toggle Status' : 'অবস্থা পরিবর্তন করুন'; ?>">
                                                    <i class="fas fa-<?php echo $provider['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-danger"
                                                        onclick="return confirm('<?php echo $currentLang === 'en' ? 'Delete this provider? This action cannot be undone.' : 'এই প্রদানকারী মুছে ফেলবেন? এই কর্মটি অপরিবর্তনীয়।'; ?>')"
                                                        title="<?php echo $currentLang === 'en' ? 'Delete Provider' : 'প্রদানকারী মুছুন'; ?>">
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

    <!-- Footer -->
    <footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

    <!-- Add/Edit Provider Modal -->
    <div id="providerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <?php echo $editProvider ? ($currentLang === 'en' ? 'Edit Provider' : 'প্রদানকারী সম্পাদনা করুন') : ($currentLang === 'en' ? 'Add New Provider' : 'নতুন প্রদানকারী যোগ করুন'); ?>
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="provider_id" value="<?php echo $editProvider ? $editProvider['id'] : ''; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Name' : 'নাম'; ?> *
                            </label>
                            <input type="text" name="name" value="<?php echo $editProvider ? htmlspecialchars($editProvider['name']) : ''; ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Phone' : 'ফোন'; ?> *
                            </label>
                            <input type="text" name="phone" value="<?php echo $editProvider ? htmlspecialchars($editProvider['phone']) : ''; ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Email' : 'ইমেইল'; ?>
                            </label>
                            <input type="email" name="email" value="<?php echo $editProvider ? htmlspecialchars($editProvider['email']) : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?> *
                            </label>
                            <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value=""><?php echo $currentLang === 'en' ? 'Select Category' : 'বিভাগ নির্বাচন করুন'; ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($editProvider && $editProvider['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo $currentLang === 'en' ? $cat['name'] : $cat['name_bn']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Description' : 'বিবরণ'; ?>
                        </label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo $editProvider ? htmlspecialchars($editProvider['description']) : ''; ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service Areas' : 'সেবা এলাকা'; ?>
                        </label>
                        <input type="text" name="service_areas" value="<?php echo $editProvider ? htmlspecialchars($editProvider['service_areas']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Min Price' : 'ন্যূনতম মূল্য'; ?>
                            </label>
                            <input type="number" step="0.01" name="price_min" value="<?php echo $editProvider ? $editProvider['price_min'] : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Max Price' : 'সর্বোচ্চ মূল্য'; ?>
                            </label>
                            <input type="number" step="0.01" name="price_max" value="<?php echo $editProvider ? $editProvider['price_max'] : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Hourly Rate' : 'ঘণ্টার হার'; ?>
                            </label>
                            <input type="number" step="0.01" name="hourly_rate" value="<?php echo $editProvider ? $editProvider['hourly_rate'] : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Availability Hours' : 'উপলব্ধতার সময়'; ?>
                        </label>
                        <input type="text" name="availability_hours" value="<?php echo $editProvider ? htmlspecialchars($editProvider['availability_hours']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Verification Status' : 'যাচাইকরণের অবস্থা'; ?>
                            </label>
                            <select name="verification_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="pending" <?php echo ($editProvider && $editProvider['verification_status'] === 'pending') ? 'selected' : ''; ?>>
                                    <?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান'; ?>
                                </option>
                                <option value="verified" <?php echo ($editProvider && $editProvider['verification_status'] === 'verified') ? 'selected' : ''; ?>>
                                    <?php echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত'; ?>
                                </option>
                                <option value="rejected" <?php echo ($editProvider && $editProvider['verification_status'] === 'rejected') ? 'selected' : ''; ?>>
                                    <?php echo $currentLang === 'en' ? 'Rejected' : 'প্রত্যাখ্যান'; ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo ($editProvider && $editProvider['is_active']) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                <?php echo $currentLang === 'en' ? 'Active' : 'সক্রিয়'; ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" name="save_provider" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            <?php echo $currentLang === 'en' ? 'Save' : 'সংরক্ষণ করুন'; ?>
                        </button>
                        <button type="button" onclick="closeProviderModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
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
        function openAddProviderModal() {
            document.getElementById('providerModal').classList.remove('hidden');
        }
        
        function closeProviderModal() {
            document.getElementById('providerModal').classList.add('hidden');
        }
        
        // Close modal if clicking outside
        document.getElementById('providerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProviderModal();
            }
        });
        
        // Auto-open modal if editing
        <?php if ($editProvider): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openAddProviderModal();
        });
        <?php endif; ?>
    </script>
</body>
</html> 