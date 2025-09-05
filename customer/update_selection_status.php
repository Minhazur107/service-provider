<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

$selectionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

if (!$selectionId || !$status) {
    redirect('my_selections.php');
}

// Verify the selection belongs to the current user
$selection = fetchOne("
    SELECT id FROM customer_provider_selections 
    WHERE id = ? AND customer_id = ?
", [$selectionId, $user['id']]);

if (!$selection) {
    redirect('my_selections.php');
}

// Update the status
if ($status === 'contacted') {
    executeQuery("
        UPDATE customer_provider_selections 
        SET status = 'contacted', customer_contacted_at = NOW() 
        WHERE id = ?
    ", [$selectionId]);
    
    setFlashMessage('success', $currentLang === 'en' ? 'Selection marked as contacted successfully!' : 'নির্বাচন সফলভাবে যোগাযোগ করা হয়েছে হিসেবে চিহ্নিত করা হয়েছে!');
} elseif ($status === 'accepted') {
    executeQuery("
        UPDATE customer_provider_selections 
        SET status = 'accepted', updated_at = NOW() 
        WHERE id = ?
    ", [$selectionId]);
    
    setFlashMessage('success', $currentLang === 'en' ? 'Selection accepted successfully!' : 'নির্বাচন সফলভাবে গৃহীত হয়েছে!');
} elseif ($status === 'rejected') {
    executeQuery("
        UPDATE customer_provider_selections 
        SET status = 'rejected', updated_at = NOW() 
        WHERE id = ?
    ", [$selectionId]);
    
    setFlashMessage('success', $currentLang === 'en' ? 'Selection rejected successfully!' : 'নির্বাচন সফলভাবে প্রত্যাখ্যান করা হয়েছে!');
}

redirect('my_selections.php');
?> 