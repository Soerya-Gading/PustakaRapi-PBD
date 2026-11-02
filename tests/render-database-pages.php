<?php
declare(strict_types=1);
// CLI-only render harness: uses the real page/auth/template code, no HTTP bypass route.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/db.php';
require dirname(__DIR__).'/includes/csrf.php';
require dirname(__DIR__).'/includes/helpers.php';
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/adapter.php';
$liveReadOnly = ($argv[1] ?? '') === '--live-read-only';
$db=$liveReadOnly ? 'db_pustakarapi' : getenv('TOKOKITA_TEST_DB');
if (!$liveReadOnly && (!$db || !preg_match('/^db_pustakarapi_uji_\d{8}_\d{6}$/',$db))) throw new RuntimeException('Disposable schema required');
$pdo=get_db_connection();$pdo->exec('USE `'.$db.'`');
if ($liveReadOnly) $pdo->exec('START TRANSACTION READ ONLY');
$_SESSION['auth_user']=['iduser'=>1,'username'=>'Pengguna Uji','idrole'=>1,'nama_role'=>'Administrator'];
$pages=['dashboard','buku','kartu-stok','laporan-penjualan','penjualan','penerimaan','pengadaan','retur'];
$outDir=getenv('TOKOKITA_RENDER_DIR')?:getenv('LOCALAPPDATA').'/hermes/cache/scratch/tokokita-db-render';
if (!is_dir($outDir)) mkdir($outDir,0777,true);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never {throw new ErrorException($message,0,$severity,$file,$line);});
foreach($pages as $name) {
    $_GET=['page'=>$name];
    ob_start();
    require dirname(__DIR__).'/pages/'.$name.'.php';
    $html=ob_get_clean();
    if (!str_contains($html,'</html>')) throw new RuntimeException('Incomplete page: '.$name);
    if ($liveReadOnly && $name==='dashboard' && str_contains($html,'Stok dalam Kondisi Aman') && (int)$pdo->query('SELECT COUNT(*) FROM v_ringkasan_stok WHERE tercatat=0')->fetchColumn()>0) {
        throw new RuntimeException('Dashboard mengklaim aman untuk stok belum tercatat.');
    }
    file_put_contents($outDir.'/'.$name.'.html',$html);
    echo 'PASS rendered '.$name.' bytes='.strlen($html).PHP_EOL;
}
