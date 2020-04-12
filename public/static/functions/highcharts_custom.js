function initialiseChart(target, title, series, opt) {
    var options = {
        bytes: false
    }
    Object.assign(options, opt);

    if (options.bytes) {
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
        yAxis: (options.yAxis || {
            title: {
                text: undefined
            },
            tickPositioner: tickPositioner,
            labels: {
                formatter: formatter
            }
        }),
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
        series
    });
}

function initialiseBarChart(target, title, series, opt) {
    options = {
        categories: 'auto'
    }
    Object.assign(options, opt);

    if (options.categories === 'auto') {
        var categories = series[0].data.map(function(v) {
            return v[0];
        });
    } else if (Array.isArray(options.categories)) {
        var categories = options.categories;
    }

    Highcharts.chart(target, {
        chart: {
            type: 'column'
        },
        title: {
            text: title
        },
        xAxis: {
            title: {
                text: 'Task'
            },
            categories
        },
        yAxis: (options.yAxis || {
            title: {
                text: undefined
            },
        }),
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
        series
    });
}

