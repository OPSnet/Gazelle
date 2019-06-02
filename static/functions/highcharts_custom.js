function InitialiseChart(target, title, startDate, interval, bytes, data) {
	var series = [].concat(data || []).map(x => {
		return {
			name: x.name,
			data: x.data
		}
	});

	if (bytes) {
		var tickPositioner = function(min, max) {
			var interval = Math.pow(2, Math.ceil(Math.log(this.tickInterval) / Math.log(2)));

			return this.getLinearTickPositions(interval, min, max);
		}

		var toBytes = function(val) {
			var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
			for (steps = 0; Math.abs(val) >= 1024; val /= 1024.0, steps++) {
			}

			return Highcharts.numberFormat(val, Math.min(steps - 1, 3)) + ' ' + units[steps];
		}

		var formatter = function() {
			return toBytes(this.value);
		}

		var tooltipFormatter = function() {
			var series = this.series;
			return '<span style="color:' + this.color + '">\u25CF</span> ' + series.name + ': <b>' + toBytes(this.y) + '</b><br />(' + Highcharts.numberFormat(this.y, 0) + ' B)';
		}
	}

	Highcharts.chart(target, {
		chart: {
			type: 'line'
		},
		title: {
			text: title
		},
		xAxis: {
			title: {
				text: undefined
			},
			type: 'datetime'
		},
		yAxis: {
			title: {
				text: undefined
			},
			tickPositioner: tickPositioner,
			labels: {
				formatter: formatter
			}
		},
		plotOptions: {
			line: {
				marker: {
					enabled: false
				}
			},
		},
		credits: {
			enabled: false
		},
		legend: {
			enabled: series.length > 1
		},
		tooltip: {
			pointFormatter: tooltipFormatter
		},
		series: series
	});
}

