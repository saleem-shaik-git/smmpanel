<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth;
use App\Services\PaymentService;
use App\Services\PaystackService;

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /add-funds.php');
    exit;
}

Auth::verifyCsrf($_POST['_csrf'] ?? null);

$amount = (float) ($_POST['amount'] ?? 0);

if ($amount < 100 || $amount > 10000000) {
    header('Location: /add-funds.php?error=invalid_amount');
    exit;
}

try {
    /*
     * Create the internal payment intent FIRST.
     *
     * This reference must be the same reference sent to Paystack.
     */
    $paymentService = new PaymentService();

    $intent = $paymentService->createIntent(
        $userId,
        $amount,
        'paystack'
    );

    $reference = $intent['reference'];

    /*
     * Get the user's email.
     */
    $pdo = \App\Database::connection();

    $stmt = $pdo->prepare(
        'SELECT name, email
         FROM users
         WHERE id = :id
           AND status = "active"
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $userId,
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Active user account not found.');
    }

    /*
     * Callback URL.
     */
    $appUrl = rtrim(
        (string) env(
            'APP_URL',
            'http://localhost:8000'
        ),
        '/'
    );

    $callback = $appUrl . '/payment-callback.php';

    /*
     * Initialize Paystack using the SAME payment_intent reference.
     */
    $service = new PaystackService(
        (string) env('PAYSTACK_SECRET_KEY', '')
    );

    $data = $service->initialize(
        (string) $user['email'],
        $amount,
        $reference,
        $callback,
        [
            'user_id' => $userId,
            'name' => (string) $user['name'],
        ]
    );

    if (empty($data['authorization_url'])) {
        throw new RuntimeException(
            'Paystack did not return an authorization URL.'
        );
    }

    header(
        'Location: ' . $data['authorization_url'],
        true,
        302
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'Paystack initialization failed: ' .
        $e->getMessage()
    );

    header(
        'Location: /add-funds.php?error=payment_initialization'
    );

    exit;
}