<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseSchedule.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseSchedule.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>

    <main class="content nurse-schedule-page">
        <header class="nurse-schedule-page-header">
            <div class="nurse-schedule-page-heading">
                <p><?= htmlspecialchars($sidebarNurse['hospital_name'] ?: 'Hospital') ?></p>
                <h1>Schedule</h1>
            </div>
        </header>

        <div class="nurse-schedule-heading">
            <div class="nurse-schedule-title">
                <h2>Duty Calendar</h2>
                <p><?= htmlspecialchars($calendar['label']) ?></p>
            </div>
            <div class="nurse-schedule-controls">
                <a class="nurse-calendar-nav" href="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php?month=' . $calendar['previous'])) ?>" aria-label="Previous month">‹</a>
                <a class="nurse-calendar-nav" href="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php?month=' . $calendar['next'])) ?>" aria-label="Next month">›</a>
                <button class="nurse-add-schedule-btn" id="nurseAddScheduleMode" type="button" aria-pressed="false">+ Add schedule</button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="nurse-schedule-message <?= $messageType === 'error' ? 'is-error' : '' ?>" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="nurse-schedule-legend">
            <span><i class="morning"></i>Morning shift</span>
            <span><i class="evening"></i>Evening shift</span>
            <span><i class="oncall"></i>On-call</span>
            <span><i class="leave"></i>Off / Leave</span>
            <span class="nurse-calendar-hint" id="nurseCalendarHint" aria-live="polite">Select “Add schedule”, then choose a day.</span>
        </div>

        <div class="nurse-calendar">
            <div class="nurse-weekdays">
                <b>SUN</b><b>MON</b><b>TUE</b><b>WED</b><b>THU</b><b>FRI</b><b>SAT</b>
            </div>
            <div class="nurse-days">
                <?php for ($i = 0; $i < $calendar['leadingBlanks']; $i++): ?>
                    <div class="nurse-day empty"></div>
                <?php endfor; ?>

                <?php foreach ($calendar['days'] as $day): ?>
                    <div class="nurse-day<?= $day['isToday'] ? ' today' : '' ?>">
                        <button type="button" class="nurse-day-select" data-date="<?= htmlspecialchars($day['date']) ?>"
                            data-has-shift="<?= $day['shifts'] ? 'true' : 'false' ?>" disabled
                            aria-label="Choose <?= htmlspecialchars((new DateTimeImmutable($day['date']))->format('F j, Y')) ?>">
                            <?= (int) $day['number'] ?>
                        </button>

                        <?php foreach ($day['shifts'] as $shift): ?>
                            <div class="nurse-shift-wrap">
                                <button
                                    type="button"
                                    class="nurse-shift <?= htmlspecialchars($shift['type']) ?>"
                                    data-nurse-open-schedule="edit"
                                    data-id="<?= (int) $shift['id'] ?>"
                                    data-date="<?= htmlspecialchars($shift['date']) ?>"
                                    data-type="<?= htmlspecialchars($shift['type']) ?>"
                                    data-start="<?= htmlspecialchars($shift['start']) ?>"
                                    data-end="<?= htmlspecialchars($shift['end']) ?>"
                                ><?= htmlspecialchars($shift['label']) ?></button>
                                <form method="post" class="nurse-delete-schedule-form" action="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php?month=' . $month)) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_schedule_csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $shift['id'] ?>">
                                    <button type="button" data-nurse-delete-schedule aria-label="Remove schedule" title="Remove schedule">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="nurse-schedule-bottom">
            <section class="nurse-tomorrow-shifts" aria-labelledby="nurseTomorrowHeading">
                <div class="nurse-tomorrow-heading">
                    <div>
                        <h3 id="nurseTomorrowHeading">TOMORROW'S SCHEDULE</h3>
                        <p><?= htmlspecialchars($tomorrowLabel) ?></p>
                    </div>
                    <span><?= count($tomorrowShifts) ?> <?= count($tomorrowShifts) === 1 ? 'shift' : 'shifts' ?></span>
                </div>
                <?php if ($tomorrowShifts): ?>
                    <div class="nurse-tomorrow-shift-list">
                        <?php foreach ($tomorrowShifts as $shift): ?>
                            <article class="nurse-tomorrow-shift">
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
                    <p class="nurse-no-tomorrow-shifts">No schedule has been added for tomorrow.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<div class="nurse-schedule-modal" id="nurseScheduleModal" aria-hidden="true" inert>
    <div class="nurse-schedule-modal-backdrop" data-nurse-close-schedule></div>
    <div class="nurse-schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="nurseScheduleModalTitle">
        <button type="button" class="nurse-schedule-modal-close" data-nurse-close-schedule>×</button>
        <h2 id="nurseScheduleModalTitle">Add schedule</h2>
        <p id="nurseScheduleSelectedDate"></p>

        <form method="post" id="nurseScheduleForm" action="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php?month=' . $month)) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_schedule_csrf_token']) ?>">
            <input type="hidden" name="action" id="nurseScheduleAction" value="add">
            <input type="hidden" name="id" id="nurseScheduleId" value="">
            <input type="hidden" name="date" id="nurseScheduleDate" required>

            <label>
                Schedule type
                <select name="type" id="nurseScheduleType" required>
                    <option value="morning">Morning shift</option>
                    <option value="evening">Evening shift</option>
                    <option value="oncall">On-call</option>
                    <option value="leave">Off / Leave</option>
                </select>
            </label>

            <div class="nurse-schedule-time-fields">
                <label>
                    Start time
                    <input type="time" name="start" id="nurseScheduleStart" value="07:00">
                </label>
                <label>
                    End time
                    <input type="time" name="end" id="nurseScheduleEnd" value="15:00">
                </label>
            </div>

            <div class="nurse-schedule-modal-actions">
                <button type="button" class="nurse-secondary-btn" data-nurse-close-schedule>Cancel</button>
                <button type="submit" class="nurse-primary-btn" id="nurseScheduleSubmit">Add schedule</button>
            </div>
        </form>
    </div>
</div>

<div class="nurse-schedule-modal nurse-delete-modal" id="nurseDeleteScheduleModal" aria-hidden="true" inert>
    <div class="nurse-schedule-modal-backdrop" data-nurse-close-delete-schedule></div>
    <section class="nurse-schedule-modal-card nurse-delete-card" role="dialog" aria-modal="true"
        aria-labelledby="nurseDeleteScheduleTitle" aria-describedby="nurseDeleteScheduleMessage">
        <h2 id="nurseDeleteScheduleTitle">Delete schedule?</h2>
        <p id="nurseDeleteScheduleMessage">This schedule will be permanently removed. Do you want to continue?</p>
        <div class="nurse-schedule-modal-actions">
            <button type="button" class="nurse-secondary-btn" data-nurse-close-delete-schedule>Cancel</button>
            <button type="button" class="nurse-delete-confirm-btn" id="nurseConfirmDeleteSchedule">Delete schedule</button>
        </div>
    </section>
</div>

<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
