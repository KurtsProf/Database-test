<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();

$mysqli = get_db_connection();

// Full set of appointments columns (excluding the auto-managed id/created_at)
// used for both the CSV export and CSV import, so the two stay in sync.
$CSV_COLUMNS = [
    'client_first_name', 'client_middle_name', 'client_last_name',
    'client_phone', 'client_address', 'client_zip_code', 'client_age', 'appointment_time',
    'staff_first_name', 'staff_middle_name', 'staff_last_name', 'staff_phone', 'staff_age',
    'clock_model',
    'customer_since', 'date_called', 'client_cell_phone', 'client_email',
    'client_address2', 'client_city', 'client_state', 'amount', 'amount_due',
    'movement1_type', 'movement1_age', 'movement2_type', 'movement2_age',
    'warranty1_status', 'warranty2_status',
    'service_notes',
    'conference_datetime', 'status', 'tech_assigned', 'booked_by',
    'last_called_date', 'times_called', 'call_result', 'occupation',
    'latitude', 'longitude',
];

// Columns that must not be empty on import (mirrors the validation used
// when creating an appointment from the New Appointment form).
$CSV_REQUIRED_COLUMNS = [
    'client_first_name', 'client_last_name', 'client_phone',
    'appointment_time', 'staff_first_name', 'staff_last_name', 'clock_model',
];

// bind_param type for columns that aren't plain strings.
$CSV_COLUMN_TYPES = [
    'client_age' => 'i', 'staff_age' => 'i', 'times_called' => 'i',
    'amount' => 'd', 'amount_due' => 'd', 'latitude' => 'd', 'longitude' => 'd',
];

function csv_column_type(string $column, array $types): string
{
    return $types[$column] ?? 's';
}

// Returns [start, end) DateTime bounds for a named date filter applied to
// appointment_time, or null for "all"/unknown filters (no filtering).
// $customDate is a Y-m-d string used only when $filter === 'custom'.
function date_filter_range(string $filter, string $customDate = ''): ?array
{
    $now = new DateTime();

    switch ($filter) {
        case 'today':
            $start = (clone $now)->setTime(0, 0, 0);
            $end = (clone $start)->modify('+1 day');
            break;
        case 'yesterday':
            $start = (clone $now)->setTime(0, 0, 0)->modify('-1 day');
            $end = (clone $start)->modify('+1 day');
            break;
        case 'week':
            $start = (clone $now)->setTime(0, 0, 0)->modify('monday this week');
            $end = (clone $start)->modify('+7 days');
            break;
        case 'month':
            $start = (clone $now)->setTime(0, 0, 0)->modify('first day of this month');
            $end = (clone $start)->modify('+1 month');
            break;
        case 'quarter': // Last 3 Months - rolling window ending today.
            $end = (clone $now)->setTime(0, 0, 0)->modify('+1 day');
            $start = (clone $end)->modify('-3 months');
            break;
        case 'year':
            $start = (clone $now)->setTime(0, 0, 0)->modify('first day of January this year');
            $end = (clone $start)->modify('+1 year');
            break;
        case 'custom':
            if ($customDate === '') {
                return null;
            }
            $start = DateTime::createFromFormat('Y-m-d', $customDate);
            if ($start === false) {
                return null;
            }
            $start->setTime(0, 0, 0);
            $end = (clone $start)->modify('+1 day');
            break;
        default:
            return null;
    }

    return [$start, $end];
}

function matches_date_filter(?string $appointment_time, string $filter, string $customDate = ''): bool
{
    $range = date_filter_range($filter, $customDate);
    if ($range === null) {
        return true;
    }
    if (!$appointment_time) {
        return false;
    }
    $ts = strtotime($appointment_time);
    if ($ts === false) {
        return false;
    }
    [$start, $end] = $range;
    return $ts >= $start->getTimestamp() && $ts < $end->getTimestamp();
}

