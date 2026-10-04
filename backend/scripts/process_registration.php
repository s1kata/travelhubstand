<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';
require_once __DIR__ . '/../components/lead_validation.php';

session_start();

// Rate limit: 3 регистрации за 60 минут с одного IP (анти-спам)
if (security_rate_limit_exceeded('registration', 3, 3600)) {
    $redirectData = [
        'errors' => ['name' => 'Слишком много попыток. Попробуйте позже.'],
        'name' => '',
        'email' => '',
        'phone' => '',
        'city' => '',
    ];
    header('Location: /frontend/window/registration-desktop.php?data=' . urlencode(json_encode($redirectData, JSON_UNESCAPED_UNICODE)));
    exit;
}

if (!defined('REMEMBER_TOKEN_SALT')) {
    define('REMEMBER_TOKEN_SALT', getenv('AUTH_REMEMBER_SALT') ?: 'travelhub-remember-token');
}

$errors = [];
$successMessage = '';
$name = $email = $phone = $city = $gender = '';
$age = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    if (!security_csrf_verify()) {
        $redirectData = [
            'errors' => ['name' => 'Сессия истекла. Обновите страницу и попробуйте снова.'],
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
        ];
        header('Location: /frontend/window/registration-desktop.php?data=' . urlencode(json_encode($redirectData, JSON_UNESCAPED_UNICODE)));
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordValue = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');

    // Валидация имени
    $nameErr = th_lead_validate_person_name($name, 2, 60);
    if ($nameErr !== null) {
        $errors['name'] = $nameErr === 'Укажите ФИО' ? 'Пожалуйста, введите ФИО.' : $nameErr;
    }

    // Валидация email
    if ($email === '') {
        $errors['email'] = 'Пожалуйста, введите email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Пожалуйста, введите корректный email.';
    }

    // Валидация пароля (не короче 8, без очевидного мусора)
    $passwordErr = th_lead_validate_password($passwordValue, 8);
    if ($passwordErr !== null) {
        $errors['password'] = $passwordErr;
    }

    // Валидация телефона (если указан)
    if ($phone !== '') {
        $phoneCheck = th_lead_validate_ru_phone($phone);
        if (!$phoneCheck['ok']) {
            $errors['phone'] = $phoneCheck['error'] ?? 'Укажите корректный мобильный телефон РФ (+7 9XX…).';
        } else {
            $phone = $phoneCheck['phone'];
        }
    }

    // Валидация города (отсекаем «Луна» и прочий мусор)
    $cityErr = th_lead_validate_city($city, false);
    if ($cityErr !== null) {
        $errors['city'] = $cityErr;
    }

    // Валидация возраста
    if ($age < 0 || $age > 120) {
        $age = 0;
    }

    // Валидация пола
    $allowedGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
    if (!in_array($gender, $allowedGenders, true)) {
        $gender = 'prefer_not_to_say';
    }

    $agreeErr = th_lead_require_agree($_POST);
    if ($agreeErr !== null) {
        $errors['agree'] = $agreeErr;
    }

    // Если нет ошибок валидации
    if (empty($errors)) {
        try {
            if (!$pdo) {
                $errors['email'] = 'Регистрация временно недоступна. Попробуйте позже или позвоните в офис.';
            } else {
                // Проверяем существование таблицы users
                try {
                    $dbDriver = strtolower(getenv('DB_DRIVER') ?: 'sqlite');
                    if ($dbDriver === 'sqlite') {
                        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
                        if (!$tableCheck->fetchColumn()) {
                            $errors['database'] = 'Таблица users не существует. База данных не инициализирована.';
                        }
                    } else {
                        $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
                        if ($tableCheck->rowCount() === 0) {
                            $errors['database'] = 'Таблица users не существует. База данных не инициализирована.';
                        }
                    }
                } catch (PDOException $e) {
                    // Продолжаем выполнение даже если проверка не удалась
                }
                
                if (empty($errors)) {
                    $duplicateFields = [];
                    
                    // Проверка email на дубликат
                    try {
                        $dbDriver = strtolower(getenv('DB_DRIVER') ?: 'sqlite');
                        if ($dbDriver === 'sqlite') {
                            $checkEmail = $pdo->prepare('SELECT id, email FROM users WHERE email LIKE :email');
                            $checkEmail->execute([':email' => $email]);
                            $existingUser = $checkEmail->fetch();
                            if ($existingUser && strtolower($existingUser['email']) === strtolower($email)) {
                                $duplicateFields[] = 'email';
                                $errors['email'] = 'Этот email уже зарегистрирован.';
                            }
                        } else {
                            $checkEmail = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                            $checkEmail->execute([':email' => $email]);
                            if ($checkEmail->fetch()) {
                                $duplicateFields[] = 'email';
                                $errors['email'] = 'Этот email уже зарегистрирован.';
                            }
                        }
                    } catch (PDOException $e) {
                        $errors['database'] = 'Ошибка проверки email: ' . $e->getMessage();
                    }
                    
                    // Проверка телефона на дубликат
                    if (empty($errors) && !empty($phone)) {
                        try {
                            $checkPhone = $pdo->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
                            $checkPhone->execute([':phone' => $phone]);
                            if ($checkPhone->fetch()) {
                                $duplicateFields[] = 'phone';
                                $errors['phone'] = 'Этот телефон уже зарегистрирован.';
                            }
                        } catch (PDOException $e) {}
                    }
                
                    // Если есть дубликаты - возвращаем ошибку
                    if (!empty($duplicateFields)) {
                        $errors['duplicate'] = true;
                    } else {
                        // Определяем роль пользователя
                        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                        $userRole = ($userCount === 0) ? 'admin' : 'user';
                        
                        // Создаем пользователя
                        try {
                            $hashedPassword = password_hash($passwordValue, PASSWORD_DEFAULT);
                            $insert = $pdo->prepare('INSERT INTO users (name, email, password, phone, city, gender, role) VALUES (:name, :email, :password, :phone, :city, :gender, :role)');
                            $result = $insert->execute([
                                ':name' => $name,
                                ':email' => $email,
                                ':password' => $hashedPassword,
                                ':phone' => !empty($phone) ? $phone : null,
                                ':city' => !empty($city) ? $city : null,
                                ':gender' => $gender,
                                ':role' => $userRole,
                            ]);

                            if (!$result) {
                                $errorInfo = $insert->errorInfo();
                                throw new PDOException('Не удалось создать пользователя. Код ошибки: ' . ($errorInfo[0] ?? 'unknown'));
                            }

                            $userId = $pdo->lastInsertId();
                            
                            // Проверяем создание пользователя
                            $verifyStmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = :id');
                            $verifyStmt->execute([':id' => $userId]);
                            $verifyStmt->fetch();
                            
                            // УСПЕШНАЯ РЕГИСТРАЦИЯ - РЕДИРЕКТ НА LOGIN
                            header('Location: /frontend/window/login-desktop.php');
                            exit;
                            
                        } catch (PDOException $e) {
                            throw $e;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('[registration] PDO: ' . $e->getMessage());
            // Не маскируем ошибки валидации/дубликатов как «временную ошибку сервера»
            if (empty($errors)) {
                $errors['name'] = 'Не удалось завершить регистрацию. Проверьте ФИО, email и пароль.';
            }
        } catch (Exception $e) {
            error_log('[registration] Exception: ' . $e->getMessage());
            if (empty($errors)) {
                $errors['name'] = 'Не удалось завершить регистрацию. Проверьте ФИО, email и пароль.';
            }
        }
    }
    
    // ЕСЛИ ЕСТЬ ОШИБКИ - ВОЗВРАЩАЕМ НА ФОРМУ
    if (!empty($errors)) {
        $redirectData = [
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'age' => $age,
            'gender' => $gender,
        ];
        
        header('Location: /frontend/window/registration-desktop.php?data=' . urlencode(json_encode($redirectData, JSON_UNESCAPED_UNICODE)));
        exit;
    }
} else {
    // Если запрос не POST - возвращаем на форму
    header('Location: /frontend/window/registration-desktop.php');
    exit;
}
?>