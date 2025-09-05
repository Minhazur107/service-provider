<?php
require_once '../includes/functions.php';

if (!isProviderLoggedIn()) {
	redirect('../auth/login.php');
}

$currentLang = getLanguage();
$provider = getCurrentProvider();

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify booking belongs to provider and is confirmed
$booking = fetchOne("SELECT * FROM bookings WHERE id = ? AND provider_id = ? AND status = 'confirmed'", [$bookingId, $provider['id']]);
if (!$booking) {
	setFlashMessage('error', $currentLang === 'en' ? 'Booking not found or cannot be completed' : 'বুকিং পাওয়া যায়নি বা সম্পন্ন করা যাবে না');
	redirect('bookings.php');
}

try {
	// Mark completed and set updated_at. Keep final_price as is if already set, else leave null (admin/provider may set separately)
	executeQuery("UPDATE bookings SET status = 'completed', updated_at = NOW() WHERE id = ?", [$bookingId]);
	setFlashMessage('success', $currentLang === 'en' ? 'Booking marked as completed' : 'বুকিং সম্পন্ন হিসেবে চিহ্নিত হয়েছে');
} catch (Exception $e) {
	setFlashMessage('error', $currentLang === 'en' ? 'Failed to complete booking' : 'বুকিং সম্পন্ন করতে ব্যর্থ');
}

redirect('dashboard.php'); 