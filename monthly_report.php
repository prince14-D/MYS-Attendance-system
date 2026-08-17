<?php
declare(strict_types=1);

$_GET['page'] = 'monthly_report';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Monthly Report';
$extraHeadHtml = <<<'HTML'
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
HTML;

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Analysis</span>
		<h1>Monthly Report</h1>
		<p class="muted">Monthly attendance overview with chart, totals, and export options.</p>
	</div>
</div>

<div class="register-snapshot-grid" aria-label="Monthly summary quick stats">
	<div class="stat-card register-mini-stat">
		<span>Month</span>
		<strong><?= h(date('M Y', strtotime($selectedMonth . '-01'))) ?></strong>
		<small>Current reporting window</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Excuses</span>
		<strong><?= h((string) $monthlyTotals['excuses']) ?></strong>
		<small>Forms submitted this month</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Total Records</span>
		<strong id="liveMonthlyTotalRecords"><?= h((string) $monthlyTotals['records']) ?></strong>
		<small>All entries in selected month</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Late Arrivals</span>
		<strong id="liveMonthlyLateArrivals"><?= h((string) $monthlyTotals['late']) ?></strong>
		<small>Clock-ins after shift start</small>
	</div>
</div>

<div class="dashboard-section monthly-report-section bootstrap-monthly-surface">
	<div class="section-heading">
		<div>
			<span class="eyebrow">Analysis</span>
			<h2>Monthly Report</h2>
		</div>
		<p class="muted">Monthly attendance overview for <?= h(date('F Y', strtotime($selectedMonth . '-01'))) ?>.</p>
	</div>

	<section class="admin-box analysis-box bootstrap-monthly-card">
		<form method="get" class="monthly-report-form">
			<div>
				<label for="month">Month</label>
				<input class="form-control" id="month" name="month" type="month" value="<?= h($selectedMonth) ?>">
			</div>
			<div>
				<label for="month_department">Department</label>
				<select class="form-select" id="month_department" name="department">
					<option value="">All Departments</option>
					<?php foreach ($departments as $department): ?>
						<option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>>
							<?= h($department['department_name']) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<button class="btn btn-primary" type="submit">View Report</button>
			<button class="btn btn-outline-primary" type="button" data-print-mode="monthly">Print Monthly</button>
		</form>

		<div class="export-links monthly-export-links">
			<a class="btn btn-outline-primary" href="excuse_form.php?month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">Employee Excuse Form (<?= h((string) $monthlyTotals['excuses']) ?>)</a>
			<a class="btn btn-outline-secondary" href="export.php?report=monthly&format=csv&month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">CSV</a>
			<a class="btn btn-outline-secondary" href="export.php?report=monthly&format=xls&month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">Excel</a>
			<a class="btn btn-outline-secondary" href="export.php?report=monthly&format=pdf&month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">PDF</a>
		</div>

		<div class="monthly-summary-grid" aria-label="Monthly attendance summary">
			<?php foreach ($monthlySummaryCards as $card): ?>
				<div class="stat-card monthly-stat-card">
					<span><?= h($card['label']) ?></span>
					<strong><?= h($card['value']) ?></strong>
					<small><?= h($card['note']) ?></small>
				</div>
			<?php endforeach; ?>
			<div class="stat-card monthly-stat-card">
				<span>Average Worked</span>
				<strong><?= h($formatMinutes($monthlyAverageWorked)) ?></strong>
				<small>Per completed record</small>
			</div>
		</div>

		<div class="monthly-live-charts row g-3">
			<div class="col-12 col-xl-8">
				<section class="admin-box monthly-live-chart-card">
					<div class="monthly-live-chart-head">
						<h3>Live Bar Chart</h3>
						<p class="muted">Daily totals, completed shifts, and late arrivals for the selected month.</p>
					</div>
					<div class="monthly-live-canvas-wrap">
						<canvas id="monthlyLiveBarChart" aria-label="Live monthly bar chart"></canvas>
					</div>
				</section>
			</div>
			<div class="col-12 col-xl-4">
				<section class="admin-box monthly-live-chart-card">
					<div class="monthly-live-chart-head">
						<h3>Live Pie Chart</h3>
						<p class="muted">Completion ratio for the selected month.</p>
					</div>
					<div class="monthly-live-canvas-wrap monthly-live-canvas-wrap-pie">
						<canvas id="monthlyLivePieChart" aria-label="Live monthly pie chart"></canvas>
					</div>
				</section>
			</div>
		</div>
		<p class="monthly-live-updated muted" id="monthlyLiveUpdatedAt">Live refresh every 15s.</p>

		<div class="monthly-chart-wrap">
			<div class="monthly-chart" aria-label="Monthly attendance bar chart">
				<?php foreach ($monthlyReportDays as $day): ?>
					<?php
						$completeHeight = (int) round(($day['complete'] / $monthlyMaxBar) * 100);
						$lateHeight = (int) round(($day['late'] / $monthlyMaxBar) * 100);
						$totalHeight = (int) round(($day['total'] / $monthlyMaxBar) * 100);
					?>
					<div class="monthly-chart-day" title="<?= h($day['date']) ?>: <?= $day['total'] ?> record<?= $day['total'] === 1 ? '' : 's' ?>, <?= $day['late'] ?> late">
						<div class="monthly-chart-bars" aria-hidden="true">
							<div class="monthly-chart-bar-group">
								<div class="monthly-chart-bar-track">
									<div class="monthly-chart-bar monthly-chart-bar-complete" style="height: <?= $completeHeight ?>%;"></div>
								</div>
								<span>Present</span>
							</div>
							<div class="monthly-chart-bar-group">
								<div class="monthly-chart-bar-track monthly-chart-bar-track-late">
									<div class="monthly-chart-bar monthly-chart-bar-late" style="height: <?= $lateHeight ?>%;"></div>
								</div>
								<span>Late</span>
							</div>
						</div>
						<div class="monthly-chart-meta">
							<strong><?= h($day['day_label']) ?></strong>
							<span><?= h($day['weekday']) ?></span>
							<small>Total <?= $day['total'] ?> | Incomplete <?= $day['incomplete'] ?></small>
						</div>
						<div class="monthly-chart-total" style="height: <?= $totalHeight ?>%;"></div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if (count($monthlyReportDays) === 0): ?>
				<div class="empty small-empty">No monthly attendance data found for this month.</div>
			<?php endif; ?>
		</div>

		<?php if (count($monthlyReportDays) > 0): ?>
			<div class="mini-table-wrap monthly-summary-table-wrap table-responsive">
				<table class="monthly-summary-table table table-hover table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Date</th>
							<th>Total</th>
							<th>Present</th>
							<th>Late</th>
							<th>Incomplete</th>
							<th>Worked Time</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($monthlyReportDays as $day): ?>
							<tr>
								<td><?= h($day['date']) ?></td>
								<td><?= $day['total'] ?></td>
								<td><?= $day['complete'] ?></td>
								<td><?= $day['late'] ?></td>
								<td><?= $day['incomplete'] ?></td>
								<td><?= h($formatMinutes($day['worked_minutes'])) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
