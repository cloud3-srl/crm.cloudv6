/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: a3ea4219cf9c3e5dee57026de28a15c1
 ***********************************************************************************/

define('advanced:views/report/reports/charts/grid1radar', 'advanced:views/report/reports/charts/base', function (Dep) {

    return Dep.extend({

        noLegend: true,

        zooming: false,

        defaultHeight: 450,

        prepareData: function () {
            let result = this.result;

            let grList = this.grList = result.grouping[0];

            if (this.options.color) {
                this.colorList = Espo.Utils.clone(this.colorList);
                this.colorList[0] = this.options.color;
            }

            var columnList = this.columnList || [this.column];

            if (this.columnList) {
                this.noLegend = false;
            }

            var max = 0;
            var min = 0;

            var chartData = [];

            this.ticks = [];

            grList.forEach((group, i) => {
                let label = this.formatGroup(0, group);
                this.ticks.push([i, label]);
            });

            columnList.forEach((column, j) => {
                if (this.secondColumnList && ~this.secondColumnList.indexOf(column)) {
                    return;
                }

                let label = this.reportHelper.formatColumn(column, this.result);

                let columnData = {
                    data: [],
                    label: label,
                    column: column,
                };

                grList.forEach((group, i) => {
                    var value = (this.result.reportData[group] || {})[column] || 0;

                    if (value > max) {
                        max = value;
                    }

                    if (value < min) {
                        min = value;
                    }

                    columnData.data.push([i, value]);
                });

                if (column in this.colors) {
                    columnData.color = this.colors[column];
                }

                chartData.push(columnData);
            });

            this.max = max;
            this.min = min;

            this.chartData = chartData;
        },

        isNoData: function () {
            if (!this.chartData.length || this.grList.length < 3) {
                return true;
            }

            var isEmpty = true;

            this.chartData.forEach(item => {
                if (item && item.data && item.data.length) {
                    isEmpty = false;
                }
            });

            if (isEmpty) {
                return true;
            }

            return false;
        },

        draw: function () {
            if (this.$container.height() === 0) {
                this.$container.empty();

                return;
            }

            if (this.isNoData()) {
                this.showNoData();

                return;
            }

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                htmlText: true,
                style: {
                    fontSize: 10,
                },
                radar: {
                    show: true,
                    fill: true,
                    shadowSize: 0,
                    lineWidth: 2,
                    fillOpacity: 0.2,
                    radiusRatio: 0.90,
                },
                grid: {
                    circular: true,
                    color: this.gridColor,
                    tickColor: this.gridColor,
                },
                xaxis: {
                    ticks: this.ticks,
                    color: this.textColor,
                    fontSize: 9,
                },
                yaxis : {
                    min: 0,
                    max: this.max,
                    minorTickFreq: 2,
                    color: this.textColor,
                    fontSize: 9,
                    tickFormatter: (value) => {
                        if (value === '0') {
                            return '';
                        }

                        let intValue = parseInt(value);

                        if (intValue > this.max - this.max * 0.09) {
                            return '';
                        }

                        if (value % 1 == 0) {
                            return this.formatNumber(Math.floor(value), this.isCurrency, true, true, true).toString();
                        }

                        return '';

                        if (intValue * 0.95 > this.max) {
                            return '';
                        }

                        return value;
                    }
                },
                legend: {
                    show: this.columnList,
                    noColumns: this.getLegendColumnNumber(),
                    container: this.$el.find('.legend-container'),
                    labelBoxMargin: 0,
                    labelFormatter: this.labelFormatter.bind(this),
                    labelBoxBorderColor: 'transparent',
                    backgroundOpacity: 0,
                },
            });

            if (this.columnList) {
                this.adjustLegend();
            }
        },

        formatGroup: function (i, value) {
            var gr = this.result.groupBy[i];

            return this.reportHelper.formatGroup(gr, value, this.result);
        },

    });
});
