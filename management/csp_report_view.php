<?php
// Database config
$host = '**censored**';
$db   = '**censored**';
$user = '**censored**';
$pass = '**censored**';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    exit("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="csp_export.xml"');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<CSPReports>\n";
    foreach ($_POST['selected'] as $id) {
        $stmt = $pdo->prepare("SELECT * FROM csp_reports WHERE id = ?");
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            echo "  <Report>\n";
            foreach ($row as $key => $value) {
                echo "    <" . htmlspecialchars($key) . ">" . htmlspecialchars($value) . "</" . htmlspecialchars($key) . ">\n";
            }
            echo "  </Report>\n";
        }
    }
    echo "</CSPReports>";
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT r.*,
                             (SELECT COUNT(*) FROM csp_reports WHERE blocked_uri = r.blocked_uri) as uri_count
                      FROM csp_reports r
                      INNER JOIN (
                          SELECT blocked_uri, MAX(id) as max_id
                          FROM csp_reports
                          GROUP BY blocked_uri
                      ) grouped ON r.blocked_uri = grouped.blocked_uri AND r.id = grouped.max_id
                      ORDER BY r.id DESC
                      LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll();

$total = $pdo->query("SELECT COUNT(DISTINCT blocked_uri) FROM csp_reports")->fetchColumn();
$totalPages = ceil($total / $limit);

$allDetailsStmt = $pdo->query("SELECT * FROM csp_reports ORDER BY id DESC");
$allDetails = $allDetailsStmt->fetchAll();
$groupedDetails = [];
foreach ($allDetails as $entry) {
    $groupedDetails[$entry['blocked_uri']][] = $entry;
}

function getUserAgentIcon($ua) {
    $ua = strtolower($ua);
    if (strpos($ua, 'chrome') !== false && strpos($ua, 'edge') === false) return 'Chrome';
    if (strpos($ua, 'firefox') !== false) return 'Firefox';
    if (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) return 'Safari';
    if (strpos($ua, 'edge') !== false) return '🔷 Edge';
    if (strpos($ua, 'opera') !== false || strpos($ua, 'opr/') !== false) return 'pera';
    if (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) return 'IE';
    return 'Unknown';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CSP Report Viewer</title>
    <style>
	.toggle-details { font-size: 18px; color: #FFF; }
	.details-row { background-color: #353535!important; }
	h1 { color: #FFF; }
	button { margin-top: 1em; }
        body { font-family: Arial, sans-serif; margin: 2em; background-color: #353535; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
	p { color: ###; }
        th, td { color: #fff; padding: 8px; border: 1px solid #ccc; vertical-align: top; }
        th { background-color: #1B1B1B; cursor: pointer; }
        .pagination { margin-top: 20px; }
        .pagination a {
            padding: 6px 12px;
            margin: 0 4px;
            text-decoration: none;
            border: 1px solid #ccc;
            color: #333;
        }
        .pagination a.active {
            font-weight: bold;
            background-color: #ddd;
        }
        .details-row { display: none; background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>CSP Reports (Unique Blocked URIs)</h1>
    <form method="POST">
    <table id="cspTable">
        <thead>
            <tr>
                <th>Select</th>
                <th>Date</th>
                <th>IP</th>
                <th>Blocked URI</th>
                <th>Violated Directive</th>
                <th>Document URI</th>
                <th>Source File</th>
                <th>User Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): 
                $uriHash = md5($report['blocked_uri']);
            ?>
            <tr>
                <td><input type="checkbox" name="selected[]" value="<?= $report['id'] ?>"></td>
                <td><?= htmlspecialchars($report['log_date']) ?></td>
                <td><?= htmlspecialchars($report['ip_address']) ?></td>
                <td>
                    <?= htmlspecialchars($report['blocked_uri']) ?><br>
                    <small><strong>Count:</strong> <a href="#" class="toggle-details" data-target="details-<?= $uriHash ?>"><?= $report['uri_count'] ?></a></small>
                </td>
                <td><?= htmlspecialchars($report['violated_directive']) ?></td>
                <td><?= htmlspecialchars($report['document_uri']) ?></td>
                <td><?= htmlspecialchars($report['source_file']) ?></td>
                <td><?= getUserAgentIcon($report['user_agent']) ?><br><small><?= htmlspecialchars($report['user_agent']) ?></small></td>
            </tr>
            <tr class="details-row" id="details-<?= $uriHash ?>">
                <td colspan="8">
                    <?php foreach ($groupedDetails[$report['blocked_uri']] as $entry): ?>
                        <div style="margin-bottom: 10px; padding: 6px; border-bottom: 1px solid #ccc;">
                            <strong>Date:</strong> <?= htmlspecialchars($entry['log_date']) ?> | 
                            <strong>IP:</strong> <?= htmlspecialchars($entry['ip_address']) ?> | 
                            <strong>Directive:</strong> <?= htmlspecialchars($entry['violated_directive']) ?><br>
                            <strong>Document URI:</strong> <?= htmlspecialchars($entry['document_uri']) ?><br>
                            <strong>Source:</strong> <?= htmlspecialchars($entry['source_file']) ?><br>
                            <strong>User Agent:</strong> <?= htmlspecialchars($entry['user_agent']) ?>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" name="export">Export Selected as XML</button>
    </form>

    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>

    <script>
    document.querySelectorAll('.toggle-details').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const targetId = link.getAttribute('data-target');
            const detailsRow = document.getElementById(targetId);
            detailsRow.style.display = detailsRow.style.display === 'table-row' ? 'none' : 'table-row';
        });
    });
    </script>
</body>
</html>
