<?php
declare(strict_types=1);
/**
 * Docker executor - CLI worker ONLY. Never require this class in a public endpoint.
 * Work files deliberately live on Linux's /tmp (not /mnt/c/... on Windows).
 * Docker containers have no network, no capabilities, no root, read-only rootfs.
 */
final class Runner {
    private string $runtime;

    public function __construct() {
        $uid = function_exists('posix_geteuid') ? (string)posix_geteuid() : (string)getmyuid();
        $this->runtime = rtrim(sys_get_temp_dir(), '/').'/dnu-judge-runtime-'.$uid;
        if (!is_dir($this->runtime) && !@mkdir($this->runtime,0700,true)) {
            throw new RuntimeException('Cannot create private Linux runtime directory');
        }
        @chmod($this->runtime,0700);
        if (!is_writable($this->runtime)) throw new RuntimeException('Runtime directory not writable');
    }

    /** @return array{kind:string,stdout:string,stderr:string,elapsed_ms:int} */
    public function execute(string $language,string $source,string $input,int $limitMs,int $memoryMb): array {
        $dir = null;
        try {
            if (!in_array($language,['python','cpp'],true)) return $this->problem('SYSTEM_ERROR');
            $dir=$this->runtime.'/job_'.bin2hex(random_bytes(12));
            if (!@mkdir($dir.'/work',0755,true) || !@mkdir($dir.'/bin',0777)) {
                throw new RuntimeException('Unable to initialize isolated workspace');
            }
            @chmod($dir.'/work',0755);
            @chmod($dir.'/bin',0777);
            $sourceName = $language==='cpp' ? 'main.cpp' : 'main.py';
            if (file_put_contents($dir.'/work/'.$sourceName,$source) !== strlen($source)
                || file_put_contents($dir.'/work/stdin.txt',$input) !== strlen($input)) {
                throw new RuntimeException('Failed to write runtime inputs');
            }
            @chmod($dir.'/work/'.$sourceName,0644);
            @chmod($dir.'/work/stdin.txt',0644);
            $cppImage = getenv('DNU_CPP_IMAGE') ?: 'gcc:14';
            if ($language==='python') {
                // Supervisor uses subprocess.wait(timeout) instead of Alpine BusyBox's
                // timeout; exit 137 (SIGKILL/OOM) must not be mistaken for TLE.
                $supervisor = file_get_contents(__DIR__.'/python_supervisor.py');
                if ($supervisor === false || file_put_contents($dir.'/work/supervise.py',$supervisor) !== strlen($supervisor)) {
                    throw new RuntimeException('Python supervisor is not available');
                }
                @chmod($dir.'/work/supervise.py',0644);
            }
            if ($language==='cpp') {
                $compile=$this->base($cppImage,$dir,512,true);
                array_push($compile,'sh','-c','g++ -std=c++17 -O2 -pipe /work/main.cpp -o /out/main');
                // Images must be pre-pulled, so these limits do not include registry downloads.
                $res=$this->capture($compile,40.0,'dnu-compile-'.bin2hex(random_bytes(8)));
                if ($res['timeout'] || in_array($res['code'],[125,126,127,-1],true)) {
                    error_log('Judge Docker compilation infrastructure failure; exit='.$res['code'].' timeout='.(int)$res['timeout'].' docker='.substr(trim($res['stderr']),0,450));
                    return $this->problem('SYSTEM_ERROR');
                }
                if ($res['code']!==0) {
                    // A permission failure is infrastructure failure, not the student's CE.
                    if (str_contains($res['stderr'],'Permission denied') || str_contains($res['stderr'],'Read-only file system')) {
                        error_log('Judge Docker compilation mount permission failure');
                        return $this->problem('SYSTEM_ERROR');
                    }
                    return $this->problem('CE',substr($res['stderr'],0,8192),$res['elapsed_ms']);
                }
                if (!is_file($dir.'/bin/main')) {
                    error_log('Judge Docker compiled binary missing from mounted /out directory');
                    return $this->problem('SYSTEM_ERROR');
                }
                $image=$cppImage;
                $innerCommand='exec timeout '.number_format(max(0.1,$limitMs/1000),3,'.','').'s /out/main < /work/stdin.txt';
            } else {
                $image='python:3.12-alpine';
                $innerCommand='exec python -I -B /work/supervise.py '.number_format(max(0.1,$limitMs/1000),3,'.','');
            }
            $run=$this->base($image,$dir,$memoryMb,false);
            array_push($run,'sh','-c',$innerCommand);
            // Inner timeout enforces submission wall-clock time; outer watchdog
            // allows up to 15 seconds of Docker Desktop/WSL startup overhead.
            $res=$this->capture($run,max(16.0,$limitMs/1000+15.0),'dnu-run-'.bin2hex(random_bytes(8)));
            if ($res['timeout'] || in_array($res['code'],[125,126,127,-1],true)) {
                error_log('Judge Docker runtime infrastructure failure; exit='.$res['code'].' timeout='.(int)$res['timeout'].' docker='.substr(trim($res['stderr']),0,450));
                return $this->problem('SYSTEM_ERROR');
            }
            // Our Python supervisor and GNU coreutils timeout both return 124 on deadline.
            // SIGKILL/exit 137 is not proof of a time limit (could be OOM).
            if ($res['code']===124) return $this->problem('TLE','',$res['elapsed_ms']);
            if ($res['code']!==0) {
                $syntax=$language==='python' && preg_match('/(SyntaxError|IndentationError|TabError):/',$res['stderr']);
                return $this->problem($syntax?'CE':'RE',substr($res['stderr'],0,8192),$res['elapsed_ms']);
            }
            return ['kind'=>'OK','stdout'=>$res['stdout'],'stderr'=>substr($res['stderr'],0,8192),'elapsed_ms'=>$res['elapsed_ms']];
        } catch (Throwable $e) {
            // Do not print user source or private tests to logs.
            error_log('Judge runner error: '.$e->getMessage());
            return $this->problem('SYSTEM_ERROR');
        } finally {
            if ($dir!==null) $this->remove($dir);
        }
    }

