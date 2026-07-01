<?php
// ============================================================
// BENGKELIN - Public Booking Form (no login required)
// ============================================================
require_once __DIR__ . '/config/database.php';
session_start();

$db = getDB();

// Load settings
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$bengkelName    = $settings['bengkel_name'] ?? 'Bengkelin';
$bengkelTagline = $settings['bengkel_tagline'] ?? 'Bengkel Otomotif SMKS Pembda Nias — Teaching Factory (Tefa)';
$bengkelAddress = $settings['bengkel_address'] ?? '';
$bengkelPhone   = $settings['bengkel_phone'] ?? '';
$bengkelEmail   = $settings['bengkel_email'] ?? '';
$bengkelHours   = $settings['bengkel_hours'] ?? '';
$maxBooking     = (int)($settings['max_booking_per_day'] ?? 10);

// Load service categories
$categories = $db->query("SELECT * FROM service_categories ORDER BY id")->fetchAll();

// Handle form submission
$success = false;
$error   = '';
$bookingNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['customer_name'] ?? '');
    $phone     = trim($_POST['customer_phone'] ?? '');
    $email     = trim($_POST['customer_email'] ?? '');
    $vBrand    = trim($_POST['vehicle_brand'] ?? '');
    $vModel    = trim($_POST['vehicle_model'] ?? '');
    $vYear     = (int)($_POST['vehicle_year'] ?? 0);
    $plate     = trim($_POST['plate_number'] ?? '');
    $vType     = $_POST['vehicle_type'] ?? 'motor';
    $svcType   = trim($_POST['service_type'] ?? '');
    $prefDate  = $_POST['preferred_date'] ?? '';
    $prefTime  = $_POST['preferred_time'] ?? '';
    $complaint = trim($_POST['complaint'] ?? '');

    // Validate
    if (!$name || !$phone || !$vBrand || !$vModel || !$svcType || !$prefDate || !$prefTime) {
        $error = 'Mohon lengkapi semua field yang wajib diisi.';
    } else {
        // Check capacity
        $dayCount = $db->prepare("SELECT COUNT(*) FROM bookings WHERE preferred_date = ? AND status NOT IN ('cancelled')");
        $dayCount->execute([$prefDate]);
        if ((int)$dayCount->fetchColumn() >= $maxBooking) {
            $error = "Maaf, jadwal tanggal tersebut sudah penuh (maks. {$maxBooking} booking/hari). Silakan pilih tanggal lain.";
        } else {
            // Generate booking number
            $today = date('Ymd');
            $cnt = $db->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $bookingNumber = 'BK-' . $today . '-' . str_pad(((int)$cnt) + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO bookings (booking_number, customer_name, customer_phone, customer_email,
                    vehicle_brand, vehicle_model, vehicle_year, plate_number, vehicle_type,
                    service_type, preferred_date, preferred_time, complaint, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')
            ");
            $stmt->execute([
                $bookingNumber, $name, $phone, $email,
                $vBrand, $vModel, $vYear ?: null, $plate, $vType,
                $svcType, $prefDate, $prefTime, $complaint
            ]);
            $success = true;
        }
    }
}

// Minimum date = tomorrow
$minDate = date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Servis — <?= htmlspecialchars($bengkelName) ?></title>
  <meta name="description" content="Booking servis online di <?= htmlspecialchars($bengkelName) ?>. Pesan jadwal servis kendaraan Anda dengan mudah.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
  <style>
    :root {
      --primary: #FF6B2B;
      --primary-dark: #E55A1B;
      --primary-light: #FF8C54;
      --primary-bg: #FFF4EF;
      --secondary: #1A1F2E;
      --success: #10B981;
      --text-primary: #1A1F2E;
      --text-secondary: #6B7280;
      --text-muted: #9CA3AF;
      --border: #E5E7EB;
      --bg: #F0F2F5;
      --card-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.05);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text-primary);
      line-height: 1.6;
      min-height: 100vh;
    }

    /* HERO */
    .hero {
      background: linear-gradient(135deg, var(--secondary) 0%, #2A3040 60%, var(--secondary) 100%);
      position: relative;
      overflow: hidden;
      padding: 80px 24px 100px;
      text-align: center;
      color: #fff;
    }
    .hero::before {
      content: '';
      position: absolute;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(255,107,43,.15) 0%, transparent 70%);
      top: -150px; right: -100px;
      border-radius: 50%;
    }
    .hero::after {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(255,107,43,.1) 0%, transparent 70%);
      bottom: -200px; left: -100px;
      border-radius: 50%;
    }
    .hero-brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      margin-bottom: 24px;
    }
    .hero-brand-icon {
      width: 48px; height: 48px;
      background: var(--primary);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #fff;
    }
    .hero-brand-text strong { font-size: 22px; color: #fff; display: block; }
    .hero-brand-text span { font-size: 12px; color: rgba(255,255,255,.5); }
    .hero h1 {
      font-size: clamp(28px, 5vw, 42px);
      font-weight: 800;
      margin-bottom: 12px;
      letter-spacing: -.5px;
      position: relative;
    }
    .hero h1 span { color: var(--primary); }
    .hero p {
      font-size: 16px;
      color: rgba(255,255,255,.65);
      max-width: 560px;
      margin: 0 auto;
      position: relative;
    }
    .hero-features {
      display: flex;
      justify-content: center;
      gap: 32px;
      margin-top: 36px;
      flex-wrap: wrap;
      position: relative;
    }
    .hero-feat {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255,255,255,.75);
      font-size: 14px;
      font-weight: 500;
    }
    .hero-feat i {
      width: 36px; height: 36px;
      background: rgba(255,107,43,.15);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--primary);
      font-size: 15px;
    }

    /* CONTAINER */
    .container {
      max-width: 1100px;
      margin: -60px auto 0;
      padding: 0 24px 60px;
      position: relative;
      z-index: 10;
    }

    /* FORM CARD */
    .booking-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 40px rgba(0,0,0,.1);
      overflow: hidden;
    }
    .booking-header {
      padding: 28px 32px 20px;
      border-bottom: 1px solid #F3F4F6;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .booking-header-icon {
      width: 48px; height: 48px;
      background: var(--primary-bg);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      color: var(--primary);
      font-size: 22px;
    }
    .booking-header h2 { font-size: 20px; font-weight: 700; }
    .booking-header p { font-size: 13px; color: var(--text-muted); }

    .booking-body { padding: 28px 32px; position: relative; }

    /* WIZARD PROGRESS */
    .wizard-progress {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
      position: relative;
    }
    .wizard-progress::before {
      content: '';
      position: absolute;
      top: 18px; left: 10%; right: 10%;
      height: 2px;
      background: var(--border);
      z-index: 0;
    }
    .wizard-progress-bar {
      position: absolute;
      top: 18px; left: 10%;
      height: 2px;
      background: var(--primary);
      z-index: 1;
      transition: width .4s ease;
      width: 0%;
    }
    .wizard-step-indicator {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      width: 33.33%;
    }
    .wizard-step-num {
      width: 38px; height: 38px;
      background: #fff;
      border: 2px solid var(--border);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; color: var(--text-muted);
      transition: all .3s;
    }
    .wizard-step-label { font-size: 13px; font-weight: 600; color: var(--text-muted); transition: all .3s; }
    .wizard-step-indicator.active .wizard-step-num { border-color: var(--primary); background: var(--primary); color: #fff; }
    .wizard-step-indicator.active .wizard-step-label { color: var(--primary); }
    .wizard-step-indicator.completed .wizard-step-num { border-color: var(--primary); background: var(--primary-bg); color: var(--primary); }
    
    .wizard-step-content { display: none; animation: fadeIn .4s ease; }
    .wizard-step-content.active { display: block; }
    @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    
    .wizard-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid var(--border-light);
    }
    .btn-prev, .btn-next {
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      display: inline-flex; align-items: center; gap: 8px;
      transition: all .2s;
    }
    .btn-prev { background: var(--bg); color: var(--text-primary); border: 1px solid var(--border); }
    .btn-prev:hover { background: #E5E7EB; }
    .btn-next { background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 12px rgba(255,107,43,.3); }
    .btn-next:hover { background: var(--primary-dark); transform: translateY(-1px); }

    /* FORM ELEMENTS */
    .form-section-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin: 24px 0 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .form-section-title:first-of-type { margin-top: 0; }
    .form-section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .form-group { margin-bottom: 14px; }
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-primary);
      margin-bottom: 5px;
    }
    .form-label .req { color: #EF4444; margin-left: 2px; }
    .form-control {
      width: 100%;
      padding: 10px 14px;
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      color: var(--text-primary);
      transition: all .2s;
      outline: none;
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,107,43,.1); }
    .form-control::placeholder { color: var(--text-muted); }
    select.form-control { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%239CA3AF'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 20px; padding-right: 34px; }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* SERVICE PILLS */
    .svc-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
    .svc-pill {
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: 20px;
      padding: 7px 14px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all .2s;
      user-select: none;
    }
    .svc-pill:hover { border-color: var(--primary); background: var(--primary-bg); }
    .svc-pill.active { border-color: var(--primary); background: var(--primary); color: #fff; }
    .svc-pill i { margin-right: 5px; }

    /* VEHICLE TYPE TOGGLE */
    .vtype-toggle { display: flex; gap: 12px; margin-bottom: 16px; }
    .vtype-opt {
      flex: 1;
      padding: 16px;
      border: 2px solid var(--border);
      border-radius: 12px;
      text-align: center;
      cursor: pointer;
      transition: all .2s;
      background: #fff;
    }
    .vtype-opt:hover { border-color: var(--primary-light); }
    .vtype-opt.active { border-color: var(--primary); background: var(--primary-bg); }
    .vtype-opt i { font-size: 28px; display: block; margin-bottom: 6px; }
    .vtype-opt span { font-size: 14px; font-weight: 600; }
    .vtype-opt.active i, .vtype-opt.active span { color: var(--primary); }
    .vtype-opt input { display: none; }

    /* SUBMIT */
    .btn-submit {
      width: 100%;
      padding: 14px;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 8px;
    }
    .btn-submit:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,107,43,.3); }

    /* SUCCESS STATE */
    .success-card {
      text-align: center;
      padding: 60px 40px;
    }
    .success-icon {
      width: 80px; height: 80px;
      background: #ECFDF5;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 36px; color: var(--success);
      margin: 0 auto 20px;
      animation: popIn .5s ease;
    }
    @keyframes popIn { 0%{transform:scale(0)} 50%{transform:scale(1.15)} 100%{transform:scale(1)} }
    .success-card h2 { font-size: 22px; margin-bottom: 8px; }
    .success-card p { color: var(--text-secondary); margin-bottom: 4px; }
    .booking-num {
      display: inline-block;
      background: var(--primary-bg);
      color: var(--primary);
      font-size: 20px;
      font-weight: 800;
      padding: 10px 24px;
      border-radius: 10px;
      letter-spacing: 1px;
      margin: 16px 0;
    }
    .success-info {
      background: var(--bg);
      border-radius: 12px;
      padding: 20px;
      margin-top: 20px;
      text-align: left;
      font-size: 14px;
    }
    .success-info li { margin-bottom: 8px; list-style: none; }
    .success-info li i { color: var(--primary); width: 20px; margin-right: 6px; }

    /* ERROR */
    .alert-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      color: #991B1B;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    /* INFO BAR */
    .info-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-top: 32px;
    }
    .info-item {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: var(--card-shadow);
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .info-item-icon {
      width: 40px; height: 40px;
      background: var(--primary-bg);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--primary);
      font-size: 18px;
      flex-shrink: 0;
    }
    .info-item h4 { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
    .info-item p { font-size: 13px; color: var(--text-secondary); }

    /* FOOTER */
    .public-footer {
      text-align: center;
      padding: 40px 0;
      font-size: 13px;
      color: var(--text-muted);
    }
    .public-footer a { color: var(--primary); text-decoration: none; }

    /* CHECK STATUS LINK */
    .check-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 16px;
      padding: 10px 20px;
      background: var(--primary);
      color: #fff;
      text-decoration: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      transition: all .2s;
    }
    .check-link:hover { background: var(--primary-dark); }

    @media (max-width: 640px) {
      .hero { padding: 50px 20px 80px; }
      .hero h1 { font-size: 24px; }
      .hero-features { gap: 16px; }
      .container { margin-top: -40px; padding: 0 16px 40px; }
      .booking-body { padding: 20px; }
      .form-row, .form-row-3 { grid-template-columns: 1fr; }
      .info-bar { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- HERO -->
  <div class="hero">
    <div class="hero-brand">
      <div class="hero-brand-icon"><i class="fas fa-wrench"></i></div>
      <div class="hero-brand-text">
        <strong><?= htmlspecialchars($bengkelName) ?></strong>
        <span><?= htmlspecialchars($bengkelTagline) ?></span>
      </div>
    </div>
    <h1>Booking <span>Servis Online</span></h1>
    <p>Pesan jadwal servis kendaraan Anda dengan mudah. Isi form di bawah dan kami akan mengkonfirmasi booking Anda melalui WhatsApp.</p>
    <div class="hero-features">
      <div class="hero-feat"><i class="fas fa-clock"></i> Proses Cepat</div>
      <div class="hero-feat"><i class="fas fa-shield-alt"></i> Garansi Servis</div>
      <div class="hero-feat"><i class="fab fa-whatsapp"></i> Konfirmasi WA</div>
      <div class="hero-feat"><i class="fas fa-tools"></i> Mekanik Ahli</div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="container">

    <?php if ($success): ?>
    <!-- ═══ SUCCESS ═══ -->
    <div class="booking-card">
      <div class="success-card">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h2>Booking Berhasil! 🎉</h2>
        <p>Terima kasih telah melakukan booking di <strong><?= htmlspecialchars($bengkelName) ?></strong></p>
        <div class="booking-num"><?= $bookingNumber ?></div>
        <p>Simpan nomor booking di atas untuk pengecekan status.</p>
        <div class="success-info">
          <ul>
            <li><i class="fas fa-clock"></i> Kami akan mengkonfirmasi booking Anda dalam waktu 1x24 jam</li>
            <li><i class="fab fa-whatsapp"></i> Konfirmasi dikirim melalui WhatsApp ke nomor Anda</li>
            <li><i class="fas fa-calendar-check"></i> Silakan datang sesuai jadwal yang sudah dikonfirmasi</li>
            <li><i class="fas fa-phone-alt"></i> Hubungi <?= htmlspecialchars($bengkelPhone) ?> jika ada pertanyaan</li>
          </ul>
        </div>
        <a href="<?= BASE_URL ?>/tracking.php" class="check-link"><i class="fas fa-search"></i> Cek Status Booking</a>
        <br>
        <a href="<?= BASE_URL ?>/booking.php" style="display:inline-block;margin-top:12px;color:var(--primary);font-weight:600;text-decoration:none"><i class="fas fa-arrow-left"></i> Booking Lagi</a>
      </div>
    </div>

    <?php else: ?>
    <!-- ═══ BOOKING FORM ═══ -->
    <div class="booking-card">
      <div class="booking-header">
        <div class="booking-header-icon"><i class="fas fa-calendar-plus"></i></div>
        <div>
          <h2>Form Booking Servis</h2>
          <p>Isi data berikut untuk memesan jadwal servis kendaraan Anda</p>
        </div>
      </div>
      <div class="booking-body">
        <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="booking-form">

          <!-- WIZARD PROGRESS -->
          <div class="wizard-progress">
            <div class="wizard-progress-bar" id="wizard-bar"></div>
            <div class="wizard-step-indicator active" id="indicator-1">
              <div class="wizard-step-num">1</div>
              <div class="wizard-step-label">Pelanggan</div>
            </div>
            <div class="wizard-step-indicator" id="indicator-2">
              <div class="wizard-step-num">2</div>
              <div class="wizard-step-label">Kendaraan</div>
            </div>
            <div class="wizard-step-indicator" id="indicator-3">
              <div class="wizard-step-num">3</div>
              <div class="wizard-step-label">Layanan</div>
            </div>
          </div>

          <!-- ─── STEP 1: DATA PELANGGAN ─── -->
          <div class="wizard-step-content active" id="step-1">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                <input type="text" name="customer_name" class="form-control" placeholder="Nama lengkap Anda" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">No. WhatsApp / Telepon <span class="req">*</span></label>
                <input type="tel" name="customer_phone" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email <span style="color:var(--text-muted);font-weight:400">(opsional)</span></label>
              <input type="email" name="customer_email" class="form-control" placeholder="email@contoh.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
            </div>
            <div class="wizard-actions" style="justify-content: flex-end;">
              <button type="button" class="btn-next" onclick="nextStep(2)">Selanjutnya <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- ─── STEP 2: DATA KENDARAAN ─── -->
          <div class="wizard-step-content" id="step-2">
            <div class="vtype-toggle">
              <label class="vtype-opt <?= ($_POST['vehicle_type'] ?? 'motor') === 'mobil' ? 'active' : '' ?>" id="vt-mobil" onclick="selectVType('mobil')">
                <input type="radio" name="vehicle_type" value="mobil" <?= ($_POST['vehicle_type'] ?? '') === 'mobil' ? 'checked' : '' ?>>
                <i class="fas fa-car"></i>
                <span>Mobil</span>
              </label>
              <label class="vtype-opt <?= ($_POST['vehicle_type'] ?? 'motor') === 'motor' ? 'active' : '' ?>" id="vt-motor" onclick="selectVType('motor')">
                <input type="radio" name="vehicle_type" value="motor" <?= ($_POST['vehicle_type'] ?? 'motor') === 'motor' ? 'checked' : '' ?>>
                <i class="fas fa-motorcycle"></i>
                <span>Motor</span>
              </label>
            </div>
            <div class="form-row-3">
              <div class="form-group">
                <label class="form-label">Merek <span class="req">*</span></label>
                <input type="text" name="vehicle_brand" class="form-control" placeholder="Toyota, Honda..." value="<?= htmlspecialchars($_POST['vehicle_brand'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Model <span class="req">*</span></label>
                <input type="text" name="vehicle_model" class="form-control" placeholder="Avanza, Vario..." value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Tahun</label>
                <input type="number" name="vehicle_year" class="form-control" placeholder="2023" min="1990" max="<?= date('Y') + 1 ?>" value="<?= htmlspecialchars($_POST['vehicle_year'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Plat Nomor</label>
              <input type="text" name="plate_number" class="form-control" placeholder="B 1234 XYZ" value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>">
            </div>
            <div class="wizard-actions">
              <button type="button" class="btn-prev" onclick="prevStep(1)"><i class="fas fa-arrow-left"></i> Kembali</button>
              <button type="button" class="btn-next" onclick="nextStep(3)">Selanjutnya <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- ─── STEP 3: LAYANAN & JADWAL ─── -->
          <div class="wizard-step-content" id="step-3">
            <div class="form-group">
              <label class="form-label">Jenis Layanan <span class="req">*</span></label>
              <div class="svc-pills" id="svc-pills">
                <?php foreach ($categories as $cat): ?>
                <div class="svc-pill" onclick="toggleSvc(this, '<?= htmlspecialchars($cat['name']) ?>')">
                  <i class="fas <?= $cat['icon'] ?>"></i> <?= htmlspecialchars($cat['name']) ?>
                </div>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="service_type" id="service_type" value="<?= htmlspecialchars($_POST['service_type'] ?? '') ?>">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Tanggal yang Diinginkan <span class="req">*</span></label>
                <input type="date" name="preferred_date" class="form-control" min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= htmlspecialchars($_POST['preferred_date'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Jam yang Diinginkan <span class="req">*</span></label>
                <select name="preferred_time" class="form-control">
                  <option value="">— Pilih Jam —</option>
                  <?php
                  $times = ['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00'];
                  foreach ($times as $t):
                    $sel = ($_POST['preferred_time'] ?? '') === $t.':00' ? 'selected' : '';
                  ?>
                  <option value="<?= $t ?>:00" <?= $sel ?>><?= $t ?> WIB</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Keluhan / Catatan <span style="color:var(--text-muted);font-weight:400">(opsional)</span></label>
              <textarea name="complaint" class="form-control" placeholder="Jelaskan keluhan atau pekerjaan yang diinginkan..."><?= htmlspecialchars($_POST['complaint'] ?? '') ?></textarea>
            </div>
            <div class="wizard-actions">
              <button type="button" class="btn-prev" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Kembali</button>
              <button type="submit" class="btn-next" id="btn-book"><i class="fas fa-calendar-check"></i> Selesaikan Booking</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- INFO BAR -->
    <div class="info-bar">
      <div class="info-item">
        <div class="info-item-icon"><i class="fas fa-map-marker-alt"></i></div>
        <div><h4>Lokasi</h4><p><?= htmlspecialchars($bengkelAddress ?: 'Jl. Pelita No. 09, Kel. Ilir, Gunungsitoli') ?></p></div>
      </div>
      <div class="info-item">
        <div class="info-item-icon"><i class="fas fa-phone-alt"></i></div>
        <div><h4>Telepon</h4><p><?= htmlspecialchars($bengkelPhone ?: '08123456789') ?></p></div>
      </div>
      <div class="info-item">
        <div class="info-item-icon"><i class="fas fa-clock"></i></div>
        <div><h4>Jam Operasional</h4><p><?= htmlspecialchars($bengkelHours ?: 'Senin - Sabtu: 08.00 - 17.00 WIB') ?></p></div>
      </div>
    </div>
  </div>

  <div class="public-footer">
    <p>© <?= date('Y') ?> <a href="<?= BASE_URL ?>/booking.php"><?= htmlspecialchars($bengkelName) ?></a> — SMKS Pembda Nias — Teaching Factory</p>
    <p style="margin-top:6px"><a href="<?= BASE_URL ?>/tracking.php"><i class="fas fa-search"></i> Cek Status Booking / WO</a></p>
  </div>

  <script>
    function selectVType(type) {
      document.querySelectorAll('.vtype-opt').forEach(el => el.classList.remove('active'));
      document.getElementById('vt-' + type).classList.add('active');
      document.querySelector(`input[name="vehicle_type"][value="${type}"]`).checked = true;
    }

    const selectedServices = new Set(
      (document.getElementById('service_type').value || '').split(', ').filter(Boolean)
    );

    // Mark active pills on reload
    document.querySelectorAll('.svc-pill').forEach(pill => {
      const name = pill.textContent.trim();
      if ([...selectedServices].some(s => name.includes(s))) pill.classList.add('active');
    });

    function toggleSvc(el, name) {
      el.classList.toggle('active');
      if (selectedServices.has(name)) selectedServices.delete(name);
      else selectedServices.add(name);
      document.getElementById('service_type').value = [...selectedServices].join(', ');
    }

    // WIZARD LOGIC
    let currentStep = 1;
    function updateWizardUI() {
      // Update bar
      const bar = document.getElementById('wizard-bar');
      if(currentStep === 1) bar.style.width = '0%';
      if(currentStep === 2) bar.style.width = '50%';
      if(currentStep === 3) bar.style.width = '100%';

      // Update indicators
      for(let i=1; i<=3; i++) {
        const ind = document.getElementById('indicator-'+i);
        ind.classList.remove('active', 'completed');
        if(i < currentStep) ind.classList.add('completed');
        if(i === currentStep) ind.classList.add('active');
        
        // Show/hide content
        const content = document.getElementById('step-'+i);
        if(i === currentStep) content.classList.add('active');
        else content.classList.remove('active');
      }
    }

    function nextStep(step) {
      // Validate current step
      const currentContent = document.getElementById('step-' + currentStep);
      const inputs = currentContent.querySelectorAll('input[required], select[required]');
      let valid = true;
      inputs.forEach(inp => {
        if(!inp.value.trim()) {
          valid = false;
          inp.style.borderColor = '#EF4444';
          setTimeout(() => inp.style.borderColor = '', 2000);
        }
      });
      if(!valid) return;

      currentStep = step;
      updateWizardUI();
    }

    function prevStep(step) {
      currentStep = step;
      updateWizardUI();
    }

    document.getElementById('booking-form')?.addEventListener('submit', function(e) {
      // Final validation for step 3
      const serviceType = document.getElementById('service_type').value;
      if(!serviceType) {
        e.preventDefault();
        alert('Mohon pilih minimal 1 jenis layanan.');
        return;
      }

      const btn = document.getElementById('btn-book');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Memproses...';
    });
  </script>
</body>
</html>
