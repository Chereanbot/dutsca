<?php
// print_transaction.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    die('Invalid transaction ID.');
}

$db = getDB();
$tx = $db->fetchOne("
    SELECT t.*, u.name as user_name, u.email, u.department, u.profile_image, p.name as processed_by_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users p ON t.processed_by = p.id
    WHERE t.id = ?
", [$id]);

if (!$tx) {
    die('Transaction not found.');
}

// Generate QR code URL (using Google Chart API for demo)
$qrData = urlencode('Transaction ID: ' . $tx['id'] . '\nUser: ' . $tx['user_name'] . '\nAmount: ₦' . number_format($tx['amount'],2) . '\nStatus: ' . $tx['status']);
$qrUrl = "https://chart.googleapis.com/chart?chs=220x220&cht=qr&chl=$qrData&choe=UTF-8";

$logo = '/dutsca/assets/images/logo.png';
$approvedBy = $tx['processed_by_name'] ?? '-';

// Add watermark and digital signature
$watermark = '/dutsca/assets/images/logo-watermark.png'; // Use a faint PNG logo for watermark
$signature = '/dutsca/assets/images/signature.png'; // Digital signature image (transparent PNG)
$hasSignature = file_exists($_SERVER['DOCUMENT_ROOT'] . $signature);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Receipt #<?= $tx['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00572d;
            --secondary: #1f9345;
            --accent: #f3c300;
            --text: #333;
            --bg: #f4f4f4;
            --white: #fff;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', Arial, sans-serif; }
        .receipt-container {
            max-width: 700px;
            margin: 2rem auto;
            background: var(--white);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 0;
            overflow: hidden;
            position: relative;
        }
        .receipt-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 400px;
            height: 400px;
            opacity: 0.07;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
            background: url('<?= $watermark ?>') center center no-repeat;
            background-size: contain;
        }
        .receipt-content { position: relative; z-index: 1; }
        .receipt-header {
            background: linear-gradient(90deg, var(--primary) 60%, var(--secondary) 100%);
            color: #fff;
            padding: 2rem 2rem 1.2rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .receipt-header .logo { height: 60px; }
        .receipt-header .verified-badge {
            background: var(--accent);
            color: var(--primary);
            padding: 0.4em 1.2em;
            border-radius: 1.5em;
            font-weight: 700;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 0.5em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .receipt-title {
            color: var(--primary);
            font-weight: 700;
            letter-spacing: 1px;
            margin: 2rem 0 1.5rem 0;
            text-align: center;
            font-size: 2rem;
        }
        .receipt-table td {
            padding: 0.7em 0.9em;
            vertical-align: top;
            border: none;
        }
        .receipt-table tr {
            border-bottom: 1px solid #eee;
        }
        .user-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); margin-right: 0.7em; }
        .qr-section {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            margin: 2.5rem 0 1.5rem 0;
            justify-content: space-between;
        }
        .qr {
            border: 5px solid var(--primary);
            border-radius: 16px;
            background: #fff;
            padding: 0.7rem;
            width: 220px;
            height: 220px;
        }
        .qr-label { color: var(--primary); font-weight: 600; text-align: center; margin-top: 0.5rem; }
        .approved-section {
            margin-top: 2.5rem;
            padding: 1.5rem 2rem 2rem 2rem;
            background: #f9f9f9;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            min-width: 260px;
        }
        .approved-label { color: var(--primary); font-weight: 600; }
        .signature-line {
            border-bottom: 2px solid #333;
            width: 220px;
            margin: 0.5rem 0 0.2rem 0;
            height: 2.2em;
            position: relative;
        }
        .signature-img {
            position: absolute;
            left: 0; bottom: -2px;
            height: 2.2em;
            max-width: 220px;
            opacity: 0.85;
        }
        .approved-name { font-weight: 700; color: var(--secondary); }
        .receipt-footer {
            text-align: center;
            color: #888;
            font-size: 1.05em;
            margin: 2rem 0 1rem 0;
            background: #f4f4f4;
            padding: 1.2em 0 0.7em 0;
            border-top: 1px solid #e0e0e0;
            letter-spacing: 0.5px;
        }
        .receipt-footer .brand {
            color: var(--primary); font-weight: 700; letter-spacing: 1px;
        }
        .receipt-footer .support {
            color: var(--secondary); font-weight: 600;
        }
        .print-btn {
            position: fixed;
            top: 24px;
            right: 32px;
            z-index: 1000;
        }
        @media print {
            body { background: #fff; }
            .receipt-container { box-shadow: none; border: 1px solid #ccc; }
            .print-btn, .no-print { display: none !important; }
        }
        @media (max-width: 800px) {
            .receipt-container { padding: 0; }
            .receipt-header, .approved-section { padding: 1rem; }
            .qr-section { flex-direction: column; gap: 1.5rem; }
        }
    </style>
</head>
<body>
<button class="btn btn-primary print-btn no-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
<div class="receipt-container">
    <div class="receipt-watermark"></div>
    <div class="receipt-content">
        <div class="receipt-header">
            <img src="<?= $logo ?>" class="logo" alt="Logo">
            <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
        </div>
        <div class="receipt-title">Transaction Receipt</div>
        <table class="table receipt-table mb-4">
            <tr>
                <td><b>Transaction ID</b></td>
                <td>#<?= $tx['id'] ?></td>
            </tr>
            <tr>
                <td><b>User</b></td>
                <td>
                    <img src="<?= ($tx['profile_image'] ?? '') ? '/dutsca/assets/images/profile/' . htmlspecialchars($tx['profile_image'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($tx['user_name'] ?? '') . '&background=00572d&color=fff&size=64' ?>" class="user-avatar" alt="Avatar">
                    <?= htmlspecialchars($tx['user_name'] ?? '') ?>
                </td>
            </tr>
            <tr>
                <td><b>Email</b></td>
                <td><?= htmlspecialchars($tx['email'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Department</b></td>
                <td><?= htmlspecialchars($tx['department'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Type</b></td>
                <td><?= ucfirst($tx['type'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Status</b></td>
                <td><?= ucfirst($tx['status'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Reference</b></td>
                <td><?= htmlspecialchars($tx['reference'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Description</b></td>
                <td><?= htmlspecialchars($tx['description'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Amount</b></td>
                <td>₦<?= number_format($tx['amount'] ?? 0,2) ?></td>
            </tr>
            <tr>
                <td><b>Created</b></td>
                <td><?= htmlspecialchars($tx['created_at'] ?? '') ?></td>
            </tr>
            <tr>
                <td><b>Processed By</b></td>
                <td><?= htmlspecialchars($tx['processed_by_name'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><b>Related User</b></td>
                <td><?= $tx['related_user_id'] ? 'User #' . htmlspecialchars($tx['related_user_id'] ?? '') : '-' ?></td>
            </tr>
        </table>
        <div class="qr-section">
            <div>
                <img src="<?= $qrUrl ?>" class="qr" alt="QR Code">
                <div class="qr-label">Scan to verify</div>
            </div>
            <div class="approved-section">
                <div class="approved-label">Approved By:</div>
                <div class="signature-line">
                    <?php if ($hasSignature): ?>
                        <img src="<?= $signature ?>" class="signature-img" alt="Signature">
                    <?php endif; ?>
                </div>
                <div class="approved-name mt-1"><?= htmlspecialchars($approvedBy) ?></div>
            </div>
        </div>
        <div class="receipt-footer">
            <span class="brand">DUTSCA Cooperative Society</span> &nbsp; | &nbsp; <i class="fas fa-globe me-1"></i> www.dutsca.com &nbsp; | &nbsp; <i class="fas fa-envelope me-1"></i> info@dutsca.com<br>
            <span class="support">Support: +234-800-123-4567</span>
        </div>
    </div>
</div>
</body>
</html> 