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

$message = '';
$error = '';

function post(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

function postIntArray(string $key): array {
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function parseNames(string $input): array {
    $parts = preg_split('/[\r\n,]+/', $input) ?: [];
    $items = [];
    foreach ($parts as $part) {
        $item = trim($part);
        if ($item !== '') {
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'bulk_delete_muscle_groups') {
        $ids = postIntArray('selected_muscle_group_ids');
        if (count($ids) === 0) {
            $error = 'Select at least one muscle group to delete.';
        } else {
            $deleted = 0;
            $failed = 0;
            $stmt = $mysqli->prepare('DELETE FROM muscle_groups WHERE id = ?');
            foreach ($ids as $id) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
            $stmt->close();
            $message = 'Deleted ' . $deleted . ' muscle group(s).';
            if ($failed > 0) {
                $error = $failed . ' muscle group(s) could not be deleted (likely in use).';
            }
        }
    }

    if ($action === 'bulk_delete_eligible_days') {
        $ids = postIntArray('selected_eligible_day_ids');
        if (count($ids) === 0) {
            $error = 'Select at least one eligible day to delete.';
        } else {
            $deleted = 0;
            $failed = 0;
            $detached = 0;
            $detachStmt = $mysqli->prepare('DELETE FROM exercise_eligible_days WHERE eligible_day_id = ?');
            $deleteStmt = $mysqli->prepare('DELETE FROM eligible_days WHERE id = ?');
            foreach ($ids as $id) {
                try {
                    $mysqli->begin_transaction();

                    $detachStmt->bind_param('i', $id);
                    $detachStmt->execute();
                    $detached += $detachStmt->affected_rows;

                    $deleteStmt->bind_param('i', $id);
                    $deleteStmt->execute();
                    if ($deleteStmt->affected_rows > 0) {
                        $deleted++;
                    } else {
                        $failed++;
                    }

                    $mysqli->commit();
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $failed++;
                }
            }
            $detachStmt->close();
            $deleteStmt->close();
            $message = 'Deleted ' . $deleted . ' eligible day(s).';
            if ($detached > 0) {
                $message .= ' Removed ' . $detached . ' existing day assignment(s) from exercises.';
            }
            if ($failed > 0) {
                $error = $failed . ' eligible day(s) could not be deleted (likely in use).';
            }
        }
    }

    if ($action === 'bulk_delete_focuses') {
        $ids = postIntArray('selected_focus_ids');
        if (count($ids) === 0) {
            $error = 'Select at least one focus to delete.';
        } else {
            $deleted = 0;
            $failed = 0;
            $stmt = $mysqli->prepare('DELETE FROM focuses WHERE id = ?');
            foreach ($ids as $id) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
            $stmt->close();
            $message = 'Deleted ' . $deleted . ' focus(es).';
            if ($failed > 0) {
                $error = $failed . ' focus(es) could not be deleted (likely in use).';
            }
        }
    }

    if ($action === 'bulk_delete_exercises') {
        $ids = postIntArray('selected_exercise_ids');
        if (count($ids) === 0) {
            $error = 'Select at least one exercise to delete.';
        } else {
            $deleted = 0;
            $stmt = $mysqli->prepare('DELETE FROM exercises WHERE id = ?');
            foreach ($ids as $id) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $deleted++;
                }
            }
            $stmt->close();
            $message = 'Deleted ' . $deleted . ' exercise(s).';
        }
    }

    if ($action === 'add_muscle_group' || $action === 'update_muscle_group') {
        $id = (int)post('id');
        $name = post('muscle_group_name');

        if ($name === '') {
            $error = 'Muscle group name is required.';
        } else {
            if ($action === 'add_muscle_group') {
                $stmt = $mysqli->prepare('INSERT INTO muscle_groups (muscle_group_name) VALUES (?)');
                $stmt->bind_param('s', $name);
            } else {
                $stmt = $mysqli->prepare('UPDATE muscle_groups SET muscle_group_name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
            }

            if ($stmt->execute()) {
                $message = $action === 'add_muscle_group' ? 'Muscle group added.' : 'Muscle group updated.';
            } else {
                $error = 'Could not save muscle group. It may already exist.';
            }
            $stmt->close();
        }
    }

    if ($action === 'add_eligible_day' || $action === 'update_eligible_day') {
        $id = (int)post('id');
        $dayNames = parseNames(post('eligible_day_names'));

        if (count($dayNames) === 0) {
            $error = 'At least one eligible day is required.';
        } else {
            if ($action === 'add_eligible_day') {
                $stmt = $mysqli->prepare('INSERT INTO eligible_days (eligible_day_name) VALUES (?)');
                $added = 0;
                foreach ($dayNames as $dayName) {
                    $stmt->bind_param('s', $dayName);
                    if ($stmt->execute()) {
                        $added++;
                    }
                }
                $stmt->close();
                if ($added > 0) {
                    $message = $added . ' eligible day(s) added.';
                } else {
                    $error = 'Could not add eligible days. They may already exist.';
                }
            } else {
                $first = $dayNames[0];
                $stmt = $mysqli->prepare('UPDATE eligible_days SET eligible_day_name = ? WHERE id = ?');
                $stmt->bind_param('si', $first, $id);
                if ($stmt->execute()) {
                    $added = 0;
                    if (count($dayNames) > 1) {
                        $ins = $mysqli->prepare('INSERT IGNORE INTO eligible_days (eligible_day_name) VALUES (?)');
                        foreach (array_slice($dayNames, 1) as $dayName) {
                            $ins->bind_param('s', $dayName);
                            $ins->execute();
                            $added += $ins->affected_rows;
                        }
                        $ins->close();
                    }
                    $message = $added > 0 ? 'Eligible day updated and extra day(s) added.' : 'Eligible day updated.';
                } else {
                    $error = 'Could not save eligible day. It may already exist.';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'add_focus' || $action === 'update_focus') {
        $id = (int)post('id');
        $focusName = post('focus_name');

        if ($focusName === '') {
            $error = 'Focus name is required.';
        } else {
            if ($action === 'add_focus') {
                $stmt = $mysqli->prepare('INSERT INTO focuses (focus_name) VALUES (?)');
                $stmt->bind_param('s', $focusName);
            } else {
                $stmt = $mysqli->prepare('UPDATE focuses SET focus_name = ? WHERE id = ?');
                $stmt->bind_param('si', $focusName, $id);
            }

            if ($stmt->execute()) {
                $message = $action === 'add_focus' ? 'Focus added.' : 'Focus updated.';
            } else {
                $error = 'Could not save focus. It may already exist.';
            }
            $stmt->close();
        }
    }

    if ($action === 'add_exercise' || $action === 'update_exercise') {
        $id = (int)post('id');
        $exerciseName = post('exercise_name');
        $difficulty = post('difficulty');
        $estimatedMinutes = (int)post('estimated_minutes');
        $muscleGroupIds = postIntArray('muscle_group_ids');
        $eligibleDayIds = postIntArray('eligible_day_ids');
        $focusIds = postIntArray('focus_ids');

        $validDifficulty = ['Beginner', 'Intermediate', 'Advanced'];
        if (
            $exerciseName === ''
            || !in_array($difficulty, $validDifficulty, true)
            || $estimatedMinutes < 1
            || count($muscleGroupIds) === 0
            || count($eligibleDayIds) === 0
        ) {
            $error = 'Exercise name, difficulty, estimated minutes, and at least one muscle group and eligible day are required.';
        } else {
            if ($action === 'add_exercise') {
                $stmt = $mysqli->prepare('INSERT INTO exercises (exercise_name, difficulty, estimated_minutes) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $exerciseName, $difficulty, $estimatedMinutes);
            } else {
                $stmt = $mysqli->prepare('UPDATE exercises SET exercise_name = ?, difficulty = ?, estimated_minutes = ? WHERE id = ?');
                $stmt->bind_param('ssii', $exerciseName, $difficulty, $estimatedMinutes, $id);
            }

            if ($stmt->execute()) {
                $exerciseId = $action === 'add_exercise' ? (int)$mysqli->insert_id : $id;

                $delMuscles = $mysqli->prepare('DELETE FROM exercise_muscle_groups WHERE exercise_id = ?');
                $delMuscles->bind_param('i', $exerciseId);
                $delMuscles->execute();
                $delMuscles->close();

                $insMuscles = $mysqli->prepare('INSERT IGNORE INTO exercise_muscle_groups (exercise_id, muscle_group_id) VALUES (?, ?)');
                foreach ($muscleGroupIds as $muscleGroupId) {
                    $insMuscles->bind_param('ii', $exerciseId, $muscleGroupId);
                    $insMuscles->execute();
                }
                $insMuscles->close();

                $delDays = $mysqli->prepare('DELETE FROM exercise_eligible_days WHERE exercise_id = ?');
                $delDays->bind_param('i', $exerciseId);
                $delDays->execute();
                $delDays->close();

                $insDays = $mysqli->prepare('INSERT IGNORE INTO exercise_eligible_days (exercise_id, eligible_day_id) VALUES (?, ?)');
                foreach ($eligibleDayIds as $eligibleDayId) {
                    $insDays->bind_param('ii', $exerciseId, $eligibleDayId);
                    $insDays->execute();
                }
                $insDays->close();

                $delFocuses = $mysqli->prepare('DELETE FROM exercise_focuses WHERE exercise_id = ?');
                $delFocuses->bind_param('i', $exerciseId);
                $delFocuses->execute();
                $delFocuses->close();

                if (count($focusIds) > 0) {
                    $insFocuses = $mysqli->prepare('INSERT IGNORE INTO exercise_focuses (exercise_id, focus_id) VALUES (?, ?)');
                    foreach ($focusIds as $focusId) {
                        $insFocuses->bind_param('ii', $exerciseId, $focusId);
                        $insFocuses->execute();
                    }
                    $insFocuses->close();
                }

                $message = $action === 'add_exercise' ? 'Exercise added.' : 'Exercise updated.';
            } else {
                $error = 'Could not save exercise.';
            }
            $stmt->close();
        }
    }
}

$editMuscleGroupId = isset($_GET['edit_muscle_group']) ? (int)$_GET['edit_muscle_group'] : 0;
$editEligibleDayId = isset($_GET['edit_eligible_day']) ? (int)$_GET['edit_eligible_day'] : 0;
$editFocusId = isset($_GET['edit_focus']) ? (int)$_GET['edit_focus'] : 0;
$editExerciseId = isset($_GET['edit_exercise']) ? (int)$_GET['edit_exercise'] : 0;

$editingMuscleGroup = null;
if ($editMuscleGroupId > 0) {
    $stmt = $mysqli->prepare('SELECT id, muscle_group_name FROM muscle_groups WHERE id = ?');
    $stmt->bind_param('i', $editMuscleGroupId);
    $stmt->execute();
    $editingMuscleGroup = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$editingEligibleDay = null;
if ($editEligibleDayId > 0) {
    $stmt = $mysqli->prepare('SELECT id, eligible_day_name FROM eligible_days WHERE id = ?');
    $stmt->bind_param('i', $editEligibleDayId);
    $stmt->execute();
    $editingEligibleDay = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$editingFocus = null;
if ($editFocusId > 0) {
    $stmt = $mysqli->prepare('SELECT id, focus_name FROM focuses WHERE id = ?');
    $stmt->bind_param('i', $editFocusId);
    $stmt->execute();
    $editingFocus = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$editingExercise = null;
if ($editExerciseId > 0) {
    $stmt = $mysqli->prepare('SELECT id, exercise_name, difficulty, estimated_minutes FROM exercises WHERE id = ?');
    $stmt->bind_param('i', $editExerciseId);
    $stmt->execute();
    $editingExercise = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editingExercise) {
        $editingExercise['selected_muscle_group_ids'] = [];
        $editingExercise['selected_eligible_day_ids'] = [];
        $editingExercise['selected_focus_ids'] = [];

        $stmt = $mysqli->prepare('SELECT muscle_group_id FROM exercise_muscle_groups WHERE exercise_id = ?');
        $stmt->bind_param('i', $editExerciseId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $editingExercise['selected_muscle_group_ids'][] = (int)$row['muscle_group_id'];
        }
        $stmt->close();

        $stmt = $mysqli->prepare('SELECT eligible_day_id FROM exercise_eligible_days WHERE exercise_id = ?');
        $stmt->bind_param('i', $editExerciseId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $editingExercise['selected_eligible_day_ids'][] = (int)$row['eligible_day_id'];
        }
        $stmt->close();

        $stmt = $mysqli->prepare('SELECT focus_id FROM exercise_focuses WHERE exercise_id = ?');
        $stmt->bind_param('i', $editExerciseId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $editingExercise['selected_focus_ids'][] = (int)$row['focus_id'];
        }
        $stmt->close();
    }
}

$muscleGroups = [];
$res = $mysqli->query('SELECT id, muscle_group_name FROM muscle_groups ORDER BY muscle_group_name');
while ($row = $res->fetch_assoc()) {
    $muscleGroups[] = $row;
}

$eligibleDays = [];
$res = $mysqli->query('SELECT id, eligible_day_name FROM eligible_days ORDER BY eligible_day_name');
while ($row = $res->fetch_assoc()) {
    $eligibleDays[] = $row;
}

$focuses = [];
$res = $mysqli->query('SELECT id, focus_name FROM focuses ORDER BY focus_name');
while ($row = $res->fetch_assoc()) {
    $focuses[] = $row;
}

$exercises = [];
$res = $mysqli->query(
    'SELECT
        e.id,
        e.exercise_name,
        e.difficulty,
        e.estimated_minutes,
        (
            SELECT GROUP_CONCAT(
                DISTINCT mg.muscle_group_name
                ORDER BY mg.muscle_group_name
                SEPARATOR " | "
            )
            FROM exercise_muscle_groups emg
            INNER JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
            WHERE emg.exercise_id = e.id
        ) AS muscle_groups,
        (
            SELECT GROUP_CONCAT(
                DISTINCT ed.eligible_day_name
                ORDER BY ed.eligible_day_name
                SEPARATOR " | "
            )
            FROM exercise_eligible_days eed
            INNER JOIN eligible_days ed ON ed.id = eed.eligible_day_id
            WHERE eed.exercise_id = e.id
        ) AS eligible_days,
        (
            SELECT GROUP_CONCAT(
                DISTINCT f.focus_name
                ORDER BY f.focus_name
                SEPARATOR " | "
            )
            FROM exercise_focuses ef
            INNER JOIN focuses f ON f.id = ef.focus_id
            WHERE ef.exercise_id = e.id
        ) AS focuses
     FROM exercises e
     ORDER BY e.exercise_name'
);
while ($row = $res->fetch_assoc()) {
    $exercises[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Circle Jerks Exercise Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .wrap { max-width: 1000px; margin: 0 auto; }
        .card { border: 1px solid #ccc; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .card-header h2 { margin: 0; }
        .accordion-toggle { width: auto; margin-top: 0; padding: 0.35rem 0.6rem; cursor: pointer; }
        .card-body { margin-top: 0.75rem; }
        label { display: block; margin-top: 0.5rem; font-weight: 600; }
        input, select { width: 100%; padding: 0.5rem; margin-top: 0.25rem; }
        .checklist label { display: block; font-weight: 400; margin-top: 0.25rem; }
        .checklist input { width: auto; margin-right: 0.5rem; }
        button { margin-top: 0.75rem; padding: 0.5rem 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
        th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; vertical-align: top; }
        .msg { color: #0a7d2a; }
        .err { color: #b00020; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Circle Jerks Exercise Tool</h1>
    <p><a href="/recommend">Go to Exercise Recommender</a></p>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="err"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2><?php echo $editingMuscleGroup ? 'Edit Muscle Group' : 'Add Muscle Group'; ?></h2>
            <button type="button" class="accordion-toggle" data-accordion-key="muscle-groups" aria-expanded="true">^</button>
        </div>
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?php echo $editingMuscleGroup ? 'update_muscle_group' : 'add_muscle_group'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editingMuscleGroup['id'] ?? 0); ?>">
            <label for="muscle_group_name">Muscle Group Name</label>
            <input id="muscle_group_name" name="muscle_group_name" required value="<?php echo htmlspecialchars((string)($editingMuscleGroup['muscle_group_name'] ?? '')); ?>">
            <button type="submit"><?php echo $editingMuscleGroup ? 'Update Muscle Group' : 'Add Muscle Group'; ?></button>
            <?php if ($editingMuscleGroup): ?><a href="/recommend/admin">Cancel Edit</a><?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('Delete selected muscle groups?');">
            <input type="hidden" name="action" value="bulk_delete_muscle_groups">
            <button type="submit">Delete Selected Muscle Groups</button>
            <table>
                <thead><tr><th>Select</th><th>Muscle Group</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($muscleGroups as $group): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_muscle_group_ids[]" value="<?php echo (int)$group['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($group['muscle_group_name']); ?></td>
                            <td><a href="?edit_muscle_group=<?php echo (int)$group['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($muscleGroups) === 0): ?><tr><td colspan="3">No muscle groups yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?php echo $editingEligibleDay ? 'Edit Eligible Day' : 'Add Eligible Day'; ?></h2>
            <button type="button" class="accordion-toggle" data-accordion-key="eligible-days" aria-expanded="true">^</button>
        </div>
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?php echo $editingEligibleDay ? 'update_eligible_day' : 'add_eligible_day'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editingEligibleDay['id'] ?? 0); ?>">
            <label for="eligible_day_names">Eligible Day Name(s)</label>
            <input id="eligible_day_names" name="eligible_day_names" required placeholder="Example: Back Day, Leg Day, Upper Day" value="<?php echo htmlspecialchars((string)($editingEligibleDay['eligible_day_name'] ?? '')); ?>">
            <button type="submit"><?php echo $editingEligibleDay ? 'Update Eligible Day' : 'Add Eligible Day'; ?></button>
            <?php if ($editingEligibleDay): ?><a href="/recommend/admin">Cancel Edit</a><?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('Delete selected eligible days?');">
            <input type="hidden" name="action" value="bulk_delete_eligible_days">
            <button type="submit">Delete Selected Eligible Days</button>
            <table>
                <thead><tr><th>Select</th><th>Eligible Day</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($eligibleDays as $day): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_eligible_day_ids[]" value="<?php echo (int)$day['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($day['eligible_day_name']); ?></td>
                            <td><a href="?edit_eligible_day=<?php echo (int)$day['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($eligibleDays) === 0): ?><tr><td colspan="3">No eligible days yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?php echo $editingFocus ? 'Edit Focus' : 'Add Focus'; ?></h2>
            <button type="button" class="accordion-toggle" data-accordion-key="focuses" aria-expanded="true">^</button>
        </div>
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?php echo $editingFocus ? 'update_focus' : 'add_focus'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editingFocus['id'] ?? 0); ?>">
            <label for="focus_name">Focus Name</label>
            <input id="focus_name" name="focus_name" required placeholder="Example: Strength, Hypertrophy, Mobility" value="<?php echo htmlspecialchars((string)($editingFocus['focus_name'] ?? '')); ?>">
            <button type="submit"><?php echo $editingFocus ? 'Update Focus' : 'Add Focus'; ?></button>
            <?php if ($editingFocus): ?><a href="/recommend/admin">Cancel Edit</a><?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('Delete selected focuses?');">
            <input type="hidden" name="action" value="bulk_delete_focuses">
            <button type="submit">Delete Selected Focuses</button>
            <table>
                <thead><tr><th>Select</th><th>Focus</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($focuses as $focus): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_focus_ids[]" value="<?php echo (int)$focus['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($focus['focus_name']); ?></td>
                            <td><a href="?edit_focus=<?php echo (int)$focus['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($focuses) === 0): ?><tr><td colspan="3">No focuses yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?php echo $editingExercise ? 'Edit Exercise' : 'Add Exercise'; ?></h2>
            <button type="button" class="accordion-toggle" data-accordion-key="exercises" aria-expanded="true">^</button>
        </div>
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?php echo $editingExercise ? 'update_exercise' : 'add_exercise'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editingExercise['id'] ?? 0); ?>">

            <label for="exercise_name">Exercise Name</label>
            <input id="exercise_name" name="exercise_name" required value="<?php echo htmlspecialchars((string)($editingExercise['exercise_name'] ?? '')); ?>">

            <label for="difficulty">Difficulty</label>
            <select id="difficulty" name="difficulty" required>
                <?php $currentDifficulty = (string)($editingExercise['difficulty'] ?? 'Beginner'); ?>
                <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $level): ?>
                    <option value="<?php echo $level; ?>" <?php echo $currentDifficulty === $level ? 'selected' : ''; ?>><?php echo $level; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="estimated_minutes">Estimated Time to Complete (minutes)</label>
            <input id="estimated_minutes" type="number" min="1" name="estimated_minutes" required value="<?php echo htmlspecialchars((string)($editingExercise['estimated_minutes'] ?? '')); ?>">

            <label>Muscle Groups (Select one or more)</label>
            <div class="checklist">
                <?php $selectedMuscleGroupIds = $editingExercise['selected_muscle_group_ids'] ?? []; ?>
                <?php foreach ($muscleGroups as $group): ?>
                    <?php $groupId = (int)$group['id']; ?>
                    <label>
                        <input type="checkbox" name="muscle_group_ids[]" value="<?php echo $groupId; ?>" <?php echo in_array($groupId, $selectedMuscleGroupIds, true) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($group['muscle_group_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <label>Eligible Days (Select one or more)</label>
            <div class="checklist">
                <?php $selectedEligibleIds = $editingExercise['selected_eligible_day_ids'] ?? []; ?>
                <?php foreach ($eligibleDays as $day): ?>
                    <?php $dayId = (int)$day['id']; ?>
                    <label>
                        <input type="checkbox" name="eligible_day_ids[]" value="<?php echo $dayId; ?>" <?php echo in_array($dayId, $selectedEligibleIds, true) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($day['eligible_day_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <label>Focus (Select zero or more)</label>
            <div class="checklist">
                <?php $selectedFocusIds = $editingExercise['selected_focus_ids'] ?? []; ?>
                <?php foreach ($focuses as $focus): ?>
                    <?php $focusId = (int)$focus['id']; ?>
                    <label>
                        <input type="checkbox" name="focus_ids[]" value="<?php echo $focusId; ?>" <?php echo in_array($focusId, $selectedFocusIds, true) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($focus['focus_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit"><?php echo $editingExercise ? 'Update Exercise' : 'Add Exercise'; ?></button>
            <?php if ($editingExercise): ?><a href="/recommend/admin">Cancel Edit</a><?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('Delete selected exercises?');">
            <input type="hidden" name="action" value="bulk_delete_exercises">
            <button type="submit">Delete Selected Exercises</button>
            <table>
                <thead><tr><th>Select</th><th>Exercise</th><th>Difficulty</th><th>Estimated Minutes</th><th>Muscle Groups</th><th>Eligible Days</th><th>Focuses</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($exercises as $exercise): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_exercise_ids[]" value="<?php echo (int)$exercise['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($exercise['exercise_name']); ?></td>
                            <td><?php echo htmlspecialchars($exercise['difficulty']); ?></td>
                            <td><?php echo (int)$exercise['estimated_minutes']; ?></td>
                            <td><?php echo htmlspecialchars((string)($exercise['muscle_groups'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($exercise['eligible_days'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($exercise['focuses'] ?? '')); ?></td>
                            <td><a href="?edit_exercise=<?php echo (int)$exercise['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($exercises) === 0): ?><tr><td colspan="8">No exercises yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </form>
        </div>
    </div>
</div>
<script>
(function () {
    var toggles = document.querySelectorAll('.accordion-toggle');

    function setState(toggle, expanded) {
        var card = toggle.closest('.card');
        if (!card) return;
        var body = card.querySelector('.card-body');
        if (!body) return;
        body.style.display = expanded ? '' : 'none';
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded ? '^' : 'v';
    }

    toggles.forEach(function (toggle) {
        var key = toggle.getAttribute('data-accordion-key');
        var storageKey = 'circleJerksAccordion.' + key;
        var saved = localStorage.getItem(storageKey);
        var expanded = saved !== 'collapsed';
        setState(toggle, expanded);

        toggle.addEventListener('click', function () {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            var next = !isExpanded;
            setState(toggle, next);
            localStorage.setItem(storageKey, next ? 'expanded' : 'collapsed');
        });
    });
})();
</script>
</body>
</html>
