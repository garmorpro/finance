<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function prompt(string $label): string
{
    echo $label . ': ';
    $value = trim((string) fgets(STDIN));

    if ($value === '') {
        echo "This field is required.\n";
        return prompt($label);
    }

    return $value;
}

function promptPassword(string $label): string
{
    echo $label . ': ';
    system('stty -echo');
    $value = trim((string) fgets(STDIN));
    system('stty echo');
    echo "\n";

    if (mb_strlen($value) < 12) {
        echo "Password must be at least 12 characters.\n";
        return promptPassword($label);
    }

    return $value;
}

$userRepo = new UserRepository();

echo "=== Create the first household owner ===\n";

$name = prompt('Your name');
$email = trim(mb_strtolower(prompt('Email')));

if ($userRepo->findByEmail($email) !== null) {
    echo "A user with that email already exists. Aborting.\n";
    exit(1);
}

$householdName = prompt('Household name');
$password = promptPassword('Password (min 12 characters)');
$confirm = promptPassword('Confirm password');

if (!hash_equals($password, $confirm)) {
    echo "Passwords do not match. Aborting.\n";
    exit(1);
}

$pdo = Connection::get();
$pdo->beginTransaction();

try {
    $userId = $userRepo->create($name, $email, password_hash($password, PASSWORD_DEFAULT));

    $householdRepo = new HouseholdRepository();
    $householdId = $householdRepo->create($householdName, $userId);
    $householdRepo->addMember($householdId, $userId, 'owner');

    (new AuditLogRepository())->log($userId, $householdId, 'household.bootstrap', 'user', $userId, null, [
        'email' => $email,
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "Failed to create owner: {$e->getMessage()}\n";
    exit(1);
}

echo "\nDone. {$name} <{$email}> is now the Owner of \"{$householdName}\".\n";
echo "You can log in at your site's /login page.\n";
