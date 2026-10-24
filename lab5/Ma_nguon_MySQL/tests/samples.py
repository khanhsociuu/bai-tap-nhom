"""Validate instructor-authored fixture programs only. NEVER use for user submissions."""
from pathlib import Path
import io,sys,json,subprocess,shutil,tempfile,argparse
root=Path(__file__).resolve().parents[1]
arg=argparse.ArgumentParser();arg.add_argument('--cpp',action='store_true');args=arg.parse_args()
entries=json.loads((root/'private_seed'/'seed.json').read_text())
tests=failed=0

def trusted_py(code,stdin):
    oldin,oldout=sys.stdin,sys.stdout
    o=io.StringIO()
    try:
        sys.stdin=io.StringIO(stdin);sys.stdout=o
        exec(compile(code,'trusted_instructor_sample','exec'),{'__name__':'__main__'})
    finally:sys.stdin,sys.stdout=oldin,oldout
    return o.getvalue()
for p in entries:
    folder=root/'private_seed'/'samples'/p['slug']
    good=(folder/'correct.py').read_text()
    bad=(folder/'wrong.py').read_text()
    difference=False
    for t in p['tests']:
        tests+=1
        if trusted_py(good,t['input']).rstrip()!=t['output'].rstrip():
            failed+=1;print('FAIL PY',p['slug'])
        if trusted_py(bad,t['input']).rstrip()!=t['output'].rstrip():difference=True
    tests+=1
    if not difference:failed+=1;print('FAIL wrong sample',p['slug'])
if args.cpp:
    if not shutil.which('g++'):raise SystemExit('g++ not installed')
    with tempfile.TemporaryDirectory() as temp:
      for p in entries:
        folder=root/'private_seed'/'samples'/p['slug']
        mismatched=False
        for kind in ['correct','wrong']:
          binary=Path(temp)/('app_'+kind)
          compile=subprocess.run(['g++','-std=c++17','-O2',str(folder/(kind+'.cpp')),'-o',str(binary)],capture_output=True,timeout=30)
          tests+=1
          if compile.returncode: failed+=1;print('FAIL compile',p['slug'],kind,compile.stderr.decode()[:100]);continue
          # one public and one boundary case for each sample
          for t in (p['tests'] if kind=='wrong' else (p['tests'][0],p['tests'][-1])):
            try:
              result=subprocess.run([str(binary)],input=t['input'],capture_output=True,text=True,timeout=3)
              tests+=1
              valid=result.returncode==0 and result.stdout.rstrip()==t['output'].rstrip()
              if kind=='correct' and not valid:failed+=1;print('FAIL C++ correct',p['slug'])
              if kind=='wrong' and not valid:mismatched=True
            except subprocess.TimeoutExpired:
              failed+=1;print('FAIL C++ timeout',p['slug'])
        tests+=1
        if not mismatched: failed+=1;print('FAIL C++ wrong lacks boundary mismatch',p['slug'])
print(f'SAMPLES: TESTS={tests} FAILED={failed}')
raise SystemExit(int(failed>0))