function matches_search(array $row, string $query): bool
{
    if ($query === '') {
        return true;
    }
    $haystack = mb_strtolower(trim(
        $row['client_first_name'] . ' ' . $row['client_middle_name'] . ' ' . $row['client_last_name'] . ' ' .
        $row['staff_first_name'] . ' ' . $row['staff_middle_name'] . ' ' . $row['staff_last_name']
    ));
    return mb_strpos($haystack, $query) !== false;
}

// --- CSV export -----------------------------------------------------------
// Streams a CSV of every appointment matching the active date filter and
// search term (mirroring what's currently visible on screen), then exits.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filter = $_GET['filter'] ?? 'all';
    $custom_date = trim($_GET['date'] ?? '');
    $search = mb_strtolower(trim($_GET['q'] ?? ''));

    $export_result = $mysqli->query('SELECT * FROM appointments ORDER BY appointment_time DESC, id DESC');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $CSV_COLUMNS);

    if ($export_result) {
        while ($row = $export_result->fetch_assoc()) {
            if (!matches_date_filter($row['appointment_time'], $filter, $custom_date)) {
                continue;
            }
            if (!matches_search($row, $search)) {
                continue;
            }
            $line = [];
            foreach ($CSV_COLUMNS as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($out, $line);
        }
    }

    fclose($out);
    $mysqli->close();
    exit;
}

// --- CSV import -------------------------------------------------------------
// Reads an uploaded CSV (same headers the export produces) and inserts each
// valid row as a brand-new appointment. Never updates existing records.
$import_message = null;
$import_error_lines = [];
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'import_csv') {
    $fatal_error = null;

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $fatal_error = 'No file was uploaded, or the upload failed.';
    } elseif (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) === false) {
        $fatal_error = 'Could not read the uploaded file.';
    } else {
        $header = fgetcsv($handle);

        if (!$header) {
            $fatal_error = 'The CSV file is empty.';
        } else {
            $header = array_map('trim', $header);
            $row_num = 1;

            while (($fields = fgetcsv($handle)) !== false) {
                $row_num++;

                if (count($fields) === 1 && trim((string) $fields[0]) === '') {
                    continue; // blank line
                }

                $data = [];
                foreach ($header as $i => $column) {
                    $data[$column] = isset($fields[$i]) ? trim((string) $fields[$i]) : '';
                }

                $missing = [];
                foreach ($CSV_REQUIRED_COLUMNS as $required) {
                    if (($data[$required] ?? '') === '') {
                        $missing[] = $required;
                    }
                }

                if (!empty($missing)) {
                    $import_error_lines[] = "Row $row_num: missing " . implode(', ', $missing) . '.';
                    continue;
                }

                if (strtotime($data['appointment_time']) === false) {
                    $import_error_lines[] = "Row $row_num: appointment_time is not a valid date/time.";
                    continue;
                }

                $columns = [];
                $placeholders = [];
                $types = '';
                $values = [];

                foreach ($CSV_COLUMNS as $column) {
                    if (!array_key_exists($column, $data)) {
                        continue;
                    }
                    $value = $data[$column];
                    $type = csv_column_type($column, $CSV_COLUMN_TYPES);

                    if ($value === '' && !in_array($column, $CSV_REQUIRED_COLUMNS, true)) {
                        $value = null;
                    } elseif ($type === 'i') {
                        $value = (int) $value;
                    } elseif ($type === 'd') {
                        $value = (float) $value;
                    }

                    $columns[] = $column;
                    $placeholders[] = '?';
                    $types .= $type;
                    $values[] = $value;
                }

                $stmt = $mysqli->prepare(
                    'INSERT INTO appointments (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
                );

                if (!$stmt) {
                    $import_error_lines[] = "Row $row_num: could not prepare insert.";
                    continue;
                }

                $stmt->bind_param($types, ...$values);

                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $import_error_lines[] = "Row $row_num: database error - " . $stmt->error;
                }
                $stmt->close();
            }
        }

        fclose($handle);
    }

    if ($fatal_error) {
        $import_message = 'Import failed: ' . $fatal_error;
    } else {
        $import_message = $imported . ' record' . ($imported === 1 ? '' : 's') . ' imported.';
    }
}

