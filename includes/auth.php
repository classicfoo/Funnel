<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function register_user(PDO $pdo, string $username, string $password, ?string $fullName, ?string $email, string $role = 'sales'): array
{
    $errors = [];

    if (trim($username) === '') {
        $errors[] = 'Username is required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!in_array($role, ['admin', 'manager', 'sales'], true)) {
        $role = 'sales';
    }

    if ($errors) {
        return $errors;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, email, role) VALUES (:username, :password, :full_name, :email, :role)');
        $stmt->execute([
            ':username' => strtolower(trim($username)),
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':full_name' => $fullName ? trim($fullName) : null,
            ':email' => $email ? strtolower(trim($email)) : null,
            ':role' => $role,
        ]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            $errors[] = 'Username or email already exists.';
        } else {
            $errors[] = 'Unable to create user: ' . $e->getMessage();
        }
    }

    return $errors;
}

function authenticate_user(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => strtolower(trim($username))]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }

    return null;
}

function get_user_by_id(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}
