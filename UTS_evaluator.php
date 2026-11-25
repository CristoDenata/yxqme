<?php
$file = '/var/www/html/ebea_kampus.php';
$raw  = file_exists($file) ? file_get_contents($file) : '';
$code = preg_replace('!/\*.*?\*/!s', '', $raw);
$code = preg_replace('!^\s*//.*$!m', '', $code);

$nim_defined = '';
if (preg_match("/define\s*\(\s*['\"]ENGINEER_NIM['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $code, $m)) {
    $nim_defined = trim($m[1]);
}
$nim_valid = ($nim_defined !== '' && $nim_defined !== 'ISI_NIM_ANDA' && strlen($nim_defined) >= 5);

$c1 = (stripos($code, 'session_set_cookie_params') !== false) &&
      (stripos($code, 'httponly') !== false) &&
      (stripos($code, 'session_regenerate_id') !== false) &&
      (stripos($code, 'Content-Security-Policy') !== false) &&
      (stripos($code, 'X-Secured-By-NIM') !== false);

$c2 = (substr_count($code, 'http_response_code(403)') >= 2) &&
      (substr_count($code, 'KomiteBeasiswa') >= 3) &&
      (stripos($code, 'catat_insiden(') !== false);

$c3 = (stripos($code, 'new PDO(') !== false) &&
      (stripos($code, 'ATTR_EMULATE_PREPARES') !== false) &&
      (stripos($code, 'in_array(') !== false) &&
      (stripos($code, 'mysqli_query(') === false);

$c4 = (preg_match('/htmlspecialchars\s*\(\s*\$_GET\[[\x27"]filter_prodi[\x27"]\]\s*,\s*ENT_QUOTES/i', $code) === 1);

$c5 = (stripos($code, 'csrf_token') !== false) &&
      (stripos($code, 'hash_equals(') !== false) &&
      (stripos($code, 'preg_match(') !== false);

$c6 = (stripos($code, 'FILTER_VALIDATE_IP') !== false) &&
      (stripos($code, 'escapeshellarg(') !== false);

$c7 = (stripos($code, 'violation_count') !== false) &&
      (stripos($code, 'http_response_code(429)') !== false) &&
      (stripos($code, 'AKUN DIBEKUKAN OLEH IPS SOC') !== false);

$items = [
    1 => ['nama' => 'Tantangan 1: Session Hardening, Regenerasi ID & HTTP Security Headers', 'ok' => $c1, 'max' => 10],
    2 => ['nama' => 'Tantangan 2: Dual-Layer RBAC (Anti-IDOR Profil & Otorisasi Approve Beasiswa)', 'ok' => $c2, 'max' => 15],
    3 => ['nama' => 'Tantangan 3: Native PDO Prepared Statements & Whitelist Array ORDER BY', 'ok' => $c3, 'max' => 20],
    4 => ['nama' => 'Tantangan 4: Contextual Output Encoding Anti-XSS (ENT_QUOTES)', 'ok' => $c4, 'max' => 10],
    5 => ['nama' => 'Tantangan 5: Proteksi Anti-CSRF (hash_equals) & Regex Rekening Bank', 'ok' => $c5, 'max' => 15],
    6 => ['nama' => 'Tantangan 6: Whitelist IP Gateway (FILTER_VALIDATE_IP) & escapeshellarg()', 'ok' => $c6, 'max' => 15],
    7 => ['nama' => 'Tantangan 7: Active Defense IPS (Auto-Lockout HTTP 429 >= 3 Pelanggaran)', 'ok' => $c7, 'max' => 15],
];
$skor = 0;
foreach ($items as $it) { if ($it['ok']) $skor += $it['max']; }
if (!$nim_valid) { $skor = 0; }
$receipt = ($skor === 100 && $nim_valid)
    ? 'UTS-CLEARED-2026-' . strtoupper($nim_defined) . '-' . strtoupper(substr(hash('sha256', $nim_defined . 'EBEASISWA-UTS-100'), 0, 10))
    : '';
$logs = file_exists('/tmp/UTS_soc.log') ? htmlspecialchars(file_get_contents('/tmp/UTS_soc.log'), ENT_QUOTES, 'UTF-8') : 'Belum ada log insiden.';
?>
<!DOCTYPE html><html><head><title>Evaluator Project UTS</title></head>
<body style='font-family:Segoe UI,Arial,sans-serif; margin:25px; background:#edf2f8;'>
  <div style='background:#1a2639; color:#fff; padding:18px; border-radius:8px;'>
    <h2 style='margin:0;'>Mesin Evaluator Otomatis Project UTS — Operation Secure-EBeasiswa</h2>
    <p style='margin:5px 0 0 0;'>Engineer NIM Terdeteksi: <b><?php echo htmlspecialchars($nim_defined ?: 'BELUM DIISI', ENT_QUOTES, 'UTF-8'); ?></b> |
    <a style='color:#ffdd57;' href='/ebea_kampus.php'>[&larr; Buka Aplikasi ebea_kampus.php]</a>
    <a style='color:#80ffdb; margin-left:10px;' href='/UTS_evaluator.php'>[Jalankan EvalUTSi Ulang &#x21bb;]</a></p>
  </div>
  <?php if (!$nim_valid): ?>
    <p style='background:#f8d7da; color:#721c24; padding:12px; border-radius:6px;'><b>PERINGATAN ANTI-PLAGIASI:</b> Anda wajib mengisi konstanta <code>define('ENGINEER_NIM', 'NIM_ASLI_ANDA');</code> pada baris atas <code>ebea_kampus.php</code> agar skor dapat dihitung!</p>
  <?php endif; ?>
  <div style='background:#fff; padding:18px; margin-top:15px; border-radius:8px; border:1px solid #ccc;'>
    <h3>Total Skor Project UTS Anda: <span style='color:<?php echo ($skor===100)?"#1b4332":"#8b1e3f";?>;'><?php echo $skor; ?> / 100 Poin</span></h3>
    <table border='1' cellpadding='8' cellspacing='0' width='100%' style='border-collapse:collapse; border-color:#ccc;'>
      <tr style='background:#1a2639; color:#fff;'><th>Status</th><th>Komponen Tantangan Project UTS</th><th>Bobot Poin</th></tr>
      <?php foreach ($items as $it): ?>
      <tr>
        <td align='center' style='color:#fff; font-weight:bold; background:<?php echo $it["ok"]?"#2d6a4f":"#c1121f";?>;'><?php echo $it['ok']?'PASS':'FAIL';?></td>
        <td><b><?php echo $it['nama']; ?></b></td>
        <td align='center'><b><?php echo $it['ok']?$it['max']:0; ?> / <?php echo $it['max']; ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php if ($receipt): ?>
  <div style='background:#d8f3dc; border:2px solid #1b4332; padding:18px; margin-top:15px; border-radius:8px;'>
    <h3 style='margin-top:0; color:#1b4332;'>SELAMAT! PROJECT UTS ANDA LULUS SEMPURNA (100/100 POIN)</h3>
    <p style='font-size:16px; background:#fff; padding:10px; border:1px dashed #1b4332;'><b>KODE BUKTI PENGUMPULAN UTS:</b> <code style='font-size:18px; color:#1a2639;'><?php echo $receipt; ?></code></p>
  </div>
  <?php endif; ?>
  <div style='background:#1e1e1e; color:#80ffdb; padding:15px; margin-top:15px; border-radius:8px;'>
    <h3 style='margin-top:0; color:#fff;'>Log Telemetri Insiden UTS (<code>/tmp/UTS_soc.log</code>)</h3>
    <pre style='margin:0;'><?php echo $logs; ?></pre>
  </div>
</body></html>