$result = $mysqli->query(
    'SELECT * FROM appointments ORDER BY appointment_time DESC, id DESC'
);

function fmt(?string $value): string
{
    return $value === null || $value === '' ? '-' : htmlspecialchars($value);
}

function fmt_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? htmlspecialchars(date('M j, Y g:i A', $ts)) : htmlspecialchars($value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Appointment Records - Jerry's Clock Repair</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">
    <div class="nav-bar">
        <nav class="tabs">
            <a class="tab" href="index.php">New Appointment</a>
            <a class="tab active" href="records.php">View Records</a>
        </nav>
        <a class="logout-link" href="logout.php">Log Out</a>
    </div>

    <h1>Appointment Records</h1>

    <?php if ($import_message !== null): ?>
    <div class="message <?= $imported > 0 ? 'success' : 'error' ?>">
        <?= htmlspecialchars($import_message) ?>
        <?php if (!empty($import_error_lines)): ?>
        <ul class="import-error-list">
            <?php foreach (array_slice($import_error_lines, 0, 20) as $line): ?>
            <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
            <?php if (count($import_error_lines) > 20): ?>
            <li>&hellip; and <?= count($import_error_lines) - 20 ?> more.</li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form class="csv-import-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="form" value="import_csv">
        <label for="csv_file">Import CSV</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
        <button type="submit">Import</button>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
    <div class="toolbar">
        <input type="text" id="recordSearch" class="search-input" placeholder="Search by client or staff name&hellip;" autocomplete="off">
        <div class="filter-bar">
            <label for="dateFilter">Date</label>
            <select id="dateFilter">
                <option value="all" selected>All</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="quarter">Last 3 Months</option>
                <option value="year">This Year</option>
                <option value="custom">Specific Date</option>
            </select>
            <input type="date" id="specificDate" class="specific-date-input" hidden>
            <button type="button" id="exportCsvBtn">Export to CSV</button>
        </div>
    </div>

    <div class="cards" id="recordCards">
        <?php while ($row = $result->fetch_assoc()): ?>
        <?php
            $search_text = mb_strtolower(trim(
                $row['client_first_name'] . ' ' . $row['client_middle_name'] . ' ' . $row['client_last_name'] . ' ' .
                $row['staff_first_name'] . ' ' . $row['staff_middle_name'] . ' ' . $row['staff_last_name']
            ));
            $appt_ts = $row['appointment_time'] ? strtotime($row['appointment_time']) : '';
        ?>
        <a class="card" href="index.php?id=<?= $row['id'] ?>" data-search="<?= htmlspecialchars($search_text) ?>" data-appt-ts="<?= htmlspecialchars((string) $appt_ts) ?>">
            <div class="card-header">
                <span class="card-time"><?= fmt_datetime($row['appointment_time']) ?></span>
                <span class="card-clock"><?= fmt($row['clock_model']) ?></span>
            </div>

            <h2>Client</h2>
            <dl class="details">
                <dt>First Name</dt><dd><?= fmt($row['client_first_name']) ?></dd>
                <dt>Middle Name</dt><dd><?= fmt($row['client_middle_name']) ?></dd>
                <dt>Last Name</dt><dd><?= fmt($row['client_last_name']) ?></dd>
                <dt>Phone</dt><dd><?= fmt($row['client_phone']) ?></dd>
                <dt>Address</dt><dd><?= fmt($row['client_address']) ?></dd>
                <dt>Zip Code</dt><dd><?= fmt($row['client_zip_code']) ?></dd>
                <dt>Age</dt><dd><?= fmt($row['client_age']) ?></dd>
            </dl>

            <h2>Staff</h2>
            <dl class="details">
                <dt>First Name</dt><dd><?= fmt($row['staff_first_name']) ?></dd>
                <dt>Middle Name</dt><dd><?= fmt($row['staff_middle_name']) ?></dd>
                <dt>Last Name</dt><dd><?= fmt($row['staff_last_name']) ?></dd>
                <dt>Phone</dt><dd><?= fmt($row['staff_phone']) ?></dd>
                <dt>Age</dt><dd><?= fmt($row['staff_age']) ?></dd>
            </dl>
        </a>
        <?php endwhile; ?>
    </div>

    <div class="empty" id="noResults" style="display: none;">No records found.</div>

    <script>
        (function () {
            var input = document.getElementById('recordSearch');
            var filterSelect = document.getElementById('dateFilter');
            var specificDateInput = document.getElementById('specificDate');
            var exportBtn = document.getElementById('exportCsvBtn');
            var cards = document.querySelectorAll('#recordCards .card');
            var noResults = document.getElementById('noResults');

            function dateFilterRange(filter, customDate) {
                var now = new Date();
                var startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var start, end;

                switch (filter) {
                    case 'today':
                        start = startOfDay;
                        end = new Date(start.getTime());
                        end.setDate(end.getDate() + 1);
                        break;
                    case 'yesterday':
                        start = new Date(startOfDay.getTime());
                        start.setDate(start.getDate() - 1);
                        end = new Date(start.getTime());
                        end.setDate(end.getDate() + 1);
                        break;
                    case 'week':
                        var day = startOfDay.getDay(); // 0 = Sun .. 6 = Sat
                        var diffToMonday = (day === 0 ? -6 : 1 - day);
                        start = new Date(startOfDay.getTime());
                        start.setDate(start.getDate() + diffToMonday);
                        end = new Date(start.getTime());
                        end.setDate(end.getDate() + 7);
                        break;
                    case 'month':
                        start = new Date(now.getFullYear(), now.getMonth(), 1);
                        end = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                        break;
                    case 'quarter':
                        end = new Date(startOfDay.getTime());
                        end.setDate(end.getDate() + 1);
                        start = new Date(end.getTime());
                        start.setMonth(start.getMonth() - 3);
                        break;
                    case 'year':
                        start = new Date(now.getFullYear(), 0, 1);
                        end = new Date(now.getFullYear() + 1, 0, 1);
                        break;
                    case 'custom':
                        if (!customDate) {
                            return null;
                        }
                        var parts = customDate.split('-');
                        start = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        end = new Date(start.getTime());
                        end.setDate(end.getDate() + 1);
                        break;
                    default:
                        return null;
                }
                return [start.getTime() / 1000, end.getTime() / 1000];
            }

            function applyFilters() {
                var query = input.value.trim().toLowerCase();
                var range = dateFilterRange(filterSelect.value, specificDateInput.value);
                var visible = 0;

                cards.forEach(function (card) {
                    var searchMatch = card.dataset.search.indexOf(query) !== -1;
                    var dateMatch = true;

                    if (range) {
                        var raw = card.dataset.apptTs;
                        var ts = raw ? parseInt(raw, 10) : NaN;
                        dateMatch = !isNaN(ts) && ts >= range[0] && ts < range[1];
                    }

                    var match = searchMatch && dateMatch;
                    card.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                noResults.style.display = visible === 0 ? '' : 'none';
            }

            function toggleSpecificDate() {
                specificDateInput.hidden = filterSelect.value !== 'custom';
            }

            input.addEventListener('input', applyFilters);
            filterSelect.addEventListener('change', function () {
                toggleSpecificDate();
                applyFilters();
            });
            specificDateInput.addEventListener('input', applyFilters);

            exportBtn.addEventListener('click', function () {
                var params = new URLSearchParams();
                params.set('export', 'csv');
                params.set('filter', filterSelect.value);
                params.set('date', specificDateInput.value);
                params.set('q', input.value.trim());
                window.location.href = 'records.php?' + params.toString();
            });

            toggleSpecificDate();
            applyFilters();
        })();
    </script>
    <?php else: ?>
        <div class="empty">No appointments recorded yet.</div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
$mysqli->close();
