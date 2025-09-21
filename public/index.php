<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = get_database_connection();
$currentUser = null;
$errors = [];

if (!empty($_SESSION['user_id'])) {
    $currentUser = get_user_by_id($pdo, (int) $_SESSION['user_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $user = authenticate_user($pdo, $username, $password);

            if ($user) {
                $_SESSION['user_id'] = (int) $user['user_id'];
                set_flash('Welcome back, ' . ($user['full_name'] ?: $user['username']) . '!');
                redirect('index.php');
            } else {
                $errors[] = 'Invalid username or password.';
            }
            break;
        case 'register':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $fullName = $_POST['full_name'] ?? null;
            $email = $_POST['email'] ?? null;
            $role = $_POST['role'] ?? 'sales';

            $errors = register_user($pdo, $username, $password, $fullName, $email, $role);
            if (!$errors) {
                set_flash('Account created. You can now log in.');
                redirect('index.php');
            }
            break;
        case 'logout':
            session_destroy();
            redirect('index.php');
            break;
        case 'add_company':
            require_login();
            $name = trim($_POST['name'] ?? '');
            $industry = trim($_POST['industry'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($name === '') {
                set_flash('Company name is required.', 'error');
                redirect('index.php?view=companies');
            }

            $stmt = $pdo->prepare('INSERT INTO companies (name, industry, website, phone) VALUES (:name, :industry, :website, :phone)');
            $stmt->execute([
                ':name' => $name,
                ':industry' => $industry ?: null,
                ':website' => $website ?: null,
                ':phone' => $phone ?: null,
            ]);

            set_flash('Company created successfully.');
            redirect('index.php?view=companies');
            break;
        case 'add_contact':
            require_login();
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $companyId = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int) $_POST['company_id'] : null;

            if ($firstName === '' || $lastName === '') {
                set_flash('First and last name are required for contacts.', 'error');
                redirect('index.php?view=contacts');
            }

            try {
                $stmt = $pdo->prepare('INSERT INTO contacts (company_id, first_name, last_name, email, phone, position) VALUES (:company_id, :first_name, :last_name, :email, :phone, :position)');
                $stmt->execute([
                    ':company_id' => $companyId,
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $email ?: null,
                    ':phone' => $phone ?: null,
                    ':position' => $position ?: null,
                ]);
                set_flash('Contact saved successfully.');
            } catch (PDOException $e) {
                set_flash('Unable to save contact: ' . $e->getMessage(), 'error');
            }

            redirect('index.php?view=contacts');
            break;
        case 'add_deal':
            require_login();
            $name = trim($_POST['name'] ?? '');
            $stage = $_POST['stage'] ?? 'lead';
            $value = $_POST['value'] !== '' ? (float) $_POST['value'] : null;
            $closeDate = $_POST['close_date'] ?? null;
            $companyId = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int) $_POST['company_id'] : null;
            $contactId = isset($_POST['contact_id']) && $_POST['contact_id'] !== '' ? (int) $_POST['contact_id'] : null;

            if ($name === '') {
                set_flash('Deal name is required.', 'error');
                redirect('index.php?view=deals');
            }

            $stmt = $pdo->prepare('INSERT INTO deals (company_id, contact_id, name, stage, value, close_date) VALUES (:company_id, :contact_id, :name, :stage, :value, :close_date)');
            $stmt->execute([
                ':company_id' => $companyId,
                ':contact_id' => $contactId,
                ':name' => $name,
                ':stage' => $stage,
                ':value' => $value,
                ':close_date' => $closeDate ?: null,
            ]);

            $dealId = (int) $pdo->lastInsertId();
            if (!empty($_SESSION['user_id'])) {
                $assign = $pdo->prepare('INSERT OR IGNORE INTO deal_assignments (deal_id, user_id) VALUES (:deal_id, :user_id)');
                $assign->execute([
                    ':deal_id' => $dealId,
                    ':user_id' => (int) $_SESSION['user_id'],
                ]);
            }

            set_flash('Deal created successfully.');
            redirect('index.php?view=deals');
            break;
        case 'update_deal_stage':
            require_login();
            $dealId = (int) ($_POST['deal_id'] ?? 0);
            $stage = $_POST['stage'] ?? 'lead';

            $stmt = $pdo->prepare('UPDATE deals SET stage = :stage WHERE deal_id = :deal_id');
            $stmt->execute([
                ':stage' => $stage,
                ':deal_id' => $dealId,
            ]);

            set_flash('Deal stage updated.');
            redirect('index.php?view=deals');
            break;
        case 'add_activity':
            require_login();
            $type = $_POST['type'] ?? 'note';
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $dealId = isset($_POST['deal_id']) && $_POST['deal_id'] !== '' ? (int) $_POST['deal_id'] : null;
            $contactId = isset($_POST['contact_id']) && $_POST['contact_id'] !== '' ? (int) $_POST['contact_id'] : null;
            $activityDate = $_POST['activity_date'] ?? null;

            $stmt = $pdo->prepare('INSERT INTO activities (deal_id, contact_id, type, subject, content, activity_date) VALUES (:deal_id, :contact_id, :type, :subject, :content, COALESCE(:activity_date, CURRENT_TIMESTAMP))');
            $stmt->execute([
                ':deal_id' => $dealId,
                ':contact_id' => $contactId,
                ':type' => $type,
                ':subject' => $subject ?: null,
                ':content' => $content ?: null,
                ':activity_date' => $activityDate ?: null,
            ]);

            set_flash('Activity logged.');
            redirect('index.php?view=activities');
            break;
        default:
            break;
    }
}

$flash = get_flash();
$view = $_GET['view'] ?? 'dashboard';

if ($currentUser) {
    $dashboardStats = [
        'companies' => (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
        'contacts' => (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
        'deals' => (int) $pdo->query('SELECT COUNT(*) FROM deals')->fetchColumn(),
        'activities' => (int) $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn(),
    ];

    $companies = $pdo->query('SELECT * FROM companies ORDER BY created_at DESC')->fetchAll();
    $contacts = $pdo->query('SELECT contacts.*, companies.name AS company_name FROM contacts LEFT JOIN companies ON contacts.company_id = companies.company_id ORDER BY contacts.created_at DESC')->fetchAll();
    $deals = $pdo->query("SELECT deals.*, companies.name AS company_name, contacts.first_name, contacts.last_name, GROUP_CONCAT(COALESCE(users.full_name, users.username), ', ') AS assigned_users
        FROM deals
        LEFT JOIN companies ON deals.company_id = companies.company_id
        LEFT JOIN contacts ON deals.contact_id = contacts.contact_id
        LEFT JOIN deal_assignments ON deals.deal_id = deal_assignments.deal_id
        LEFT JOIN users ON deal_assignments.user_id = users.user_id
        GROUP BY deals.deal_id
        ORDER BY deals.created_at DESC")->fetchAll();
    $activities = $pdo->query("SELECT activities.*, deals.name AS deal_name, contacts.first_name, contacts.last_name
        FROM activities
        LEFT JOIN deals ON activities.deal_id = deals.deal_id
        LEFT JOIN contacts ON activities.contact_id = contacts.contact_id
        ORDER BY activities.activity_date DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funnel CRM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="layout">
        <header class="top-bar">
            <h1>Funnel CRM</h1>
            <?php if ($currentUser): ?>
                <div class="user-info">
                    <span><?= e($currentUser['full_name'] ?: $currentUser['username']) ?></span>
                    <form method="post">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="link">Log out</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <main class="content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$currentUser): ?>
                <section class="auth-grid">
                    <div class="card">
                        <h2>Sign in</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="login">
                            <label>
                                <span>Username</span>
                                <input type="text" name="username" required>
                            </label>
                            <label>
                                <span>Password</span>
                                <input type="password" name="password" required>
                            </label>
                            <button type="submit">Sign in</button>
                        </form>
                    </div>
                    <div class="card">
                        <h2>Create account</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="register">
                            <label>
                                <span>Full name</span>
                                <input type="text" name="full_name">
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="email">
                            </label>
                            <label>
                                <span>Username</span>
                                <input type="text" name="username" required>
                            </label>
                            <label>
                                <span>Password</span>
                                <input type="password" name="password" required>
                            </label>
                            <label>
                                <span>Role</span>
                                <select name="role">
                                    <option value="sales">Sales</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </label>
                            <button type="submit">Register</button>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <nav class="tabs">
                    <a href="?view=dashboard" class="<?= $view === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    <a href="?view=companies" class="<?= in_array($view, ['companies', 'companies_form'], true) ? 'active' : '' ?>">Companies</a>
                    <a href="?view=contacts" class="<?= in_array($view, ['contacts', 'contacts_form'], true) ? 'active' : '' ?>">Contacts</a>
                    <a href="?view=deals" class="<?= in_array($view, ['deals', 'deals_form'], true) ? 'active' : '' ?>">Deals</a>
                    <a href="?view=activities" class="<?= in_array($view, ['activities', 'activities_form'], true) ? 'active' : '' ?>">Activities</a>
                </nav>

                <?php if ($view === 'dashboard'): ?>
                    <section class="cards-grid">
                        <div class="card metric">
                            <span class="metric-label">Companies</span>
                            <span class="metric-value"><?= $dashboardStats['companies'] ?></span>
                        </div>
                        <div class="card metric">
                            <span class="metric-label">Contacts</span>
                            <span class="metric-value"><?= $dashboardStats['contacts'] ?></span>
                        </div>
                        <div class="card metric">
                            <span class="metric-label">Deals</span>
                            <span class="metric-value"><?= $dashboardStats['deals'] ?></span>
                        </div>
                        <div class="card metric">
                            <span class="metric-label">Activities</span>
                            <span class="metric-value"><?= $dashboardStats['activities'] ?></span>
                        </div>
                    </section>
                    <section class="card">
                        <h2>Recent deals</h2>
                        <div class="table">
                            <div class="table-head">
                                <span>Name</span>
                                <span>Company</span>
                                <span>Stage</span>
                                <span>Value</span>
                            </div>
                            <?php foreach (array_slice($deals, 0, 5) as $deal): ?>
                                <div class="table-row">
                                    <span><?= e($deal['name']) ?></span>
                                    <span><?= e($deal['company_name'] ?? '—') ?></span>
                                    <span class="pill"><?= e($deal['stage']) ?></span>
                                    <span><?= $deal['value'] ? '$' . number_format((float) $deal['value'], 2) : '—' ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$deals): ?>
                                <div class="table-row muted">No deals yet.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php elseif ($view === 'companies'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2>Companies</h2>
                            <a href="?view=companies_form" class="button primary with-icon">
                                <span class="icon" aria-hidden="true">+</span>
                                <span>Add Company</span>
                            </a>
                        </div>
                        <div class="table">
                            <div class="table-head">
                                <span>Name</span>
                                <span>Industry</span>
                                <span>Phone</span>
                            </div>
                            <?php foreach ($companies as $company): ?>
                                <div class="table-row">
                                    <span><?= e($company['name']) ?></span>
                                    <span><?= e($company['industry'] ?? '—') ?></span>
                                    <span><?= e($company['phone'] ?? '—') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$companies): ?>
                                <div class="table-row muted">No companies yet.</div>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_company">
                            <label>
                                <span>Name</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>Industry</span>
                                <input type="text" name="industry">
                            </label>
                            <label>
                                <span>Website</span>
                                <input type="url" name="website">
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" name="phone">
                            </label>
                            <button type="submit">Create</button>
                        </form>
                    </section>
                <?php elseif ($view === 'companies_form'): ?>
                    <section class="card card-form">
                        <h2>Add Company</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_company">
                            <label>
                                <span>Name</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>Industry</span>
                                <input type="text" name="industry">
                            </label>
                            <label>
                                <span>Website</span>
                                <input type="url" name="website">
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" name="phone">
                            </label>
                            <button type="submit">Create</button>
                        </form>
                    </section>
                <?php elseif ($view === 'contacts'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2>Contacts</h2>
                            <a href="?view=contacts_form" class="button with-icon">
                                <span class="icon" aria-hidden="true">+</span>
                                <span>Add Contact</span>
                            </a>
                        </div>
                        <div class="table">
                            <div class="table-head">
                                <span>Name</span>
                                <span>Company</span>
                                <span>Email</span>
                                <span>Phone</span>
                            </div>
                            <?php foreach ($contacts as $contact): ?>
                                <div class="table-row">
                                    <span><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></span>
                                    <span><?= e($contact['company_name'] ?? '—') ?></span>
                                    <span><?= e($contact['email'] ?? '—') ?></span>
                                    <span><?= e($contact['phone'] ?? '—') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$contacts): ?>
                                <div class="table-row muted">No contacts yet.</div>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_contact">
                            <label>
                                <span>First name</span>
                                <input type="text" name="first_name" required>
                            </label>
                            <label>
                                <span>Last name</span>
                                <input type="text" name="last_name" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="email">
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" name="phone">
                            </label>
                            <label>
                                <span>Position</span>
                                <input type="text" name="position">
                            </label>
                            <label>
                                <span>Company</span>
                                <select name="company_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['company_id'] ?>"><?= e($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">Save</button>
                        </form>
                    </section>
                <?php elseif ($view === 'contacts_form'): ?>
                    <section class="card card-form">
                        <a href="?view=contacts" class="button ghost with-icon back-link">
                            <span class="icon" aria-hidden="true">←</span>
                            <span>Back to Contacts</span>
                        </a>
                        <h2>Add Contact</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_contact">
                            <label>
                                <span>First name</span>
                                <input type="text" name="first_name" required>
                            </label>
                            <label>
                                <span>Last name</span>
                                <input type="text" name="last_name" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="email">
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" name="phone">
                            </label>
                            <label>
                                <span>Position</span>
                                <input type="text" name="position">
                            </label>
                            <label>
                                <span>Company</span>
                                <select name="company_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['company_id'] ?>"><?= e($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">Save</button>
                        </form>
                    </section>
                <?php elseif ($view === 'deals'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2>Deals</h2>
                            <a href="?view=deals_form" class="button with-icon">
                                <span class="icon" aria-hidden="true">+</span>
                                <span>Add Deal</span>
                            </a>
                        </div>
                        <div class="table">
                            <div class="table-head">
                                <span>Name</span>
                                <span>Company</span>
                                <span>Stage</span>
                                <span>Value</span>
                                <span>Assigned</span>
                            </div>
                            <?php foreach ($deals as $deal): ?>
                                <div class="table-row">
                                    <span><?= e($deal['name']) ?></span>
                                    <span><?= e($deal['company_name'] ?? '—') ?></span>
                                    <span>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="action" value="update_deal_stage">
                                            <input type="hidden" name="deal_id" value="<?= $deal['deal_id'] ?>">
                                            <select name="stage" onchange="this.form.submit()">
                                                <?php foreach (['lead','qualified','proposal','negotiation','closed_won','closed_lost'] as $stageOption): ?>
                                                    <option value="<?= $stageOption ?>" <?= $deal['stage'] === $stageOption ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $stageOption)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </span>
                                    <span><?= $deal['value'] ? '$' . number_format((float) $deal['value'], 2) : '—' ?></span>
                                    <span><?= e($deal['assigned_users'] ?? 'Unassigned') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$deals): ?>
                                <div class="table-row muted">No deals yet.</div>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_deal">
                            <label>
                                <span>Name</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>Stage</span>
                                <select name="stage">
                                    <?php foreach (['lead','qualified','proposal','negotiation','closed_won','closed_lost'] as $stageOption): ?>
                                        <option value="<?= $stageOption ?>"><?= ucfirst(str_replace('_', ' ', $stageOption)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Value</span>
                                <input type="number" step="0.01" name="value">
                            </label>
                            <label>
                                <span>Expected close</span>
                                <input type="date" name="close_date">
                            </label>
                            <label>
                                <span>Company</span>
                                <select name="company_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['company_id'] ?>"><?= e($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Primary contact</span>
                                <select name="contact_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['contact_id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">Create deal</button>
                        </form>
                    </section>
                <?php elseif ($view === 'deals_form'): ?>
                    <section class="card card-form">
                        <a href="?view=deals" class="button ghost with-icon back-link">
                            <span class="icon" aria-hidden="true">←</span>
                            <span>Back to Deals</span>
                        </a>
                        <h2>Add Deal</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_deal">
                            <label>
                                <span>Name</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>Stage</span>
                                <select name="stage">
                                    <?php foreach (['lead','qualified','proposal','negotiation','closed_won','closed_lost'] as $stageOption): ?>
                                        <option value="<?= $stageOption ?>"><?= ucfirst(str_replace('_', ' ', $stageOption)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Value</span>
                                <input type="number" step="0.01" name="value">
                            </label>
                            <label>
                                <span>Expected close</span>
                                <input type="date" name="close_date">
                            </label>
                            <label>
                                <span>Company</span>
                                <select name="company_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['company_id'] ?>"><?= e($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Primary contact</span>
                                <select name="contact_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['contact_id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">Create deal</button>
                        </form>
                    </section>
                <?php elseif ($view === 'activities'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2>Activities</h2>
                            <a href="?view=activities_form" class="button with-icon">
                                <span class="icon" aria-hidden="true">+</span>
                                <span>Add Activity</span>
                            </a>
                        </div>
                        <div class="table">
                            <div class="table-head">
                                <span>Type</span>
                                <span>Subject</span>
                                <span>Related to</span>
                                <span>Date</span>
                            </div>
                            <?php foreach ($activities as $activity): ?>
                                <div class="table-row">
                                    <span class="pill"><?= e($activity['type']) ?></span>
                                    <span><?= e($activity['subject'] ?? '—') ?></span>
                                    <span>
                                        <?php if ($activity['deal_name']): ?>
                                            <?= e($activity['deal_name']) ?>
                                        <?php elseif ($activity['first_name']): ?>
                                            <?= e($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </span>
                                    <span><?= e(date('Y-m-d H:i', strtotime((string) $activity['activity_date']))) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$activities): ?>
                                <div class="table-row muted">No activities yet.</div>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_activity">
                            <label>
                                <span>Type</span>
                                <select name="type">
                                    <?php foreach (['call','email','meeting','note'] as $typeOption): ?>
                                        <option value="<?= $typeOption ?>"><?= ucfirst($typeOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Subject</span>
                                <input type="text" name="subject">
                            </label>
                            <label>
                                <span>Notes</span>
                                <textarea name="content" rows="4"></textarea>
                            </label>
                            <label>
                                <span>Deal</span>
                                <select name="deal_id">
                                    <option value="">None</option>
                                    <?php foreach ($deals as $deal): ?>
                                        <option value="<?= $deal['deal_id'] ?>"><?= e($deal['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Contact</span>
                                <select name="contact_id">
                                    <option value="">None</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['contact_id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Date</span>
                                <input type="datetime-local" name="activity_date">
                            </label>
                            <button type="submit">Log activity</button>
                        </form>
                    </section>
                <?php elseif ($view === 'activities_form'): ?>
                    <section class="card card-form">
                        <a href="?view=activities" class="button ghost with-icon back-link">
                            <span class="icon" aria-hidden="true">←</span>
                            <span>Back to Activities</span>
                        </a>
                        <h2>Add Activity</h2>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="add_activity">
                            <label>
                                <span>Type</span>
                                <select name="type">
                                    <?php foreach (['call','email','meeting','note'] as $typeOption): ?>
                                        <option value="<?= $typeOption ?>"><?= ucfirst($typeOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Subject</span>
                                <input type="text" name="subject">
                            </label>
                            <label>
                                <span>Notes</span>
                                <textarea name="content" rows="4"></textarea>
                            </label>
                            <label>
                                <span>Deal</span>
                                <select name="deal_id">
                                    <option value="">None</option>
                                    <?php foreach ($deals as $deal): ?>
                                        <option value="<?= $deal['deal_id'] ?>"><?= e($deal['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Contact</span>
                                <select name="contact_id">
                                    <option value="">None</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['contact_id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Date</span>
                                <input type="datetime-local" name="activity_date">
                            </label>
                            <button type="submit">Log activity</button>
                        </form>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <footer class="footer">Built with SQLite &amp; PHP</footer>
    </div>
</body>
</html>
