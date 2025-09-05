<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
	redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Handle logout
if (isset($_GET['logout'])) {
	session_destroy();
	redirect('../index.php');
}

$bookingId = $_GET['id'] ?? 0;

// Fetch booking (pending or confirmed can be edited)
$booking = fetchOne("\n\tSELECT b.*, sp.name as provider_name, sp.phone as provider_phone, sc.name as category_name, sc.name_bn as category_name_bn\n\tFROM bookings b\n\tJOIN service_providers sp ON b.provider_id = sp.id\n\tJOIN service_categories sc ON b.category_id = sc.id\n\tWHERE b.id = ? AND b.customer_id = ? AND b.status IN ('pending','confirmed')\n", [$bookingId, $user['id']]);

if (!$booking) {
	setFlashMessage('error', $currentLang === 'en' ? 'Booking not found or cannot be edited' : 'বুকিং পাওয়া যায়নি বা সম্পাদনা করা যাবে না');
	redirect('bookings.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$serviceType = sanitizeInput($_POST['service_type']);
	$bookingDate = $_POST['booking_date'];
	$bookingTime = $_POST['booking_time'];
	$customerAddress = sanitizeInput($_POST['customer_address']);
	$notes = sanitizeInput($_POST['notes']);
	
	if (empty($bookingDate)) {
		$error = $currentLang === 'en' ? 'Service date is required' : 'সেবার তারিখ প্রয়োজন';
	} elseif (strtotime($bookingDate) < strtotime('today')) {
		$error = $currentLang === 'en' ? 'Service date cannot be in the past' : 'সেবার তারিখ অতীত হতে পারে না';
	} elseif (empty($bookingTime)) {
		$error = $currentLang === 'en' ? 'Service time is required' : 'সেবার সময় প্রয়োজন';
	} elseif (empty($customerAddress)) {
		$error = $currentLang === 'en' ? 'Service address is required' : 'সেবার ঠিকানা প্রয়োজন';
	} else {
		try {
			executeQuery("\n\t\t\tUPDATE bookings\n\t\t\tSET service_type = ?, booking_date = ?, booking_time = ?, customer_address = ?, notes = ?, updated_at = NOW()\n\t\t\tWHERE id = ? AND customer_id = ?\n\t\t", [
				$serviceType ?: null,
				$bookingDate,
				$bookingTime,
				$customerAddress,
				$notes ?: null,
				$booking['id'],
				$user['id']
			]);
			setFlashMessage('success', $currentLang === 'en' ? 'Booking updated successfully!' : 'বুকিং সফলভাবে হালনাগাদ হয়েছে!');
			redirect('booking_details.php?id=' . $booking['id']);
		} catch (Exception $e) {
			$error = $currentLang === 'en' ? 'Failed to update booking. Please try again.' : 'বুকিং হালনাগাদ করতে ব্যর্থ। আবার চেষ্টা করুন।';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $currentLang === 'en' ? 'Edit Booking' : 'বুকিং সম্পাদনা'; ?> - S24</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
	<!-- Header -->
	<header class="bg-white shadow-sm">
		<nav class="container mx-auto px-4 py-4">
			<div class="flex justify-between items-center">
				<div class="flex items-center space-x-4">
					<a href="../index.php" class="text-2xl font-bold text-purple-600">S24</a>
					<span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Edit Booking' : 'বুকিং সম্পাদনা'; ?></span>
				</div>
				<div class="flex items-center space-x-4">
					<a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="text-purple-600 hover:text-purple-700">
						<i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Booking' : 'বুকিং এ ফিরে যান'; ?>
					</a>
					<a href="?logout=1" class="text-red-600 hover:text-red-700">
						<i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
					</a>
				</div>
			</div>
		</nav>
	</header>

	<div class="container mx-auto px-4 py-8">
		<div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6">
			<h1 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $currentLang === 'en' ? 'Edit Booking' : 'বুকিং সম্পাদনা'; ?></h1>

			<?php if ($error): ?>
				<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
					<?php echo $error; ?>
				</div>
			<?php endif; ?>

			<form method="POST" class="space-y-6">
				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?></label>
						<input type="text" name="service_type" value="<?php echo htmlspecialchars($_POST['service_type'] ?? ($booking['service_type'] ?? '')); ?>"
							class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Date' : 'সেবার তারিখ'; ?> *</label>
						<input type="date" name="booking_date" value="<?php echo htmlspecialchars($_POST['booking_date'] ?? $booking['booking_date']); ?>"
							min="<?php echo date('Y-m-d'); ?>"
							class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Time' : 'সেবার সময়'; ?> *</label>
						<input type="time" name="booking_time" value="<?php echo htmlspecialchars($_POST['booking_time'] ?? $booking['booking_time']); ?>"
							class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Address' : 'সেবার ঠিকানা'; ?> *</label>
						<textarea name="customer_address" rows="3"
							class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required><?php echo htmlspecialchars($_POST['customer_address'] ?? ($booking['customer_address'] ?? '')); ?></textarea>
					</div>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Additional Notes' : 'অতিরিক্ত নোট'; ?></label>
					<textarea name="notes" rows="4"
						class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['notes'] ?? ($booking['notes'] ?? '')); ?></textarea>
				</div>

				<div class="flex justify-end space-x-4">
					<a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
						<?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
					</a>
					<button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
						<i class="fas fa-save mr-2"></i>
						<?php echo $currentLang === 'en' ? 'Save Changes' : 'পরিবর্তন সংরক্ষণ করুন'; ?>
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Footer -->
	<footer class="bg-gray-800 text-white py-8 mt-12">
		<div class="container mx-auto px-4 text-center">
			<p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
		</div>
	</footer>
</body>
</html> 