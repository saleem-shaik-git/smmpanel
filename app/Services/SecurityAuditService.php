<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
final class SecurityAuditService
{
    public static function log(?int $userId,string $event,array $metadata=[]):void
    {
        $stmt=Database::connection()->prepare('INSERT INTO security_audit_logs(user_id,event_type,ip_address,user_agent,metadata) VALUES(:uid,:event,:ip,:ua,:metadata)');
        $stmt->execute([':uid'=>$userId,':event'=>substr($event,0,80),':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),':ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),':metadata'=>json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    }
}
