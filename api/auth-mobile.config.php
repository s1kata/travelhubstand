<?php
/**
 * Mobile / next-patch API config for travelhub63.ru
 * НЕ коммитьте с реальными секретами в публичный репозиторий.
 */
return [
    'debug' => false,

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'garant77li',
        'user' => 'garant77li',
        'pass' => '8909qpzM',
        'charset' => 'utf8mb4',
    ],

    'jwt_secret' => 'KJr+f91X21KfBSg+6nAKxVGWLtZjCmbJR1gf4NDgF00CeiTcHGhqxir+LPh9AcQX',
    'uon_api_key' => getenv('UON_API_KEY') ?: 'sb8V0iM9BqqZX44gM21u1777975308',
    'tinkoff_terminal_key' => getenv('TINKOFF_TERMINAL_KEY') ?: '1773332116483',
    'tinkoff_password' => getenv('TINKOFF_PASSWORD') ?: 'H7!7mXlXMQmuAsik',
    'app_url' => rtrim(getenv('APP_URL') ?: 'https://travelhub63.ru', '/'),
    'api_url' => rtrim(getenv('API_URL') ?: 'https://travelhub63.ru', '/'),
    /** Access JWT: 30 мин (refresh продлевает сессию без повторного входа). */
    'access_ttl' => 1800,
    'refresh_ttl' => 31536000,

    'tables' => [
        'users' => 'mobile_users',
        'refresh_tokens' => 'mobile_refresh_tokens',
        'password_reset_tokens' => 'mobile_password_reset_tokens',
    ],

    'columns' => [
        'id' => 'id',
        'email' => 'email',
        'password' => 'password',
        'full_name' => 'full_name',
        'phone' => 'phone',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'last_login_at' => 'last_login_at',
        'deleted_at' => 'deleted_at',
        'passport_json' => 'passport_json',
    ],

    'password_algo' => 'bcrypt',
    'site_url' => 'https://travelhub63.ru',
    'send_reset_email' => true,
    'allow_cors' => true,
    'health_check_token' => 'cd4d9b575b4d83189b84e0ca6eb8d94018b14848d0129f9da012e88d2d4e43ad',
    'jwt_issuer' => 'travelhub-auth',
    'allowed_origins' => [
        'https://travelhub63.ru',
    ],

    // ОБЯЗАТЕЛЬНО для /api/hotels/* и /api/tours/* (приложение).
    'tourvisor_token' => getenv('TOURVISOR_TOKEN') ?: 'eyJhbGciOiJFZERTQSIsImtpZCI6IjIwMzNkMzE0LTdlNTktNDAyYS05ZDBmLWZlMjMzYzhiODFkOCIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ0di1hcGktYXV0aCIsInN1YiI6IjM0ODAiLCJhdWQiOlsic2VhcmNoIl0sImV4cCI6MTg1Mzg3MDQwMCwibmJmIjoxNzc2NDM0NDQxLCJpYXQiOjE3NzY0MzQ0NDEsImp0aSI6IjAxOWQ5YmJlLTZlOGEtN2YxMy05NDI4LTRkNDMxM2E2OWZhYSJ9.Q0zL7s8XRyy0Cr15XortLPhfkiou1AMq4EJE00equheDhQpfErRTo-MY9xJdC6HVyAGWVrjQOE6JSM3dd8ZBBA',
    'tourvisor_api_base' => rtrim(
        getenv('TOURVISOR_API_BASE') ?: 'https://api.tourvisor.ru/search/api/v1',
        '/'
    ),
    'cron_token' => getenv('CRON_TOKEN') ?: 'cd4d9b575b4d83189b84e0ca6eb8d94018b14848d0129f9da012e88d2d4e43ad',
];
