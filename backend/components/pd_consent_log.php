<?php
/**
 * Журнал доказательств согласий (152-ФЗ ст. 9): кто, когда, какая форма, какая редакция.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/legal.php';

if (!function_exists('th_pd_consent_ensure_table')) {
    function th_pd_consent_ensure_table(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS pd_consents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                subject_name TEXT,
                subject_phone TEXT,
                subject_email TEXT,
                user_id INTEGER,
                source_form TEXT NOT NULL,
                source_page TEXT,
                funnel_source TEXT,
                pd_accepted INTEGER NOT NULL DEFAULT 1,
                ads_accepted INTEGER NOT NULL DEFAULT 0,
                terms_accepted INTEGER NOT NULL DEFAULT 0,
                consent_version TEXT NOT NULL,
                pd_text TEXT NOT NULL,
                ads_text TEXT,
                advertiser TEXT NOT NULL,
                ip TEXT,
                user_agent TEXT
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS pd_consents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL,
                subject_name VARCHAR(120) NULL,
                subject_phone VARCHAR(32) NULL,
                subject_email VARCHAR(255) NULL,
                user_id INT NULL,
                source_form VARCHAR(80) NOT NULL,
                source_page VARCHAR(512) NULL,
                funnel_source VARCHAR(80) NULL,
                pd_accepted TINYINT(1) NOT NULL DEFAULT 1,
                ads_accepted TINYINT(1) NOT NULL DEFAULT 0,
                terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
                consent_version VARCHAR(32) NOT NULL,
                pd_text TEXT NOT NULL,
                ads_text TEXT NULL,
                advertiser VARCHAR(255) NOT NULL,
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $done = true;
    }
}

if (!function_exists('th_pd_consent_append_file')) {
    function th_pd_consent_append_file(array $row): void
    {
        $root = defined('TH_PROJECT_ROOT') ? (string) TH_PROJECT_ROOT : dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'pd_consents.jsonl', $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('th_pd_consent_record')) {
    /**
     * @param array<string, mixed> $row
     */
    function th_pd_consent_record(array $row): void
    {
        $op = th_legal_operator();
        $adsAccepted = !empty($row['ads_accepted']) ? 1 : 0;
        $payload = [
            'created_at' => date('Y-m-d H:i:s'),
            'subject_name' => mb_substr(trim((string) ($row['subject_name'] ?? '')), 0, 120),
            'subject_phone' => mb_substr(trim((string) ($row['subject_phone'] ?? '')), 0, 32),
            'subject_email' => mb_substr(trim((string) ($row['subject_email'] ?? '')), 0, 255),
            'user_id' => isset($row['user_id']) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null,
            'source_form' => mb_substr(trim((string) ($row['source_form'] ?? 'site')), 0, 80) ?: 'site',
            'source_page' => mb_substr(trim((string) ($row['source_page'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))), 0, 512),
            'funnel_source' => mb_substr(trim((string) ($row['funnel_source'] ?? '')), 0, 80),
            'pd_accepted' => 1,
            'ads_accepted' => $adsAccepted,
            'terms_accepted' => !empty($row['terms_accepted']) ? 1 : 0,
            'consent_version' => $op['consent_version'],
            'pd_text' => th_pd_consent_plain_text(),
            'ads_text' => $adsAccepted ? th_ad_consent_plain_text() : '',
            'advertiser' => $op['advertiser_short'],
            'ip' => mb_substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 64),
            'user_agent' => mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
        ];

        global $pdo;
        try {
            if ($pdo instanceof PDO) {
                th_pd_consent_ensure_table($pdo);
                $stmt = $pdo->prepare(
                    'INSERT INTO pd_consents (
                        created_at, subject_name, subject_phone, subject_email, user_id,
                        source_form, source_page, funnel_source,
                        pd_accepted, ads_accepted, terms_accepted,
                        consent_version, pd_text, ads_text, advertiser, ip, user_agent
                    ) VALUES (
                        :created_at, :subject_name, :subject_phone, :subject_email, :user_id,
                        :source_form, :source_page, :funnel_source,
                        :pd_accepted, :ads_accepted, :terms_accepted,
                        :consent_version, :pd_text, :ads_text, :advertiser, :ip, :user_agent
                    )'
                );
                $stmt->execute($payload);
                return;
            }
        } catch (Throwable $e) {
            error_log('[pd_consents] DB write failed: ' . $e->getMessage());
        }
        th_pd_consent_append_file($payload);
    }
}

if (!function_exists('th_pd_consent_crm_note')) {
    function th_pd_consent_crm_note(bool $adsAccepted, bool $termsAccepted = false): string
    {
        $op = th_legal_operator();
        $lines = [
            'Согласие на ПД: да, редакция ' . $op['consent_version'] . ' (' . $op['doc_date'] . ')',
            'Реклама: ' . ($adsAccepted ? 'да' : 'нет') . ', рекламораспространитель: ' . $op['advertiser_short'],
        ];
        if ($termsAccepted) {
            $lines[] = 'Пользовательское соглашение: принято, редакция от ' . $op['doc_date'];
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('th_pd_consent_capture_from_request')) {
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $subject name, phone, email, source_form, funnel_source, user_id
     */
    function th_pd_consent_capture_from_request(array $input, array $subject): void
    {
        $ads = function_exists('th_lead_is_agree_accepted')
            ? th_lead_is_agree_accepted($input['agree_ads'] ?? null)
            : false;
        $terms = function_exists('th_lead_is_agree_accepted')
            ? th_lead_is_agree_accepted($input['agree_terms'] ?? null)
            : false;
        th_pd_consent_record([
            'subject_name' => (string) ($subject['name'] ?? ''),
            'subject_phone' => (string) ($subject['phone'] ?? ''),
            'subject_email' => (string) ($subject['email'] ?? ''),
            'user_id' => $subject['user_id'] ?? null,
            'source_form' => (string) ($subject['source_form'] ?? 'site'),
            'funnel_source' => (string) ($subject['funnel_source'] ?? ''),
            'source_page' => (string) ($subject['source_page'] ?? ''),
            'ads_accepted' => $ads ? 1 : 0,
            'terms_accepted' => $terms ? 1 : 0,
        ]);
    }
}
