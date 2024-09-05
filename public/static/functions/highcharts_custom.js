/* global Highcharts */

function initialiseChart(target, title, series, opt) {
    let options = {
        bytes: false
    };
    Object.assign(options, opt);

    if (options.bytes) {
        var tickPositioner = function(min, max) {
            return this.getLinearTickPositions(
                Math.pow(2, Math.ceil(Math.log(this.tickInterval) / Math.log(2))),
                min,
                max
            );
        };

        let toBytes = function(val) {
            let units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
            let steps = 0;
            for (; Math.abs(val) >= 1024; steps++) {
                val /= 1024.0;
            }

            return Highcharts.numberFormat(val, Math.min(steps - 1, 3)) + ' ' + units[steps];
        };

        var formatter = function() {
            return toBytes(this.value);
        };

        var tooltipFormatter = function() {
            var series = this.series;
            return '<span style="color:' + this.color + '">\u25CF</span> ' + series.name + ': <b>' + toBytes(this.y) + '</b><br />(' + Highcharts.numberFormat(this.y, 0) + ' B)';
        };
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
    let categories;
    let options = {
        categories: 'auto'
    };
    Object.assign(options, opt);

    if (options.categories === 'auto') {
        categories = series[0].data.map(function(v) {
            return v[0];
        });
    } else if (Array.isArray(options.categories)) {
        categories = options.categories;
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

