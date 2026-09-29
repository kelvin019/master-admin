<?php
declare(strict_types=1);

function mail_client_ensure_tables(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS cms_sent_mail (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(190) NOT NULL,
        to_name VARCHAR(190) NOT NULL DEFAULT "",
        cc TEXT NULL, bcc TEXT NULL,
        subject VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        in_reply_to BIGINT NULL,
        status ENUM("sent","failed") NOT NULL,
        error_message VARCHAR(1000) NULL,
        sent_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sent_created(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function mail_contact_messages(string $filter = 'all'): array {
    $where = $filter === 'unread' ? ' AND status="unread"' : '';
    $rows = db()->query('SELECT id,title,status,data,created_at FROM cms_content WHERE module_key="messages"'.$where.' ORDER BY created_at DESC')->fetchAll();
    return array_map(static function(array $row): array {
        $data = json_decode((string)$row['data'], true) ?: [];
        return ['id'=>(int)$row['id'], 'name'=>(string)($data['name'] ?: $row['title'] ?: 'Website visitor'), 'email'=>(string)($data['email'] ?? ''), 'phone'=>(string)($data['phone'] ?? ''), 'subject'=>(string)($data['subject'] ?: 'Website enquiry'), 'message'=>(string)($data['message'] ?? ''), 'status'=>(string)$row['status'], 'created_at'=>(string)$row['created_at']];
    }, $rows);
}

function mail_contact_find(int $id): ?array {
    $statement = db()->prepare('SELECT id,title,status,data,created_at FROM cms_content WHERE id=? AND module_key="messages"');
    $statement->execute([$id]); $row = $statement->fetch();
    if (!$row) return null;
    $data = json_decode((string)$row['data'], true) ?: [];
    return ['id'=>(int)$row['id'], 'name'=>(string)($data['name'] ?: $row['title'] ?: 'Website visitor'), 'email'=>(string)($data['email'] ?? ''), 'phone'=>(string)($data['phone'] ?? ''), 'subject'=>(string)($data['subject'] ?: 'Website enquiry'), 'message'=>(string)($data['message'] ?? ''), 'status'=>(string)$row['status'], 'created_at'=>(string)$row['created_at']];
}

function mail_mark_contact_read(int $id, bool $replied = false): void {
    $status = $replied ? 'replied' : 'read';
    db()->prepare('UPDATE cms_content SET status=? WHERE id=? AND module_key="messages"')->execute([$status, $id]);
}

function mail_transport_config(): array {
    return [
        'enabled' => (bool)db_setting('mail.smtp_enabled', cfg('mail.smtp_enabled', false)),
        'host' => (string)db_setting('mail.smtp_host', cfg('mail.smtp_host', '')),
        'port' => (int)db_setting('mail.smtp_port', cfg('mail.smtp_port', 587)),
        'username' => (string)db_setting('mail.smtp_username', cfg('mail.smtp_username', '')),
        'password' => (string)db_setting('mail.smtp_password', cfg('mail.smtp_password', '')),
        'encryption' => (string)db_setting('mail.smtp_encryption', cfg('mail.smtp_encryption', 'tls')),
        'from_email' => (string)db_setting('mail.from_email', cfg('mail.from_email', 'no-reply@localhost')),
        'from_name' => (string)db_setting('mail.from_name', cfg('mail.from_name', cfg('site.name', 'Master CMS'))),
    ];
}

function mail_send_message(array $message): array {
    mail_client_ensure_tables();
    $to = trim((string)($message['to'] ?? ''));
    $subject = trim((string)($message['subject'] ?? ''));
    $body = trim((string)($message['body'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid recipient email address.');
    if ($subject === '') throw new RuntimeException('Enter a subject.');
    if ($body === '') throw new RuntimeException('Write a message before sending.');
    $transport = mail_transport_config();
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#17233d">'.nl2br(e($body)).'</body></html>';
    [$sent, $error] = $transport['enabled'] && $transport['host'] !== ''
        ? mail_send_via_smtp($transport, $to, trim((string)($message['to_name'] ?? '')), $subject, $html, trim((string)($message['cc'] ?? '')), trim((string)($message['bcc'] ?? '')))
        : mail_send_via_native($transport, $to, $subject, $html, trim((string)($message['cc'] ?? '')), trim((string)($message['bcc'] ?? '')));
    db()->prepare('INSERT INTO cms_sent_mail(to_email,to_name,cc,bcc,subject,body,in_reply_to,status,error_message,sent_by) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$to, trim((string)($message['to_name'] ?? '')), trim((string)($message['cc'] ?? '')), trim((string)($message['bcc'] ?? '')), $subject, $body, (int)($message['in_reply_to'] ?? 0) ?: null, $sent ? 'sent' : 'failed', $error, current_user()['id'] ?? null]);
    if (!$sent) throw new RuntimeException($error);
    return ['ok'=>true];
}

function mail_send_via_smtp(array $transport, string $to, string $toName, string $subject, string $html, string $cc, string $bcc): array {
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) return [false, 'PHPMailer is not installed. Run "composer install" in the admin root.'];
    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = $transport['host'];
        $mailer->Port = $transport['port'];
        $mailer->SMTPAuth = $transport['username'] !== '';
        if ($mailer->SMTPAuth) { $mailer->Username = $transport['username']; $mailer->Password = $transport['password']; }
        $mailer->SMTPAutoTLS = $transport['encryption'] !== '';
        if ($transport['encryption'] === 'ssl') $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($transport['encryption'] === 'tls') $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else $mailer->SMTPAutoTLS = false;
        $mailer->setFrom($transport['from_email'], $transport['from_name']);
        $mailer->addReplyTo($transport['from_email'], $transport['from_name']);
        $mailer->addAddress($to, $toName);
        foreach (array_filter(array_map('trim', explode(',', $cc))) as $address) $mailer->addCC($address);
        foreach (array_filter(array_map('trim', explode(',', $bcc))) as $address) $mailer->addBCC($address);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = trim(strip_tags($html));
        $mailer->send();
        return [true, null];
    } catch (\PHPMailer\PHPMailer\Exception) {
        return [false, $mailer->ErrorInfo ?: 'The SMTP server rejected the message.'];
    }
}

function mail_send_via_native(array $transport, string $to, string $subject, string $html, string $cc, string $bcc): array {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: '.str_replace(["\r", "\n"], '', $transport['from_name']).' <'.str_replace(["\r", "\n"], '', $transport['from_email']).'>',
        'Reply-To: '.str_replace(["\r", "\n"], '', $transport['from_email']),
    ];
    if ($cc !== '') $headers[] = 'Cc: '.str_replace(["\r", "\n"], '', $cc);
    if ($bcc !== '') $headers[] = 'Bcc: '.str_replace(["\r", "\n"], '', $bcc);
    $sent = @mail($to, $subject, $html, implode("\r\n", $headers));
    return [$sent, $sent ? null : 'PHP mail() did not accept the message. Configure SMTP in Settings for reliable delivery.'];
}
