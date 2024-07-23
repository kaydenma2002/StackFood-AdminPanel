
function newdonutChart(id,value,labels){
    var options = {
        series: value,
        labels: labels,
        chart: {
            width: "100%",
            height: 420,
            type: 'donut'
        },
        responsive: [{
            breakpoint: undefined,
            options: {},
        }],
        plotOptions: {
        pie: {
            startAngle: 0,
            endAngle: 360,
            expandOnClick: true,
            offsetX: 0,
            offsetY: 0,
            customScale: 1,
            dataLabels: {
                offset: 0,
                minAngleToShowLabel: 10
            },
            donut: {
            size: '65%',
            background: 'transparent',
            labels: {
                show: true,
                name: {
                show: true,
                fontSize: '22px',
                fontFamily: 'Helvetica, Arial, sans-serif',
                fontWeight: 600,
                color: undefined,
                offsetY: -10,
                formatter: function (val) {
                    return val
                }
                },
                value: {
                show: true,
                fontSize: '16px',
                fontFamily: 'Helvetica, Arial, sans-serif',
                fontWeight: 400,
                color: undefined,
                offsetY: 16,
                formatter: function (val) {
                    return val
                }
                },
                total: {
                show: true,
                showAlways: false,
                label: 'Total',
                fontSize: '22px',
                fontFamily: 'Helvetica, Arial, sans-serif',
                fontWeight: 600,
                color: '#373d3f',
                formatter: function (w) {
                    return w.globals.seriesTotals.reduce((a, b) => {
                    return a + b
                    }, 0)
                }
                }
            }
            },
        }
        }
    };
    var chart = new ApexCharts(document.querySelector(id), options);
    chart.render();
}
