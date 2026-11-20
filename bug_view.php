<?php

declare(strict_types=1);

require __DIR__ . '/bugs_lib.php';

$me = requireLogin();
$bug = bugFromRequest();

/** Блок карточки: заголовок и текст с переносами строк. */
function field(string $title, string $text): void
{
    echo '<div class="field"><b>' . h($title) . '</b>' . ($text !== '' ? nl2br(h($text)) : '<span class="muted">—</span>') . '</div>';
}

pageHeader('Ошибка №' . $bug['bug_id']);
?>
<p class="links"><a href="bugs.php">← К списку ошибок</a></p>
<?php showMessage(); ?>
<div class="bug-head">
    <h1>BUG #<?= (int) $bug['bug_id'] ?> · <?= h($bug['title']) ?></h1>
    <div class="muted">Проект: <?= h($bug['project_code'] . ' — ' . $bug['project_name']) ?> &nbsp;|&nbsp;
        Дата: <?= h(date('d.m.Y H:i', strtotime($bug['created_at']))) ?></div>
</div>
<div class="bug-body">
    <div class="bug-meta">
        <?= priorityBadge($bug['priority']) ?>
        <?= statusBadge($bug['status']) ?>
        <span>Автор: <b><?= h($bug['author_name']) ?></b></span>
        <span>· Исполнитель: <b><?= h($bug['assignee_name'] ?? 'не назначен') ?></b></span>
        <?php if (canEditBug($me, $bug)): ?>
            <a class="btn btn-sm" style="margin-left:auto" href="bug_edit.php?id=<?= (int) $bug['bug_id'] ?>">Изменить</a>
        <?php endif; ?>
    </div>
    <?php
    field('Описание', $bug['description']);
    field('Шаги воспроизведения', $bug['steps']);
    field('Ожидаемый результат', $bug['expected_result']);
    field('Фактический результат', $bug['actual_result']);
    ?>
</div>
<?php pageFooter();
