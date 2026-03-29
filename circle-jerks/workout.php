<?php
declare(strict_types=1);

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('DB_PORT') ?: '3306');
$DB_NAME = getenv('DB_NAME') ?: 'circle_jerks';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');

function parseCsvIds(string $csv): array {
    if (trim($csv) === '') {
        return [];
    }

    $ids = [];
    foreach (explode(',', $csv) as $part) {
        $id = (int)trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

$ids = parseCsvIds((string)($_GET['ids'] ?? ''));
$targetMinutes = (int)($_GET['minutes'] ?? 0);
$experience = (string)($_GET['experience_level'] ?? '');

$workout = [];
$totalMinutes = 0;

if (count($ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $orderField = implode(',', array_map('intval', $ids));

    $sql = "SELECT
                e.id,
                e.exercise_name,
                e.difficulty,
                e.estimated_minutes,
                (
                    SELECT GROUP_CONCAT(DISTINCT mg.muscle_group_name ORDER BY mg.muscle_group_name SEPARATOR ' | ')
                    FROM exercise_muscle_groups emg
                    INNER JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                ) AS muscle_groups
            FROM exercises e
            WHERE e.id IN ($placeholders)
            ORDER BY FIELD(e.id, $orderField)";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $workout[] = $row;
                $totalMinutes += (int)$row['estimated_minutes'];
            }
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workout Session</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #fafafa; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .meta { color: #444; font-size: 0.95rem; }
        .list { margin-top: 1rem; display: grid; gap: 0.6rem; }
        .item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 0.85rem 0.9rem;
            cursor: pointer;
            transition: transform 240ms ease, opacity 240ms ease, background-color 240ms ease, border-color 240ms ease;
            position: relative;
        }
        .item:hover { border-color: #bbb; }
        .item .title {
            font-weight: 700;
            position: relative;
            display: inline-block;
        }
        .item .title::after {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 0;
            height: 2px;
            background: #666;
            transform: translateY(-50%);
            transition: width 260ms ease;
        }
        .item .sub { color: #555; margin-top: 0.2rem; font-size: 0.95rem; }
        .item.done {
            background: #f1f1f1;
            border-color: #d2d2d2;
            opacity: 0.82;
        }
        .item.done .title::after { width: 100%; }
        .item.done .sub { color: #777; }
        .empty {
            margin-top: 1rem;
            border: 1px dashed #ccc;
            border-radius: 10px;
            padding: 1rem;
            background: #fff;
        }
        .hint { color: #666; margin-top: 0.75rem; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>Workout Session</h1>
        <a href="recommend.php">Back to Recommendations</a>
    </div>

    <?php if (count($workout) > 0): ?>
        <div class="meta">
            Exercises: <?php echo count($workout); ?>
            | Total Minutes: <?php echo (int)$totalMinutes; ?>
            <?php if ($targetMinutes > 0): ?>| Target: <?php echo (int)$targetMinutes; ?><?php endif; ?>
            <?php if ($experience !== ''): ?>| Level: <?php echo htmlspecialchars($experience); ?><?php endif; ?>
        </div>

        <div id="workout-list" class="list" data-storage-key="workoutState.<?php echo htmlspecialchars(implode('-', array_map('intval', $ids))); ?>">
            <?php foreach ($workout as $exercise): ?>
                <article class="item" data-id="<?php echo (int)$exercise['id']; ?>" tabindex="0" role="button" aria-pressed="false">
                    <div class="title"><?php echo htmlspecialchars($exercise['exercise_name']); ?></div>
                    <div class="sub">
                        <?php echo htmlspecialchars($exercise['difficulty']); ?>
                        | <?php echo (int)$exercise['estimated_minutes']; ?> min
                        | <?php echo htmlspecialchars((string)($exercise['muscle_groups'] ?? '')); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="hint">Tap an exercise to mark complete. Completed items animate, cross out, and move to the bottom. Tap again to undo.</div>
    <?php else: ?>
        <div class="empty">No workout was provided. Generate recommendations first, then click <strong>Get Started</strong>.</div>
    <?php endif; ?>
</div>

<script>
(function () {
    var list = document.getElementById('workout-list');
    if (!list) return;

    var storageKey = list.getAttribute('data-storage-key') || 'workoutState.default';

    function saveState() {
        var state = [];
        var items = list.querySelectorAll('.item');
        items.forEach(function (item) {
            state.push({ id: item.getAttribute('data-id'), done: item.classList.contains('done') });
        });
        localStorage.setItem(storageKey, JSON.stringify(state));
    }

    function restoreState() {
        var raw = localStorage.getItem(storageKey);
        if (!raw) return;

        var state;
        try {
            state = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!Array.isArray(state)) return;

        var map = {};
        state.forEach(function (entry) {
            if (entry && entry.id) map[String(entry.id)] = !!entry.done;
        });

        var items = Array.prototype.slice.call(list.querySelectorAll('.item'));
        var pending = [];
        var done = [];

        items.forEach(function (item) {
            var isDone = !!map[item.getAttribute('data-id')];
            item.classList.toggle('done', isDone);
            item.setAttribute('aria-pressed', isDone ? 'true' : 'false');
            (isDone ? done : pending).push(item);
        });

        pending.concat(done).forEach(function (item) { list.appendChild(item); });
    }

    function moveItem(item, toDone) {
        item.classList.toggle('done', toDone);
        item.setAttribute('aria-pressed', toDone ? 'true' : 'false');

        window.setTimeout(function () {
            if (toDone) {
                list.appendChild(item);
            } else {
                var firstDone = list.querySelector('.item.done');
                if (firstDone) {
                    list.insertBefore(item, firstDone);
                } else {
                    list.appendChild(item);
                }
            }
            saveState();
        }, 260);
    }

    function onActivate(item) {
        var toDone = !item.classList.contains('done');
        moveItem(item, toDone);
    }

    list.addEventListener('click', function (e) {
        var item = e.target.closest('.item');
        if (!item || !list.contains(item)) return;
        onActivate(item);
    });

    list.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var item = e.target.closest('.item');
        if (!item || !list.contains(item)) return;
        e.preventDefault();
        onActivate(item);
    });

    restoreState();
})();
</script>
</body>
</html>
