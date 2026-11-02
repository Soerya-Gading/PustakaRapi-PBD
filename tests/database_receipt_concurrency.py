"""Force two receipt calls to queue on one PO, then release together.
A stale RR snapshot must not allow total received to exceed ordered quantity.
"""
from pathlib import Path
import os, sys, subprocess, time, json
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from database_coursework import state,run_sql,MYSQL
root=Path(__file__).resolve().parents[1]
db=state()['test_database'];assert db.startswith('db_pustakarapi_uji_')
book,user,vendor=map(int,run_sql(db,'SELECT MIN(idbarang),(SELECT MIN(iduser) FROM `user`),(SELECT MIN(idvendor) FROM vendor) FROM barang').splitlines()[1].split('\t'))
po=int(run_sql(db,f"INSERT INTO pengadaan(timestamp,user_iduser,status,vendor_idvendor,subtotal_nilai,ppn,total_nilai) VALUES(NOW(),{user},'P',{vendor},10000,1100,11100);SET @p=LAST_INSERT_ID();INSERT INTO detail_pengadaan(harga_satuan,jumlah,sub_total,idbarang,idpengadaan) VALUES(1000,10,10000,{book},@p);SELECT @p;").splitlines()[1])
env=dict(os.environ,TOKOKITA_TEST_DB=db)
holder=subprocess.Popen(['C:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe','-d','opcache.enable_cli=0','tests/hold-purchase-lock.php',str(po)],cwd=root,env=env,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
assert holder.stdout.readline().strip()=='LOCKED'
clients=[]
try:
    sql=f"CALL sp_terima_pengadaan({po},{user},JSON_ARRAY(JSON_OBJECT('idbarang',{book},'jumlah_terima',6)),@out);SELECT @out;"
    for _ in range(2):
        clients.append(subprocess.Popen([str(MYSQL),'--host=127.0.0.1','--user=root','--batch','--raw',db,'-e',sql],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True))
    deadline=time.monotonic()+10
    queued=0
    while time.monotonic()<deadline:
        queued=int(run_sql(db,"SELECT COUNT(DISTINCT REQUESTING_ENGINE_TRANSACTION_ID) FROM performance_schema.data_lock_waits").splitlines()[1])
        if queued>=2:break
        time.sleep(.05)
    assert queued>=2,'Two clients failed to queue deterministically'
finally:
    holder.communicate('release\n',timeout=10)
results=[]
for p in clients:
    out,err=p.communicate(timeout=15);results.append({'exit_code':p.returncode,'out':out,'error':err})
total=int(run_sql(db,f'SELECT COALESCE(SUM(d.jumlah_terima),0) FROM detail_penerimaan d JOIN penerimaan p ON p.idpenerimaan=d.idpenerimaan WHERE p.idpengadaan={po}').splitlines()[1])
passed=sum(r['exit_code']==0 for r in results)==1 and total==6
print(json.dumps({'test':'queued concurrent receipts','po':po,'ordered':10,'received':total,'results':results,'pass':passed},indent=2))
assert passed,'Concurrent receipts exceeded order or unexpected error'
