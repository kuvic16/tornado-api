<?php

namespace App\Http\Controllers;

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

    /**
     * Initialize the class
     */
    public function __construct()
    {
    }

    /**
     * Get Storm reports
     * @return array
     */
    public function stormReports(Request $request){
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



    /**
     * Prepare CIMMS response
     *
     * @param $request
     * @param $probType
     * @param $probLabel
     * @return array
     */
    private function prepareCimmsResponse($request, $probType, $probLabel){
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

            Log::info('Downloading cimms hail probability...');
            $url = "https://cimss.ssec.wisc.edu/severe_conv/NOAACIMSS_PROBSEVERE";
            $res = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                ]]);

            $response = $res->getBody()->getContents();

            $reports = []; $newMessage = false; $skip = false; $index = -1; $minDistance = 0; $next = false;
            $minBearing = 0; $maxBearing = 0;
            foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
                if (strpos($line, "Line:") !== false) {
                    $newMessage = true;
                    $prob = null;
                    preg_match("/(?<=ProbHail: )\d*(?=%)/", $line, $hail);
                    if($probType == 'ProbHail') $prob =  $hail;

                    preg_match("/(?<=ProbTor: )\d*(?=%)/", $line, $tor);
                    if($probType == 'ProbTor') $prob =  $tor;

                    preg_match("/(?<=ProbWind: )\d*(?=%)/", $line, $wind);
                    if($probType == 'ProbWind') $prob =  $wind;

//                    if (count($prob) > 0 && intval($prob[0]) == 0) {
//                        $skip = true;
//                    } else {
                    $index = $index + 1;
                    $minDistance = 0;
                    $minBearing = 0; $maxBearing = 0;
                    preg_match("/(?<=% ).*(?=\")/", $line, $m);
                    preg_match("/(?<=Object ID: )\d*/", $m[0], $id);
                    $reports[$index] = [
                        "id"                => $id[0],
                        "distance"          => 0,
                        "range"             => '',
                        "ProbHail"          => $hail[0] . '%',
                        "ProbTor"           => $tor[0] . '%',
                        "ProbWind"          => $wind[0] . '%',
                        "description"       => $m[0]
                    ];
//                    }
                    continue;
                }elseif ($line == 'End:') {
                    $newMessage = false;
                    $skip = false;
                    $reports[$index]["distance"] = $minDistance;
                    $reports[$index]["range"] = $minBearing . "-" . $maxBearing;
                }elseif($newMessage) {
                    if ($skip) continue;
                    else {
                        $distance = $this->distance($lat, $lon, doubleval(explode(",", $line)[0]), doubleval(explode(",", $line)[1]));
                        if ($minDistance == 0 || $minDistance > $distance) $minDistance = $distance;

                        $bearing = $this->getBearing($lat, $lon, doubleval(explode(",", $line)[0]), doubleval(explode(",", $line)[1]));
                        if ($minBearing == 0 || $minBearing > $bearing) $minBearing = $bearing;
                        if ($maxBearing == 0 || $maxBearing < $bearing) $maxBearing = $bearing;
                    }
                }

            }
            return $reports;
        }catch (\Exception $ex){
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
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
