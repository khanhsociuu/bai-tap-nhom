<?php
declare(strict_types=1);
/** CLI WORKER ONLY. Docker engine/socket is NEVER mounted or exposed to the web process. */
final class Runner {
    private string $runtime;
    public function __construct() {
        $this->runtime=dirname(__DIR__).'/.runtime';
        if (!is_dir($this->runtime)) mkdir($this->runtime,0700,true);
        chmod($this->runtime,0700);
    }
    /** @return array{kind:string,stdout:string,elapsed_ms:int} */
    public function execute(string $language,string $source,string $input,int $limitMs,int $memoryMb): array {
        $dir=$this->runtime.'/job_'.bin2hex(random_bytes(10));
        if (!mkdir($dir.'/work',0755,true) || !mkdir($dir.'/bin',0777)) return ['kind'=>'SYSTEM_ERROR','stdout'=>'','stderr'=>'','elapsed_ms'=>0];
        chmod($dir.'/bin',0777);
        $sourceName=$language==='cpp'?'main.cpp':'main.py';
        file_put_contents($dir.'/work/'.$sourceName,$source);
        file_put_contents($dir.'/work/stdin.txt',$input);
        chmod($dir.'/work/'.$sourceName,0644);
        chmod($dir.'/work/stdin.txt',0644);
        try {
            if ($language==='cpp') {
                $cmd=$this->base('gcc:14',$dir,512,true);
                array_push($cmd,'sh','-c','g++ -std=c++17 -O2 -pipe /work/main.cpp -o /out/main');
                $compile=$this->capture($cmd,15,'dnu-compile-'.bin2hex(random_bytes(7)));
                if ($compile['timeout']) return ['kind'=>'CE','stdout'=>'','stderr'=>substr($compile['stderr'],0,8192),'elapsed_ms'=>$compile['elapsed_ms']];
                if (in_array($compile['code'],[125,126,127,137,-1],true)) return ['kind'=>'SYSTEM_ERROR','stdout'=>'','stderr'=>'','elapsed_ms'=>0];
                if ($compile['code']!==0) return ['kind'=>'CE','stdout'=>'','stderr'=>substr($compile['stderr'],0,8192),'elapsed_ms'=>$compile['elapsed_ms']];
                $cmd=$this->base('gcc:14',$dir,$memoryMb,false);
                array_push($cmd,'sh','-c','exec /out/main < /work/stdin.txt');
            } elseif ($language==='python') {
                $cmd=$this->base('python:3.12-alpine',$dir,$memoryMb,false);
                array_push($cmd,'sh','-c','exec python -B /work/main.py < /work/stdin.txt');
            } else return ['kind'=>'SYSTEM_ERROR','stdout'=>'','stderr'=>'','elapsed_ms'=>0];
            $run=$this->capture($cmd,($limitMs/1000)+1.0,'dnu-run-'.bin2hex(random_bytes(7)));
            if ($run['timeout']) return ['kind'=>'TLE','stdout'=>'','stderr'=>'','elapsed_ms'=>$run['elapsed_ms']];
            if ($run['code']===125 || $run['code']===-1) return ['kind'=>'SYSTEM_ERROR','stdout'=>'','stderr'=>'','elapsed_ms'=>0];
            if ($run['code']!==0) {
                $isSyntax=$language==='python' && preg_match('/(SyntaxError|IndentationError|TabError):/',$run['stderr']);
                return ['kind'=>$isSyntax?'CE':'RE','stdout'=>'','stderr'=>substr($run['stderr'],0,8192),'elapsed_ms'=>$run['elapsed_ms']];
            }
            return ['kind'=>'OK','stdout'=>$run['stdout'],'stderr'=>substr($run['stderr'],0,8192),'elapsed_ms'=>$run['elapsed_ms']];
        } catch(Throwable $e) {
            error_log('Runner error: '.$e->getMessage());
            return ['kind'=>'SYSTEM_ERROR','stdout'=>'','stderr'=>'','elapsed_ms'=>0];
        } finally { $this->remove($dir); }
    }
    private function base(string $image,string $dir,int $memory,bool $compile): array {
        $cmd=['docker','run','--rm','--network','none','--read-only','--cap-drop','ALL',
            '--security-opt','no-new-privileges','--pids-limit','64','--cpus','1',
            '--memory',max(32,min(512,$memory)).'m','--memory-swap',max(32,min(512,$memory)).'m',
            '--user','65534:65534','--ulimit','nofile=64:64',
            '--tmpfs','/tmp:rw,nosuid,size=64m,mode=1777',
            '-v',$dir.'/work:/work:ro','-v',$dir.'/bin:/out:'.($compile?'rw':'ro'),
            '--workdir','/work',$image];
        return $cmd;
    }
    /** Restricted output; no shell interpolation. Kill container on timeout. */
    private function capture(array $args,float $seconds,string $name): array {
        // Inject Docker --name after 'docker run' so timed-out containers can be forcibly removed.
        array_splice($args,2,0,['--name',$name]);
        $pipes=[];
        $proc=@proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) return ['code'=>-1,'stdout'=>'','stderr'=>'','elapsed_ms'=>0,'timeout'=>false];
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $start=microtime(true); $out='';$err='';$timed=false;$exit=-1;
        while (true) {
            $status=proc_get_status($proc);
            foreach ([1,2] as $i) {
                $chunk=stream_get_contents($pipes[$i]);
                if ($i===1) $out=substr($out.$chunk,0,65536);
                else $err=substr($err.$chunk,0,65536);
            }
            if (!$status['running']) { $exit=$status['exitcode']; break; }
            if (microtime(true)-$start > $seconds) { $timed=true; $this->killContainer($name); proc_terminate($proc); break; }
            usleep(35000);
        }
        foreach ([1,2] as $i) { $chunk=stream_get_contents($pipes[$i]); if ($i===1) $out=substr($out.$chunk,0,65536); else $err=substr($err.$chunk,0,65536); fclose($pipes[$i]); }
        $closed=proc_close($proc);
        if ($exit<0 && $closed>=0) $exit=$closed;
        return ['code'=>$exit,'stdout'=>$out,'stderr'=>$err,'elapsed_ms'=>(int)((microtime(true)-$start)*1000),'timeout'=>$timed];
    }
    private function killContainer(string $name): void {
        $p=@proc_open(['docker','rm','-f',$name],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if (is_resource($p)) proc_close($p);
    }
    private function remove(string $path): void {
        if (!is_dir($path)) return;
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            $p=$item->getPathname();
            if ($item->isDir()) $this->remove($p); else @unlink($p);
        }
        @rmdir($path);
    }
}
