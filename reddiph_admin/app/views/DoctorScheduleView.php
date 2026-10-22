<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/DoctorSchedule.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/DoctorSchedule.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span>DOCTOR</span>
        </div>

        <nav class="main-nav" aria-label="Doctor navigation">
            <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>">
                <span class="nav-icon">⌂</span>
                <span>Notified Cases</span>
            </a>
            <a class="<?= $currentPage === 'case-history' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php')) ?>">
                <span class="nav-icon">◇</span>
                <span>Case History</span>
            </a>
            <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php')) ?>">
                <span class="nav-icon">▣</span>
                <span>Schedule</span>
            </a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorSettingsController.php')) ?>">
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>
        </nav>

        <div class="doctor-profile">
            <span class="doctor-profile-avatar" aria-hidden="true">
                <?php if (!empty($sidebarDoctor['profile_image_url'])): ?>
                    <img src="<?= htmlspecialchars($sidebarDoctor['profile_image_url']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($sidebarDoctor['initials']) ?>
                <?php endif; ?>
            </span>
            <span class="doctor-profile-copy">
                <strong><?= htmlspecialchars($sidebarDoctor['full_name']) ?></strong>
                <span><?= htmlspecialchars($sidebarDoctor['coordinator_role']) ?></span>
            </span>
        </div>
    </aside>

    <main class="content schedule-page">
        <header class="schedule-page-header">
            <div class="schedule-page-heading">
                <p><?= htmlspecialchars($sidebarDoctor['hospital_name']) ?></p>
                <h1>Schedule</h1>
            </div>
        </header>

        <div class="schedule-heading">
            <div class="schedule-title">
                <h2>Duty Calendar</h2>
                <p><?= htmlspecialchars($calendar['label']) ?></p>
            </div>
            <div class="schedule-controls">
                <a class="calendar-nav" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php?month=' . $calendar['previous'])) ?>" aria-label="Previous month">‹</a>
                <a class="calendar-nav" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php?month=' . $calendar['next'])) ?>" aria-label="Next month">›</a>
                <button class="add-schedule-btn" id="addScheduleMode" type="button" aria-pressed="false">+ Add schedule</button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="schedule-message <?= $messageType === 'error' ? 'is-error' : '' ?>" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="legend">
            <span><i class="morning"></i>Morning shift</span>
            <span><i class="evening"></i>Evening shift</span>
            <span><i class="oncall"></i>On-call</span>
            <span><i class="leave"></i>Off / Leave</span>
            <span class="calendar-hint" id="calendarHint" aria-live="polite">Select “Add schedule”, then choose a day.</span>
        </div>

        <div class="calendar">
            <div class="weekdays">
                <b>SUN</b><b>MON</b><b>TUE</b><b>WED</b><b>THU</b><b>FRI</b><b>SAT</b>
            </div>
            <div class="days">
                <?php for ($i = 0; $i < $calendar['leadingBlanks']; $i++): ?>
                    <div class="day empty"></div>
                <?php endfor; ?>

                <?php foreach ($calendar['days'] as $day): ?>
                    <div class="day<?= $day['isToday'] ? ' today' : '' ?>">
                        <button type="button" class="day-select" data-date="<?= htmlspecialchars($day['date']) ?>"
                            data-has-shift="<?= $day['shifts'] ? 'true' : 'false' ?>" disabled
                            aria-label="Choose <?= htmlspecialchars((new DateTimeImmutable($day['date']))->format('F j, Y')) ?>">
                            <?= (int) $day['number'] ?>
                        </button>

                        <?php foreach ($day['shifts'] as $shift): ?>
                            <div class="shift-wrap">
                                <button
                                    type="button"
                                    class="shift <?= htmlspecialchars($shift['type']) ?>"
                                    data-open-schedule="edit"
                                    data-id="<?= (int) $shift['id'] ?>"
                                    data-date="<?= htmlspecialchars($shift['date']) ?>"
                                    data-type="<?= htmlspecialchars($shift['type']) ?>"
                                    data-start="<?= htmlspecialchars($shift['start']) ?>"
                                    data-end="<?= htmlspecialchars($shift['end']) ?>"
                                >
                                    <?= htmlspecialchars($shift['label']) ?>
                                </button>
                                <form method="post" class="delete-schedule-form" action="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php?month=' . $month)) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['doctor_schedule_csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $shift['id'] ?>">
                                    <button type="submit" aria-label="Remove schedule" title="Remove schedule">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="schedule-bottom">
            <section class="tomorrow-shifts" aria-labelledby="tomorrow-heading">
                <div class="tomorrow-heading">
                    <div>
                        <h3 id="tomorrow-heading">TOMORROW'S SCHEDULE</h3>
                        <p><?= htmlspecialchars($tomorrowLabel) ?></p>
                    </div>
                    <span><?= count($tomorrowShifts) ?> <?= count($tomorrowShifts) === 1 ? 'shift' : 'shifts' ?></span>
                </div>
                <?php if ($tomorrowShifts): ?>
                    <div class="tomorrow-shift-list">
                        <?php foreach ($tomorrowShifts as $shift): ?>
                            <article class="tomorrow-shift">
                                <i class="<?= htmlspecialchars($shift['type']) ?>" aria-hidden="true"></i>
                                <div>
                                    <strong><?= htmlspecialchars(
                                        $shift['start'] !== '' && $shift['end'] !== '' && $shift['end'] < $shift['start']
                                            ? 'Night shift'
                                            : ucfirst($shift['type'] ?: 'Scheduled shift')
                                    ) ?></strong>
                                    <span><?= htmlspecialchars($shift['label']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-tomorrow-shifts">No schedule has been added for tomorrow.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<div class="schedule-modal" id="scheduleModal" aria-hidden="true" inert>
    <div class="schedule-modal-backdrop" data-close-schedule></div>
    <div class="schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle">
        <button type="button" class="schedule-modal-close" data-close-schedule>×</button>
        <h2 id="scheduleModalTitle">Add schedule</h2>
        <p id="scheduleSelectedDate"></p>

        <form method="post" id="scheduleForm" action="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php?month=' . $month)) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['doctor_schedule_csrf_token']) ?>">
            <input type="hidden" name="action" id="scheduleAction" value="add">
            <input type="hidden" name="id" id="scheduleId" value="">
            <input type="hidden" name="date" id="scheduleDate" required>

            <label>
                Schedule type
                <select name="type" id="scheduleType" required>
                    <option value="morning">Morning shift</option>
                    <option value="evening">Evening shift</option>
                    <option value="oncall">On-call</option>
                    <option value="leave">Off / Leave</option>
                </select>
            </label>

            <div class="schedule-time-fields">
                <label>
                    Start time
                    <input type="time" name="start" id="scheduleStart" value="07:00">
                </label>
                <label>
                    End time
                    <input type="time" name="end" id="scheduleEnd" value="15:00">
                </label>
            </div>

            <div class="schedule-modal-actions">
                <button type="button" class="secondary-btn" data-close-schedule>Cancel</button>
                <button type="submit" class="primary-btn" id="scheduleSubmit">Add schedule</button>
            </div>
        </form>
    </div>
</div>

<div class="schedule-modal schedule-delete-modal" id="deleteScheduleModal" aria-hidden="true" inert>
    <div class="schedule-modal-backdrop" data-close-delete-schedule></div>
    <section class="schedule-modal-card schedule-delete-card" role="dialog" aria-modal="true"
        aria-labelledby="deleteScheduleTitle" aria-describedby="deleteScheduleMessage">
        <h2 id="deleteScheduleTitle">Delete schedule?</h2>
        <p id="deleteScheduleMessage">This schedule will be permanently removed. Do you want to continue?</p>
        <div class="schedule-modal-actions">
            <button type="button" class="secondary-btn" data-close-delete-schedule>Cancel</button>
            <button type="button" class="delete-confirm-btn" id="confirmDeleteSchedule">Delete schedule</button>
        </div>
    </section>
</div>

<?php $staffAlertRole = 'doctor'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script>
    window.doctorScheduleMonth = <?= json_encode($month) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
