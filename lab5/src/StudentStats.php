<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
/** Lab 05 adaptation: LEFT JOIN + COUNT(entity.id) yields zero instead of lost rows. */
final class StudentStats {
    public function __construct(private PgSql\Connection $c) {}
    public function forClass(int $classId,int $viewerId,string $role): array {
        $row=one($this->c,'SELECT cl.id AS classroom_id,cl.name,
          COUNT(DISTINCT m.user_id) AS students,COUNT(DISTINCT s.id) AS submissions
          FROM classrooms cl
          LEFT JOIN class_memberships m ON m.classroom_id=cl.id
          LEFT JOIN submissions s ON s.user_id=m.user_id
          WHERE cl.id=$1 AND (cl.teacher_id=$2 OR $3=\'admin\')
          GROUP BY cl.id,cl.name',[$classId,$viewerId,$role]);
        if(!$row) return [];
        $row['students']=(int)$row['students'];$row['submissions']=(int)$row['submissions'];
        return $row;
    }
    public function byTeacher(int $viewerId,string $role): array {
        $data=rows($this->c,'SELECT cl.name classroom,u.username,u.display_name,
           COUNT(DISTINCT s.problem_id) FILTER(WHERE j.verdict=\'AC\' AND j.status=\'done\') solved,
           COUNT(DISTINCT s.id) submissions
           FROM classrooms cl JOIN class_memberships m ON m.classroom_id=cl.id
           JOIN users u ON u.id=m.user_id LEFT JOIN submissions s ON s.user_id=u.id
           LEFT JOIN judging_runs j ON j.submission_id=s.id
           WHERE cl.teacher_id=$1 OR $2=\'admin\'
           GROUP BY cl.id,cl.name,u.id,u.username,u.display_name ORDER BY cl.name,u.username',[$viewerId,$role]);
        foreach($data as &$row){$row['solved']=(int)$row['solved'];$row['submissions']=(int)$row['submissions'];}unset($row);
        return $data;
    }
}
