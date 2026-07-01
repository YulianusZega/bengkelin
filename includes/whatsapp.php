<?php
// ============================================================
// BENGKELIN - WhatsApp Integration via Fonnte API
// ============================================================
require_once __DIR__ . '/../config/database.php';

class WhatsApp {
    private PDO $db;
    private string $token;
    private string $apiUrl = 'https://api.fonnte.com/send';

    public function __construct() {
        $this->db = getDB();
        $settings = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wa_token','bengkel_name','bengkel_phone')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->token = $settings['wa_token'] ?? '';
    }

    public function isConfigured(): bool {
        return !empty($this->token);
    }

    /**
     * Send a WhatsApp message via Fonnte
     */
    public function send(string $phone, string $message, string $type = 'general', ?int $referenceId = null): bool {
        if (!$this->isConfigured()) return false;

        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        $postData = [
            'target'  => $phone,
            'message' => $message,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->token
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = ($httpCode === 200);
        $status  = $success ? 'sent' : 'failed';

        // Log the message
        $this->log($phone, $message, $type, $referenceId, $status, $response);

        return $success;
    }

    /**
     * Log WA message to database
     */
    private function log(string $phone, string $message, string $type, ?int $refId, string $status, ?string $response): void {
        $stmt = $this->db->prepare("
            INSERT INTO wa_logs (recipient_phone, message, type, reference_id, status, response)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$phone, $message, $type, $refId, $status, $response]);
    }

    // ──────────────── MESSAGE TEMPLATES ────────────────

    /**
     * Send booking confirmation message
     */
    public function sendBookingConfirm(array $booking): bool {
        $settings = $this->db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $name = $settings['bengkel_name'] ?? 'Bengkelin';
        $addr = $settings['bengkel_address'] ?? '';
        $phone = $settings['bengkel_phone'] ?? '';

        $date = date('d M Y', strtotime($booking['preferred_date']));
        $time = substr($booking['preferred_time'], 0, 5);

        $msg = "✅ *Booking Dikonfirmasi!*\n\n"
             . "Halo *{$booking['customer_name']}*,\n"
             . "Booking Anda di *{$name}* telah dikonfirmasi.\n\n"
             . "📋 *No. Booking:* {$booking['booking_number']}\n"
             . "🚗 *Kendaraan:* {$booking['vehicle_brand']} {$booking['vehicle_model']}\n"
             . "📅 *Tanggal:* {$date}\n"
             . "🕐 *Jam:* {$time} WIB\n"
             . "🔧 *Layanan:* {$booking['service_type']}\n\n"
             . "📍 *Alamat:* {$addr}\n"
             . "📞 *Hubungi:* {$phone}\n\n"
             . "Silakan datang sesuai jadwal. Terima kasih! 🙏";

        return $this->send($booking['customer_phone'], $msg, 'booking_confirm', $booking['id']);
    }

    /**
     * Send WO created notification
     */
    public function sendWoCreated(array $wo, string $customerPhone, string $customerName): bool {
        $settings = $this->db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $name = $settings['bengkel_name'] ?? 'Bengkelin';

        $msg = "🔧 *Work Order Dibuat*\n\n"
             . "Halo *{$customerName}*,\n"
             . "Kendaraan Anda sedang diproses di *{$name}*.\n\n"
             . "📋 *No. WO:* {$wo['wo_number']}\n"
             . "📅 *Check-in:* " . date('d M Y H:i', strtotime($wo['check_in_at'])) . "\n\n"
             . "Anda bisa cek status kendaraan secara real-time melalui link berikut:\n"
             . BASE_URL . "/tracking.php?q=" . urlencode($wo['wo_number']) . "\n\n"
             . "Terima kasih telah mempercayakan kendaraan Anda kepada kami! 🙏";

        return $this->send($customerPhone, $msg, 'wo_created', $wo['id']);
    }

    /**
     * Send WO completed notification
     */
    public function sendWoDone(array $wo, string $customerPhone, string $customerName): bool {
        $settings = $this->db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $name  = $settings['bengkel_name'] ?? 'Bengkelin';
        $phone = $settings['bengkel_phone'] ?? '';
        $total = 'Rp ' . number_format((float)$wo['total'], 0, ',', '.');

        $msg = "✅ *Kendaraan Selesai Dikerjakan!*\n\n"
             . "Halo *{$customerName}*,\n"
             . "Kendaraan Anda sudah selesai diservice di *{$name}*.\n\n"
             . "📋 *No. WO:* {$wo['wo_number']}\n"
             . "💰 *Total Biaya:* {$total}\n\n"
             . "Silakan datang untuk mengambil kendaraan Anda.\n"
             . "📞 *Hubungi:* {$phone}\n\n"
             . "Detail lengkap:\n"
             . BASE_URL . "/tracking.php?q=" . urlencode($wo['wo_number']) . "\n\n"
             . "Terima kasih! ⭐";

        return $this->send($customerPhone, $msg, 'wo_done', $wo['id']);
    }
}
