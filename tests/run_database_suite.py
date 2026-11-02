"""Reproducible integration suite. Creates and removes a disposable schema.
Requires MySQL local root configuration used by tools/database_coursework.py.
Never inserts test transactions into db_pustakarapi.
"""
from pathlib import Path
import json
import os
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools'))
from database_coursework import state, SCRATCH
PHP = 'C:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe'
report = []

def run(command, env=None):
    p = subprocess.run(command, cwd=ROOT, env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    output = p.stdout.decode('utf8', errors='replace')
    print(output)
    report.append({'command': command, 'exit_code': p.returncode, 'output': output})
    if p.returncode:
        raise RuntimeError('Test command failed')

try:
    run([sys.executable, 'tools/database_coursework.py', 'prepare'])
    env = dict(os.environ, TOKOKITA_TEST_DB=state()['test_database'])
    for sql, mode in [('01_functions.sql','functions'),('02_views.sql','views'),('03_triggers.sql','triggers'),('04_procedures.sql','procedures')]:
        run([sys.executable,'tools/database_coursework.py','apply-test','--file',sql])
        run([PHP,'-d','opcache.enable_cli=0','tests/database_contract.php',mode],env)
    for mode in ['integration','retur','orphan']:
        run([PHP,'-d','opcache.enable_cli=0','tests/database_contract.php',mode],env)
    run([sys.executable,'tests/database_concurrency.py'],env)
    run([sys.executable,'tests/database_receipt_concurrency.py'],env)
    run([PHP,'-d','opcache.enable_cli=0','tests/render-database-pages.php'],env)
    run([sys.executable,'tools/database_coursework.py','verify'])
finally:
    dest = SCRATCH / 'tokokita-database-test-report.json'
    dest.write_text(json.dumps(report,indent=2,ensure_ascii=False),encoding='utf8')
    print('REPORT:',dest)
    # Inspect result before removing the disposable database when debugging.
    if all(r['exit_code']==0 for r in report):
        run([sys.executable,'tools/database_coursework.py','cleanup'])
