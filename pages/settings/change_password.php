<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flashSet('danger','Token tidak valid'); header('Location: '.BASE_URL.'/pages/settings/index.php'); exit; }

$user    = currentUser();
$oldPass = $_POST['old_password'] ?? '';
$newPass = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!password_verify($oldPass, $user['password'])) { flashSet('danger','Password lama salah.'); }
elseif (strlen($newPass) < 8)                      { flashSet('danger','Password baru minimal 8 karakter.'); }
elseif ($newPass !== $confirm)                      { flashSet('danger','Konfirmasi password tidak cocok.'); }
else {
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);
    $_SESSION['user']['password'] = $hash;
    flashSet('success','Password berhasil diubah.');
}

header('Location: '.BASE_URL.'/pages/settings/index.php');
exit;
