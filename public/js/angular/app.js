/**
 * @author: Shaiful Islam <kuvic16@gmail.com>
 * @since version 1.0
 */
var app = angular.module('covid',['ngRoute','ngResource', 'ngMaterial'], function($interpolateProvider) {
    $interpolateProvider.startSymbol('[[');
    $interpolateProvider.endSymbol(']]');
});

app.config(['$httpProvider', '$routeProvider', '$controllerProvider', function($httpProvider, $routeProvider, $controllerProvider,  $window) {
	app.registerCtrl = $controllerProvider.register;
}])
.run(function($rootScope, $window) {
    console.log("covid is running...");
    $rootScope.baseUrl = "";
});
