<?php
declare(strict_types=1);

function get_database_connection(): PDO
{
    $dbPath = __DIR__ . '/../database/crm.sqlite';
    $needsInitialization = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($needsInitialization) {
        initialize_schema($pdo);
    } else {
        // Ensure schema exists when database file is present but tables are missing.
        initialize_schema($pdo);
    }

    return $pdo;
}

function initialize_schema(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            user_id     INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT UNIQUE NOT NULL,
            password    TEXT NOT NULL,
            full_name   TEXT,
            email       TEXT UNIQUE,
            role        TEXT CHECK(role IN ('admin', 'manager', 'sales')) DEFAULT 'sales',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS companies (
            company_id  INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            industry    TEXT,
            website     TEXT,
            phone       TEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS contacts (
            contact_id  INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id  INTEGER,
            first_name  TEXT NOT NULL,
            last_name   TEXT NOT NULL,
            email       TEXT UNIQUE,
            phone       TEXT,
            position    TEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS deals (
            deal_id     INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id  INTEGER,
            contact_id  INTEGER,
            name        TEXT NOT NULL,
            stage       TEXT CHECK(stage IN ('lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost')),
            value       REAL,
            close_date  DATE,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(contact_id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS activities (
            activity_id INTEGER PRIMARY KEY AUTOINCREMENT,
            deal_id     INTEGER,
            contact_id  INTEGER,
            type        TEXT CHECK(type IN ('call', 'email', 'meeting', 'note')),
            subject     TEXT,
            content     TEXT,
            activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (deal_id) REFERENCES deals(deal_id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES contacts(contact_id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS deal_assignments (
            deal_id INTEGER,
            user_id INTEGER,
            PRIMARY KEY (deal_id, user_id),
            FOREIGN KEY (deal_id) REFERENCES deals(deal_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )"
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}