<?php
$liveChartLabels = [];
$liveChartTotals = [];
$liveChartComplete = [];
$liveChartLate = [];

foreach ($monthlyReportDays as $day) {
	$liveChartLabels[] = (string) $day['day_label'];
	$liveChartTotals[] = (int) $day['total'];
	$liveChartComplete[] = (int) $day['complete'];
	$liveChartLate[] = (int) $day['late'];
}
?>
<script>
	(() => {
		if (typeof Chart === 'undefined') {
			return;
		}

		const initialPayload = {
			totals: {
				records: <?= (int) $monthlyTotals['records'] ?>,
				complete: <?= (int) $monthlyTotals['complete'] ?>,
				incomplete: <?= (int) $monthlyTotals['incomplete'] ?>,
				late: <?= (int) $monthlyTotals['late'] ?>
			},
			days: <?= json_encode($monthlyReportDays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
		};

		const barCanvas = document.getElementById('monthlyLiveBarChart');
		const pieCanvas = document.getElementById('monthlyLivePieChart');
		const monthInput = document.getElementById('month');
		const departmentInput = document.getElementById('month_department');
		const totalRecordsEl = document.getElementById('liveMonthlyTotalRecords');
		const lateArrivalsEl = document.getElementById('liveMonthlyLateArrivals');
		const updatedAtEl = document.getElementById('monthlyLiveUpdatedAt');

		let barChart = null;
		let pieChart = null;

		function dayLabels(days) {
			return days.map((day) => String(day.day_label || ''));
		}

		function daySeries(days, key) {
			return days.map((day) => Number(day[key] || 0));
		}

		function renderCharts(payload) {
			const days = Array.isArray(payload.days) ? payload.days : [];
			const totals = payload.totals || {};
			const labels = dayLabels(days);
			const dayTotals = daySeries(days, 'total');
			const dayComplete = daySeries(days, 'complete');
			const dayLate = daySeries(days, 'late');

			if (barCanvas) {
				if (!barChart) {
					barChart = new Chart(barCanvas, {
						type: 'bar',
						data: {
							labels,
							datasets: [
								{
									label: 'Total',
									data: dayTotals,
									backgroundColor: 'rgba(18, 63, 122, 0.75)',
									borderColor: 'rgba(10, 45, 89, 1)',
									borderWidth: 1,
									borderRadius: 6,
								},
								{
									label: 'Complete',
									data: dayComplete,
									backgroundColor: 'rgba(14, 165, 117, 0.72)',
									borderColor: 'rgba(6, 95, 70, 1)',
									borderWidth: 1,
									borderRadius: 6,
								},
								{
									label: 'Late',
									data: dayLate,
									backgroundColor: 'rgba(200, 30, 50, 0.72)',
									borderColor: 'rgba(159, 23, 39, 1)',
									borderWidth: 1,
									borderRadius: 6,
								}
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									position: 'top'
								}
							},
							scales: {
								y: {
									beginAtZero: true,
									ticks: {
										precision: 0
									}
								}
							}
						}
					});
				} else {
					barChart.data.labels = labels;
					barChart.data.datasets[0].data = dayTotals;
					barChart.data.datasets[1].data = dayComplete;
					barChart.data.datasets[2].data = dayLate;
					barChart.update();
				}
			}

			if (pieCanvas) {
				if (!pieChart) {
					pieChart = new Chart(pieCanvas, {
						type: 'pie',
						data: {
							labels: ['Complete', 'Incomplete'],
							datasets: [
								{
									data: [Number(totals.complete || 0), Number(totals.incomplete || 0)],
									backgroundColor: ['rgba(14, 165, 117, 0.76)', 'rgba(200, 30, 50, 0.76)'],
									borderColor: ['rgba(6, 95, 70, 1)', 'rgba(159, 23, 39, 1)'],
									borderWidth: 1
								}
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									position: 'bottom'
								}
							}
						}
					});
				} else {
					pieChart.data.datasets[0].data = [Number(totals.complete || 0), Number(totals.incomplete || 0)];
					pieChart.update();
				}
			}

			if (totalRecordsEl) {
				totalRecordsEl.textContent = String(Number(totals.records || 0));
			}

			if (lateArrivalsEl) {
				lateArrivalsEl.textContent = String(Number(totals.late || 0));
			}
		}

		function formatLocalTimestamp(dateLike) {
			const date = new Date(dateLike);

			if (Number.isNaN(date.getTime())) {
				return null;
			}

			return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		}

		async function refreshLiveCharts() {
			if (document.hidden) {
				return;
			}

			const params = new URLSearchParams({
				month: monthInput?.value || '<?= h($selectedMonth) ?>',
				department: departmentInput?.value || ''
			});

			try {
				const response = await fetch(`monthly_report_live.php?${params.toString()}`, {
					headers: { 'Accept': 'application/json' },
					cache: 'no-store'
				});

				if (!response.ok) {
					throw new Error('Live chart request failed');
				}

				const payload = await response.json();

				if (!payload || payload.ok !== true) {
					throw new Error('Live chart payload invalid');
				}

				renderCharts(payload);

				if (updatedAtEl) {
					const stamp = formatLocalTimestamp(payload.updated_at);
					updatedAtEl.textContent = stamp ? `Live updated at ${stamp} (every 15s).` : 'Live refresh every 15s.';
				}
			} catch (error) {
				if (updatedAtEl) {
					updatedAtEl.textContent = 'Live refresh issue. Retrying automatically.';
				}
			}
		}

		renderCharts(initialPayload);
		refreshLiveCharts();
		window.setInterval(refreshLiveCharts, 15000);
	})();
</script>
<?php
require_once __DIR__ . '/admin_shell_end.php';
