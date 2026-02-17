<?php
// verify_payment.php - API untuk verifikasi pembayaran (v3.0 - 4 tables)
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Ambil data dari request
$input = json_decode(file_get_contents('php://input'), true);

// Validasi input
if (empty($input['order_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order ID required'
    ]);
    exit;
}

// Sanitasi data
$order_id = (int)$input['order_id'];
$gdrive_link = isset($input['gdrive_link']) ? sanitize_input($input['gdrive_link']) : '';
$payment_amount = isset($input['payment_amount']) ? floatval($input['payment_amount']) : 0;
$admin_notes = isset($input['admin_notes']) ? sanitize_input($input['admin_notes']) : '';
$verified_by = (int)$_SESSION['admin_id']; // ID admin (bukan username)

// Koneksi database
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Start transaction untuk update 3 tabel
$conn->begin_transaction();

try {
    // 1. Update PAYMENTS table
    $stmt_payment = $conn->prepare("UPDATE payments SET 
        total_amount = ?,
        payment_verified = 1,
        verified_at = NOW(),
        verified_by = ?
        WHERE order_id = ?");
    
    if (!$stmt_payment) {
        throw new Exception('Prepare payment statement failed: ' . $conn->error);
    }
    
    $stmt_payment->bind_param("dii", $payment_amount, $verified_by, $order_id);
    
    if (!$stmt_payment->execute()) {
        throw new Exception('Failed to update payment: ' . $stmt_payment->error);
    }
    
    $stmt_payment->close();
    
    // 2. Update PRODUCTIONS table
    $stmt_prod = $conn->prepare("UPDATE productions SET 
        gdrive_link = ?,
        status = 'in_progress',
        admin_notes = ?
        WHERE order_id = ?");
    
    if (!$stmt_prod) {
        throw new Exception('Prepare production statement failed: ' . $conn->error);
    }
    
    $stmt_prod->bind_param("ssi", $gdrive_link, $admin_notes, $order_id);
    
    if (!$stmt_prod->execute()) {
        throw new Exception('Failed to update production: ' . $stmt_prod->error);
    }
    
    $stmt_prod->close();
    
    // 3. Update ORDERS status
    $stmt_order = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
    
    if (!$stmt_order) {
        throw new Exception('Prepare order statement failed: ' . $conn->error);
    }
    
    $stmt_order->bind_param("i", $order_id);
    
    if (!$stmt_order->execute()) {
        throw new Exception('Failed to update order status: ' . $stmt_order->error);
    }
    
    $stmt_order->close();
    
    // 4. Get order details untuk WA message
    $order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    
    if (!$order_stmt) {
        throw new Exception('Prepare select statement failed: ' . $conn->error);
    }
    
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $result = $order_stmt->get_result();
    $order = $result->fetch_assoc();
    $order_stmt->close();
    
    // Commit transaction - semua berhasil!
    $conn->commit();
    
    if ($order) {
        // Get estimate time based on package type
        $estimate = ($order['package'] === 'Fast Track') ? '1-2 hari kerja' : '3-4 hari kerja';
        
        // Generate WhatsApp notification message TO CUSTOMER
        $wa_message = urlencode(
            "Halo *" . $order['customer_name'] . "*,\n\n" .
            "✅ *Pembayaran Terverifikasi!*\n\n" .
            "Terima kasih atas pembayaran Anda. Berikut detail pesanan:\n\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "Order ID: *#" . $order['id'] . "*\n" .
            "Layanan: *" . $order['service'] . "*\n" .
            "Paket: *" . $order['package'] . "*\n" .
            "Estimasi Selesai: *" . $estimate . "*\n" .
            "Status: *Sedang Diproses* 🎬\n" .
            "━━━━━━━━━━━━━━━━━━\n\n" .
            "Video Anda sudah kami terima dan sedang dalam antrian editing.\n\n" .
            "Kami akan segera mengerjakan pesanan Anda dan memberitahu ketika sudah selesai.\n\n" .
            "Terima kasih telah mempercayai *FrameKlip*! 🎥✨\n\n" .
            "---\n" .
            "FrameKlip - Jasa Editing Video Profesional"
        );
        
        // Clean customer phone number
        $customer_phone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
        
        // Add 62 if starts with 0
        if (substr($customer_phone, 0, 1) === '0') {
            $customer_phone = '62' . substr($customer_phone, 1);
        }
        
        // Build WhatsApp URL to CUSTOMER
        $wa_url = 'https://wa.me/' . $customer_phone . '?text=' . $wa_message;
        
        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran berhasil diverifikasi',
            'order_id' => $order_id,
            'wa_url' => $wa_url,
            'customer_phone' => $customer_phone
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran berhasil diverifikasi'
        ]);
    }
    
} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal verifikasi pembayaran: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
