app.controller('HomeController', function ($scope, $rootScope, $location, $window, $http) {

    $scope.searchObj = {};
    $scope.searchObj.country = "all";

    $scope.initData = {};
    $scope.resultObj = null;
    $scope.rootURL = "/analysis/home";

    $scope.searchButtonListener = function() {
        $scope.search();
    };

    $scope.line_chart_data = null;

    $scope.search = function(){
        $('.py-4').LoadingOverlay("show");
        var config = {
            params: $scope.searchObj,
            headers : {'Accept' : 'application/json'}
        };
        $http.get($scope.rootURL + "/search", config).then(function (response) {
            if (response != null) {
                $scope.resultObj = response.data;
                $scope.line_chart_data = $scope.resultObj.list;
                $scope.amChartWithAverage($scope.resultObj.list);
            }
            $('.py-4').LoadingOverlay("hide");
        });
    };

    $scope.init = function () {
        $('.py-4').LoadingOverlay("show");
        $http.get($scope.rootURL + "/init").then(function (response) {
            if (response != null) {
                $scope.initData = response.data;
            }
            $('.py-4').LoadingOverlay("hide");
        });
    };

    //$scope.countries = [];
    $scope.selected_country = '';
    $scope.selected_state = '';
    $scope.selected_county = '';
    $scope.onCountryChangeListener = function(countryObj){
        var id = countryObj.country;
        $scope.selected_country = countryObj.country;
        $scope.state_list = [];
        $scope.county_list = [];
        $scope.selected_state = '';
        $scope.selected_county = '';

        if($("[id='" + id + "']").prop("checked") == true){
            $scope.getStateList(countryObj.country);
            console.log("Checkbox is checked.");
            $scope.searchObj.country = countryObj.country;
            $scope.searchObj.state = countryObj.state;
            var config = {
                params: $scope.searchObj,
                headers : {'Accept' : 'application/json'}
            };
            $http.get($scope.rootURL + "/search", config).then(function (response) {
                if (response != null) {
                    $scope.resultObj = response.data;
                    $scope.addNewSeries(id, $scope.selected_country, $scope.resultObj.list);
                }
                $('.py-4').LoadingOverlay("hide");
            });
        }else{
            chart.map.getKey(id).dispose();
        }
    };

    $scope.state_list = [];
    $scope.getStateList = function(countryName){
        $scope.state_list = [];
        $scope.searchObj.country = countryName;
        var config = {
            params: $scope.searchObj,
            headers : {'Accept' : 'application/json'}
        };
        $http.get($scope.rootURL + "/state", config).then(function (response) {
            if (response != null) {
                $scope.state_list = response.data;
            }
        });
    };

    $scope.onStateChangeListener = function(stateObj){
        var id = $scope.selected_country + stateObj.state;
        $scope.selected_state = stateObj.state;
        $scope.county_list = [];
        $scope.selected_county = '';

        if($("[id='" + id + "']").prop("checked") == true){
            $scope.getCountyList($scope.selected_country, stateObj.state);
            console.log("Checkbox is checked.");
            $scope.searchObj.country = $scope.selected_country;
            $scope.searchObj.state = stateObj.state;
            var config = {
                params: $scope.searchObj,
                headers : {'Accept' : 'application/json'}
            };
            $http.get($scope.rootURL + "/search", config).then(function (response) {
                if (response != null) {
                    $scope.resultObj = response.data;
                    $scope.addNewSeries(id, $scope.selected_state, $scope.resultObj.list);
                }
                $('.py-4').LoadingOverlay("hide");
            });
        }else{
            chart.map.getKey(id).dispose();
        }
    };

    $scope.county_list = [];
    $scope.getCountyList = function(countryName, stateName){
        $scope.county_list = [];
        $scope.searchObj.country = countryName;
        $scope.searchObj.state = stateName;
        var config = {
            params: $scope.searchObj,
            headers : {'Accept' : 'application/json'}
        };
        $http.get($scope.rootURL + "/county", config).then(function (response) {
            if (response != null) {
                $scope.county_list = response.data;
            }
        });
    };

    $scope.onCountyChangeListener = function(countyObj){
        var id = $scope.selected_country + $scope.selected_state + countyObj.county;
        $scope.selected_county = countyObj.county;
        if($("[id='" + id + "']").prop("checked") == true){
            $scope.searchObj.country = $scope.selected_country;
            $scope.searchObj.state = $scope.selected_state;
            $scope.searchObj.county = $scope.selected_county;
            var config = {
                params: $scope.searchObj,
                headers : {'Accept' : 'application/json'}
            };
            $http.get($scope.rootURL + "/search", config).then(function (response) {
                if (response != null) {
                    $scope.resultObj = response.data;
                    $scope.addNewSeries(id, $scope.selected_county, $scope.resultObj.list);
                }
                $('.py-4').LoadingOverlay("hide");
            });
        }else{
            chart.map.getKey(id).dispose();
        }
    };


    // https://codepen.io/team/amcharts/pen/PRdxvB/
    var chart = am4core.create("chartdiv", am4charts.XYChart);
    var valueAxis2 = null;
    $scope.amChartWithAverage = function(dataList){
        am4core.useTheme(am4themes_animated);

        chart.data = dataList;

        var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
        categoryAxis.dataFields.category = "days";
        categoryAxis.title.text = "Days";

        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
        valueAxis.title.text = "Exponential Growth";

        valueAxis2 = chart.yAxes.push(new am4charts.ValueAxis());
        valueAxis2.title.text = "";
        valueAxis2.renderer.opposite = true;

        var series2 = chart.series.push(new am4charts.LineSeries());
        series2.data = dataList;
        series2.dataFields.valueY = "growth";
        series2.dataFields.categoryX = "days";
        series2.name = "Average";
        series2.id = "Average"
        series2.tooltipText = "{name} growth: [bold]{valueY}[/]";
        series2.strokeWidth = 3;
        series2.yAxis = valueAxis2;

        chart.legend = new am4charts.Legend();

        chart.cursor = new am4charts.XYCursor();
    };

    $scope.addNewSeries = function(id, name, dataList){
        if(dataList.length > 0) {
            //var id = (countryObj.state ? countryObj.state : '') + countryObj.country;
            var series2 = chart.series.push(new am4charts.LineSeries());
            series2.data = dataList;
            series2.dataFields.valueY = "growth";
            series2.dataFields.categoryX = "days";
            //series2.name = countryObj.state ? countryObj.state : countryObj.country;
            series2.name = name;
            series2.id = id;
            series2.tooltipText = "{name} growth: [bold]{valueY}[/]";
            series2.strokeWidth = 3;
            series2.yAxis = valueAxis2;
            series2.stroke = am4core.color($scope.getRandomColor());
        }
    };

    $scope.getRandomColor = function() {
        var letters = '0123456789ABCDEF';
        var color = '#';
        for (var i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    };

    $scope.countryFilterList = function($val, $countryObj){
        if($val){
           return ($countryObj.country.toLowerCase().includes($val.toLowerCase()));
        }else return true;
    };

    $scope.stateFilterList = function($val, $stateObj){
        if($val){
            return ($stateObj.state.toLowerCase().includes($val.toLowerCase()));
        }else return true;
    };

    $scope.countyFilterList = function($val, $countyObj){
        if($val){
            return ($countyObj.county.toLowerCase().includes($val.toLowerCase()));
        }else return true;
    };

    $scope.init();
    $scope.search();
});