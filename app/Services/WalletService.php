<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
use RuntimeException;
final class WalletService
{
    public static function credit(int $userId,float $amount,string $reference,string $description=''):void{self::move($userId,$amount,'credit',$reference,$description);}
    public static function debit(int $userId,float $amount,string $reference,string $description=''):void{self::move($userId,$amount,'debit',$reference,$description);}
    private static function move(int $userId,float $amount,string $direction,string $reference,string $description):void
    {
        $amount=round($amount,4);if($amount<=0)throw new RuntimeException('Wallet amount must be positive.');$pdo=Database::connection();$pdo->beginTransaction();
        try{$existing=$pdo->prepare('SELECT id FROM wallet_ledger WHERE reference=:reference LIMIT 1');$existing->execute([':reference'=>$reference]);if($existing->fetchColumn()!==false){$pdo->rollBack();return;}$stmt=$pdo->prepare('SELECT balance FROM users WHERE id=:id FOR UPDATE');$stmt->execute([':id'=>$userId]);$row=$stmt->fetch();if(!$row)throw new RuntimeException('User not found.');$before=(float)$row['balance'];$after=$direction==='credit'?$before+$amount:$before-$amount;if($after<0)throw new RuntimeException('Insufficient wallet balance.');$type=$direction==='credit'?'deposit':'order';$pdo->prepare('UPDATE users SET balance=:balance WHERE id=:id')->execute([':balance'=>$after,':id'=>$userId]);$pdo->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,reference,description,status) VALUES(:uid,:type,:amount,:before,:after,:ref,:description,'completed')")->execute([':uid'=>$userId,':type'=>$type,':amount'=>$amount,':before'=>$before,':after'=>$after,':ref'=>$reference,':description'=>$description]);$tx=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO wallet_ledger(user_id,transaction_id,direction,amount,balance_before,balance_after,reference,metadata) VALUES(:uid,:tx,:direction,:amount,:before,:after,:ref,:metadata)')->execute([':uid'=>$userId,':tx'=>$tx,':direction'=>$direction,':amount'=>$amount,':before'=>$before,':after'=>$after,':ref'=>$reference,':metadata'=>json_encode(['description'=>$description],JSON_THROW_ON_ERROR)]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
