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

function difficultyRank(string $difficulty): int {
    if ($difficulty === 'Beginner') {
        return 1;
    }
    if ($difficulty === 'Intermediate') {
        return 2;
    }
    return 3;
}

function fetchExercisesByIds(mysqli $mysqli, array $ids): array {
    if (count($ids) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $orderField = implode(',', array_map('intval', $ids));

    $sql = "SELECT
                e.id,
                e.exercise_name,
                e.difficulty,
                e.estimated_minutes,
                COALESCE(GROUP_CONCAT(DISTINCT eed.eligible_day_id), '') AS eligible_day_ids_csv,
                (
                    SELECT GROUP_CONCAT(DISTINCT mg.muscle_group_name ORDER BY mg.muscle_group_name SEPARATOR ' | ')
                    FROM exercise_muscle_groups emg
                    INNER JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                ) AS muscle_groups,
                (
                    SELECT GROUP_CONCAT(DISTINCT ed2.eligible_day_name ORDER BY ed2.eligible_day_name SEPARATOR ' | ')
                    FROM exercise_eligible_days eed2
                    INNER JOIN eligible_days ed2 ON ed2.id = eed2.eligible_day_id
                    WHERE eed2.exercise_id = e.id
                ) AS eligible_days
            FROM exercises e
            LEFT JOIN exercise_eligible_days eed ON eed.exercise_id = e.id
            WHERE e.id IN ($placeholders)
            GROUP BY e.id, e.exercise_name, e.difficulty, e.estimated_minutes
            ORDER BY FIELD(e.id, $orderField)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types = str_repeat('i', count($ids));
    $params = $ids;
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function buildCandidatePool(mysqli $mysqli, int $maxDifficultyRank, array $selectedDayIds, array $excludeExerciseIds = []): array {
    if (count($selectedDayIds) === 0) {
        return [];
    }

    $params = [];
    $types = 'i';
    $params[] = $maxDifficultyRank;

    $dayPlaceholders = implode(',', array_fill(0, count($selectedDayIds), '?'));
    $sql = "SELECT
                e.id,
                e.exercise_name,
                e.difficulty,
                e.estimated_minutes,
                COALESCE(GROUP_CONCAT(DISTINCT eed.eligible_day_id), '') AS eligible_day_ids_csv,
                (
                    SELECT GROUP_CONCAT(DISTINCT mg.muscle_group_name ORDER BY mg.muscle_group_name SEPARATOR ' | ')
                    FROM exercise_muscle_groups emg
                    INNER JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                ) AS muscle_groups,
                (
                    SELECT GROUP_CONCAT(DISTINCT ed2.eligible_day_name ORDER BY ed2.eligible_day_name SEPARATOR ' | ')
                    FROM exercise_eligible_days eed2
                    INNER JOIN eligible_days ed2 ON ed2.id = eed2.eligible_day_id
                    WHERE eed2.exercise_id = e.id
                ) AS eligible_days
            FROM exercises e
            LEFT JOIN exercise_eligible_days eed ON eed.exercise_id = e.id
            WHERE CASE e.difficulty
                    WHEN 'Beginner' THEN 1
                    WHEN 'Intermediate' THEN 2
                    WHEN 'Advanced' THEN 3
                  END <= ?
              AND EXISTS (
                    SELECT 1
                    FROM exercise_eligible_days eed_filter
                    WHERE eed_filter.exercise_id = e.id
                      AND eed_filter.eligible_day_id IN ($dayPlaceholders)
              )";

    foreach ($selectedDayIds as $dayId) {
        $types .= 'i';
        $params[] = $dayId;
    }

    if (count($excludeExerciseIds) > 0) {
        $excludePlaceholders = implode(',', array_fill(0, count($excludeExerciseIds), '?'));
        $sql .= " AND e.id NOT IN ($excludePlaceholders)";
        foreach ($excludeExerciseIds as $id) {
            $types .= 'i';
            $params[] = (int)$id;
        }
    }

    $sql .= " GROUP BY e.id, e.exercise_name, e.difficulty, e.estimated_minutes
              ORDER BY e.estimated_minutes ASC, e.exercise_name ASC
              LIMIT 200";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function addMatchedEligibleDays(array $rows, array $selectedDayIds, array $dayNameMap): array {
    foreach ($rows as $i => $row) {
        $csv = (string)($row['eligible_day_ids_csv'] ?? '');
        $dayIdSet = [];
        if ($csv !== '') {
            foreach (explode(',', $csv) as $idStr) {
                $id = (int)$idStr;
                if ($id > 0) {
                    $dayIdSet[$id] = true;
                }
            }
        }

        $matchedNames = [];
        foreach ($selectedDayIds as $dayId) {
            if (isset($dayIdSet[$dayId]) && isset($dayNameMap[$dayId])) {
                $matchedNames[] = $dayNameMap[$dayId];
            }
        }

        $rows[$i]['matched_eligible_days'] = implode(' | ', $matchedNames);
    }

    return $rows;
}

function parseMuscleGroupKeys(?string $muscleGroups): array {
    $raw = trim((string)$muscleGroups);
    if ($raw === '') {
        return [];
    }

    $keys = [];
    foreach (explode('|', $raw) as $part) {
        $name = trim($part);
        if ($name === '') {
            continue;
        }
        $keys[strtolower($name)] = true;
    }

    return array_keys($keys);
}

function addMuscleGroupKeys(array $rows): array {
    foreach ($rows as $i => $row) {
        $rows[$i]['muscle_group_keys'] = parseMuscleGroupKeys((string)($row['muscle_groups'] ?? ''));
    }
    return $rows;
}

function muscleDistributionFromIndexes(array $selectedIndexes, array $candidates): array {
    $counts = [];
    foreach ($selectedIndexes as $idx) {
        if (!isset($candidates[$idx])) {
            continue;
        }
        $groups = $candidates[$idx]['muscle_group_keys'] ?? [];
        if (!is_array($groups)) {
            $groups = [];
        }
        foreach ($groups as $groupKey) {
            $counts[$groupKey] = ($counts[$groupKey] ?? 0) + 1;
        }
    }

    $hardOver = 0;
    $softOver = 0;
    foreach ($counts as $count) {
        if ($count > 3) {
            $hardOver += ($count - 3);
        }
        if ($count > 2) {
            $softOver += ($count - 2);
        }
    }

    return [
        'hard_over' => $hardOver,
        'soft_over' => $softOver,
    ];
}

function rebalanceByMuscleGroups(
    array $selectedIndexes,
    array $candidates,
    int $selectedMinutes,
    int $minTargetMinutes,
    int $maxTargetMinutes
): array {
    if (count($selectedIndexes) < 2 || count($candidates) <= count($selectedIndexes)) {
        return $selectedIndexes;
    }

    $selectedSet = [];
    $totalMinutes = 0;
    foreach ($selectedIndexes as $idx) {
        $selectedSet[$idx] = true;
        $totalMinutes += (int)($candidates[$idx]['estimated_minutes'] ?? 0);
    }

    $iterations = 0;
    while ($iterations < 24) {
        $iterations++;
        $currentDist = muscleDistributionFromIndexes($selectedIndexes, $candidates);
        $currentHard = (int)$currentDist['hard_over'];
        $currentSoft = (int)$currentDist['soft_over'];
        $currentTimeDist = abs($totalMinutes - $selectedMinutes);
        $bestSwap = null;

        foreach ($selectedIndexes as $pos => $selIdx) {
            $oldMinutes = (int)($candidates[$selIdx]['estimated_minutes'] ?? 0);
            foreach ($candidates as $candIdx => $candidate) {
                if (isset($selectedSet[$candIdx])) {
                    continue;
                }

                $newTotal = $totalMinutes - $oldMinutes + (int)$candidate['estimated_minutes'];
                if ($newTotal < $minTargetMinutes || $newTotal > $maxTargetMinutes) {
                    continue;
                }

                $trialIndexes = $selectedIndexes;
                $trialIndexes[$pos] = $candIdx;
                $trialDist = muscleDistributionFromIndexes($trialIndexes, $candidates);
                $trialHard = (int)$trialDist['hard_over'];
                $trialSoft = (int)$trialDist['soft_over'];
                $trialTimeDist = abs($newTotal - $selectedMinutes);

                $isBetter =
                    $trialHard < $currentHard
                    || ($trialHard === $currentHard && $trialSoft < $currentSoft)
                    || ($trialHard === $currentHard && $trialSoft === $currentSoft && $trialTimeDist < $currentTimeDist);

                if (!$isBetter) {
                    continue;
                }

                if ($bestSwap === null) {
                    $bestSwap = [
                        'pos' => $pos,
                        'cand_idx' => $candIdx,
                        'new_total' => $newTotal,
                        'hard' => $trialHard,
                        'soft' => $trialSoft,
                        'time_dist' => $trialTimeDist,
                    ];
                    continue;
                }

                if (
                    $trialHard < (int)$bestSwap['hard']
                    || ($trialHard === (int)$bestSwap['hard'] && $trialSoft < (int)$bestSwap['soft'])
                    || ($trialHard === (int)$bestSwap['hard'] && $trialSoft === (int)$bestSwap['soft'] && $trialTimeDist < (int)$bestSwap['time_dist'])
                ) {
                    $bestSwap = [
                        'pos' => $pos,
                        'cand_idx' => $candIdx,
                        'new_total' => $newTotal,
                        'hard' => $trialHard,
                        'soft' => $trialSoft,
                        'time_dist' => $trialTimeDist,
                    ];
                }
            }
        }

        if ($bestSwap === null) {
            break;
        }

        $replacePos = (int)$bestSwap['pos'];
        $oldIdx = $selectedIndexes[$replacePos];
        unset($selectedSet[$oldIdx]);
        $selectedIndexes[$replacePos] = (int)$bestSwap['cand_idx'];
        $selectedSet[(int)$bestSwap['cand_idx']] = true;
        $totalMinutes = (int)$bestSwap['new_total'];
    }

    return $selectedIndexes;
}

$focusAreas = [];
$dayNameMap = [];
$res = $mysqli->query('SELECT id, eligible_day_name FROM eligible_days ORDER BY eligible_day_name');
while ($row = $res->fetch_assoc()) {
    $focusAreas[] = $row;
    $dayNameMap[(int)$row['id']] = (string)$row['eligible_day_name'];
}

$timeOptions = [30, 45, 60, 90, 120];
$selectedMinutes = 60;
$selectedFocusIds = [];
$experience = 'Intermediate';
$recommendations = [];
$recommendedTotalMinutes = 0;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'recommend');

    $selectedMinutes = (int)($_POST['minutes'] ?? 60);
    if (!in_array($selectedMinutes, $timeOptions, true)) {
        $selectedMinutes = 60;
    }

    $experienceInput = (string)($_POST['experience_level'] ?? 'Intermediate');
    if (in_array($experienceInput, ['Beginner', 'Intermediate', 'Advanced'], true)) {
        $experience = $experienceInput;
    }

    $selectedFocusIds = postIntArray('eligible_day_ids');
    $minTargetMinutes = max(1, $selectedMinutes - 10);
    $maxTargetMinutes = $selectedMinutes + 10;
    $maxDifficultyRank = difficultyRank($experience);

    if ($action === 'export_xls') {
        $currentIds = parseCsvIds((string)($_POST['current_recommendation_ids'] ?? ''));
        $xlsRows = addMatchedEligibleDays(fetchExercisesByIds($mysqli, $currentIds), $selectedFocusIds, $dayNameMap);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="recommended_workout.xls"');

        echo "Exercise\tDifficulty\tEstimated Minutes\tMuscle Groups\tEligible Days\n";
        foreach ($xlsRows as $row) {
            $line = [
                (string)$row['exercise_name'],
                (string)$row['difficulty'],
                (string)((int)$row['estimated_minutes']),
                (string)($row['muscle_groups'] ?? ''),
                (string)($row['matched_eligible_days'] ?? ''),
            ];
            echo implode("\t", array_map(static function ($v): string {
                return str_replace(["\t", "\r", "\n"], ' ', $v);
            }, $line)) . "\n";
        }
        exit;
    }

    if (count($selectedFocusIds) === 0) {
        $error = 'Select at least one eligible day for strict recommendations.';
    }

    if ($error === '') {
        if ($action === 'reroll_exercise') {
            $currentIds = parseCsvIds((string)($_POST['current_recommendation_ids'] ?? ''));
            $rerollId = (int)($_POST['reroll_exercise_id'] ?? 0);

            if (count($currentIds) === 0 || $rerollId < 1) {
                $error = 'Could not reroll exercise due to missing recommendation state.';
            } else {
                $rerollIndex = array_search($rerollId, $currentIds, true);
                if ($rerollIndex === false) {
                    $error = 'Selected exercise is no longer in the recommendation list.';
                } else {
                    $currentRows = fetchExercisesByIds($mysqli, $currentIds);
                    $currentRowsById = [];
                    $oldTotal = 0;
                    foreach ($currentRows as $row) {
                        $id = (int)$row['id'];
                        $currentRowsById[$id] = $row;
                        $oldTotal += (int)$row['estimated_minutes'];
                    }

                    if (!isset($currentRowsById[$rerollId])) {
                        $error = 'Could not locate the selected exercise to reroll.';
                    } else {
                        $oldMinutes = (int)$currentRowsById[$rerollId]['estimated_minutes'];
                        $baseIds = $currentIds;
                        unset($baseIds[$rerollIndex]);
                        $baseIds = array_values($baseIds);

                        $excludeIds = $baseIds;
                        $excludeIds[] = $rerollId;
                        $pool = addMuscleGroupKeys(buildCandidatePool($mysqli, $maxDifficultyRank, $selectedFocusIds, $excludeIds));

                        $bestReplacement = null;
                        $baseRows = [];
                        foreach ($baseIds as $baseId) {
                            if (isset($currentRowsById[$baseId])) {
                                $baseRows[] = $currentRowsById[$baseId];
                            }
                        }
                        $baseRows = addMuscleGroupKeys($baseRows);
                        foreach ($pool as $candidate) {
                            $candMinutes = (int)$candidate['estimated_minutes'];
                            $newTotal = $oldTotal - $oldMinutes + $candMinutes;
                            $withinRange = ($newTotal >= $minTargetMinutes && $newTotal <= $maxTargetMinutes) ? 1 : 0;
                            $timeDist = abs($newTotal - $selectedMinutes);
                            $exerciseDist = abs($candMinutes - $oldMinutes);
                            $trialRows = $baseRows;
                            $trialRows[] = $candidate;
                            $trialIndexes = [];
                            foreach ($trialRows as $i => $_tmp) {
                                $trialIndexes[] = $i;
                            }
                            $dist = muscleDistributionFromIndexes($trialIndexes, $trialRows);
                            $hardOver = (int)$dist['hard_over'];
                            $softOver = (int)$dist['soft_over'];

                            $candidateScore = [
                                $withinRange,
                                -$hardOver,
                                -$softOver,
                                -$timeDist,
                                -$exerciseDist,
                            ];

                            if ($bestReplacement === null || $candidateScore > $bestReplacement['score']) {
                                $bestReplacement = [
                                    'id' => (int)$candidate['id'],
                                    'score' => $candidateScore,
                                ];
                            }
                        }

                        if ($bestReplacement === null) {
                            $message = 'No alternate exercise available for reroll with current filters.';
                        } else {
                            $currentIds[$rerollIndex] = $bestReplacement['id'];
                            $message = 'Exercise rerolled.';
                        }

                        $recommendations = addMatchedEligibleDays(fetchExercisesByIds($mysqli, $currentIds), $selectedFocusIds, $dayNameMap);
                        $recommendedTotalMinutes = 0;
                        foreach ($recommendations as $row) {
                            $recommendedTotalMinutes += (int)$row['estimated_minutes'];
                        }
                    }
                }
            }
        } else {
            $candidates = addMuscleGroupKeys(buildCandidatePool($mysqli, $maxDifficultyRank, $selectedFocusIds));
            foreach ($candidates as $idx => $candidate) {
                $csv = (string)$candidate['eligible_day_ids_csv'];
                $dayIds = [];
                if ($csv !== '') {
                    foreach (explode(',', $csv) as $idStr) {
                        $id = (int)$idStr;
                        if ($id > 0) {
                            $dayIds[$id] = true;
                        }
                    }
                }

                $matchCount = 0;
                foreach ($selectedFocusIds as $focusId) {
                    if (isset($dayIds[$focusId])) {
                        $matchCount++;
                    }
                }
                $candidates[$idx]['match_count'] = $matchCount;
            }

            $minCount = 6;
            $maxCount = 8;
            if (count($candidates) < $minCount) {
                $message = 'Not enough matching exercises to build a 6-8 exercise workout.';
            } else {
                $dp = [];
                $dp[0][0] = ['score' => 0, 'prev_count' => -1, 'prev_sum' => -1, 'idx' => -1];

                foreach ($candidates as $idx => $candidate) {
                    $minutes = (int)$candidate['estimated_minutes'];
                    $matchScore = (int)$candidate['match_count'];

                    for ($count = $maxCount - 1; $count >= 0; $count--) {
                        if (!isset($dp[$count])) {
                            continue;
                        }

                        $currentSums = array_keys($dp[$count]);
                        foreach ($currentSums as $sumKey) {
                            $sum = (int)$sumKey;
                            $newSum = $sum + $minutes;
                            if ($newSum > $maxTargetMinutes) {
                                continue;
                            }

                            $newCount = $count + 1;
                            $newScore = (int)$dp[$count][$sumKey]['score'] + $matchScore;
                            $existing = $dp[$newCount][$newSum] ?? null;

                            if ($existing === null || $newScore > (int)$existing['score']) {
                                $dp[$newCount][$newSum] = [
                                    'score' => $newScore,
                                    'prev_count' => $count,
                                    'prev_sum' => $sum,
                                    'idx' => $idx,
                                ];
                            }
                        }
                    }
                }

                $best = null;
                for ($count = $minCount; $count <= $maxCount; $count++) {
                    if (!isset($dp[$count])) {
                        continue;
                    }

                    foreach ($dp[$count] as $sum => $state) {
                        $sumInt = (int)$sum;
                        if ($sumInt < $minTargetMinutes || $sumInt > $maxTargetMinutes) {
                            continue;
                        }

                        $candidateBest = [
                            'count' => $count,
                            'sum' => $sumInt,
                            'score' => (int)$state['score'],
                        ];

                        if ($best === null) {
                            $best = $candidateBest;
                            continue;
                        }

                        $bestDist = abs($best['sum'] - $selectedMinutes);
                        $currDist = abs($candidateBest['sum'] - $selectedMinutes);

                        if (
                            $candidateBest['score'] > $best['score']
                            || ($candidateBest['score'] === $best['score'] && $currDist < $bestDist)
                            || ($candidateBest['score'] === $best['score'] && $currDist === $bestDist && $candidateBest['count'] > $best['count'])
                        ) {
                            $best = $candidateBest;
                        }
                    }
                }

                if ($best === null) {
                    $message = 'Could not build a 6-8 exercise workout within the selected time window (±10 minutes).';
                } else {
                    $count = (int)$best['count'];
                    $sum = (int)$best['sum'];
                    $chosenIndexes = [];
                    while ($count > 0) {
                        $state = $dp[$count][$sum];
                        $chosenIndexes[] = (int)$state['idx'];
                        $sum = (int)$state['prev_sum'];
                        $count = (int)$state['prev_count'];
                    }

                    $chosenIndexes = array_reverse($chosenIndexes);
                    $chosenIndexes = rebalanceByMuscleGroups(
                        $chosenIndexes,
                        $candidates,
                        $selectedMinutes,
                        $minTargetMinutes,
                        $maxTargetMinutes
                    );
                    $recommendations = [];
                    $recommendedTotalMinutes = 0;
                    foreach ($chosenIndexes as $chosenIdx) {
                        $exercise = $candidates[$chosenIdx];
                        $recommendations[] = $exercise;
                        $recommendedTotalMinutes += (int)$exercise['estimated_minutes'];
                    }

                    $recommendations = addMatchedEligibleDays($recommendations, $selectedFocusIds, $dayNameMap);
                }
            }
        }
    }
}

