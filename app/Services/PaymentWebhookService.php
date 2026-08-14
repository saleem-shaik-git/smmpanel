<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
use RuntimeException;
final class PaymentWebhookService
{
    public function processPaystack(string $rawBody, string $signature):bool
    {$secret=(string)(getenv('PAYSTACK_SECRET_KEY')?:'');if($secret===''||!hash_equals(hash_hmac('sha512',$rawBody,$secret),$signature))throw new RuntimeException('Invalid webhook signature.');$payload=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR);$eventId=(string)($payload['data']['id']??$payload['data']['reference']??hash('sha256',$rawBody));$pdo=Database::connection();$stmt=$pdo->prepare('INSERT INTO payment_webhook_events(provider,event_id,event_type,signature_valid,payload) VALUES(\'paystack\',:id,:type,1,:payload) ON DUPLICATE KEY UPDATE id=id');$stmt->execute([':id'=>$eventId,':type'=>(string)($payload['event']??''),':payload'=>$rawBody]);$check=$pdo->prepare('SELECT processed FROM payment_webhook_events WHERE provider=\'paystack\' AND event_id=:id');$check->execute([':id'=>$eventId]);if((int)$check->fetchColumn()===1)return true;if(($payload['event']??'')==='charge.success'){$data=$payload['data'];$reference=(string)($data['reference']??'');$providerRef=(string)($data['id']??$reference);if($reference==='')throw new RuntimeException('Webhook reference missing.');$this->assertAmountAndCurrency($reference,$data);(new PaymentService())->completeIntent($reference,$providerRef,$payload);}$pdo->prepare("UPDATE payment_webhook_events SET processed=1,processed_at=NOW() WHERE provider='paystack' AND event_id=:id")->execute([':id'=>$eventId]);return true;}
    private function assertAmountAndCurrency(string $reference,array $data):void{$stmt=Database::connection()->prepare('SELECT amount,currency,status FROM payment_intents WHERE reference=:ref LIMIT 1');$stmt->execute([':ref'=>$reference]);$intent=$stmt->fetch();if(!$intent)throw new RuntimeException('Unknown payment reference.');$expectedMinor=(int)round((float)$intent['amount']*100);if((int)($data['amount']??0)!==$expectedMinor)throw new RuntimeException('Payment amount mismatch.');if(strtoupper((string)($data['currency']??''))!==strtoupper((string)$intent['currency']))throw new RuntimeException('Payment currency mismatch.');if($intent['status']!=='pending'&&$intent['status']!=='paid')throw new RuntimeException('Invalid payment state.');}
}
