<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
final class JobRunService
{
    public static function start(string $name):int{$stmt=Database::connection()->prepare("INSERT INTO job_runs(job_name,status) VALUES(:name,'running')");$stmt->execute([':name'=>$name]);return (int)Database::connection()->lastInsertId();}
    public static function finish(int $id,array $result):void{$stmt=Database::connection()->prepare("UPDATE job_runs SET status='completed',processed=:processed,updated=:updated,failed=:failed,finished_at=NOW() WHERE id=:id");$stmt->execute([':processed'=>(int)($result['checked']??$result['provider_services']??0),':updated'=>(int)($result['updated']??0),':failed'=>(int)($result['failed']??0),':id'=>$id]);}
    public static function fail(int $id,\Throwable $e):void{$stmt=Database::connection()->prepare("UPDATE job_runs SET status='failed',error_message=:error,finished_at=NOW() WHERE id=:id");$stmt->execute([':error'=>substr($e->getMessage(),0,2000),':id'=>$id]);}
}
