"""Check the real Python timeout controller logic without relying on Docker."""
from pathlib import Path
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parent.parent
src = (ROOT / 'src/python_supervisor.py').read_text(encoding='utf-8')
checks = [
    ('correct', 'print(5)', 0, '5'),
    ('syntax', 'print(', 1, 'SyntaxError'),
    ('runtime', '1/0', 1, 'ZeroDivisionError'),
    ('timeout', 'while True: pass', 124, ''),
    ('killed', 'import os, signal; os.kill(os.getpid(), signal.SIGKILL)', 137, ''),
]
fail = 0
with tempfile.TemporaryDirectory(prefix='dnu-supervisor-check-') as d:
    w = Path(d)
    (w / 'stdin.txt').write_text('2 3\n')
    script = src.replace("'/work/stdin.txt'", repr(str(w / 'stdin.txt')))
    script = script.replace("'/work/main.py'", repr(str(w / 'main.py')))
    script = script.replace("cwd='/work'", 'cwd=' + repr(str(w)))
    script = script.replace("'/tmp/dnu-stdout'", repr(str(w / 'stdout.txt')))
    script = script.replace("'/tmp/dnu-stderr'", repr(str(w / 'stderr.txt')))
    (w / 'supervisor.py').write_text(script)
    for label, code, status, expected in checks:
        (w / 'main.py').write_text(code)
        r = subprocess.run([sys.executable, str(w / 'supervisor.py'), '0.500' if label == 'timeout' else '2.000'], capture_output=True, text=True, timeout=7)
        ok = r.returncode == status and expected in r.stdout + r.stderr
        print(('PASS' if ok else 'FAIL') + f': {label} code={r.returncode} expected={status}')
        fail += not ok
print(f'SUPERVISOR: TESTS={len(checks)} FAILED={fail}')
sys.exit(1 if fail else 0)
