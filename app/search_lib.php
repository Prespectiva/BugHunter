<?php

declare(strict_types=1);

/**
 * Поиск, фильтрация, сортировка и пагинация ошибок (ПР №7).
 */

require_once __DIR__ . '/bugs_lib.php';

/** Белый список сортировки: ключ из GET => выражение для ORDER BY (имена столбцов PDO не параметризует). */
const SORT_COLUMNS = [
    'date'     => 'b.created_at',
    'priority' => "CASE b.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END",
    'status'   => 'b.status',
    'id'       => 'b.bug_id',
];

const SORT_TITLES = ['date' => 'По дате создания', 'priority' => 'По приоритету', 'status' => 'По статусу', 'id' => 'По номеру'];

const PER_PAGE = 10;

/** Общая часть запроса: ошибка + проект + автор + исполнитель. */
const BUGS_FROM_SQL = 'FROM bugs b
        JOIN projects p   ON p.project_id = b.project_id
        JOIN users a      ON a.user_id = b.author_id
        LEFT JOIN users s ON s.user_id = b.assignee_id';

/**
 * Безопасный разбор GET-параметров. Некорректные значения не вызывают ошибок:
 * они игнорируются, а в $warnings попадает пояснение для пользователя.
 *
 * @return array{0: array, 1: list<string>}
 */
function parseFilters(array $get): array
{
    $f = [
        'q' => '', 'project' => 0, 'status' => '', 'priority' => '', 'author' => 0, 'assignee' => 0,
        'date_from' => '', 'date_to' => '', 'sort' => 'date', 'dir' => 'desc', 'page' => 1,
    ];
    $warn = [];

    $raw = static function (string $k) use ($get, &$warn): string {
        if (!isset($get[$k])) {
            return '';
        }
        if (!is_string($get[$k])) {
            $warn[] = "Параметр «{$k}» имеет неверный формат и проигнорирован.";
            return '';
        }
        return trim($get[$k]);
    };
    $int = static function (string $k) use ($raw, &$warn): int {
        $v = $raw($k);
        if ($v === '') {
            return 0;
        }
        if (!ctype_digit($v) || strlen($v) > 9) {
            $warn[] = "Параметр «{$k}» должен быть числом — значение проигнорировано.";
            return 0;
        }
        return (int) $v;
    };
    $date = static function (string $k) use ($raw, &$warn): string {
        $v = $raw($k);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $v;
        }
        $warn[] = "Дата «{$k}» должна быть в формате ГГГГ-ММ-ДД — значение проигнорировано.";
        return '';
    };

    $q = $raw('q');
    if (mb_strlen($q) > 100) {
        $q = mb_substr($q, 0, 100);
        $warn[] = 'Поисковый запрос обрезан до 100 символов.';
    }
    $f['q'] = $q;

    $f['project']  = $int('project');
    $f['author']   = $int('author');
    $f['assignee'] = $int('assignee');

    $status = $raw('status');
    if ($status !== '') {
        if (in_array($status, STATUSES, true)) {
            $f['status'] = $status;
        } else {
            $warn[] = 'Неизвестный статус — фильтр проигнорирован.';
        }
    }
    $priority = $raw('priority');
    if ($priority !== '') {
        if (array_key_exists($priority, PRIORITIES)) {
            $f['priority'] = $priority;
        } else {
            $warn[] = 'Неизвестный приоритет — фильтр проигнорирован.';
        }
    }

    $f['date_from'] = $date('date_from');
    $f['date_to']   = $date('date_to');
    if ($f['date_from'] !== '' && $f['date_to'] !== '' && $f['date_from'] > $f['date_to']) {
        [$f['date_from'], $f['date_to']] = [$f['date_to'], $f['date_from']];
    }

    $sort = $raw('sort');
    if ($sort !== '') {
        if (array_key_exists($sort, SORT_COLUMNS)) {
            $f['sort'] = $sort;
        } else {
            $warn[] = 'Неизвестное поле сортировки — использована сортировка по дате.';
        }
    }
    $dir = strtolower($raw('dir'));
    if ($dir === 'asc' || $dir === 'desc') {
        $f['dir'] = $dir;
    } elseif ($dir !== '') {
        $warn[] = 'Неизвестное направление сортировки — использовано «по убыванию».';
    }

    $page = $raw('page');
    if ($page !== '') {
        if (ctype_digit($page) && strlen($page) <= 9 && (int) $page >= 1) {
            $f['page'] = (int) $page;
        } else {
            $warn[] = 'Номер страницы некорректен — показана первая страница.';
        }
    }

    return [$f, $warn];
}

/**
 * Динамическая сборка WHERE: в массив $where попадают только заполненные фильтры,
 * все значения передаются через $params и привязываются в execute()/bindValue().
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function buildWhere(array $f): array
{
    $where = [];
    $params = [];

    if ($f['q'] !== '') {
        // Скобки изолируют OR от AND. Спецсимволы LIKE (% _ \) в запросе экранируются.
        $where[] = "(mb_lower(b.title) LIKE :q ESCAPE '\\'
                  OR mb_lower(b.description) LIKE :q ESCAPE '\\'
                  OR mb_lower(b.steps) LIKE :q ESCAPE '\\'
                  OR mb_lower(a.full_name) LIKE :q ESCAPE '\\')";
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($f['q'], 'UTF-8'));
        $params[':q'] = '%' . $escaped . '%';
    }
    if ($f['project'] > 0) {
        $where[] = 'b.project_id = :pid';
        $params[':pid'] = $f['project'];
    }
    if ($f['status'] !== '') {
        $where[] = 'b.status = :status';
        $params[':status'] = $f['status'];
    }
    if ($f['priority'] !== '') {
        $where[] = 'b.priority = :priority';
        $params[':priority'] = $f['priority'];
    }
    if ($f['author'] > 0) {
        $where[] = 'b.author_id = :author';
        $params[':author'] = $f['author'];
    }
    if ($f['assignee'] > 0) {
        $where[] = 'b.assignee_id = :assignee';
        $params[':assignee'] = $f['assignee'];
    }
    if ($f['date_from'] !== '') {
        $where[] = 'date(b.created_at) >= :dfrom';
        $params[':dfrom'] = $f['date_from'];
    }
    if ($f['date_to'] !== '') {
        $where[] = 'date(b.created_at) <= :dto';
        $params[':dto'] = $f['date_to'];
    }

    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

/** Параметры адресной строки (только непустые) — для ссылок пагинации и экспорта. */
function filterQuery(array $f, array $extra = []): array
{
    $q = [];
    foreach (['q', 'status', 'priority', 'date_from', 'date_to'] as $k) {
        if ($f[$k] !== '') {
            $q[$k] = $f[$k];
        }
    }
    foreach (['project', 'author', 'assignee'] as $k) {
        if ($f[$k] > 0) {
            $q[$k] = $f[$k];
        }
    }
    if ($f['sort'] !== 'date') {
        $q['sort'] = $f['sort'];
    }
    if ($f['dir'] !== 'desc') {
        $q['dir'] = $f['dir'];
    }
    return array_merge($q, $extra);
}

/** Список всех пользователей для фильтра «автор». */
function allUsers(): array
{
    return getPdo()->query('SELECT user_id, full_name FROM users ORDER BY full_name')->fetchAll();
}