<?php
declare(strict_types=1);
final class Policy {
    public static function checkSource(string $language,string $source): void {
        if (!in_array($language,['python','cpp'],true)) throw new InvalidArgumentException('Ngôn ngữ không hỗ trợ');
        if (trim($source)==='' || strlen($source)>65536 || str_contains($source,"\0"))
            throw new InvalidArgumentException('Mã nguồn trống hoặc vượt giới hạn 64 KiB');
    }
    public static function equalOutput(string $a,string $b): bool {
        $norm=static fn(string $s):string => rtrim(str_replace(["\r\n","\r"],"\n",$s));
        return $norm($a)===$norm($b);
    }
    public static function overall(array $verdicts): string {
        foreach (['CE','TLE','RE','WA'] as $code) if (in_array($code,$verdicts,true)) return $code;
        return 'AC';
    }
    public static function publicRun(array $row): array {
        // Whitelist only; never send test input/output, worker configuration, lease token.
        return array_intersect_key($row,array_flip(['id','submission_id','generation','status','verdict','passed_count','total_count','created_at','finished_at']));
    }
}
