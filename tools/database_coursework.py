"""Backup, create disposable test DB, apply SQL files, verify live data unchanged.
Run via Python stdlib on Windows. Never use this tool to reset master data.
"""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
MYSQL = Path('C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe')
DUMP = MYSQL.with_name('mysqldump.exe')
LIVE = 'db_pustakarapi'
SCRATCH = Path(os.environ['LOCALAPPDATA']) / 'hermes/cache/scratch'
STATE = SCRATCH / 'tokokita-database-work.json'
FILES = ['01_functions.sql', '02_views.sql', '03_triggers.sql', '04_procedures.sql']


def run_sql(db, sql):
    p = subprocess.run([str(MYSQL), '--host=127.0.0.1', '--user=root', '--batch', '--raw', '--default-character-set=utf8mb4', db], input=sql.encode('utf8'), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if p.returncode:
        raise RuntimeError(p.stderr.decode('utf8', errors='replace'))
    return p.stdout.decode('utf8', errors='replace')


def snapshot(db):
    tables = run_sql(db, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME").splitlines()[1:]
    return {t: run_sql(db, f'SELECT COUNT(*) FROM `{t}`; CHECKSUM TABLE `{t}`;') for t in tables}


def state():
    return json.loads(STATE.read_text(encoding='utf8'))


def save(data):
    SCRATCH.mkdir(parents=True, exist_ok=True)
    STATE.write_text(json.dumps(data, indent=2), encoding='utf8')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['prepare', 'apply-test', 'apply-live', 'verify', 'cleanup'])
    parser.add_argument('--file', choices=FILES)
    args = parser.parse_args()
    if args.action == 'prepare':
        stamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
        test = 'db_pustakarapi_uji_' + stamp
        before = snapshot(LIVE)
        run_sql(LIVE, f'CREATE DATABASE `{test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;')
        tables = list(before)
        masters = ['satuan','barang','vendor','role','user','margin_penjualan']
        # Disposable schema gets real table definitions + master rows only.
        # No live transaction rows, routines, triggers or views are imported.
        common = [str(DUMP), '--host=127.0.0.1', '--user=root', '--single-transaction', '--skip-comments', '--skip-triggers', '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4']
        for flags, names in [(['--no-data'],tables), (['--no-create-info'],masters)]:
            p = subprocess.run(common + flags + [LIVE] + names, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            if p.returncode:
                raise RuntimeError(p.stderr.decode(errors='replace'))
            run_sql(test, p.stdout.decode('utf8'))
        info = {'test_database': test, 'live_database': LIVE, 'before': before, 'created_at': stamp}
        save(info)
        print(json.dumps({'test_database': test, 'base_tables': len(before), 'snapshot_saved': str(STATE)}, indent=2))
    elif args.action.startswith('apply-'):
        s = state()
        db = s['test_database'] if args.action == 'apply-test' else LIVE
        for name in [args.file] if args.file else FILES:
            sql = (ROOT / 'sql' / name).read_text(encoding='utf8')
            run_sql(db, sql)
            print(f'APPLIED {db}: {name}')
        print(run_sql(db, "SELECT TABLE_TYPE,COUNT(*) jumlah FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_TYPE; SELECT ROUTINE_TYPE,COUNT(*) jumlah FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() GROUP BY ROUTINE_TYPE; SELECT COUNT(*) triggers_total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE();"))
    elif args.action == 'verify':
        s = state()
        after = snapshot(LIVE)
        assert after == s['before'], 'Live base table count/checksum changed!'
        print('PASS: seluruh jumlah baris dan checksum 15 tabel utama tetap sama.')
        print(run_sql(LIVE, "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME; SELECT ROUTINE_NAME,ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_TYPE,ROUTINE_NAME; SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE(); SELECT COUNT(*) foreign_keys FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE();"))
    else:
        s = state()
        test = s['test_database']
        if not re.fullmatch(r'db_pustakarapi_uji_\d{8}_\d{6}', test):
            raise RuntimeError('Refused: not a disposable test schema')
        run_sql(LIVE, f'DROP DATABASE `{test}`;')
        print('Removed disposable test database:', test)


if __name__ == '__main__':
    main()