$currentRecommendationIdsCsv = '';
if (count($recommendations) > 0) {
    $ids = [];
    foreach ($recommendations as $row) {
        $ids[] = (int)$row['id'];
    }
    $currentRecommendationIdsCsv = implode(',', $ids);
}
$collapseFilterCard = ($_SERVER['REQUEST_METHOD'] === 'POST' && count($recommendations) > 0 && $error === '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exercise Recommendations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .wrap { max-width: 960px; margin: 0 auto; }
        .card { border: 1px solid #ccc; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .card-header h2 { margin: 0; }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .accordion-toggle { width: auto; margin-top: 0; padding: 0.35rem 0.6rem; cursor: pointer; }
        .card-body { margin-top: 0.75rem; }
        .nav a { margin-right: 1rem; }
        label { display: block; margin-top: 0.75rem; font-weight: 600; }
        input[type="range"], select { width: 100%; margin-top: 0.4rem; }
        .checklist { margin-top: 0.5rem; }
        .checklist label { font-weight: 400; margin-top: 0.3rem; }
        .checklist input { width: auto; margin-right: 0.5rem; }
        .time-value { margin-top: 0.35rem; font-weight: 700; }
        button { margin-top: 1rem; padding: 0.55rem 0.95rem; }
        .inline-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .inline-actions form { display: inline; margin: 0; }
        .reroll-btn { margin-top: 0; padding: 0.35rem 0.65rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
        th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; vertical-align: top; }
        .msg { color: #0a7d2a; }
        .err { color: #b00020; }
        .print-plan { display: none; }

        @media print {
            .nav,
            .inline-actions,
            .accordion-toggle,
            #criteria-body,
            .msg,
            .err,
            .reroll-btn,
            table {
                display: none !important;
            }

            body { margin: 0.5in; font-size: 11pt; }
            .card { border: 0; padding: 0; margin: 0 0 0.2in 0; }
            .print-plan { display: block !important; }
            .print-plan h3 { margin: 0 0 0.12in 0; }
            .print-plan ol { margin: 0; padding-left: 1.2rem; }
            .print-plan li { margin-bottom: 0.08in; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header-row">
        <h1>Exercise Recommendations</h1>
        <div class="nav">
            <a href="/recommend/admin">Manage Database</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Build Workout Criteria</h2>
            <button
                type="button"
                class="accordion-toggle"
                id="criteria-toggle"
                aria-expanded="<?php echo $collapseFilterCard ? 'false' : 'true'; ?>"
            ><?php echo $collapseFilterCard ? 'v' : '^'; ?></button>
        </div>
        <div class="card-body" id="criteria-body" <?php echo $collapseFilterCard ? 'style="display:none;"' : ''; ?>>
        <form method="post">
            <input type="hidden" name="action" value="recommend">

            <label for="time-slider">How much time do you have?</label>
            <?php
            $selectedIndex = array_search($selectedMinutes, $timeOptions, true);
            if ($selectedIndex === false) {
                $selectedIndex = 2;
            }
            ?>
            <input id="time-slider" type="range" min="0" max="4" step="1" value="<?php echo (int)$selectedIndex; ?>">
            <input id="minutes" type="hidden" name="minutes" value="<?php echo (int)$selectedMinutes; ?>">
            <div id="time-value" class="time-value"><?php echo (int)$selectedMinutes; ?> minutes</div>

            <label>What is your focus? (multi-select)</label>
            <div class="checklist">
                <?php foreach ($focusAreas as $focus): ?>
                    <?php $focusId = (int)$focus['id']; ?>
                    <label>
                        <input
                            type="checkbox"
                            name="eligible_day_ids[]"
                            value="<?php echo $focusId; ?>"
                            <?php echo in_array($focusId, $selectedFocusIds, true) ? 'checked' : ''; ?>
                        >
                        <?php echo htmlspecialchars($focus['eligible_day_name']); ?>
                    </label>
                <?php endforeach; ?>
                <?php if (count($focusAreas) === 0): ?>
                    <div>No eligible days found. Add them on the Manage page first.</div>
                <?php endif; ?>
            </div>

            <label for="experience_level">Experience Level</label>
            <select id="experience_level" name="experience_level" required>
                <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $level): ?>
                    <option value="<?php echo $level; ?>" <?php echo $experience === $level ? 'selected' : ''; ?>>
                        <?php echo $level; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Get Recommendations</button>
        </form>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="err"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (count($recommendations) > 0): ?>
        <div class="card">
            <h2>Recommended Workout (<?php echo count($recommendations); ?> exercises, <?php echo (int)$recommendedTotalMinutes; ?> total minutes)</h2>
            <div>Target: <?php echo (int)$selectedMinutes; ?> minutes</div>

            <div class="inline-actions" style="margin-top: 0.75rem;">
                <button type="button" onclick="window.print()">Print / Save as PDF</button>

                <form method="post">
                    <input type="hidden" name="action" value="export_xls">
                    <input type="hidden" name="minutes" value="<?php echo (int)$selectedMinutes; ?>">
                    <input type="hidden" name="experience_level" value="<?php echo htmlspecialchars($experience); ?>">
                    <input type="hidden" name="current_recommendation_ids" value="<?php echo htmlspecialchars($currentRecommendationIdsCsv); ?>">
                    <?php foreach ($selectedFocusIds as $dayId): ?>
                        <input type="hidden" name="eligible_day_ids[]" value="<?php echo (int)$dayId; ?>">
                    <?php endforeach; ?>
                    <button type="submit">Download XLS</button>
                </form>
            </div>

            <div class="print-plan">
                <h3>Workout Plan</h3>
                <ol>
                    <?php foreach ($recommendations as $rec): ?>
                        <li>
                            <?php echo htmlspecialchars($rec['exercise_name']); ?>
                            (<?php echo (int)$rec['estimated_minutes']; ?> min, <?php echo htmlspecialchars($rec['difficulty']); ?>)
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Exercise</th>
                        <th>Difficulty</th>
                        <th>Estimated Minutes</th>
                        <th>Muscle Groups</th>
                        <th>Eligible Days</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recommendations as $rec): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rec['exercise_name']); ?></td>
                            <td><?php echo htmlspecialchars($rec['difficulty']); ?></td>
                            <td><?php echo (int)$rec['estimated_minutes']; ?></td>
                            <td><?php echo htmlspecialchars((string)($rec['muscle_groups'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($rec['matched_eligible_days'] ?? '')); ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="action" value="reroll_exercise">
                                    <input type="hidden" name="minutes" value="<?php echo (int)$selectedMinutes; ?>">
                                    <input type="hidden" name="experience_level" value="<?php echo htmlspecialchars($experience); ?>">
                                    <input type="hidden" name="reroll_exercise_id" value="<?php echo (int)$rec['id']; ?>">
                                    <input type="hidden" name="current_recommendation_ids" value="<?php echo htmlspecialchars($currentRecommendationIdsCsv); ?>">
                                    <?php foreach ($selectedFocusIds as $dayId): ?>
                                        <input type="hidden" name="eligible_day_ids[]" value="<?php echo (int)$dayId; ?>">
                                    <?php endforeach; ?>
                                    <button class="reroll-btn" type="submit">Re-roll</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                <form method="get" action="/recommend/workout">
                    <input type="hidden" name="ids" value="<?php echo htmlspecialchars($currentRecommendationIdsCsv); ?>">
                    <input type="hidden" name="minutes" value="<?php echo (int)$selectedMinutes; ?>">
                    <input type="hidden" name="experience_level" value="<?php echo htmlspecialchars($experience); ?>">
                    <?php foreach ($selectedFocusIds as $dayId): ?>
                        <input type="hidden" name="eligible_day_ids[]" value="<?php echo (int)$dayId; ?>">
                        <button type="submit">Get Started</button>
                    <?php endforeach; ?>
                </form>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var options = [30, 45, 60, 90, 120];
    var slider = document.getElementById('time-slider');
    var hiddenMinutes = document.getElementById('minutes');
    var timeValue = document.getElementById('time-value');

    function syncTime() {
        var idx = parseInt(slider.value, 10);
        if (Number.isNaN(idx) || idx < 0 || idx >= options.length) {
            idx = 2;
        }
        var mins = options[idx];
        hiddenMinutes.value = mins;
        timeValue.textContent = mins + ' minutes';
    }

    slider.addEventListener('input', syncTime);
    syncTime();

    var toggle = document.getElementById('criteria-toggle');
    var body = document.getElementById('criteria-body');
    if (toggle && body) {
        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            var next = !expanded;
            toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
            toggle.textContent = next ? '^' : 'v';
            body.style.display = next ? '' : 'none';
        });
    }
})();
</script>
</body>
</html>
