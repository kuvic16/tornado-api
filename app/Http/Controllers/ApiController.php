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
    }

    /**
     * Get all reports together
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
            $lon = doubleval($params[2]);
            $reports = Report::all();
            $response = [
                'storm_report'   => $this->getStormReports($reports, $lat, $lon),
                'cimms'          => $this->getCimmsReports($reports, $lat, $lon),
                'spotter'            => $this->getSvrReport(),
                'warning'        => $this->getWarningReport()
            ];
            //die;
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }

    /**
     * Get Storm reports
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
                    //if ($diff <= 36000) {
                        $distance = $this->distance($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                        if ($distance <= 1500) {
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
                            $direction = $this->getCompassDirection($bearing);
                            $obj = [
                                'event' => $report->event,
                                'time' => gmdate("Y-m-d H:i:s", intval($report->unix_timestamp)),
                                'size' => $report->magnitude,
                                'remarks' => $report->remarks,
                                'distance' => $distance,
                                'direction' => $bearing . ' ' . $direction
                            ];
                            array_push($response, $obj);
                            break;
                        }
                    //}
                }
            }
            return $response;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }



    private function getCimmsReports($reports, $lat, $lon){
        try {
            $response = [];
            foreach($reports as $report) {
                if ($report->report_type == self::CIMMS_REPORT) {
                    //var_dump($report->latlon); die;
                    $obj = $this->calculateDistanceBearing($lat, $lon, $report->latlon);
                    $obj = [
                        "id"                => $report->object_id,
                        "distance"          => 0,
                        "range"             => '',
                        "ProbHail"          => $report->prob_hail . '%',
                        "ProbTor"           => $report->prob_tor . '%',
                        "ProbWind"          => $report->prob_wind . '%',
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

    private function calculateDistanceBearing($lat, $lon, $latlons){
        $response = [
            'distance' => 0, ''
        ];
        foreach(explode(':', $latlons) as $latlon){

        }
    }

    private function getSvrReport(){
        $response = [];
        $obj = [
            "type" => '',
            "distance" => '',
            "bearing" => '',
            "description" => ''
        ];
        array_push($response, $obj);

        return $response;
    }

    private function getWarningReport(){
        $response = [];
        $obj = [
            "type" => 'SVR/TOR',
            "description" => ''
        ];
        array_push($response, $obj);

        return $response;
    }


    /**
     * Get Tornado Warning
     * @return array
     */
    public function tornadoWarning(Request $request){
        $client = new Client();
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
            $lon = doubleval($params[2]);

            Log::info('Downloading storm feed...');
            $url = "http://rs.allisonhouse.com/feeds/781bb370445428356172366d2f5c0507/lsr.php";
            $res = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                ]]);

            $response = $res->getBody()->getContents();

            $reports = [];
            foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
                $obj = explode(";", $line);
                if(count($obj) != 10){
                    continue;
                }
                //var_dump(count($obj));
                $event = [
                    'NUM' => $obj[0],
                    'EVENT' => strtolower($obj[1]),
                    'UNIX_TIMESTAMP' => $obj[2],
                    'LATITUDE' => explode(",", $obj[3])[0],
                    'LONGITUDE' => explode(",", $obj[3])[1],
                    'MAGNITUDE' => $obj[4],
                    'CITY' => $obj[5],
                    'COUNTY' => $obj[6],
                    'STATE' => $obj[7],
                    'SOURCE' => $obj[8],
                    'REMARKS' => $obj[9]
                ];

                if(strpos($event['EVENT'], 'snow') !== false || strpos($event['EVENT'], 'hail') !== false){
                    $cd = new \DateTime( gmdate("Y-m-d H:i:s", intval($event['UNIX_TIMESTAMP'])) );
                    $now = new \DateTime( gmdate("Y-m-d H:i:s") );
                    $diff = $now->getTimestamp() - $cd->getTimestamp();
                    if($diff <= 3600){
                        $distance = $this->distance($lat, $lon, doubleval($event['LATITUDE']), doubleval($event['LONGITUDE']));

                        if($distance <= 1500){
                            //var_dump($distance);
                            if(strpos($event['EVENT'], 'hail') !== false){
                                $matches = array();
                                preg_match('/[\d.]+/', $event['MAGNITUDE'], $matches);
                                if(count($matches) > 0 && floatval($matches[0]) <= 1){
                                    continue;
                                }
                            }

                            // prepare report
                            $bearing = $this->getBearing($lat, $lon, doubleval($event['LATITUDE']), doubleval($event['LONGITUDE']));
                            $direction = $this->getCompassDirection($bearing);
                            $report = [
                                'event' => $event['EVENT'],
                                'time' => gmdate("Y-m-d H:i:s", intval($event['UNIX_TIMESTAMP'])),
                                'size' => $event['MAGNITUDE'],
                                'remarks' => $event['REMARKS'],
                                'distance' => $distance,
                                'direction' => $bearing . ' ' . $direction
                            ];
                            array_push($reports, $report);
                        }
                    }
                }
            }
            return $reports;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }


    /**
     * Get CIMMS Hail Probability
     *
     * @param Illuminate\Http\Request
     * @return array
     */
    public function cimmsHailProbability(Request $request){
        return $this->prepareCimmsResponse($request, "ProbHail", "hail_probability");
    }

    /**
     * Get CIMMS Tornado Probability
     *
     * @param Illuminate\Http\Request
     * @return array
     */
    public function cimmsTornadoProbability(Request $request){
        return $this->prepareCimmsResponse($request, "ProbTor", "tornado_probability");
    }


    /**
     * Get CIMMS Wind Probability
     *
     * @param Illuminate\Http\Request
     * @return array
     */
    public function cimmsWindProbability(Request $request){
        return $this->prepareCimmsResponse($request, "ProbWind", "wind_probability");
    }







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

    function getBearing($lat1, $lon1, $lat2, $lon2) {
        return (rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * cos(deg2rad($lat2)), cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360) % 360;
    }

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


}
