<?php
// ==========================================================================
// SOAL PROJECT UTS: SISTEM PENCAIRAN DANA E-BEASISWA KAMPUS (VULNERABLE)
// Temukan dan perbaiki 7 Celah Keamanan + bangun Active Defense IPS!
// ==========================================================================
define('ENGINEER_NIM', '242011022');
define('ENGINEER_NAME', 'Cristo Denata');

session_set_cookie_params(['httponly' => true, 'secure' => false, 'samesite' => 'Strict']);
session_regenerate_id(true);
header("Content-Security-Policy: default-src 'self'; script-src 'self'");
header("X-Secured-By-NIM: " . ENGINEER_NIM);

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCsrfToken();

function catat_insiden($kode_event, $pesan) {
    $waktu = date('Y-m-d H:i:s');
    $uid   = $_SESSION['user_id'] ?? 'Anon';
    $baris = "[$waktu] [NIM-ENG:" . ENGINEER_NIM . "] [UID:$uid] [$kode_event] $pesan" . PHP_EOL;
    file_put_contents('/tmp/UTS_soc.log', $baris, FILE_APPEND);
}

$data_beasiswa = [
    1 => ['nama' => 'Dr. Aris (Ketua Komite)', 'role' => 'KomiteBeasiswa', 'rekening' => 'BRI-0011223344', 'status' => 'Otorisator Pusat', 'dana' => 'Rp 500.000.000 (Dana Abadi)'],
    2 => ['nama' => 'Budi Santoso', 'role' => 'Mahasiswa', 'rekening' => 'BCA-8899001122', 'status' => 'Menunggu Verifikasi', 'dana' => 'Rp 6.500.000'],
    3 => ['nama' => 'Citra Lestari', 'role' => 'Mahasiswa', 'rekening' => 'MANDIRI-7766554433', 'status' => 'Disetujui', 'dana' => 'Rp 6.500.000']
];

if (isset($_GET['switch_role']) && isset($data_beasiswa[(int)$_GET['switch_role']])) {
    $_SESSION['user_id'] = (int)$_GET['switch_role'];
    $_SESSION['role']    = $data_beasiswa[$_SESSION['user_id']]['role'];
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
    $_SESSION['role']    = 'Mahasiswa';
}

