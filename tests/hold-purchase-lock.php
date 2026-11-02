<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/db.php';
$db=getenv('TOKOKITA_TEST_DB');
if (!$db || !preg_match('/^db_pustakarapi_uji_\d{8}_\d{6}$/',$db)) throw new RuntimeException('Disposable schema required');
$pdo=get_db_connection();$pdo->exec('USE `'.$db.'`');
$pdo->beginTransaction();
$stmt=$pdo->prepare('SELECT idpengadaan FROM pengadaan WHERE idpengadaan=? FOR UPDATE');
$stmt->execute([(int)$argv[1]]);$stmt->fetchColumn();$stmt->closeCursor();
echo "LOCKED\n";fflush(STDOUT);
fgets(STDIN);
$pdo->commit();
