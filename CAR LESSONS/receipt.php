<?php
session_start();
include "staff_auth.php";
include "config.php";

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$payment = null;
$verification_url = '';

function buildVerificationUrl($searchValue) {
    return appVerificationUrl($searchValue);
}

if ($payment_id > 0) {
    $query = mysqli_query($conn, "SELECT p.*, 
        a.application_number,
        a.license_plate_number,
        a.applicant_name,
        a.applicant_contact,
        a.county AS application_county,
        v.id AS vehicle_id,
        v.plate_number,
        v.owner_name,
        v.owner_contact,
        v.county AS vehicle_county,
        v.make,
        v.model,
        v.year,
        v.color,
        v.vehicle_type
    FROM payments p
    LEFT JOIN applications a ON p.application_id = a.id
    LEFT JOIN vehicles v ON v.id = COALESCE(p.vehicle_id, a.vehicle_id)
    WHERE p.id = $payment_id LIMIT 1");
    if ($query) {
        $payment = mysqli_fetch_assoc($query);
        if (!empty($payment['license_plate_number'])) {
            $verification_url = buildVerificationUrl($payment['license_plate_number']);
        } elseif (!empty($payment['plate_number'])) {
            $verification_url = buildVerificationUrl($payment['plate_number']);
        } elseif (!empty($payment['application_number'])) {
            $verification_url = buildVerificationUrl($payment['application_number']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; }
        .receipt { max-width: 700px; margin: 0 auto; background: white; border: 1px solid #d1d5db; border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .header { text-align: center; border-bottom: 2px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 20px; }
        .header h1 { color: #0a2463; margin: 0 0 6px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; color: #374151; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .total { font-size: 20px; font-weight: bold; color: #0a2463; }
        .verify-block { margin-top: 24px; padding: 18px; border: 1px solid #dbeafe; border-radius: 16px; background: #f8fbff; display: flex; gap: 18px; align-items: center; }
        .verify-qr { width: 124px; min-height: 124px; padding: 8px; border-radius: 12px; background: white; border: 1px solid #d1d5db; display: flex; align-items: center; justify-content: center; }
        .verify-copy h3 { margin: 0 0 8px; color: #0a2463; }
        .verify-copy p { margin: 0 0 8px; color: #475569; line-height: 1.5; }
        .verify-copy code { display: inline-block; padding: 6px 8px; border-radius: 8px; background: #e2e8f0; color: #0f172a; font-size: 12px; }
        .actions { text-align: center; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; background: #0a2463; color: white; text-decoration: none; }
        @media print { body { background: white; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>Transport Union Authority</h1>
            <p>License Plate Payment Receipt</p>
        </div>
        <?php if ($payment): ?>
            <div class="meta">
                <div>
                    <div class="row"><strong>Receipt #</strong><span><?php echo htmlspecialchars($payment['receipt_number']); ?></span></div>
                    <div class="row"><strong>Application</strong><span><?php echo htmlspecialchars($payment['application_number'] ?? 'Existing Plate Request'); ?></span></div>
                    <div class="row"><strong>Applicant</strong><span><?php echo htmlspecialchars($payment['applicant_name'] ?: $payment['owner_name'] ?: ''); ?></span></div>
                    <div class="row"><strong>Plate</strong><span><?php echo htmlspecialchars($payment['license_plate_number'] ?: $payment['plate_number'] ?: ''); ?></span></div>
                </div>
                <div>
                    <div class="row"><strong>Contact</strong><span><?php echo htmlspecialchars($payment['applicant_contact'] ?: $payment['owner_contact'] ?: ''); ?></span></div>
                    <div class="row"><strong>County</strong><span><?php echo htmlspecialchars($payment['application_county'] ?: $payment['vehicle_county'] ?: ''); ?></span></div>
                    <div class="row"><strong>Vehicle</strong><span><?php echo htmlspecialchars(trim(($payment['make'] ?? '') . ' ' . ($payment['model'] ?? '')) ?: ''); ?></span></div>
                    <div class="row"><strong>Date</strong><span><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span></div>
                </div>
            </div>
            <div class="row"><strong>Payment Method</strong><span><?php echo htmlspecialchars($payment['payment_method']); ?></span></div>
            <div class="row"><strong>Currency</strong><span><?php echo htmlspecialchars(appNormalizePaymentCurrency($payment['payment_currency'] ?? 'USD')); ?></span></div>
            <div class="row"><strong>Status</strong><span><?php echo htmlspecialchars($payment['status']); ?></span></div>
            <div class="row total"><strong>Amount Paid</strong><span><?php echo htmlspecialchars(appFormatPaymentAmount($payment['amount'], $payment['payment_currency'] ?? 'USD')); ?></span></div>
            <?php if ($verification_url): ?>
                <div class="verify-block">
                    <div class="verify-qr" data-verification-url="<?php echo htmlspecialchars($verification_url, ENT_QUOTES); ?>"></div>
                    <div class="verify-copy">
                        <h3>Scan To Verify</h3>
                        <p>Scan this QR code to open the verification page for <?php echo htmlspecialchars($payment['license_plate_number'] ?: $payment['plate_number'] ?: ($payment['application_number'] ?? '')); ?>.</p>
                        <code><?php echo htmlspecialchars($payment['license_plate_number'] ?: $payment['plate_number'] ?: ($payment['application_number'] ?? '')); ?></code>
                    </div>
                </div>
            <?php endif; ?>
            <p style="margin-top: 20px; color: #6b7280;">Thank you for paying for your license plate processing.</p>
        <?php else: ?>
            <p>No payment record was found.</p>
        <?php endif; ?>
        <div class="actions">
            <a href="#" class="btn" onclick="window.print(); return false;">Print Receipt</a>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-verification-url]').forEach(function (node) {
            var url = node.getAttribute('data-verification-url');
            if (!url) {
                return;
            }

            new QRCode(node, {
                text: url,
                width: 108,
                height: 108,
                colorDark: '#0a2463',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
