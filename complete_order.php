<?php
/**
 * Complete Order API
 * File: complete_order.php
 * 
 * Mark order as completed and send WhatsApp notification
 */

session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['order_id']) || empty($input['order_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

$order_id = intval($input['order_id']);

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// First, get order details
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$result = $order_stmt->get_result();
$order = $result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
    $conn->close();
    exit;
}

// Check if order is in processing status
if ($order['status'] !== 'processing') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order must be in processing status. Current status: ' . $order['status']
    ]);
    $conn->close();
    exit;
}

// Update order status to completed AND update production status
$conn->begin_transaction();

try {
    // 1. Update orders table
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $order_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update order: ' . $update_stmt->error);
    }
    $update_stmt->close();
    
    // 2. Update productions table
    $prod_stmt = $conn->prepare("UPDATE productions SET status = 'completed' WHERE order_id = ?");
    $prod_stmt->bind_param("i", $order_id);
    
    if (!$prod_stmt->execute()) {
        throw new Exception('Failed to update production: ' . $prod_stmt->error);
    }
    $prod_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Generate WhatsApp notification for completion
    $wa_message = urlencode(
        "Halo " . $order['customer_name'] . ",\n\n" .
        "🎉 *Video Anda Sudah Selesai!*\n\n" .
        "Detail Pesanan:\n" .
        "━━━━━━━━━━━━━━━━━━\n" .
        "Order ID: *#" . $order['id'] . "*\n" .
        "Layanan: " . $order['service'] . "\n" .
        "Paket: " . $order['package'] . "\n" .
        "Status: *Selesai* ✅\n\n" .
        "Video hasil editing sudah kami kirim melalui chat ini. " .
        "Silakan cek dan berikan feedback Anda.\n\n" .
        "Jika ada revisi atau pertanyaan, silakan balas chat ini.\n\n" .
        "Terima kasih telah mempercayai FrameKlip! 🎥\n" .
        "Jangan lupa order lagi ya! 😊\n\n" .
        "---\n" .
        "FrameKlip - Jasa Editing Video Profesional"
    );
    
    // Get customer phone number (remove non-numeric characters)
    $customer_phone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
    
    // Add 62 if starts with 0
    if (substr($customer_phone, 0, 1) === '0') {
        $customer_phone = '62' . substr($customer_phone, 1);
    }
    
    // Build WhatsApp URL (to customer)
    $wa_url = 'https://wa.me/' . $customer_phone . '?text=' . $wa_message;
    
    echo json_encode([
        'success' => true,
        'message' => 'Order berhasil ditandai sebagai SELESAI',
        'order_id' => $order_id,
        'wa_url' => $wa_url,
        'customer_name' => $order['customer_name'],
        'customer_phone' => $customer_phone
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to complete order: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
<?php
/**
 * Complete Order API
 * File: complete_order.php
 * 
 * Mark order as completed and send WhatsApp notification
 */

session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['order_id']) || empty($input['order_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

$order_id = intval($input['order_id']);

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// First, get order details
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$result = $order_stmt->get_result();
$order = $result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
    $conn->close();
    exit;
}

// Check if order is in processing status
if ($order['status'] !== 'processing') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order must be in processing status. Current status: ' . $order['status']
    ]);
    $conn->close();
    exit;
}

// Update order status to completed AND update production status
$conn->begin_transaction();

try {
    // 1. Update orders table
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $order_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update order: ' . $update_stmt->error);
    }
    $update_stmt->close();
    
    // 2. Update productions table
    $prod_stmt = $conn->prepare("UPDATE productions SET status = 'completed' WHERE order_id = ?");
    $prod_stmt->bind_param("i", $order_id);
    
    if (!$prod_stmt->execute()) {
        throw new Exception('Failed to update production: ' . $prod_stmt->error);
    }
    $prod_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Generate WhatsApp notification for completion
    $wa_message = urlencode(
        "Halo " . $order['customer_name'] . ",\n\n" .
        "🎉 *Video Anda Sudah Selesai!*\n\n" .
        "Detail Pesanan:\n" .
        "━━━━━━━━━━━━━━━━━━\n" .
        "Order ID: *#" . $order['id'] . "*\n" .
        "Layanan: " . $order['service'] . "\n" .
        "Paket: " . $order['package'] . "\n" .
        "Status: *Selesai* ✅\n\n" .
        "Video hasil editing sudah kami kirim melalui chat ini. " .
        "Silakan cek dan berikan feedback Anda.\n\n" .
        "Jika ada revisi atau pertanyaan, silakan balas chat ini.\n\n" .
        "Terima kasih telah mempercayai FrameKlip! 🎥\n" .
        "Jangan lupa order lagi ya! 😊\n\n" .
        "---\n" .
        "FrameKlip - Jasa Editing Video Profesional"
    );
    
    // Get customer phone number (remove non-numeric characters)
    $customer_phone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
    
    // Add 62 if starts with 0
    if (substr($customer_phone, 0, 1) === '0') {
        $customer_phone = '62' . substr($customer_phone, 1);
    }
    
    // Build WhatsApp URL (to customer)
    $wa_url = 'https://wa.me/' . $customer_phone . '?text=' . $wa_message;
    
    echo json_encode([
        'success' => true,
        'message' => 'Order berhasil ditandai sebagai SELESAI',
        'order_id' => $order_id,
        'wa_url' => $wa_url,
        'customer_name' => $order['customer_name'],
        'customer_phone' => $customer_phone
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to complete order: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
