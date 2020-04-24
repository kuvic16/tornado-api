@extends('layouts.app')

@section('content')
<div class="" ng-controller="HomeController" ng-cloak>
    <h2>Exponential Growth After 100 Cases</h2>
    <div class="row">
        <div class="col-md-2">
            <input class="search-text-box" type="text" ng-model="country_search_key" id="search-country" placeholder="Search Country" />
            <div class="list-group" style="height: 400px; overflow-y: auto">
                <input ng-repeat-start="country_obj in initData.country_list"
                    ng-show="countryFilterList(country_search_key, country_obj)"
                    type="checkbox" ng-model="nCountry" ng-change="onCountryChangeListener(country_obj)"
                    name="CheckBoxInputName" value="[[country_obj.country]]"
                    id="[[country_obj.country]]" />
                <label ng-show="countryFilterList(country_search_key, country_obj)" ng-repeat-end
                    class="list-group-item" for="[[country_obj.country]]">[[country_obj.country]]</label>
            </div>
        </div>
        <div class="col-md-2" ng-if="state_list.length > 0">
            <input class="search-text-box" type="text" ng-model="state_search_key" id="search-state" placeholder="Search State" />
            <div class="list-group" style="height: 400px; overflow-y: auto">
                <input ng-repeat-start="state_obj in state_list"
                    ng-show="stateFilterList(state_search_key, state_obj)"
                    type="checkbox" ng-model="nState" ng-change="onStateChangeListener(state_obj)"
                    name="CheckBoxInputName" value="[[selected_country]][[state_obj.state]]"
                    id="[[selected_country]][[state_obj.state]]" />
                <label ng-show="stateFilterList(state_search_key, state_obj)" ng-repeat-end
                    class="list-group-item" for="[[selected_country]][[state_obj.state]]">[[state_obj.state]]</label>
            </div>
        </div>
        <div class="col-md-2" ng-if="county_list.length > 0">
            <input class="search-text-box" type="text" ng-model="county_search_key" id="search-county" placeholder="Search Country" />
            <div class="list-group" style="height: 400px; overflow-y: auto">
                <input ng-repeat-start="county_obj in county_list"
                    ng-show="countyFilterList(county_search_key, county_obj)"
                    type="checkbox" ng-model="nCounty" ng-change="onCountyChangeListener(county_obj)"
                    name="CheckBoxInputName" value="[[selected_country]][[selected_state]][[county_obj.county]]"
                    id="[[selected_country]][[selected_state]][[county_obj.county]]" />
                <label ng-show="countyFilterList(county_search_key, county_obj)" ng-repeat-end
                    class="list-group-item" for="[[selected_country]][[selected_state]][[county_obj.county]]">[[county_obj.county]]</label>
            </div>
        </div>
        <div class="col-md-6">
            <div id="chartdiv" style="height: 500px;"></div>
            <div class="bs-callout bs-callout-info" id="callout-navs-tabs-plugin">
                <h4>PRO TIP:</h4>
                <p>Click on the country name at the bottom to toggle on/off. You can also zoom into the data by holding down your mouse button and moving left or right over the graph</p>
            </div>
        </div>
    </div>
</div>
@endsection
@section('pageScript')
    <script src="{{ asset('js/angular/controllers/HomeController.js') }}"></script>
    <script>
        $( function() {

        });
    </script>
@endsection