<?php
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// Handle actions
if (isset($_POST['action']) && isset($_POST['booking_id'])) {
    $bookingId = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    if ($action === 'confirm') {
        // Get booking details for notifications
        $booking = fetchOne("
            SELECT b.*, u.name as customer_name, sp.name as provider_name 
            FROM bookings b 
            JOIN users u ON b.customer_id = u.id 
            JOIN service_providers sp ON b.provider_id = sp.id 
            WHERE b.id = ?
        ", [$bookingId]);
        
        if ($booking) {
            // Update booking status
            executeQuery("UPDATE bookings SET status = 'confirmed' WHERE id = ?", [$bookingId]);
            
            // Send notification to customer
            sendBookingApprovalNotification($bookingId, $booking['customer_id'], $booking['provider_name']);
            
            // Send notification to provider
            sendBookingConfirmationNotification($bookingId, $booking['provider_id'], $booking['customer_name']);
            
            setFlashMessage('success', 'Booking confirmed successfully. Notifications sent to customer and provider.');
        } else {
            setFlashMessage('error', 'Error: Could not find booking details');
        }
    } elseif ($action === 'complete') {
        executeQuery("UPDATE bookings SET status = 'completed' WHERE id = ?", [$bookingId]);
        setFlashMessage('success', 'Booking marked as completed');
    } elseif ($action === 'cancel') {
        executeQuery("UPDATE bookings SET status = 'cancelled' WHERE id = ?", [$bookingId]);
        setFlashMessage('success', 'Booking cancelled successfully');
    }
    
    redirect('bookings.php');
}

// Handle selection actions (cancel)
if (isset($_POST['selection_action']) && isset($_POST['selection_id'])) {
	$selectionId = (int)$_POST['selection_id'];
	$selectionAction = $_POST['selection_action'];

	if ($selectionAction === 'reject') {
		executeQuery("UPDATE customer_provider_selections SET status = 'rejected', updated_at = NOW() WHERE id = ?", [$selectionId]);
		setFlashMessage('success', 'Selection rejected successfully');
	} elseif ($selectionAction === 'approve') {
		// Approve selection: create confirmed booking, update selection, notify users
		$selection = fetchOne("\n\t\t\tSELECT cps.*, u.name as customer_name, sp.name as provider_name\n\t\t\tFROM customer_provider_selections cps\n\t\t\tJOIN users u ON cps.customer_id = u.id\n\t\t\tJOIN service_providers sp ON cps.provider_id = sp.id\n\t\t\tWHERE cps.id = ?\n\t\t", [$selectionId]);
		if ($selection) {
			try {
				$pdo = getDBConnection();
				$pdo->beginTransaction();
				// Create booking as confirmed so provider can see it immediately
				$stmt = $pdo->prepare("\n\t\t\t\tINSERT INTO bookings (customer_id, provider_id, category_id, service_type, booking_date, booking_time, notes, customer_address, status)\n\t\t\t\tVALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')\n\t\t\t");
				$stmt->execute([
					$selection['customer_id'],
					$selection['provider_id'],
					$selection['category_id'],
					$selection['service_type'] ?: null,
					$selection['preferred_date'],
					$selection['preferred_time'],
					$selection['customer_notes'] ?: null,
					$selection['customer_address'] ?: null
				]);
				$bookingId = $pdo->lastInsertId();
				// Update selection status to accepted
				$pdo->prepare("UPDATE customer_provider_selections SET status = 'accepted', provider_responded_at = NOW(), updated_at = NOW() WHERE id = ?")
					->execute([$selectionId]);
				$pdo->commit();
				// Notifications
				sendBookingApprovalNotification($bookingId, $selection['customer_id'], $selection['provider_name']);
				sendBookingConfirmationNotification($bookingId, $selection['provider_id'], $selection['customer_name']);
				setFlashMessage('success', 'Selection approved and booking created');
			} catch (Exception $e) {
				if (isset($pdo)) { try { $pdo->rollBack(); } catch (Exception $e2) {} }
				setFlashMessage('error', 'Failed to approve selection');
			}
		}
	}

	redirect('bookings.php');
}

// Get filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($status) {
    $whereConditions[] = "b.status = ?";
    $params[] = $status;
}

