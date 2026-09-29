<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();

$mysqli = get_db_connection();

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

function fmt_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? htmlspecialchars(date('M j, Y', $ts)) : htmlspecialchars($value);
}

function fmt_money(?string $value): string
{
    return $value === null || $value === '' ? '-' : '$' . htmlspecialchars(number_format((float) $value, 2));
}

// Reformats a DATETIME value from the database into the value a
// datetime-local input expects (Y-m-d\TH:i).
function input_datetime_local(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

function input_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function post_or_null(string $key): ?string
{
    $value = trim($_POST[$key] ?? '');
    return $value === '' ? null : $value;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? 'create';

    if ($form === 'update_record') {
        $post_id = (int) ($_POST['id'] ?? 0);

        $last_name  = trim($_POST['client_last_name'] ?? '');
        $first_name = trim($_POST['client_first_name'] ?? '');
        $home_phone = trim($_POST['client_phone'] ?? '');

        if ($last_name === '') $errors[] = 'Last name is required.';
        if ($first_name === '') $errors[] = 'First name is required.';
        if ($home_phone === '') $errors[] = 'Home phone is required.';

        if (empty($errors) && $post_id > 0) {
            $fields = [
                ['customer_since', post_or_null('customer_since'), 's'],
                ['date_called', post_or_null('date_called'), 's'],
                ['client_last_name', $last_name, 's'],
                ['client_first_name', $first_name, 's'],
                ['client_phone', $home_phone, 's'],
                ['client_cell_phone', post_or_null('client_cell_phone'), 's'],
                ['amount', post_or_null('amount'), 'd'],
                ['amount_due', post_or_null('amount_due'), 'd'],
                ['client_address', post_or_null('client_address'), 's'],
                ['client_address2', post_or_null('client_address2'), 's'],
                ['client_city', post_or_null('client_city'), 's'],
                ['client_state', post_or_null('client_state'), 's'],
                ['client_zip_code', post_or_null('client_zip_code'), 's'],
                ['client_email', post_or_null('client_email'), 's'],
                ['movement1_type', post_or_null('movement1_type'), 's'],
                ['movement1_age', post_or_null('movement1_age'), 's'],
                ['movement2_type', post_or_null('movement2_type'), 's'],
                ['movement2_age', post_or_null('movement2_age'), 's'],
                ['warranty1_status', post_or_null('warranty1_status'), 's'],
                ['warranty2_status', post_or_null('warranty2_status'), 's'],
                ['service_notes', post_or_null('service_notes'), 's'],
                ['conference_datetime', post_or_null('conference_datetime'), 's'],
                ['status', post_or_null('status'), 's'],
                ['tech_assigned', post_or_null('tech_assigned'), 's'],
                ['booked_by', post_or_null('booked_by'), 's'],
                ['last_called_date', post_or_null('last_called_date'), 's'],
                ['times_called', post_or_null('times_called'), 'i'],
                ['call_result', post_or_null('call_result'), 's'],
                ['occupation', post_or_null('occupation'), 's'],
                ['latitude', post_or_null('latitude'), 'd'],
                ['longitude', post_or_null('longitude'), 'd'],
            ];

            $types  = implode('', array_column($fields, 2)) . 'i';
            $values = array_column($fields, 1);
            $values[] = $post_id;

            $sql  = 'UPDATE appointments SET ' . implode(', ', array_map(fn($f) => $f[0] . ' = ?', $fields)) . ' WHERE id = ?';
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param($types, ...$values);

            if ($stmt->execute()) {
                $stmt->close();
                $mysqli->close();
                header('Location: index.php?id=' . $post_id . '&saved=1');
                exit;
            }

            $errors[] = 'Failed to save record: ' . $stmt->error;
            $stmt->close();
        }

        if ($post_id > 0) {
            $id = $post_id;
        }
    } elseif ($form === 'add_activity') {
        $post_id = (int) ($_POST['id'] ?? 0);

        $activity_date = trim($_POST['activity_date'] ?? '');
        $activity      = trim($_POST['activity'] ?? '');
        $regarding     = post_or_null('regarding');

        if ($activity_date === '') $errors[] = 'Activity date is required.';
        if ($activity === '') $errors[] = 'Activity is required.';

        if (empty($errors) && $post_id > 0) {
            $stmt = $mysqli->prepare(
                'INSERT INTO activity_log (appointment_id, activity_date, activity, regarding) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('isss', $post_id, $activity_date, $activity, $regarding);

            if ($stmt->execute()) {
                $stmt->close();
                $mysqli->close();
                header('Location: index.php?id=' . $post_id . '&saved=1');
                exit;
            }

            $errors[] = 'Failed to add activity entry: ' . $stmt->error;
            $stmt->close();
        }

        if ($post_id > 0) {
            $id = $post_id;
        }
    } elseif ($form === 'add_service') {
        $post_id = (int) ($_POST['id'] ?? 0);

        $service_date   = post_or_null('service_date');
        $serviced_by    = post_or_null('serviced_by');
        $description    = post_or_null('description');
        $estimate       = post_or_null('estimate');
        $date_in        = post_or_null('date_in');
        $delivered_date = post_or_null('delivered_date');

        if ($post_id > 0) {
            $stmt = $mysqli->prepare(
                'INSERT INTO service_history (appointment_id, service_date, serviced_by, description, estimate, date_in, delivered_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issssss', $post_id, $service_date, $serviced_by, $description, $estimate, $date_in, $delivered_date);

            if ($stmt->execute()) {
                $stmt->close();
                $mysqli->close();
                header('Location: index.php?id=' . $post_id . '&saved=1');
                exit;
            }

            $errors[] = 'Failed to add service entry: ' . $stmt->error;
            $stmt->close();
        }

        if ($post_id > 0) {
            $id = $post_id;
        }
    } else {
        // Create a new appointment.
        $client_first_name  = trim($_POST['client_first_name'] ?? '');
        $client_middle_name = trim($_POST['client_middle_name'] ?? '');
        $client_last_name   = trim($_POST['client_last_name'] ?? '');
        $client_phone       = trim($_POST['client_phone'] ?? '');
        $client_address     = trim($_POST['client_address'] ?? '');
        $client_zip_code    = trim($_POST['client_zip_code'] ?? '');
        $client_age         = trim($_POST['client_age'] ?? '');
        $appointment_time   = trim($_POST['appointment_time'] ?? '');

        $staff_first_name  = trim($_POST['staff_first_name'] ?? '');
        $staff_middle_name = trim($_POST['staff_middle_name'] ?? '');
        $staff_last_name   = trim($_POST['staff_last_name'] ?? '');
        $staff_phone       = trim($_POST['staff_phone'] ?? '');
        $staff_age         = trim($_POST['staff_age'] ?? '');

        $clock_model = trim($_POST['clock_model'] ?? '');

        if ($client_first_name === '') $errors[] = 'Client first name is required.';
        if ($client_last_name === '') $errors[] = 'Client last name is required.';
        if ($client_phone === '') $errors[] = 'Client phone number is required.';
        if ($appointment_time === '') $errors[] = 'Appointment time is required.';
        if ($staff_first_name === '') $errors[] = 'Staff first name is required.';
        if ($staff_last_name === '') $errors[] = 'Staff last name is required.';
        if ($clock_model === '') $errors[] = 'Clock model / type is required.';

        if (empty($errors)) {
            $stmt = $mysqli->prepare(
                'INSERT INTO appointments (
                    client_first_name, client_middle_name, client_last_name,
                    client_phone, client_address, client_zip_code, client_age, appointment_time,
                    staff_first_name, staff_middle_name, staff_last_name,
                    staff_phone, staff_age,
                    clock_model
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $client_age_val = $client_age === '' ? null : (int) $client_age;
            $staff_age_val  = $staff_age === '' ? null : (int) $staff_age;

            $stmt->bind_param(
                'ssssssisssssis',
                $client_first_name,
                $client_middle_name,
                $client_last_name,
                $client_phone,
                $client_address,
                $client_zip_code,
                $client_age_val,
                $appointment_time,
                $staff_first_name,
                $staff_middle_name,
                $staff_last_name,
                $staff_phone,
                $staff_age_val,
                $clock_model
            );

            if ($stmt->execute()) {
                $stmt->close();
                $mysqli->close();
                header('Location: index.php?success=1');
                exit;
            } else {
                $errors[] = 'Failed to save record: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$record = null;
$activity_entries = [];
$service_entries = [];

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $stmt = $mysqli->prepare('SELECT * FROM activity_log WHERE appointment_id = ? ORDER BY activity_date DESC, id DESC');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $activity_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $mysqli->prepare('SELECT * FROM service_history WHERE appointment_id = ? ORDER BY service_date DESC, id DESC');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $service_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $record ? 'Customer Record' : 'New Appointment' ?> - Jerry's Clock Repair</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page <?= $record ? 'wide' : '' ?>">
    <div class="nav-bar">
        <nav class="tabs">
            <a class="tab <?= $record ? '' : 'active' ?>" href="index.php">New Appointment</a>
            <a class="tab <?= $record ? 'active' : '' ?>" href="records.php">View Records</a>
        </nav>
        <a class="logout-link" href="logout.php">Log Out</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($record): ?>
        <?php if (isset($_GET['saved'])): ?>
            <div class="message success">Saved.</div>
        <?php endif; ?>

        <a class="back-link" href="records.php">&larr; Back to all records</a>

        <h1><?= fmt($record['client_first_name']) ?> <?= fmt($record['client_last_name']) ?></h1>
        <div class="subhead">
            Appointment: <?= fmt_datetime($record['appointment_time']) ?>
            &middot; Clock: <?= fmt($record['clock_model']) ?>
        </div>

        <form method="post" action="index.php?id=<?= $id ?>">
            <input type="hidden" name="form" value="update_record">
            <input type="hidden" name="id" value="<?= $id ?>">

            <h2>Customer Information</h2>
            <div class="row">
                <div class="field">
                    <label for="customer_since">Customer Since</label>
                    <input type="date" id="customer_since" name="customer_since" value="<?= input_date($record['customer_since']) ?>">
                </div>
                <div class="field">
                    <label for="date_called">Date Called</label>
                    <input type="date" id="date_called" name="date_called" value="<?= input_date($record['date_called']) ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_first_name">First Name</label>
                    <input type="text" id="client_first_name" name="client_first_name" value="<?= htmlspecialchars($record['client_first_name']) ?>">
                </div>
                <div class="field">
                    <label for="client_last_name">Last Name</label>
                    <input type="text" id="client_last_name" name="client_last_name" value="<?= htmlspecialchars($record['client_last_name']) ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_phone">Home Phone</label>
                    <input type="tel" id="client_phone" name="client_phone" value="<?= htmlspecialchars($record['client_phone']) ?>">
                </div>
                <div class="field">
                    <label for="client_cell_phone">Cell Phone</label>
                    <input type="tel" id="client_cell_phone" name="client_cell_phone" value="<?= htmlspecialchars($record['client_cell_phone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="client_email">Email</label>
                    <input type="email" id="client_email" name="client_email" value="<?= htmlspecialchars($record['client_email'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" value="<?= htmlspecialchars($record['amount'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="amount_due">Amount Due</label>
                    <input type="number" step="0.01" id="amount_due" name="amount_due" value="<?= htmlspecialchars($record['amount_due'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_address">Address</label>
                    <input type="text" id="client_address" name="client_address" value="<?= htmlspecialchars($record['client_address'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="client_address2">Address 2</label>
                    <input type="text" id="client_address2" name="client_address2" value="<?= htmlspecialchars($record['client_address2'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_city">City</label>
                    <input type="text" id="client_city" name="client_city" value="<?= htmlspecialchars($record['client_city'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="client_state">State</label>
                    <input type="text" id="client_state" name="client_state" value="<?= htmlspecialchars($record['client_state'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="client_zip_code">Zip Code</label>
                    <input type="text" id="client_zip_code" name="client_zip_code" value="<?= htmlspecialchars($record['client_zip_code'] ?? '') ?>">
                </div>
            </div>

            <h2>Movement / Warranty Info</h2>
            <div class="row">
                <div class="field">
                    <label for="movement1_type">Movement 1 Type</label>
                    <input type="text" id="movement1_type" name="movement1_type" value="<?= htmlspecialchars($record['movement1_type'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="movement1_age">Movement 1 Age</label>
                    <input type="text" id="movement1_age" name="movement1_age" value="<?= htmlspecialchars($record['movement1_age'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="warranty1_status">Warranty 1 (Prorated Status)</label>
                    <input type="text" id="warranty1_status" name="warranty1_status" value="<?= htmlspecialchars($record['warranty1_status'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="movement2_type">Movement 2 Type</label>
                    <input type="text" id="movement2_type" name="movement2_type" value="<?= htmlspecialchars($record['movement2_type'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="movement2_age">Movement 2 Age</label>
                    <input type="text" id="movement2_age" name="movement2_age" value="<?= htmlspecialchars($record['movement2_age'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="warranty2_status">Warranty 2 (Prorated Status)</label>
                    <input type="text" id="warranty2_status" name="warranty2_status" value="<?= htmlspecialchars($record['warranty2_status'] ?? '') ?>">
                </div>
            </div>

            <h2>Directions / Notes</h2>
            <div class="field">
                <label for="service_notes">Service Directions / Notes</label>
                <textarea id="service_notes" name="service_notes" rows="4"><?= htmlspecialchars($record['service_notes'] ?? '') ?></textarea>
            </div>

            <h2>Status / Follow-up</h2>
            <div class="row">
                <div class="field">
                    <label for="conference_datetime">Conference Date &amp; Time</label>
                    <input type="datetime-local" id="conference_datetime" name="conference_datetime" value="<?= input_datetime_local($record['conference_datetime']) ?>">
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <input type="text" id="status" name="status" value="<?= htmlspecialchars($record['status'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="tech_assigned">Tech Assigned</label>
                    <input type="text" id="tech_assigned" name="tech_assigned" value="<?= htmlspecialchars($record['tech_assigned'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="booked_by">Booked By</label>
                    <input type="text" id="booked_by" name="booked_by" value="<?= htmlspecialchars($record['booked_by'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="occupation">Occupation</label>
                    <input type="text" id="occupation" name="occupation" value="<?= htmlspecialchars($record['occupation'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="last_called_date">Last Called</label>
                    <input type="date" id="last_called_date" name="last_called_date" value="<?= input_date($record['last_called_date']) ?>">
                </div>
                <div class="field">
                    <label for="times_called">Times Called</label>
                    <input type="number" min="0" id="times_called" name="times_called" value="<?= htmlspecialchars($record['times_called'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="call_result">Call Result / Notes</label>
                    <input type="text" id="call_result" name="call_result" value="<?= htmlspecialchars($record['call_result'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="latitude">Latitude</label>
                    <input type="number" step="any" id="latitude" name="latitude" value="<?= htmlspecialchars($record['latitude'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="longitude">Longitude</label>
                    <input type="number" step="any" id="longitude" name="longitude" value="<?= htmlspecialchars($record['longitude'] ?? '') ?>">
                </div>
            </div>

            <div class="actions">
                <button type="submit">Save Changes</button>
            </div>
        </form>

        <h2>Activity Log</h2>
        <?php if (!empty($activity_entries)): ?>
            <table class="log-table">
                <thead>
                    <tr><th>Date</th><th>Activity</th><th>Regarding / Notes</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_entries as $entry): ?>
                    <tr>
                        <td><?= fmt_date($entry['activity_date']) ?></td>
                        <td><?= fmt($entry['activity']) ?></td>
                        <td><?= fmt($entry['regarding']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty small">No activity logged yet.</div>
        <?php endif; ?>

        <form method="post" action="index.php?id=<?= $id ?>" class="inline-add-form">
            <input type="hidden" name="form" value="add_activity">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row">
                <div class="field">
                    <label for="activity_date">Date</label>
                    <input type="date" id="activity_date" name="activity_date">
                </div>
                <div class="field">
                    <label for="activity">Activity</label>
                    <input type="text" id="activity" name="activity">
                </div>
                <div class="field">
                    <label for="regarding">Regarding / Notes</label>
                    <input type="text" id="regarding" name="regarding">
                </div>
                <div class="field field-button">
                    <button type="submit">Add Entry</button>
                </div>
            </div>
        </form>

        <h2>Service History</h2>
        <?php if (!empty($service_entries)): ?>
            <div class="service-list">
                <?php foreach ($service_entries as $entry): ?>
                <div class="service-entry">
                    <div class="service-entry-header">
                        <span><?= fmt_date($entry['service_date']) ?></span>
                        <span>By: <?= fmt($entry['serviced_by']) ?></span>
                        <span>Estimate: <?= fmt_money($entry['estimate']) ?></span>
                    </div>
                    <div class="service-entry-body"><?= fmt($entry['description']) ?></div>
                    <div class="service-entry-footer">
                        <span>Date In: <?= fmt_date($entry['date_in']) ?></span>
                        <span>Delivered: <?= fmt_date($entry['delivered_date']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty small">No past service entries yet.</div>
        <?php endif; ?>

        <form method="post" action="index.php?id=<?= $id ?>" class="inline-add-form">
            <input type="hidden" name="form" value="add_service">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row">
                <div class="field">
                    <label for="service_date">Date</label>
                    <input type="date" id="service_date" name="service_date">
                </div>
                <div class="field">
                    <label for="serviced_by">By</label>
                    <input type="text" id="serviced_by" name="serviced_by">
                </div>
                <div class="field">
                    <label for="estimate">Estimate</label>
                    <input type="number" step="0.01" id="estimate" name="estimate">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="date_in">Date In</label>
                    <input type="date" id="date_in" name="date_in">
                </div>
                <div class="field">
                    <label for="delivered_date">Delivered</label>
                    <input type="date" id="delivered_date" name="delivered_date">
                </div>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2"></textarea>
            </div>
            <div class="actions">
                <button type="submit">Add Service Entry</button>
            </div>
        </form>

        <h2>Appointment &amp; Staff</h2>
        <dl class="details">
            <dt>Appointment Time</dt><dd><?= fmt_datetime($record['appointment_time']) ?></dd>
            <dt>Clock Model</dt><dd><?= fmt($record['clock_model']) ?></dd>
            <dt>Client Age</dt><dd><?= fmt((string) ($record['client_age'] ?? '')) ?></dd>
            <dt>Staff</dt><dd><?= fmt(trim(($record['staff_first_name'] ?? '') . ' ' . ($record['staff_last_name'] ?? ''))) ?></dd>
            <dt>Staff Phone</dt><dd><?= fmt($record['staff_phone']) ?></dd>
        </dl>

    <?php else: ?>

        <h1>New Appointment</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">Appointment saved successfully.</div>
        <?php endif; ?>

        <form method="post" action="index.php">
            <input type="hidden" name="form" value="create">

            <h2>Client Information</h2>

            <div class="row">
                <div class="field">
                    <label for="client_first_name">First Name</label>
                    <input type="text" id="client_first_name" name="client_first_name" value="<?= htmlspecialchars($_POST['client_first_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="client_middle_name">Middle Name</label>
                    <input type="text" id="client_middle_name" name="client_middle_name" value="<?= htmlspecialchars($_POST['client_middle_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="client_last_name">Last Name</label>
                    <input type="text" id="client_last_name" name="client_last_name" value="<?= htmlspecialchars($_POST['client_last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_phone">Phone Number</label>
                    <input type="tel" id="client_phone" name="client_phone" value="<?= htmlspecialchars($_POST['client_phone'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="client_address">Address</label>
                    <input type="text" id="client_address" name="client_address" value="<?= htmlspecialchars($_POST['client_address'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="client_zip_code">Zip Code</label>
                    <input type="text" id="client_zip_code" name="client_zip_code" value="<?= htmlspecialchars($_POST['client_zip_code'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="client_age">Age</label>
                    <input type="number" id="client_age" name="client_age" min="0" max="150" value="<?= htmlspecialchars($_POST['client_age'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="appointment_time">Appointment Time</label>
                    <input type="datetime-local" id="appointment_time" name="appointment_time" value="<?= htmlspecialchars($_POST['appointment_time'] ?? '') ?>">
                </div>
            </div>

            <h2>Staff Information</h2>

            <div class="row">
                <div class="field">
                    <label for="staff_first_name">First Name</label>
                    <input type="text" id="staff_first_name" name="staff_first_name" value="<?= htmlspecialchars($_POST['staff_first_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="staff_middle_name">Middle Name</label>
                    <input type="text" id="staff_middle_name" name="staff_middle_name" value="<?= htmlspecialchars($_POST['staff_middle_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="staff_last_name">Last Name</label>
                    <input type="text" id="staff_last_name" name="staff_last_name" value="<?= htmlspecialchars($_POST['staff_last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="staff_phone">Phone Number</label>
                    <input type="tel" id="staff_phone" name="staff_phone" value="<?= htmlspecialchars($_POST['staff_phone'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="staff_age">Age</label>
                    <input type="number" id="staff_age" name="staff_age" min="0" max="150" value="<?= htmlspecialchars($_POST['staff_age'] ?? '') ?>">
                </div>
            </div>

            <h2>Clock Details</h2>

            <div class="field">
                <label for="clock_model">Clock Model / Type Being Fixed</label>
                <input type="text" id="clock_model" name="clock_model" value="<?= htmlspecialchars($_POST['clock_model'] ?? '') ?>">
            </div>

            <div class="actions">
                <button type="submit">Save Appointment</button>
            </div>
        </form>

    <?php endif; ?>
</div>
</body>
</html>
<?php
$mysqli->close();
