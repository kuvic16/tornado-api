<?php

namespace App\Http\Controllers;

use App\Report;
use App\Services\ReportPullService;
use DB;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class ApiController
 * @package App\Http\Controllers
 */
class ApiController extends Controller
{
    const STORM_REPORT = "STORM_REPORT";
    const CIMMS_REPORT = "CIMMS_REPORT";
    const SPOTTER_REPORT = "SPOTTER_REPORT";
    const TORNADO_REPORT = "TORNADO_REPORT";

    /**
     * Report pull service variable
     *
     * @var ReportPullService
     */
    private $reportPullService;

    /**
     * Initialize the class
     */
    public function __construct(ReportPullService $reportPullService)
    {
        $this->reportPullService = $reportPullService;
    }

    /**
     * Test api for report pull service
     *
     * @return void
     */
    public function test(){
        $this->reportPullService->run();
//        $cd = new \DateTime(gmdate("Y-m-d H:i:s", 1588418400));
//        $now = new \DateTime(gmdate("Y-m-d H:i:s"));
//        $diff = $now->getTimestamp() - $cd->getTimestamp();
//        var_dump($diff);

    }

    /**
     * Get all reports together
     *
     * @param $request
     *
     * @return array | object
     */
    public function getReports(Request $request){
        try {
            $params = [];
            if($request->get('uid')){
                $uid = $request->get('uid');
                $params = explode(",", $uid);
            }
            if(count($params) < 3){
                return [
                    'error' => 'uid parameter missing which should contain uid,lat,lon'
                ];
            }
            $lat = doubleval($params[1]);
            $lon = $this->properLon(doubleval($params[2]));
            $reports = Report::all();
            $response = [
                'storm_report'   => $this->getStormReports($reports, $lat, $lon),
                'cimms'          => $this->getCimmsReports($reports, $lat, $lon),
                'warning'        => $this->getWarningReport($reports, $lat, $lon)
            ];
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }

    /**
     * Get Storm reports
     *
     * @param $reports
     * @param $lat
     * @param $lon
     *
     * @return array
     */
    public function getStormReports($reports, $lat, $lon){
        try {
            $response = [];
            foreach($reports as $report){
                if($report->report_type == self::STORM_REPORT) {
                    $cd = new \DateTime(gmdate("Y-m-d H:i:s", intval($report->unix_timestamp)));
                    $now = new \DateTime(gmdate("Y-m-d H:i:s"));
                    $diff = $now->getTimestamp() - $cd->getTimestamp();
                    //var_dump($diff); die;
                    if ($diff <= 3600) {
                        $report->longitude = $this->properLon($report->longitude);
                        $distance = $this->distance($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                        if ($distance <= 45) {
                            //var_dump($distance);die;
                            if (strpos($report->event, 'hail') !== false) {
                                $matches = array();
                                preg_match('/[\d.]+/', $report->magnitude, $matches);
                                if (count($matches) > 0 && floatval($matches[0]) <= 1) {
                                    continue;
                                }
                            }

                            // prepare report
                            $bearing = $this->getBearing($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                            //$direction = $this->getCompassDirection($bearing);
                            $time = gmdate("Y-m-d H:i:s", intval($report->unix_timestamp));
                            $remarks = "time: " . $time . " ";
                            $remarks .= $report->remarks;
                            $obj = [
                                'event'     => $report->event,
                                'size'      => $report->magnitude,
                                'remarks'   => $remarks,
                                'distance'  => $distance,
                                'range'     => $bearing
                            ];
                            array_push($response, $obj);
                            break;
                        }
                    }
                }elseif($report->report_type == self::SPOTTER_REPORT) {
                    $event = ''; $size = 0;
                    if($report->tornado > 0 || $report->funnelcloud > 0 || $report->wallcloud){
                        $event = 'tornado';
                    }elseif($report->hail > 0){
                        $event = 'hail';
                        $size = $report->hailsize;
                    }
                    if(!empty($event)) {
                        $time = gmdate("Y-m-d H:i:s", intval($report->unix_timestamp));
                        $cd = new \DateTime($time);
                        $now = new \DateTime(gmdate("Y-m-d H:i:s"));
                        $diff = $now->getTimestamp() - $cd->getTimestamp();
                        if ($diff <= 3600) {
                            $report->longitude = $this->properLon($report->longitude);
                            $distance = $this->distance($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                            if ($distance <= 45) {
                                $bearing = $this->getBearing($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                                //$direction = $this->getCompassDirection($bearing);
                                $remarks = "time: " . $time . " ";
                                $remarks .= $report->remarks;

                                $obj = [
                                    'event' => $event,
                                    'remarks' => $remarks,
                                    'distance' => $distance,
                                    'range' => $bearing
                                ];
                                if ($size > 0) {
                                    $obj['size'] = $size;
                                }
                                array_push($response, $obj);
                            }
                        }
                    }
                }
            }
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }


    /**
     * Get CIMMS Report
     *
     * @param $reports
     * @param $lat
     * @param $lon
     *
     * @return array
     */
    private function getCimmsReports($reports, $lat, $lon){
        try {
            $response = [];
            foreach($reports as $report) {
                if ($report->report_type == self::CIMMS_REPORT) {
                    $cObj = $this->calculateDistanceRange($lat, $lon, $report->latlon);
                    $obj = [
                        "id"                => $report->object_id,
                        "distance"          => $cObj['distance'],
                        "range"             => $cObj['range'],
                        "ProbHail"          => $report->prob_hail . '%',
                        "ProbTor"           => $report->prob_tor . '%',
                        "ProbWind"          => $report->prob_wind . '%',
                        "mesh"              => $report->mesh,
                        "description"       => $report->remarks
                    ];
                    array_push($response, $obj);
                    break;
                }
            }
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }

    /**
     * Calculate distance and range
     *
     * @param $lat
     * @param $lon
     * @param $latlons
     *
     * @return array
     */
    private function calculateDistanceRange($lat, $lon, $latlons){
        $minDistance = 0; $minRange = 0; $maxRange = 0;
        foreach(explode(':', $latlons) as $latlon){
            $lat1 = doubleval(explode(',', $latlon)[0]);
            $lon1 = doubleval(explode(',', $latlon)[1]);
            $lon1 = $this->properLon($lon1);

            $distance = round($this->distance($lat, $lon, $lat1, $lon1));
            if ($minDistance == 0 || $minDistance > $distance) $minDistance = $distance;

            $range = round($this->getBearing($lat, $lon, $lat1, $lon1));
            if ($minRange == 0 || $minRange > $range) $minRange = $range;
            if ($maxRange == 0 || $maxRange < $range) $maxRange = $range;
        }

        return [
            'distance' => $minDistance, 'range' => $minRange . '-' .$maxRange
        ];
    }


    /**
     * Get warning report
     *
     * @param $reports
     * @param $lat
     * @param $lon
     *
     * @return array
     */
    private function getWarningReport($reports, $lat, $lon){
        try {
            $response = [];
            foreach($reports as $report) {
                if ($report->report_type == self::TORNADO_REPORT) {
                    $type = "";
                    if($report->phenom == 'SV'){
                        $type = 'SVR';
                    }
                    if($report->phenom == 'TO' || $report->phenom == 'TOR' || $report->phenom == 'TR'){
                        $type = 'TOR';
                    }
                    if(!empty($type)) {
                        $obj = [
                            "type"          => $type,
                            "description"   => $report->remarks,
                            "is_inside"     => $this->contains($lat, $lon, $report->latlon)
                        ];
                        array_push($response, $obj);
                    }
                }
            }
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }


    /**
     * Calculate distance between two lat lon points
     *
     * @param $lat1
     * @param $lon1
     * @param $lat2
     * @param $lon2
     *
     * @return float
     */
    function distance($lat1, $lon1, $lat2, $lon2) {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            return $miles;
        }
    }

    /**
     * Calculate bearing between two lat lon points
     *
     * @param $lat1
     * @param $lon1
     * @param $lat2
     * @param $lon2
     *
     * @return int
     */
    function getBearing($lat1, $lon1, $lat2, $lon2) {
        return (rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * cos(deg2rad($lat2)), cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360) % 360;
    }

    /**
     * Calculate compass direction from bearing
     *
     * @param $bearing
     *
     * @return string
     */
    function getCompassDirection($bearing) {
        $tmp = round($bearing / 22.5);
        switch($tmp) {
            case 1:
                $direction = "NNE";
                break;
            case 2:
                $direction = "NE";
                break;
            case 3:
                $direction = "ENE";
                break;
            case 4:
                $direction = "E";
                break;
            case 5:
                $direction = "ESE";
                break;
            case 6:
                $direction = "SE";
                break;
            case 7:
                $direction = "SSE";
                break;
            case 8:
                $direction = "S";
                break;
            case 9:
                $direction = "SSW";
                break;
            case 10:
                $direction = "SW";
                break;
            case 11:
                $direction = "WSW";
                break;
            case 12:
                $direction = "W";
                break;
            case 13:
                $direction = "WNW";
                break;
            case 14:
                $direction = "NW";
                break;
            case 15:
                $direction = "NNW";
                break;
            default:
                $direction = "N";
        }
        return $direction;
    }

    /**
     * Is user inside into ploygon
     *
     * @param $lat
     * @param $lon
     * @param $latlons
     *
     * @return bool
     */
    private function contains($lat, $lon, $latlons){
        $_vertices = explode(':', $latlons);
        $lastPoint = $_vertices[count($_vertices) - 1];
        $lastPointLat = doubleval(explode(',', $lastPoint)[0]);
        $lastPointLon = $this->properLon(doubleval(explode(',', $lastPoint)[1]));
        $isInside = false;
        $x = $lon;
        foreach ($_vertices as $point){
            $pointLat = doubleval(explode(',', $point)[0]);
            $pointLon = doubleval(explode(',', $point)[1]);
            $pointLon = $this->properLon($pointLon);

            $x1 = $lastPointLon;
            $x2 = $pointLon;
            $dx = $x2 - $x1;

            if (abs($dx) > 180.0){
                if ($x > 0){
                    while ($x1 < 0)
                        $x1 += 360;
                    while ($x2 < 0)
                        $x2 += 360;
                }else{
                    while ($x1 > 0)
                        $x1 -= 360;
                    while ($x2 > 0)
                        $x2 -= 360;
                }
                $dx = $x2 - $x1;
            }

            if (($x1 <= $x && $x2 > $x) || ($x1 >= $x && $x2 < $x)){
                $grad = ($pointLat - $lastPointLat) / $dx;
                $intersectAtLat = $lastPointLat + (($x - $x1) * $grad);
                if ($intersectAtLat > $lat)
                    $isInside = !$isInside;
            }
            $lastPointLat = $pointLat;
            $lastPointLon = $pointLon;
        }
        return $isInside;
    }

    /**
     * Make longitude always negative
     *
     * @param $lon
     *
     * @return mixed
     */
    private function properLon($lon){
        if($lon < 0) return $lon;
        else return ($lon * -1);
    }


}
