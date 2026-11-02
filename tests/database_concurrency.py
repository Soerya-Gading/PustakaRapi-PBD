"""Concurrent SP sales test; disposable database only, never live data."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from database_coursework import run_sql, state
import subprocess
import json

s = state()
db = s['test_database']
assert db.startswith('db_pustakarapi_uji_')
row = run_sql(db, 'SELECT b.idbarang,fn_hitung_stok(b.idbarang) stok,(SELECT MIN(iduser) FROM `user`),(SELECT MIN(idmargin_penjualan) FROM margin_penjualan WHERE status=1) FROM barang b WHERE fn_hitung_stok(b.idbarang)>1 ORDER BY idbarang LIMIT 1').splitlines()[1].split('\t')
book, stock, user, margin = map(int, row)
qty = stock - 1
before = int(run_sql(db, 'SELECT COUNT(*) FROM penjualan').splitlines()[1])
sql = f"CALL sp_simpan_penjualan({user},{margin},JSON_ARRAY(JSON_OBJECT('idbarang',{book},'jumlah',{qty})),@out);SELECT @out;"
def sell(_):
    try:
        return {'status':'success', 'output':run_sql(db, sql)}
    except RuntimeError as e:
        return {'status':'rejected', 'output':str(e)}
with ThreadPoolExecutor(max_workers=2) as pool:
    results = list(pool.map(sell, range(2)))
after = int(run_sql(db, 'SELECT COUNT(*) FROM penjualan').splitlines()[1])
remaining = int(run_sql(db, f'SELECT fn_hitung_stok({book})').splitlines()[1])
assert sum(r['status']=='success' for r in results)==1, results
assert after==before+1 and remaining==1, (before, after, remaining)
print(json.dumps({'test':'concurrent sales same book','initial_stock':stock,'qty_each':qty,'sales_added':after-before,'remaining':remaining,'results':results,'pass':True}, indent=2))
