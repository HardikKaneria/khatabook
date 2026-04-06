<?php

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        protected string $code;
        protected string $message;
        protected $data;

        public function __construct(string $code = '', string $message = '', $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        protected $data;
        protected int $status;

        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request implements ArrayAccess
    {
        protected string $method;
        protected string $route;
        protected array $params = [];
        protected array $json_params = [];
        protected array $headers = [];

        public function __construct(string $method = 'GET', string $route = '/')
        {
            $this->method = strtoupper($method);
            $this->route = $route;
        }

        public function set_param(string $key, $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_param(string $key)
        {
            if (array_key_exists($key, $this->params)) {
                return $this->params[$key];
            }

            if (array_key_exists($key, $this->json_params)) {
                return $this->json_params[$key];
            }

            return null;
        }

        public function set_json_params(array $params): void
        {
            $this->json_params = $params;
        }

        public function get_json_params(): array
        {
            return $this->json_params;
        }

        public function set_header(string $key, $value): void
        {
            $this->headers[strtolower($key)] = (string) $value;
        }

        public function get_header(string $key): string
        {
            return $this->headers[strtolower($key)] ?? '';
        }

        #[\ReturnTypeWillChange]
        public function offsetExists($offset): bool
        {
            return array_key_exists((string) $offset, $this->params);
        }

        #[\ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->params[(string) $offset] ?? null;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet($offset, $value): void
        {
            if ($offset === null) {
                return;
            }

            $this->params[(string) $offset] = $value;
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset($offset): void
        {
            unset($this->params[(string) $offset]);
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 0;

        public function __construct($user_id = 0)
        {
            $user = get_userdata((int) $user_id);
            if ($user) {
                $this->ID = (int) $user->ID;
            }
        }

        public function set_role($role): void
        {
            $user = get_userdata($this->ID);
            if (!$user) {
                return;
            }

            $user->roles = [(string) $role];
            $GLOBALS['kbs_test_state']['users'][$this->ID] = $user;
            $email = strtolower((string) ($user->user_email ?? ''));
            if ($email !== '') {
                $GLOBALS['kbs_test_state']['users_by_email'][$email] = $user;
            }
        }
    }
}

final class KbsTestWpdb
{
    public string $prefix = 'wp_';
    public string $usermeta = 'wp_usermeta';
    public string $users = 'wp_users';
    public int $insert_id = 0;

    private array $tables = [];
    private array $auto_ids = [];
    private array $schemas = [];
    private array $transaction_snapshots = [];
    private array $fail_insert_counts = [];

    public function reset(): void
    {
        $this->tables = [];
        $this->auto_ids = [];
        $this->transaction_snapshots = [];
        $this->fail_insert_counts = [];
        $this->schemas = [
            $this->prefix . 'kbs_otp_attempts' => ['id', 'email', 'otp_code', 'status', 'created_at', 'ip', 'context'],
        ];
        $this->insert_id = 0;
    }

    public function seed(string $table, array $rows): void
    {
        $this->tables[$table] = array_values($rows);

        $max = 0;
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $max = max($max, (int) $row['id']);
            }
            if (isset($row['umeta_id'])) {
                $max = max($max, (int) $row['umeta_id']);
            }
        }
        $this->auto_ids[$table] = $max;
    }

    public function table(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    public function fail_next_insert(string $table, int $count = 1): void
    {
        if ($count <= 0) {
            return;
        }

        $this->fail_insert_counts[$table] = ($this->fail_insert_counts[$table] ?? 0) + $count;
    }

    public function prepare($query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $prepared = (string) $query;
        foreach ($args as $arg) {
            $replacement = 'NULL';
            if (is_int($arg)) {
                $replacement = (string) $arg;
            } elseif (is_float($arg)) {
                $replacement = rtrim(rtrim(sprintf('%.6F', $arg), '0'), '.');
            } elseif ($arg !== null) {
                $replacement = "'" . str_replace("'", "\\'", (string) $arg) . "'";
            }

            $prepared = preg_replace('/%d|%f|%s/', $replacement, $prepared, 1) ?? $prepared;
        }

        return $prepared;
    }

    public function esc_like(string $value): string
    {
        return addslashes($value);
    }

    public function get_var(string $query)
    {
        $query = trim($query);

        if (stripos($query, 'information_schema.tables') !== false) {
            return 1;
        }

        if (preg_match('/SHOW COLUMNS FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            return null;
        }

        if (preg_match("/SELECT\\s+COUNT\\(1\\)\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+user_id\\s*=\\s*(\\d+)\\s+AND\\s+org_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $userId = (int) $matches[2];
            $orgId = (int) $matches[3];
            return count(array_filter($this->table($table), static function (array $row) use ($userId, $orgId): bool {
                return (int) ($row['user_id'] ?? 0) === $userId && (int) ($row['org_id'] ?? 0) === $orgId;
            }));
        }

        if (preg_match("/SELECT\\s+user_id\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+meta_key\\s*=\\s*'auth_token'\\s+AND\\s+meta_value\\s*=\\s*'([^']+)'/i", $query, $matches)) {
            $table = $matches[1];
            $token = stripslashes($matches[2]);
            foreach ($this->table($table) as $row) {
                if (($row['meta_key'] ?? null) === 'auth_token' && (string) ($row['meta_value'] ?? '') === $token) {
                    return (int) ($row['user_id'] ?? 0);
                }
            }
            return null;
        }

        if (preg_match("/SELECT\\s+SUM\\(amount\\)\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+invoice_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $invoiceId = (int) $matches[2];
            $sum = 0.0;
            foreach ($this->table($table) as $row) {
                if ((int) ($row['invoice_id'] ?? 0) === $invoiceId) {
                    $sum += (float) ($row['amount'] ?? 0);
                }
            }
            return $sum > 0 ? $sum : null;
        }

        if (preg_match("/SELECT\\s+note_number\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+note_number\\s+LIKE\\s*'([^']+)'/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $like = str_replace('%', '', stripslashes($matches[3]));
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $like): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId
                    && strpos((string) ($row['note_number'] ?? ''), $like) === 0;
            }));
            usort($rows, static function (array $left, array $right): int {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
            return $rows[0]['note_number'] ?? null;
        }

        if (preg_match("/SELECT\\s+org_id\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+user_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $userId = (int) $matches[2];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($userId): bool {
                return (int) ($row['user_id'] ?? 0) === $userId;
            }));
            usort($rows, static function (array $left, array $right): int {
                $leftPrimary = (int) ($left['is_primary'] ?? 0);
                $rightPrimary = (int) ($right['is_primary'] ?? 0);
                if ($leftPrimary !== $rightPrimary) {
                    return $rightPrimary <=> $leftPrimary;
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $rows[0]['org_id'] ?? null;
        }

        if (preg_match("/SELECT\\s+id\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+code\\s*=\\s*'([^']+)'/i", $query, $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $code = stripslashes($matches[3]);
            foreach ($this->table($table) as $row) {
                if ((int) ($row['org_id'] ?? 0) === $orgId && (string) ($row['code'] ?? '') === $code) {
                    return (int) ($row['id'] ?? 0);
                }
            }
            return null;
        }

        if (preg_match("/SELECT\\s+COUNT\\(1\\)\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)(?:\\s+AND\\s+date\\s*>=\\s*'([^']+)')?(?:\\s+AND\\s+date\\s*<=\\s*'([^']+)')?(?:\\s+AND\\s+invoice_id\\s*=\\s*(\\d+))?/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $from = $matches[3] ?? '';
            $to = $matches[4] ?? '';
            $invoiceId = isset($matches[5]) ? (int) $matches[5] : 0;

            return count(array_filter($this->table($table), static function (array $row) use ($orgId, $from, $to, $invoiceId): bool {
                if ((int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }
                $date = (string) ($row['date'] ?? '');
                if ($from !== '' && $date < $from) {
                    return false;
                }
                if ($to !== '' && $date > $to) {
                    return false;
                }
                if ($invoiceId > 0 && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                    return false;
                }
                return true;
            }));
        }

        if (preg_match("/SELECT\\s+org_name\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            foreach ($this->table($table) as $row) {
                if ((int) ($row['org_id'] ?? 0) === $orgId) {
                    return $row['org_name'] ?? null;
                }
            }
            return null;
        }

        return null;
    }

    public function get_row(string $query, $output = OBJECT)
    {
        $query = trim($query);

        if (preg_match('/SHOW COLUMNS FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $table = $matches[1];
            $rows = array_map(static function (string $field): array {
                return ['Field' => $field];
            }, $this->schemas[$table] ?? []);
            $row = $rows[0] ?? null;
            return $this->format_row($row, $output);
        }

        if (preg_match("/SELECT\\s+org_id,\\s*is_primary,\\s*role\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+user_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $userId = (int) $matches[2];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($userId): bool {
                return (int) ($row['user_id'] ?? 0) === $userId;
            }));
            usort($rows, static function (array $left, array $right): int {
                $leftPrimary = (int) ($left['is_primary'] ?? 0);
                $rightPrimary = (int) ($right['is_primary'] ?? 0);
                if ($leftPrimary !== $rightPrimary) {
                    return $rightPrimary <=> $leftPrimary;
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
            return $this->format_row($rows[0] ?? null, $output);
        }

        if (preg_match("/SELECT\\s+(.+)\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+(?:id|account_id)\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[2];
            $orgId = (int) $matches[3];
            $targetId = (int) $matches[4];
            $idField = stripos($query, 'account_id') !== false ? 'account_id' : 'id';
            foreach ($this->table($table) as $row) {
                if ((int) ($row['org_id'] ?? 0) === $orgId && (int) ($row[$idField] ?? 0) === $targetId) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match("/SELECT\\s+account_id\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+journal_id\\s*=\\s*(\\d+)\\s+AND\\s+debit\\s*>\\s*0/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $journalId = (int) $matches[3];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $journalId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId
                    && (int) ($row['journal_id'] ?? 0) === $journalId
                    && (float) ($row['debit'] ?? 0) > 0;
            }));
            usort($rows, static function (array $left, array $right): int {
                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_row($rows[0] ?? null, $output);
        }

        if (preg_match("/SELECT\\s+(.+)\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+category\\s*=\\s*'([^']+)'/i", $query, $matches)) {
            $table = $matches[2];
            $orgId = (int) $matches[3];
            $category = stripslashes($matches[4]);
            foreach ($this->table($table) as $row) {
                if ((int) ($row['org_id'] ?? 0) === $orgId && (string) ($row['category'] ?? '') === $category) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match("/SELECT\\s+\\*\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+LIMIT\\s+1/i", $query, $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            foreach ($this->table($table) as $row) {
                if ((int) ($row['org_id'] ?? 0) === $orgId) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match("/SELECT\\s+\\*\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+source_invoice_id\\s*=\\s*(\\d+)\\s+ORDER\\s+BY\\s+id\\s+DESC\\s+LIMIT\\s+1/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $sourceInvoiceId = (int) $matches[3];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $sourceInvoiceId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId
                    && (int) ($row['source_invoice_id'] ?? 0) === $sourceInvoiceId;
            }));
            usort($rows, static function (array $left, array $right): int {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
            return $this->format_row($rows[0] ?? null, $output);
        }

        return null;
    }

    public function get_results(string $query, $output = OBJECT): array
    {
        $query = trim($query);

        if (preg_match('/SHOW COLUMNS FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $table = $matches[1];
            $rows = array_map(static function (string $field): array {
                return ['Field' => $field];
            }, $this->schemas[$table] ?? []);
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+r\\.org_id,\\s*r\\.is_primary,\\s*r\\.role,\\s*o\\.org_name\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+r\\s+LEFT JOIN\\s+([a-zA-Z0-9_]+)\\s+o\\s+ON\\s+o\\.org_id\\s*=\\s*r\\.org_id\\s+WHERE\\s+r\\.user_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $rolesTable = $matches[1];
            $orgsTable = $matches[2];
            $userId = (int) $matches[3];
            $orgNames = [];
            foreach ($this->table($orgsTable) as $orgRow) {
                $orgNames[(int) ($orgRow['org_id'] ?? 0)] = $orgRow['org_name'] ?? null;
            }

            $rows = [];
            foreach ($this->table($rolesTable) as $row) {
                if ((int) ($row['user_id'] ?? 0) !== $userId) {
                    continue;
                }

                $row['org_name'] = $orgNames[(int) ($row['org_id'] ?? 0)] ?? null;
                $rows[] = $row;
            }

            usort($rows, static function (array $left, array $right): int {
                $leftPrimary = (int) ($left['is_primary'] ?? 0);
                $rightPrimary = (int) ($right['is_primary'] ?? 0);
                if ($leftPrimary !== $rightPrimary) {
                    return $rightPrimary <=> $leftPrimary;
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });

            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+id,\\s*amount,\\s*date,\\s*journal_id\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+invoice_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $invoiceId = (int) $matches[3];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $invoiceId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId && (int) ($row['invoice_id'] ?? 0) === $invoiceId;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['date'] ?? '') !== ($right['date'] ?? '')) {
                    return strcmp((string) $left['date'], (string) $right['date']);
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+id,\\s*invoice_id,\\s*journal_id,\\s*amount,\\s*date,\\s*created_at\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)(?:\\s+AND\\s+date\\s*>=\\s*'([^']+)')?(?:\\s+AND\\s+date\\s*<=\\s*'([^']+)')?(?:\\s+AND\\s+invoice_id\\s*=\\s*(\\d+))?\\s+ORDER\\s+BY\\s+date\\s+DESC,\\s+id\\s+DESC\\s+LIMIT\\s+(\\d+)\\s+OFFSET\\s+(\\d+)/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $from = $matches[3] ?? '';
            $to = $matches[4] ?? '';
            $invoiceId = isset($matches[5]) ? (int) $matches[5] : 0;
            $limit = (int) $matches[6];
            $offset = (int) $matches[7];

            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $from, $to, $invoiceId): bool {
                if ((int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }
                $date = (string) ($row['date'] ?? '');
                if ($from !== '' && $date < $from) {
                    return false;
                }
                if ($to !== '' && $date > $to) {
                    return false;
                }
                if ($invoiceId > 0 && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                    return false;
                }
                return true;
            }));

            usort($rows, static function (array $left, array $right): int {
                if (($left['date'] ?? '') !== ($right['date'] ?? '')) {
                    return strcmp((string) ($right['date'] ?? ''), (string) ($left['date'] ?? ''));
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });

            return $this->format_rows(array_slice($rows, $offset, $limit), $output);
        }

        if (preg_match("/SELECT\\s+description,\\s*quantity,\\s*unit_price,\\s*tax_rate,\\s*tax_amount,\\s*tax_type,\\s*line_total\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+profile_id\\s*=\\s*(\\d+)\\s+ORDER\\s+BY\\s+id\\s+ASC/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $profileId = (int) $matches[3];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $profileId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId && (int) ($row['profile_id'] ?? 0) === $profileId;
            }));
            usort($rows, static function (array $left, array $right): int {
                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+\\*\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+status\\s*=\\s*'ACTIVE'\\s+AND\\s+next_run_date\\s+IS\\s+NOT\\s+NULL\\s+AND\\s+next_run_date\\s*<=\\s*'([^']+)'(?:\\s+AND\\s+org_id\\s*=\\s*(\\d+))?(?:\\s+AND\\s+id\\s*=\\s*(\\d+))?\\s+ORDER\\s+BY\\s+next_run_date\\s+ASC,\\s+id\\s+ASC\\s+LIMIT\\s+25/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $asOf = $matches[2];
            $orgId = isset($matches[3]) ? (int) $matches[3] : 0;
            $profileId = isset($matches[4]) ? (int) $matches[4] : 0;
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($asOf, $orgId, $profileId): bool {
                if (strtoupper((string) ($row['status'] ?? '')) !== 'ACTIVE') {
                    return false;
                }
                $nextRun = (string) ($row['next_run_date'] ?? '');
                if ($nextRun === '' || $nextRun > $asOf) {
                    return false;
                }
                if ($orgId > 0 && (int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }
                if ($profileId > 0 && (int) ($row['id'] ?? 0) !== $profileId) {
                    return false;
                }
                return true;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['next_run_date'] ?? '') !== ($right['next_run_date'] ?? '')) {
                    return strcmp((string) ($left['next_run_date'] ?? ''), (string) ($right['next_run_date'] ?? ''));
                }
                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows(array_slice($rows, 0, 25), $output);
        }

        if (preg_match("/SELECT\\s+id,\\s*invoice_id,\\s*note_number,\\s*note_type,\\s*note_date,\\s*amount,\\s*reason,\\s*status(?:,\\s*created_at)?\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)(?:\\s+AND\\s+invoice_id\\s*=\\s*(\\d+))?\\s+ORDER\\s+BY\\s+note_date\\s+(ASC|DESC),\\s+id\\s+(ASC|DESC)/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $invoiceId = isset($matches[3]) ? (int) $matches[3] : 0;
            $dateDirection = strtoupper($matches[4]);
            $idDirection = strtoupper($matches[5]);
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $invoiceId): bool {
                if ((int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }
                if ($invoiceId > 0 && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                    return false;
                }
                return true;
            }));
            usort($rows, static function (array $left, array $right) use ($dateDirection, $idDirection): int {
                $dateCompare = strcmp((string) ($left['note_date'] ?? ''), (string) ($right['note_date'] ?? ''));
                if ($dateCompare !== 0) {
                    return $dateDirection === 'DESC' ? ($dateCompare * -1) : $dateCompare;
                }
                $idCompare = ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
                return $idDirection === 'DESC' ? ($idCompare * -1) : $idCompare;
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+\\*\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)(?:\\s+AND\\s+invoice_id\\s*=\\s*(\\d+))?(?:\\s+AND\\s+contact_id\\s*=\\s*(\\d+))?(?:\\s+AND\\s+status\\s*=\\s*'([^']+)')?\\s+ORDER\\s+BY\\s+promised_date\\s+ASC,\\s+id\\s+DESC/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $invoiceId = isset($matches[3]) ? (int) $matches[3] : 0;
            $contactId = isset($matches[4]) ? (int) $matches[4] : 0;
            $status = isset($matches[5]) ? stripslashes($matches[5]) : '';
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $invoiceId, $contactId, $status): bool {
                if ((int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }
                if ($invoiceId > 0 && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                    return false;
                }
                if ($contactId > 0 && (int) ($row['contact_id'] ?? 0) !== $contactId) {
                    return false;
                }
                if ($status !== '' && (string) ($row['status'] ?? '') !== $status) {
                    return false;
                }
                return true;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['promised_date'] ?? '') !== ($right['promised_date'] ?? '')) {
                    return strcmp((string) ($left['promised_date'] ?? ''), (string) ($right['promised_date'] ?? ''));
                }
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+description,\\s*quantity,\\s*unit_price,\\s*tax_rate,\\s*tax_amount,\\s*tax_type,\\s*line_total\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+invoice_id\\s*=\\s*(\\d+)/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $invoiceId = (int) $matches[3];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $invoiceId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId && (int) ($row['invoice_id'] ?? 0) === $invoiceId;
            }));
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+id,\\s*contact_id,\\s*(?:invoice_number,\\s*)?customer_name,\\s*customer_email,\\s*date,\\s*due_date,\\s*total,\\s*status\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+ORDER\\s+BY\\s+date\\s+ASC,\\s+id\\s+ASC/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['date'] ?? '') !== ($right['date'] ?? '')) {
                    return strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+(?:id,\\s*)?invoice_id,\\s*amount,\\s*date\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+ORDER\\s+BY\\s+date\\s+ASC,\\s+id\\s+ASC/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['date'] ?? '') !== ($right['date'] ?? '')) {
                    return strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+expense_date,\\s*amount,\\s*status\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+ORDER\\s+BY\\s+expense_date\\s+ASC,\\s+id\\s+ASC/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId): bool {
                return (int) ($row['org_id'] ?? 0) === $orgId;
            }));
            usort($rows, static function (array $left, array $right): int {
                if (($left['expense_date'] ?? '') !== ($right['expense_date'] ?? '')) {
                    return strcmp((string) ($left['expense_date'] ?? ''), (string) ($right['expense_date'] ?? ''));
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT\\s+id,\\s*record_type,\\s*record_id,\\s*related_record_type,\\s*related_record_id,\\s*action,\\s*summary,\\s*details_json,\\s*actor_user_id,\\s*actor_label,\\s*created_at\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+WHERE\\s+org_id\\s*=\\s*(\\d+)\\s+AND\\s+\\(\\s*\\(record_type\\s*=\\s*'([^']+)'\\s+AND\\s+record_id\\s*=\\s*(\\d+)\\)\\s+OR\\s+\\(related_record_type\\s*=\\s*'([^']+)'\\s+AND\\s+related_record_id\\s*=\\s*(\\d+)\\)\\s*\\)\\s+ORDER\\s+BY\\s+created_at\\s+DESC,\\s+id\\s+DESC\\s+LIMIT\\s+(\\d+)/i", preg_replace('/\s+/', ' ', $query), $matches)) {
            $table = $matches[1];
            $orgId = (int) $matches[2];
            $recordType = stripslashes($matches[3]);
            $recordId = (int) $matches[4];
            $relatedType = stripslashes($matches[5]);
            $relatedId = (int) $matches[6];
            $limit = (int) $matches[7];

            $rows = array_values(array_filter($this->table($table), static function (array $row) use ($orgId, $recordType, $recordId, $relatedType, $relatedId): bool {
                if ((int) ($row['org_id'] ?? 0) !== $orgId) {
                    return false;
                }

                $isDirect = (string) ($row['record_type'] ?? '') === $recordType && (int) ($row['record_id'] ?? 0) === $recordId;
                $isRelated = (string) ($row['related_record_type'] ?? '') === $relatedType && (int) ($row['related_record_id'] ?? 0) === $relatedId;

                return $isDirect || $isRelated;
            }));

            usort($rows, static function (array $left, array $right): int {
                if (($left['created_at'] ?? '') !== ($right['created_at'] ?? '')) {
                    return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });

            return $this->format_rows(array_slice($rows, 0, $limit), $output);
        }

        return [];
    }

    public function get_col(string $query): array
    {
        $query = trim($query);

        if (preg_match("/SELECT\\s+DISTINCT\\s+u\\.user_email\\s+FROM\\s+([a-zA-Z0-9_]+)\\s+r\\s+INNER JOIN\\s+([a-zA-Z0-9_]+)\\s+u\\s+ON\\s+u\\.ID\\s*=\\s*r\\.user_id\\s+WHERE\\s+r\\.org_id\\s*=\\s*(\\d+)/i", $query, $matches)) {
            $rolesTable = $matches[1];
            $usersTable = $matches[2];
            $orgId = (int) $matches[3];

            preg_match_all("/'([^']+)'/", $query, $roleMatches);
            $roles = array_values(array_filter(array_map('stripslashes', $roleMatches[1] ?? []), static function (string $value): bool {
                return $value !== '';
            }));

            $userEmails = [];
            foreach ($this->table($usersTable) as $userRow) {
                $userEmails[(int) ($userRow['ID'] ?? 0)] = $userRow['user_email'] ?? '';
            }

            $emails = [];
            foreach ($this->table($rolesTable) as $row) {
                $role = (string) ($row['role'] ?? '');
                if ((int) ($row['org_id'] ?? 0) !== $orgId || ($roles && !in_array($role, $roles, true))) {
                    continue;
                }

                $email = $userEmails[(int) ($row['user_id'] ?? 0)] ?? '';
                if ($email !== '') {
                    $emails[$email] = $email;
                }
            }

            return array_values($emails);
        }

        return [];
    }

    public function insert(string $table, array $data, array $formats = []): bool
    {
        if (($this->fail_insert_counts[$table] ?? 0) > 0) {
            $this->fail_insert_counts[$table]--;
            $this->insert_id = 0;
            return false;
        }

        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        if (!isset($data['id']) && !isset($data['umeta_id']) && !isset($data['org_id'])) {
            $this->auto_ids[$table] = ($this->auto_ids[$table] ?? 0) + 1;
            $data['id'] = $this->auto_ids[$table];
            $this->insert_id = $data['id'];
        } elseif (!isset($data['id']) && !isset($data['umeta_id']) && preg_match('/(?:vy_|kbs_)(?:contacts|invoices|invoice_items|invoice_payments|invoice_recurring_profiles|invoice_recurring_items|invoice_notes|invoice_promises|expenses|accounts|bank_accounts|journal_entries|journal_lines|record_history|user_org_roles|settings|otp_attempts)$/', $table)) {
            $this->auto_ids[$table] = ($this->auto_ids[$table] ?? 0) + 1;
            $data['id'] = $this->auto_ids[$table];
            $this->insert_id = $data['id'];
        } else {
            $this->insert_id = (int) ($data['id'] ?? $data['umeta_id'] ?? 0);
        }

        $this->tables[$table][] = $data;
        return true;
    }

    public function update(string $table, array $data, array $where, array $formats = [], array $where_formats = [])
    {
        $updated = 0;
        foreach ($this->tables[$table] ?? [] as $index => $row) {
            if (!$this->row_matches($row, $where)) {
                continue;
            }

            $this->tables[$table][$index] = array_merge($row, $data);
            $updated++;
        }

        return $updated;
    }

    public function delete(string $table, array $where, array $where_formats = [])
    {
        $deleted = 0;
        $remaining = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($this->row_matches($row, $where)) {
                $deleted++;
                continue;
            }

            $remaining[] = $row;
        }

        $this->tables[$table] = $remaining;
        return $deleted;
    }

    public function query(string $query)
    {
        $trimmed = trim($query);
        $upper = strtoupper($trimmed);

        if ($upper === 'START TRANSACTION') {
            $this->transaction_snapshots[] = [
                'tables' => $this->tables,
                'auto_ids' => $this->auto_ids,
                'insert_id' => $this->insert_id,
            ];
            return true;
        }

        if ($upper === 'ROLLBACK') {
            $snapshot = array_pop($this->transaction_snapshots);
            if (is_array($snapshot)) {
                $this->tables = $snapshot['tables'];
                $this->auto_ids = $snapshot['auto_ids'];
                $this->insert_id = (int) ($snapshot['insert_id'] ?? 0);
            }
            return true;
        }

        if ($upper === 'COMMIT') {
            array_pop($this->transaction_snapshots);
            return true;
        }

        if (preg_match("/UPDATE\\s+([a-zA-Z0-9_]+)\\s+SET\\s+status\\s*=\\s*'([^']+)',\\s*otp_code\\s*=\\s*'([^']+)'\\s+WHERE\\s+email\\s*=\\s*'([^']+)'\\s+AND\\s+status\\s*=\\s*'([^']+)'(?:\\s+AND\\s+context\\s*=\\s*'([^']+)')?/i", $trimmed, $matches)) {
            $table = $matches[1];
            $newStatus = stripslashes($matches[2]);
            $newOtp = stripslashes($matches[3]);
            $email = stripslashes($matches[4]);
            $oldStatus = stripslashes($matches[5]);
            $context = isset($matches[6]) ? stripslashes($matches[6]) : null;

            $latestIndex = null;
            foreach ($this->tables[$table] ?? [] as $index => $row) {
                if ((string) ($row['email'] ?? '') !== $email || (string) ($row['status'] ?? '') !== $oldStatus) {
                    continue;
                }
                if ($context !== null && (string) ($row['context'] ?? '') !== $context) {
                    continue;
                }
                $latestIndex = $index;
            }

            if ($latestIndex !== null) {
                $this->tables[$table][$latestIndex]['status'] = $newStatus;
                $this->tables[$table][$latestIndex]['otp_code'] = $newOtp;
            }
        }

        return true;
    }

    private function row_matches(array $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            if (($row[$key] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }

    private function format_row(?array $row, $output)
    {
        if ($row === null) {
            return null;
        }

        return $output === ARRAY_A ? $row : (object) $row;
    }

    private function format_rows(array $rows, $output): array
    {
        return array_map(function (array $row) use ($output) {
            return $this->format_row($row, $output);
        }, $rows);
    }
}

function kbs_test_reset_env(): void
{
    $GLOBALS['kbs_test_state'] = [
        'users' => [],
        'users_by_email' => [],
        'user_meta' => [],
        'transients' => [],
        'current_user_id' => 0,
        'current_time_mysql' => '2026-04-03 10:00:00',
        'rest_root' => 'https://example.test/wp-json/',
        'rest_nonce' => 'rest-nonce',
    ];

    if (!isset($GLOBALS['wpdb']) || !($GLOBALS['wpdb'] instanceof KbsTestWpdb)) {
        $GLOBALS['wpdb'] = new KbsTestWpdb();
    }

    $GLOBALS['wpdb']->reset();
    $GLOBALS['wpdb']->seed($GLOBALS['wpdb']->users, []);
    $GLOBALS['wpdb']->seed($GLOBALS['wpdb']->usermeta, []);

    if (class_exists('\KBS\Helpers\OrgHelper')) {
        $reflection = new ReflectionClass('\KBS\Helpers\OrgHelper');
        if ($reflection->hasProperty('resolved_org_id')) {
            $property = $reflection->getProperty('resolved_org_id');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }
}

function kbs_test_add_user(array $user): void
{
    $id = (int) ($user['ID'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Test users require a positive ID.');
    }

    $defaults = [
        'ID' => $id,
        'user_login' => 'user_' . $id,
        'user_email' => '',
        'display_name' => '',
        'roles' => [],
    ];
    $record = array_merge($defaults, $user);
    $object = (object) $record;

    $GLOBALS['kbs_test_state']['users'][$id] = $object;
    $GLOBALS['kbs_test_state']['users_by_email'][strtolower((string) $record['user_email'])] = $object;

    $users = array_values(array_map(static function ($row): array {
        return [
            'ID' => (int) $row->ID,
            'user_email' => (string) $row->user_email,
            'display_name' => (string) $row->display_name,
        ];
    }, $GLOBALS['kbs_test_state']['users']));
    $GLOBALS['wpdb']->seed($GLOBALS['wpdb']->users, $users);
}

function kbs_test_seed_table(string $table, array $rows): void
{
    $GLOBALS['wpdb']->seed($table, $rows);
}

function kbs_test_get_table(string $table): array
{
    return $GLOBALS['wpdb']->table($table);
}

function kbs_test_fail_next_insert(string $table, int $count = 1): void
{
    $GLOBALS['wpdb']->fail_next_insert($table, $count);
}

function kbs_test_set_user_meta(int $user_id, string $meta_key, $value): void
{
    update_user_meta($user_id, $meta_key, $value);
}

function kbs_test_set_current_user(int $user_id): void
{
    wp_set_current_user($user_id);
}

function kbs_test_seed_org_membership(int $user_id, int $org_id, string $role = 'company_admin', bool $is_primary = true, string $org_name = 'Demo Org'): void
{
    $rolesTable = $GLOBALS['wpdb']->prefix . 'kbs_user_org_roles';
    $orgsTable = $GLOBALS['wpdb']->prefix . 'kbs_organizations';

    $roles = kbs_test_get_table($rolesTable);
    $roles[] = [
        'id' => count($roles) + 1,
        'user_id' => $user_id,
        'org_id' => $org_id,
        'role' => $role,
        'is_primary' => $is_primary ? 1 : 0,
    ];
    kbs_test_seed_table($rolesTable, $roles);

    $orgs = array_values(array_filter(kbs_test_get_table($orgsTable), static function (array $row) use ($org_id): bool {
        return (int) ($row['org_id'] ?? 0) !== $org_id;
    }));
    $orgs[] = [
        'org_id' => $org_id,
        'org_name' => $org_name,
        'is_gst_registered' => 1,
        'default_income_tax_rate' => 25,
    ];
    kbs_test_seed_table($orgsTable, $orgs);
}

function kbs_test_make_request(string $method, string $route, array $params = [], array $json = [], array $headers = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }
    if ($json) {
        $request->set_json_params($json);
    }
    foreach ($headers as $key => $value) {
        $request->set_header((string) $key, $value);
    }
    return $request;
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false)
    {
        if ($type === 'mysql') {
            return $GLOBALS['kbs_test_state']['current_time_mysql'];
        }

        return time();
    }
}

if (!function_exists('rest_url')) {
    function rest_url(): string
    {
        return $GLOBALS['kbs_test_state']['rest_root'];
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string
    {
        return $GLOBALS['kbs_test_state']['rest_nonce'];
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value)
    {
        return $value;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value): string
    {
        return json_encode($value);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value)
    {
        return trim((string) $value);
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false)
    {
        $username = strtolower((string) $username);
        $username = preg_replace('/[^a-z0-9_\-]/', '', $username) ?? '';
        return trim($username);
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        if ($field !== 'email') {
            return false;
        }

        return $GLOBALS['kbs_test_state']['users_by_email'][strtolower((string) $value)] ?? false;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return $GLOBALS['kbs_test_state']['users'][(int) $user_id] ?? false;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email): bool
    {
        return isset($GLOBALS['kbs_test_state']['users_by_email'][strtolower((string) $email)]);
    }
}

if (!function_exists('username_exists')) {
    function username_exists($username): bool
    {
        $username = (string) $username;
        foreach ($GLOBALS['kbs_test_state']['users'] as $user) {
            if ((string) ($user->user_login ?? '') === $username) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['kbs_test_state']['current_user_id'] ?? 0);
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($user_id): void
    {
        $GLOBALS['kbs_test_state']['current_user_id'] = (int) $user_id;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return get_current_user_id() > 0;
    }
}

if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie($user_id, $remember = false): void
    {
    }
}

if (!function_exists('wp_destroy_current_session')) {
    function wp_destroy_current_session(): void
    {
    }
}

if (!function_exists('wp_clear_auth_cookie')) {
    function wp_clear_auth_cookie(): void
    {
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = []): bool
    {
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'Vyavhar';
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false): string
    {
        return str_repeat('a', max(1, (int) $length));
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user($userdata)
    {
        $nextId = count($GLOBALS['kbs_test_state']['users']) + 1000;
        kbs_test_add_user([
            'ID' => $nextId,
            'user_login' => (string) ($userdata['user_login'] ?? ('user_' . $nextId)),
            'user_email' => (string) ($userdata['user_email'] ?? ''),
            'display_name' => (string) ($userdata['display_name'] ?? ''),
            'roles' => [(string) ($userdata['role'] ?? 'c_employee')],
        ]);

        return $nextId;
    }
}

if (!function_exists('kbs_render_email_body')) {
    function kbs_render_email_body(string $message, array $args = []): string
    {
        return $message;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        $user = get_userdata(get_current_user_id());
        return $user ?: (object) ['ID' => 0, 'display_name' => '', 'user_email' => ''];
    }
}

if (!function_exists('user_can')) {
    function user_can($user_id, $capability): bool
    {
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return false;
        }

        $roles = is_array($user->roles ?? null) ? $user->roles : [];
        if ($capability === 'manage_options' || $capability === 'administrator') {
            return in_array('administrator', $roles, true);
        }

        return in_array($capability, $roles, true);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        return user_can(get_current_user_id(), $capability);
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false)
    {
        $userId = (int) $user_id;
        $meta = $GLOBALS['kbs_test_state']['user_meta'][$userId] ?? [];

        if ($key === '') {
            return $meta;
        }

        if (!array_key_exists($key, $meta)) {
            return $single ? '' : [];
        }

        return $single ? $meta[$key] : [$meta[$key]];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value): bool
    {
        $userId = (int) $user_id;
        $metaKey = (string) $meta_key;
        $GLOBALS['kbs_test_state']['user_meta'][$userId][$metaKey] = $meta_value;

        $table = $GLOBALS['wpdb']->usermeta;
        $rows = array_values(array_filter(kbs_test_get_table($table), static function (array $row) use ($userId, $metaKey): bool {
            return !((int) ($row['user_id'] ?? 0) === $userId && (string) ($row['meta_key'] ?? '') === $metaKey);
        }));
        $rows[] = [
            'user_id' => $userId,
            'meta_key' => $metaKey,
            'meta_value' => $meta_value,
        ];
        $GLOBALS['wpdb']->seed($table, $rows);

        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key): bool
    {
        $userId = (int) $user_id;
        $metaKey = (string) $meta_key;
        unset($GLOBALS['kbs_test_state']['user_meta'][$userId][$metaKey]);

        $table = $GLOBALS['wpdb']->usermeta;
        $rows = array_values(array_filter(kbs_test_get_table($table), static function (array $row) use ($userId, $metaKey): bool {
            return !((int) ($row['user_id'] ?? 0) === $userId && (string) ($row['meta_key'] ?? '') === $metaKey);
        }));
        $GLOBALS['wpdb']->seed($table, $rows);

        return true;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0): bool
    {
        $GLOBALS['kbs_test_state']['transients'][(string) $key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key)
    {
        return $GLOBALS['kbs_test_state']['transients'][(string) $key] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key): bool
    {
        unset($GLOBALS['kbs_test_state']['transients'][(string) $key]);
        return true;
    }
}

kbs_test_reset_env();