    private function problem(string $kind,string $stderr='',int $elapsed=0): array {
        return ['kind'=>$kind,'stdout'=>'','stderr'=>$stderr,'elapsed_ms'=>$elapsed];
    }

    private function base(string $image,string $dir,int $memory,bool $compile): array {
        $mb=max(32,min(512,$memory));
        return ['docker','run','--rm','--pull','never','--network','none','--read-only',
            '--cap-drop','ALL','--security-opt','no-new-privileges','--pids-limit','64',
            '--cpus','1','--memory',$mb.'m','--memory-swap',$mb.'m',
            '--user','65534:65534','--ulimit','nofile=64:64',
            '--tmpfs','/tmp:rw,nosuid,nodev,size=64m,mode=1777',
            '-v',$dir.'/work:/work:ro',
            '-v',$dir.'/bin:/out:'.($compile?'rw':'ro'),
            '--workdir','/work',$image];
    }

    /** Bounded capture; shell never interpolates user-controlled source or stdin. */
    private function capture(array $args,float $seconds,string $name): array {
        array_splice($args,2,0,['--name',$name]);
        $pipes=[];
        $proc=@proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) return ['code'=>-1,'stdout'=>'','stderr'=>'','elapsed_ms'=>0,'timeout'=>false];
        fclose($pipes[0]);
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
        $start=microtime(true);$out='';$err='';$timed=false;$exit=-1;
        while(true) {
            $st=proc_get_status($proc);
            foreach([1,2] as $i) {
                $chunk=stream_get_contents($pipes[$i]);
                if ($chunk===false) $chunk='';
                if($i===1) $out=substr($out.$chunk,0,65536);
                else $err=substr($err.$chunk,0,65536);
            }
            if(!$st['running']) {$exit=$st['exitcode'];break;}
            if(microtime(true)-$start>$seconds) {
                $timed=true;
                $this->killContainer($name);
                proc_terminate($proc);
                break;
            }
            usleep(35000);
        }
        foreach([1,2] as $i) {
            $chunk=stream_get_contents($pipes[$i]);
            if ($chunk===false) $chunk='';
            if($i===1) $out=substr($out.$chunk,0,65536);
            else $err=substr($err.$chunk,0,65536);
            fclose($pipes[$i]);
        }
        $closed=proc_close($proc);
        if($exit<0&&$closed>=0)$exit=$closed;
        return ['code'=>$exit,'stdout'=>$out,'stderr'=>$err,'elapsed_ms'=>(int)((microtime(true)-$start)*1000),'timeout'=>$timed];
    }
    private function killContainer(string $name):void {
        $p=@proc_open(['docker','rm','-f',$name],
            [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if(is_resource($p))proc_close($p);
    }
    private function remove(string $path):void {
        if(!is_dir($path))return;
        foreach(new DirectoryIterator($path) as $entry) {
            if($entry->isDot())continue;
            $p=$entry->getPathname();
            if($entry->isDir()&&!$entry->isLink())$this->remove($p);
            else @unlink($p);
        }
        @rmdir($path);
    }
}
