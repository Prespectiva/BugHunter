<?php

declare(strict_types=1);

require __DIR__ . '/../app/bugs_lib.php';

$me = requireLogin();
$bug = bugFromRequest();
$id = (int) $bug['bug_id'];
$pdo = getPdo();

// Комментарии с именем автора (JOIN users)
$cStmt = $pdo->prepare(
    'SELECT c.text, c.created_at, u.full_name AS author, u.role
     FROM comments c JOIN users u ON u.user_id = c.user_id
     WHERE c.bug_id = ? ORDER BY c.created_at ASC, c.id ASC'
);
$cStmt->execute([$id]);
$comments = $cStmt->fetchAll();

// История смены статусов с именем автора изменения (JOIN users)
$hStmt = $pdo->prepare(
    'SELECT hs.old_status, hs.new_status, hs.created_at, u.full_name AS author
     FROM bug_history hs JOIN users u ON u.user_id = hs.user_id
     WHERE hs.bug_id = ? ORDER BY hs.created_at ASC, hs.id ASC'
);
$hStmt->execute([$id]);
$history = $hStmt->fetchAll();

// Единая лента: события смены статуса и комментарии по времени
$timeline = [];
foreach ($history as $row) {
    $timeline[] = ['type' => 'status', 'at' => (string) $row['created_at'], 'row' => $row];
}
foreach ($comments as $row) {
    $timeline[] = ['type' => 'comment', 'at' => (string) $row['created_at'], 'row' => $row];
}
usort($timeline, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

function field(string $title, string $text): void
{
    echo '<div class="field"><b>' . h($title) . '</b>' . ($text !== '' ? nl2br(h($text)) : '<span class="muted">—</span>') . '</div>';
}

// Кнопки перехода: только допустимые для текущего статуса и роли
$nextStatuses = allowedNextStatuses($me, $bug['status']);

pageHeader('Ошибка №' . $id);
?>
<p class="links"><a href="bugs.php">← К списку ошибок</a></p>
<?php showMessage(); ?>
<div class="bug-head">
    <h1>BUG #<?= $id ?> · <?= h($bug['title']) ?></h1>
    <div class="muted">Проект: <?= h($bug['project_code'] . ' — ' . $bug['project_name']) ?> · Создана: <?= h($bug['created_at']) ?></div>
</div>
<div class="bug-body">
    <div class="bug-meta">
        <?= priorityBadge($bug['priority']) ?>
        <?= statusBadge($bug['status']) ?>
        <span>Автор: <b><?= h($bug['author_name']) ?></b></span>
        <span>Исполнитель: <b><?= h($bug['assignee_name'] ?? '—') ?></b></span>
    </div>

    <?php
    field('Описание', $bug['description']);
    field('Шаги воспроизведения', $bug['steps']);
    field('Ожидаемый результат', $bug['expected_result']);
    field('Фактический результат', $bug['actual_result']);
    ?>

    <?php if ($nextStatuses !== []): ?>
        <div class="field status-actions">
            <b>Действия со статусом</b>
            <div class="status-buttons">
                <?php foreach ($nextStatuses as $ns): ?>
                    <form method="POST" action="bug_status_change.php">
                        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="bug_id" value="<?= $id ?>">
                        <input type="hidden" name="new_status" value="<?= h($ns) ?>">
                        <button type="submit" class="btn-sm">Перевести в <?= h($ns) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="comments" style="margin-top:20px;">
    <h2 style="margin-top:0">Обсуждение и история (комментариев: <?= count($comments) ?>)</h2>

    <?php if ($timeline === []): ?>
        <p class="muted">Пока нет ни комментариев, ни смен статуса.</p>
    <?php endif; ?>

    <?php foreach ($timeline as $ev): $r = $ev['row']; ?>
        <?php if ($ev['type'] === 'status'): ?>
            <div class="tl-item tl-status">
                <strong>Статус:</strong> <?= h($r['old_status']) ?> → <?= h($r['new_status']) ?>
                <span class="muted">(<?= h($r['author']) ?> · <?= h($r['created_at']) ?>)</span>
            </div>
        <?php else: ?>
            <div class="tl-item tl-comment">
                <div class="tl-meta">
                    <b><?= h($r['author']) ?></b> (<?= h(roleTitle($r['role'])) ?>) · <?= h($r['created_at']) ?>
                </div>
                <div><?= nl2br(h($r['text'])) ?></div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <form class="form" method="POST" action="comment_add.php" style="margin-top:20px;">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="bug_id" value="<?= $id ?>">
        <label for="text">Новый комментарий</label>
        <textarea id="text" name="text" rows="3" maxlength="2000" placeholder="Введите комментарий..." required></textarea>
        <button type="submit">Отправить</button>
    </form>
</div>

<?php pageFooter();