$notif_aksi = '';
$uid_aktif = $_SESSION['user_id'];
$role_aktif = $_SESSION['role'];
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['target_id'])) {
    $tid = (int)$_GET['target_id'];
    if ($role_aktif !== 'KomiteBeasiswa') {
        http_response_code(403);
        catat_insiden('RBAC_BYPASS', "UID $uid_aktif mencoba approve tanpa role KomiteBeasiswa");
        exit;
    }
    if (isset($data_beasiswa[$tid])) {
        $data_beasiswa[$tid]['status'] = 'DISETUJUI PENCAIRAN';
        $notif_aksi = 'Pencairan beasiswa untuk ID ' . $tid . ' berhasil disetujui!';
        catat_insiden('APPROVE_OK', "KomiteBeasiswa approve ID $tid");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rekening_baru'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? ''))
    if (!hash_equals($csrf_token, $post_token)) {
        http_response_code(403);
        catat_insiden('CSRF_MISMATCH', "UID $uid_aktif token CSRF tidak cocok");
        exit;
    }
    
    $rekening_baru = trim($_POST['rekening_baru']);
    if (!preg_match('/^[0-9]{8,16}$/', $rekening_baru)) {
        $notif_aksi = 'Format rekening tidak valid (8-16 digit angka)!';
    } else {
        $data_beasiswa[$uid_aktif]['rekening'] = $rekening_baru;
        $notif_aksi = 'Nomor rekening pencairan berhasil diperbarui!';
    }
}

$view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : $_SESSION['user_id'];
if ($view_id !== $uid_aktif && $role_aktif !== 'KomiteBeasiswa') {
    http_response_code(403);
    catat_insiden('IDOR_ATTEMPT', "UID $uid_aktif mencoba view profil ID $view_id");
}
$profil  = $data_beasiswa[$view_id] ?? null;
$filter_prodi = isset($_GET['filter_prodi']) ? htmlspecialchars($_GET['filter_prodi'], ENT_QUOTES, 'UTF-8') : 'Teknik Informatika';

require_once '/var/www/html/config/config.inc.php';
$db_h = $_DVWA['db_server'] ?? '127.0.0.1';
$db_u = $_DVWA['db_user'] ?? 'app';
$db_p = $_DVWA['db_password'] ?? 'pesas';
$db_n = $_DVWA['db_database'] ?? 'dvwa';
$hasil_mhs = '';
$allowed_cols = ['user_id', 'first_name', 'last_name', 'user'];
    $sort_col = in_array($_GET['sort_col'] ?? '', $allowed_cols) ? $_GET['sort_col'] : 'user_id';
    
    $pdo = new PDO("mysql:host=$db_h;dbname=$db_n;charset=utf8mb4", $db_u, $db_p, [
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, user FROM users WHERE user_id = ? ORDER BY $sort_col ASC");
    $stmt->execute([$uid]);
    
    if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasil_mhs = "Data: {$r['first_name']} {$r['last_name']} (Username: {$r['user']})";
    } else {
        $hasil_mhs = 'Mahasiswa tidak ditemukan.';
    }

$output_gateway = '';
if (isset($_POST['bank_host']) && $_POST['bank_host'] !== '') {
    $host = trim($_POST['bank_host']);
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $output_gateway = shell_exec('ping -c 1 ' . escapeshellarg($host));
    } else {
        $output_gateway = 'IP tidak valid!';
    }
}

if (!isset($_SESSION['violation_count'])) {
    $_SESSION['violation_count'] = 0;
}
if (isset($_GET['reset_ips'])) {
    $_SESSION['violation_count'] = 0;
}
if ($_SESSION['violation_count'] >= 3) {
    http_response_code(429);
    catat_insiden('IPS_LOCKOUT', "UID $uid_aktif AKUN DIBEKUKAN OLEH IPS SOC");
    die("AKUN DIBEKUKAN OLEH IPS SOC - Terlalu banyak pelanggaran keamanan!");
}

?>
<!DOCTYPE html><html><head><title>Portal E-Beasiswa Kampus (UTS Target)</title></head>
<body style='font-family:Segoe UI,Arial,sans-serif; margin:25px; background:#f4f6f9;'>
  <div style='background:#1a2639; color:#fff; padding:15px; border-radius:6px;'>
    <h2 style='margin:0;'>Sistem Pencairan E-Beasiswa Kampus (Target Project UTS)</h2>
    <small>Engineer NIM: <b><?php echo ENGINEER_NIM; ?></b> | Sesi Aktif: <b>ID <?php echo (int)$_SESSION['user_id']; ?> (<?php echo $_SESSION['role']; ?>)</b> |
    Simulasi Sesi: <a style='color:#ffdd57;' href='?switch_role=2&view_id=2'>[Mhs Budi (ID 2)]</a>
    <a style='color:#ffdd57;' href='?switch_role=1&view_id=1'>[Komite Beasiswa (ID 1)]</a>
    <a style='color:#ff9999;' href='?reset_ips=1'>[Reset IPS Lockout]</a> |
    <a style='color:#80ffdb; font-weight:bold;' href='/UTS_evaluator.php'>[Buka Evaluator UTS &rarr;]</a></small>
  </div>
  <?php if ($notif_aksi): ?><p style='background:#d4edda; padding:10px;'><?php echo $notif_aksi; ?></p><?php endif; ?>
  <div style='background:#fff; padding:15px; margin-top:15px; border:1px solid #ccc; border-radius:6px;'>
    <h3>1. Filter Program Studi: <?php echo $filter_prodi; ?></h3>
    <h3>2. Rincian Pencairan Beasiswa &amp; Nomor Rekening</h3>
    <?php if ($profil): ?>
      <p><b>Nama Penerima:</b> <?php echo $profil['nama']; ?> | <b>Status:</b> <?php echo $profil['status']; ?><br>
      <b>Rekening Pencairan:</b> <span style='color:#8b1e3f; font-weight:bold;'><?php echo $profil['rekening']; ?></span> |
      <b>Alokasi:</b> <?php echo $profil['dana']; ?></p>
    <?php endif; ?>
    <h3>3. Form Perubahan Nomor Rekening Bank</h3>
    <form method='POST'>
      <input type='hidden' name='csrf_token' value='<?php echo $csrf_token; ?>'>
      Nomor Rekening Baru (8-16 Digit Angka): <input type='text' name='rekening_baru' placeholder='Contoh: 8899001122'>
      <button type='submit'>Simpan Rekening</button>
    </form>
    <h3>4. Pencarian &amp; Pengurutan Direktori Penerima</h3>
    <form method='GET'>
      User ID: <input type='text' name='uid' placeholder='1-5'>
      Urutkan: <select name='sort_col'><option value='user_id'>ID</option><option value='first_name'>Nama Depan</option></select>
      <button type='submit'>Cari</button>
    </form>
    <p><?php echo $hasil_mhs; ?></p>
    <h3>5. Cek Koneksi Server Payment Gateway Bank</h3>
    <form method='POST'>
    <input type='hidden' name='csrf_token' value='<?php echo $csrf_token; ?>'>
      IP Gateway Bank: <input type='text' name='bank_host' placeholder='127.0.0.1'>
      <button type='submit'>Cek Gateway</button>
    </form>
    <pre style='background:#eee; padding:8px;'><?php echo htmlspecialchars($output_gateway ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
  </div>
</body></html>
