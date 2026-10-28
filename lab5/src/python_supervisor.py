#!/usr/bin/env python3
"""Fixed worker-owned timeout controller, executed *inside* an unprivileged Docker container.
Not a public Web endpoint. Python child output is bounded by RLIMIT_FSIZE.
"""
import os
import resource
import signal
import subprocess
import sys


def main():
    if len(sys.argv) != 2:
        return 125
    try:
        seconds = float(sys.argv[1])
        if not (0.05 <= seconds <= 30.0):
            return 125
    except ValueError:
        return 125

    def bound_output():
        resource.setrlimit(resource.RLIMIT_FSIZE, (262144, 262144))

    with open('/work/stdin.txt', 'rb') as stdin, \
         open('/tmp/dnu-stdout', 'wb') as stdout, \
         open('/tmp/dnu-stderr', 'wb') as stderr:
        try:
            p = subprocess.Popen(
                [sys.executable, '-I', '-B', '/work/main.py'],
                stdin=stdin, stdout=stdout, stderr=stderr,
                cwd='/work', start_new_session=True,
                preexec_fn=bound_output,
            )
            try:
                code = p.wait(timeout=seconds)
            except subprocess.TimeoutExpired:
                try:
                    os.killpg(p.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                p.wait()
                code = 124
        except (OSError, subprocess.SubprocessError):
            code = 125

    # These files live on an isolated 64 MiB tmpfs; expose only capped output.
    for path, stream in [('/tmp/dnu-stdout', sys.stdout.buffer),
                         ('/tmp/dnu-stderr', sys.stderr.buffer)]:
        try:
            with open(path, 'rb') as f:
                stream.write(f.read(65536))
                stream.flush()
        except OSError:
            pass

    # POSIX negative signal return codes must not be interpreted as TLE.
    return code if code >= 0 else 128 - code


if __name__ == '__main__':
    sys.exit(main())