if ($category) {
    $whereConditions[] = "b.category_id = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(u.name LIKE ? OR sp.name LIKE ? OR b.service_type LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($date_from) {
    $whereConditions[] = "b.booking_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $whereConditions[] = "b.booking_date <= ?";
    $params[] = $date_to;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get bookings
$bookings = fetchAll("
    SELECT b.id, b.customer_id, b.provider_id, b.category_id, b.service_type, 
           b.booking_date, b.booking_time, b.notes, b.customer_address, b.status, 
           b.final_price, b.cancellation_reason, b.cancellation_fee, b.created_at, b.updated_at,
           u.name as customer_name, u.phone as customer_phone, 
           sp.name as provider_name, sp.phone as provider_phone,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN service_providers sp ON b.provider_id = sp.id
    LEFT JOIN service_categories sc ON b.category_id = sc.id
    $whereClause
    ORDER BY b.created_at DESC
", $params);

// Get categories for filter
$categories = fetchAll("SELECT id, name, name_bn FROM service_categories WHERE is_active = 1");

// Get active selections for display
$activeSelections = fetchAll("
    SELECT cps.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
           sp.name as provider_name, sp.phone as provider_phone, sp.email as provider_email,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM customer_provider_selections cps
    JOIN users u ON cps.customer_id = u.id
    JOIN service_providers sp ON cps.provider_id = sp.id
    JOIN service_categories sc ON cps.category_id = sc.id
    WHERE cps.status IN ('pending', 'contacted')
    ORDER BY cps.created_at DESC
", []);

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Manage Bookings' : 'বুকিং পরিচালনা'; ?> - S24 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 30%;
            right: 30%;
            animation-delay: 1s;
        }
        
        .floating-element:nth-child(5) {
            width: 40px;
            height: 40px;
            bottom: 40%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .nav-link {
            color: #000000;
            transition: all 0.3s ease;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.1);
        }
        
        .nav-link.active {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.1);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
        
        .status-badge.confirmed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .status-badge.completed {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .status-badge.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .table-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table-row:hover {
            background: rgba(79, 70, 229, 0.05);
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .selection-card {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .selection-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.2);
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
        <div class="floating-element"></div>
    </div>

    <!-- Header -->
    <header class="admin-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-home mr-2"></i>S24
                    </a>
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-shield-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Admin Panel' : 'অ্যাডমিন প্যানেল'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users mr-2"></i><?php echo $currentLang === 'en' ? 'Users' : 'ব্যবহারকারী'; ?>
                    </a>
                    <a href="providers.php" class="nav-link">
                        <i class="fas fa-user-check mr-2"></i><?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link active">
                        <i class="fas fa-calendar-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star mr-2"></i><?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <span class="text-gray-700 font-medium">
                        <i class="fas fa-user-shield mr-2"></i><?php echo htmlspecialchars($admin['username']); ?> (<?php echo $admin['role']; ?>)
                    </span>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <!-- Page Header -->
        <div class="admin-card p-8 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-2">
                        <i class="fas fa-calendar-check mr-3"></i><?php echo $currentLang === 'en' ? 'Manage Bookings' : 'বুকিং পরিচালনা'; ?>
                    </h1>
                    <p class="text-gray-600 text-lg">
                        <i class="fas fa-chart-line mr-2"></i><?php echo count($bookings); ?> <?php echo $currentLang === 'en' ? 'total bookings' : 'মোট বুকিং'; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-purple-600">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="admin-card p-6 mb-8">
                <div class="flex items-center <?php echo $flash['type'] === 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 text-xl"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($flash['message']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="admin-card p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-filter mr-2 text-purple-600"></i><?php echo $currentLang === 'en' ? 'Filter Bookings' : 'বুকিং ফিল্টার'; ?>
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-1"></i><?php echo $currentLang === 'en' ? 'Search' : 'অনুসন্ধান'; ?>
                    </label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $currentLang === 'en' ? 'Customer, provider, service...' : 'গ্রাহক, প্রদানকারী, সেবা...'; ?>"
                           class="form-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1"></i><?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?>
                    </label>
                    <select name="category" class="form-input w-full">
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
                        <i class="fas fa-info-circle mr-1"></i><?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?>
                    </label>
                    <select name="status" class="form-input w-full">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Status' : 'সব অবস্থা'; ?></option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান'; ?>
                        </option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Confirmed' : 'নিশ্চিত'; ?>
                        </option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Completed' : 'সম্পন্ন'; ?>
                        </option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Cancelled' : 'বাতিল'; ?>
                        </option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-1"></i><?php echo $currentLang === 'en' ? 'From Date' : 'শুরুর তারিখ'; ?>
                    </label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                           class="form-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-1"></i><?php echo $currentLang === 'en' ? 'To Date' : 'শেষের তারিখ'; ?>
                    </label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                           class="form-input w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Selections -->
        <div class="admin-card p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-handshake mr-3 text-blue-600"></i>
                <?php echo $currentLang === 'en' ? 'Active Selections' : 'সক্রিয় নির্বাচন'; ?>
            </h2>
            
            <?php if (empty($activeSelections)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-handshake text-6xl text-gray-300 mb-6"></i>
                    <p class="text-gray-500 text-lg">
                        <?php echo $currentLang === 'en' ? 'No active selections found' : 'কোনো সক্রিয় নির্বাচন পাওয়া যায়নি'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($activeSelections as $selection): ?>
                        <div class="selection-card p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                                <!-- Selection Status -->
                                <div class="flex items-center">
                                    <span class="status-badge <?php echo $selection['status'] === 'pending' ? 'pending' : 'confirmed'; ?>">
                                        <?php if ($selection['status'] === 'pending'): ?>
                                            <i class="fas fa-plus mr-2"></i><?php echo $currentLang === 'en' ? 'Add Provider' : 'প্রদানকারী যোগ করুন'; ?>
                                        <?php elseif ($selection['status'] === 'contacted'): ?>
                                            <i class="fas fa-phone mr-2"></i><?php echo $currentLang === 'en' ? 'Contacted' : 'যোগাযোগ করা হয়েছে'; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <!-- Provider Info -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 text-lg">
                                        <i class="fas fa-user-check mr-2 text-blue-600"></i><?php echo htmlspecialchars($selection['provider_name']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($selection['provider_phone']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($selection['provider_email']); ?>
                                    </p>
                                </div>
                                
                                <!-- Service Details -->
                                <div>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <i class="fas fa-tag mr-2 text-green-600"></i><?php echo $currentLang === 'en' ? $selection['category_name'] : $selection['category_name_bn']; ?>
                                    </p>
                                    <?php if ($selection['service_type']): ?>
                                        <p class="text-sm text-gray-600 mb-1">
                                            <i class="fas fa-tools mr-2"></i><?php echo htmlspecialchars($selection['service_type']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-calendar mr-2"></i><?php echo formatDate($selection['preferred_date']); ?> at <?php echo $selection['preferred_time']; ?>
                                    </p>
                                </div>
                                
                                <!-- Customer Info -->
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-user mr-2 text-purple-600"></i><?php echo htmlspecialchars($selection['customer_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mb-1">
                                        <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($selection['customer_phone']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mb-1">
                                        <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($selection['customer_email']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <i class="fas fa-clock mr-2"></i><?php echo formatDateTime($selection['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($selection['customer_notes']): ?>
                                <div class="mt-4 pt-4 border-t border-blue-200">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-comment mr-2 text-blue-600"></i><?php echo htmlspecialchars($selection['customer_notes']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($selection['customer_address']): ?>
                                <div class="mt-3">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-2 text-red-600"></i><?php echo htmlspecialchars($selection['customer_address']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Admin actions -->
                            <div class="mt-6 flex space-x-3">
                                <form method="POST" onsubmit="return confirm('<?php echo $currentLang === 'en' ? 'Approve this selection?' : 'এই নির্বাচন অনুমোদন করবেন?'; ?>')">
                                    <input type="hidden" name="selection_id" value="<?php echo $selection['id']; ?>">
                                    <input type="hidden" name="selection_action" value="approve">
                                    <button type="submit" class="btn-success">
                                        <i class="fas fa-check mr-2"></i><?php echo $currentLang === 'en' ? 'Approve' : 'অনুমোদন'; ?>
                                    </button>
                                </form>
                                <a href="edit_selection.php?id=<?php echo $selection['id']; ?>" class="btn-warning">
                                    <i class="fas fa-edit mr-2"></i><?php echo $currentLang === 'en' ? 'Edit' : 'সম্পাদনা'; ?>
                                </a>
                                <form method="POST" onsubmit="return confirm('<?php echo $currentLang === 'en' ? 'Reject this selection?' : 'এই নির্বাচন প্রত্যাখ্যান করবেন?'; ?>')">
                                    <input type="hidden" name="selection_id" value="<?php echo $selection['id']; ?>">
                                    <input type="hidden" name="selection_action" value="reject">
                                    <button type="submit" class="btn-danger">
                                        <i class="fas fa-times mr-2"></i><?php echo $currentLang === 'en' ? 'Reject' : 'প্রত্যাখ্যান'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bookings Table -->
        <div class="admin-card overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list mr-3 text-purple-600"></i><?php echo $currentLang === 'en' ? 'All Bookings' : 'সব বুকিং'; ?>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="table-header">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-2"></i><?php echo $currentLang === 'en' ? 'Booking Info' : 'বুকিং তথ্য'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-user mr-2"></i><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-user-check mr-2"></i><?php echo $currentLang === 'en' ? 'Provider' : 'প্রদানকারী'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-tag mr-2"></i><?php echo $currentLang === 'en' ? 'Service Details' : 'সেবার বিবরণ'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Customer Address' : 'গ্রাহকের ঠিকানা'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-handshake mr-2"></i><?php echo $currentLang === 'en' ? 'Selection Status' : 'নির্বাচনের অবস্থা'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-2"></i><?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-cogs mr-2"></i><?php echo $currentLang === 'en' ? 'Actions' : 'কর্ম'; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center text-gray-500">
                                    <i class="fas fa-calendar text-6xl mb-6 block text-gray-300"></i>
                                    <p class="text-lg"><?php echo $currentLang === 'en' ? 'No bookings found' : 'কোনো বুকিং পাওয়া যায়নি'; ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-6">
                                        <div class="space-y-2">
                                            <div class="text-lg font-bold text-gray-900">
                                                <i class="fas fa-hashtag mr-2 text-purple-600"></i>#<?php echo $booking['id']; ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-calendar mr-2"></i><?php echo formatDate($booking['booking_date']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-clock mr-2"></i><?php echo $booking['booking_time']; ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-calendar-plus mr-2"></i><?php echo formatDate($booking['created_at']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="space-y-2">
                                            <div class="text-lg font-semibold text-gray-900">
                                                <i class="fas fa-user mr-2 text-blue-600"></i><?php echo htmlspecialchars($booking['customer_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($booking['customer_phone']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="space-y-2">
                                            <div class="text-lg font-semibold text-gray-900">
                                                <i class="fas fa-user-check mr-2 text-green-600"></i><?php echo htmlspecialchars($booking['provider_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($booking['provider_phone']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="space-y-2">
                                            <div class="text-lg font-semibold text-gray-900">
                                                <i class="fas fa-tag mr-2 text-purple-600"></i><?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?>
                                            </div>
                                            <?php if (isset($booking['service_type']) && $booking['service_type']): ?>
                                                <div class="text-sm text-gray-600">
                                                    <i class="fas fa-tools mr-2"></i><?php echo htmlspecialchars($booking['service_type']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($booking['final_price']) && $booking['final_price']): ?>
                                                <div class="text-lg font-bold text-green-600">
                                                    <i class="fas fa-money-bill-wave mr-2"></i><?php echo formatPrice($booking['final_price']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($booking['notes']) && $booking['notes']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-comment mr-2"></i><?php echo htmlspecialchars(substr($booking['notes'], 0, 50)) . (strlen($booking['notes']) > 50 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2 text-red-600"></i><?php echo isset($booking['customer_address']) ? htmlspecialchars($booking['customer_address']) : '-'; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="text-sm">
                                            <?php 
                                            // Check if there's a selection for this booking
                                            $selection = null;
                                            foreach ($activeSelections as $sel) {
                                                if ($sel['customer_id'] == $booking['customer_id'] && 
                                                    $sel['provider_id'] == $booking['provider_id'] &&
                                                    $sel['category_id'] == $booking['category_id']) {
                                                    $selection = $sel;
                                                    break;
                                                }
                                            }
                                            
                                            if ($selection) {
                                                if ($selection['status'] === 'pending') {
                                                    echo '<span class="status-badge pending"><i class="fas fa-plus mr-1"></i>Add Provider</span>';
                                                } elseif ($selection['status'] === 'contacted') {
                                                    echo '<span class="status-badge confirmed"><i class="fas fa-phone mr-1"></i>Contacted</span>';
                                                } else {
                                                    echo '<span class="status-badge pending">-</span>';
                                                }
                                            } else {
                                                // Fallback to booking status
                                                if ($booking['status'] === 'pending') {
                                                    echo '<span class="status-badge pending"><i class="fas fa-plus mr-1"></i>Add Provider</span>';
                                                } elseif ($booking['status'] === 'confirmed') {
                                                    echo '<span class="status-badge confirmed"><i class="fas fa-phone mr-1"></i>Contacted</span>';
                                                } else {
                                                    echo '<span class="status-badge pending">-</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <span class="status-badge <?php echo $booking['status']; ?>">
                                            <i class="fas fa-circle mr-2"></i><?php echo t($booking['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-6">
                                        <div class="flex space-x-3">
                                            <a href="../customer/booking_details.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn-warning" title="<?php echo $currentLang === 'en' ? 'View Details' : 'বিস্তারিত দেখুন'; ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn-success" 
                                                            onclick="return confirm('<?php echo $currentLang === 'en' ? 'Confirm this booking?' : 'এই বুকিং নিশ্চিত করবেন?'; ?>')"
                                                            title="<?php echo $currentLang === 'en' ? 'Confirm Booking' : 'বুকিং নিশ্চিত করুন'; ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn-warning" 
                                                            onclick="return confirm('<?php echo $currentLang === 'en' ? 'Mark as completed?' : 'সম্পন্ন হিসেবে চিহ্নিত করবেন?'; ?>')"
                                                            title="<?php echo $currentLang === 'en' ? 'Mark as Completed' : 'সম্পন্ন হিসেবে চিহ্নিত করুন'; ?>">
                                                        <i class="fas fa-flag-checkered"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn-danger"
                                                            onclick="return confirm('<?php echo $currentLang === 'en' ? 'Cancel this booking?' : 'এই বুকিং বাতিল করবেন?'; ?>')"
                                                            title="<?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
</body>
</html> 