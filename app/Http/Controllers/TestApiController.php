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
class TestApiController extends Controller
{
    const STORM_REPORT = "STORM_REPORT";
    const CIMMS_REPORT = "CIMMS_REPORT";
    const SPOTTER_REPORT = "SPOTTER_REPORT";
    const TORNADO_REPORT = "TORNADO_REPORT";
    const ALLOWED_MILES = 45;
    const ALLOWED_MINUTES = 3600;

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
    public function test()
    {
        $this->reportPullService->run();
    }

    /**
     * Get all reports together
     *
     * @param $request
     *
     * @return array | object
     */
    public function getReports(Request $request)
    {
        try {
            $params = [];
            if ($request->get('uid')) {
                $uid = $request->get('uid');
                $params = explode(",", $uid);
            }
            if (count($params) < 3) {
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
        } catch (\Exception $ex) {
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
    public function getStormReports($reports, $lat, $lon)
    {
        try {
            $response = [];
            foreach ($reports as $report) {
                if ($report->report_type == self::STORM_REPORT) {
                    if ($this->isOneHourOld($report->unix_timestamp)) {
                        $report->longitude = $this->properLon($report->longitude);
                        $distance = $this->distance($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                        if ($this->isNear($distance)) {
                            if (strpos($report->event, 'hail') !== false) {
                                $matches = array();
                                preg_match('/[\d.]+/', $report->magnitude, $matches);
                                if (count($matches) > 0 && floatval($matches[0]) <= 1) {
                                    continue;
                                }
                            }

                            // prepare report
                            $bearing = $this->getBearing($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                            $time = gmdate("Y-m-d H:i:s", intval($report->unix_timestamp));
                            $remarks = "time: " . $time . " ";
                            $remarks .= $report->remarks;
                            $obj = [
                                'event'     => $report->event,
                                'size'      => $report->magnitude,
                                'remarks'   => $remarks,
                                'distance'  => $distance,
                                'range'     => $this->getBearingRange($bearing)
                            ];
                            array_push($response, $obj);
                        }
                    }
                } elseif ($report->report_type == self::SPOTTER_REPORT) {
                    $event = '';
                    $size = 0;
                    if ($report->tornado > 0 || $report->funnelcloud > 0 || $report->wallcloud) {
                        $event = 'tornado';
                    } elseif ($report->hail > 0) {
                        $event = 'hail';
                        $size = $report->hailsize;
                    }
                    if (!empty($event)) {
                        if ($this->isOneHourOld($report->unix_timestamp)) {
                            $report->longitude = $this->properLon($report->longitude);
                            $distance = $this->distance($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                            if ($this->isNear($distance)) {
                                $bearing = $this->getBearing($lat, $lon, doubleval($report->latitude), doubleval($report->longitude));
                                $time = gmdate("Y-m-d H:i:s", intval($report->unix_timestamp));
                                $remarks = "time: " . $time . " ";
                                $remarks .= $report->remarks;

                                $obj = [
                                    'event'    => $event,
                                    'remarks'  => $remarks,
                                    'distance' => $distance,
                                    'range'    => $this->getBearingRange($bearing)
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
            //$response = $this->shortenDistanceRange($response);
            return $response;
        } catch (\Exception $ex) {
            Log::error('Error: ' . $ex->getMessage());
        }
        return [];
    }

    /**
     * Calculate bearing range
     * 
     * @param int $bearing
     * 
     * @return string
     */
    private function getBearingRange($bearing)
    {
        if ($bearing > 10) {
            return ($bearing - 10) . "-" . ($bearing + 10);
        } else {
            return (350 + $bearing) . "-" . ($bearing + 10);
        }
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
    private function getCimmsReports($reports, $lat, $lon)
    {
        try {
            $response = [];
            foreach ($reports as $report) {
                if ($report->report_type == self::CIMMS_REPORT) {
                    $cObj = $this->calculateDistanceRange($lat, $lon, $report->latlon);
                    if ($this->isNear($cObj['distance'])) {
                        $obj = [
                            "id"          => $report->object_id,
                            "distance"    => $cObj['distance'],
                            "range"       => $cObj['range'],
                            //"bearings"    => $cObj['bearings'],
                            "ProbHail"    => $report->prob_hail . '%',
                            "ProbTor"     => $report->prob_tor . '%',
                            "ProbWind"    => $report->prob_wind . '%',
                            "mesh"        => $report->mesh,
                            "description" => $report->remarks
                        ];
                        array_push($response, $obj);
                    }
                }
            }
            $response = $this->shortenDistanceRange($response);
            return $response;
        } catch (\Exception $ex) {
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
    private function calculateDistanceRange($lat, $lon, $latlons)
    {
        $minDistance = 0;
        $minRange = 0;
        $maxRange = 0;
        $closestMaxRange = 0;
        $closestMinRange = 0;
        $validMinClosest = false;
        $validMaxClosest = false;
        $bearings = [];
        foreach (explode(':', $latlons) as $latlon) {
            $lat1 = doubleval(explode(',', $latlon)[0]);
            $lon1 = doubleval(explode(',', $latlon)[1]);
            $lon1 = $this->properLon($lon1);

            $distance = round($this->distance($lat, $lon, $lat1, $lon1));
            if ($minDistance == 0 || $minDistance > $distance) $minDistance = $distance;

            $range = round($this->getBearing($lat, $lon, $lat1, $lon1));
            if ($minRange == 0 || $minRange > $range) $minRange = $range;
            if ($maxRange == 0 || $maxRange < $range) $maxRange = $range;


            if ($range >= 1 && $range <= 15) $validMinClosest = true;
            if ($range > 345) $validMaxClosest = true;
            if ($range < 180 && $range > $closestMinRange) {
                $closestMinRange = $range;
            }

            if ($range > 180 && ($closestMaxRange == 0 || $range < $closestMaxRange)) {
                $closestMaxRange = $range;
            }

            $bearing = [
                'latlon'  => $latlon,
                'bearing' => $range
            ];
            array_push($bearings, $bearing);
        }

        if ($validMinClosest && $validMaxClosest) {
            $range =  $closestMaxRange . '-' . $closestMinRange;
        } else {
            $range = $minRange . '-' . $maxRange;
        }
        return [
            'distance' => $minDistance, 'range' => $range, 'bearings' => $bearings
        ];
    }

    /**
     * Shorten dinstance range by finding out overlapping
     * 
     * @param array $reports
     * 
     * @return array
     */
    private function shortenDistanceRange($reports)
    {

        // $reports = [];
        // array_push($reports, ["id" => "328579", "distance" => 18, "range" => "76-155"]);
        // array_push($reports, ["id" => "328562", "distance" => 34, "range" => "89-98"]);
        // array_push($reports, ["id" => "328562", "distance" => 34, "range" => "55-118"]);
        // array_push($reports, ["id" => "328577", "distance" => 24, "range" => "56-66"]);
        // array_push($reports, ["id" => "328319", "distance" => 7, "range"  => "66-114"]);


        // array_push($reports, ["id" => "328562", "distance" => 1, "range" => "100-120"]);
        // array_push($reports, ["id" => "328562", "distance" => 1, "range" => "90-20"]);
        // array_push($reports, ["id" => "328577", "distance" => 2, "range" => "80-140"]);
        // array_push($reports, ["id" => "328319", "distance" => 3, "range"  => "60-160"]);

        if (count($reports) == 0) return [];

        //var_dump($reports);
        //die;
        usort($reports, function ($first, $second) {
            return $first['distance'] > $second['distance'];
        });

        $reports[0]['cancel'] = false;
        $ranges = array_map('intval', explode('-', $reports[0]['range']));
        $max = $ranges[0];
        $min = $ranges[1];
        if ($min > $max) {
            $max = $ranges[1];
            $min = $ranges[0];
        } else {
            $tmp = $max;
            $max = $max + $min;
            $min = $tmp;
        }

        $duplicates = [];
        for ($i = 1; $i < count($reports); $i++) {
            $reportX = $reports[$i];
            $rangesX = array_map('intval', explode('-', $reportX['range']));

            $maxX = $rangesX[0];
            $minX = $rangesX[1];
            if ($minX > $maxX) {
                $maxX = $rangesX[1];
                $minX = $rangesX[0];
            } else {
                $tmp = $maxX;
                $maxX = $maxX + $minX;
                $minX = $tmp;
            }

            $reports[$i]['cancel'] = false;
            if ($minX >= $min && $maxX <= $max) {
                $reports[$i]['cancel'] = true;
                continue;
            }

            if ($minX < $min && $maxX <= $max) {
                $reports[$i]['range'] =  "$minX-" . ($min - 1);
                $min = $minX;
                continue;
            }

            if ($minX >= $min && $maxX > $max) {
                $reports[$i]['range'] = ($max + 1) . "-$maxX";
                $max = $maxX;
                continue;
            }

            if ($minX < $min && $maxX > $max) {
                $reports[$i]['range'] =  "$minX-" . ($min - 1);
                $min = $minX;
                array_push($duplicates, $reports[$i]);


                $reports[$i]['range'] = ($max + 1) . "-$maxX";
                $max = $maxX;
                continue;
            }
        }

        // var_dump($reports);
        // die;

        $reports = array_merge($reports, $duplicates);
        usort($reports, function ($first, $second) {
            return $first['distance'] > $second['distance'];
        });
        // var_dump($reports);
        // die;

        // $reports = array_filter($reports, function ($item) {
        //     return $item['cancel'] === false;
        // });

        // $reports = array_map(function ($item) {
        //     unset($item['cancel']);
        //     return $item;
        // }, $reports);
        //var_dump($reports);
        //die;

        $response = [];
        foreach ($reports as $report) {
            if ($report['cancel'] === false) {
                unset($report['cancel']);
                array_push($response, $report);
            }
        }
        return $response;
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
    private function getWarningReport($reports, $lat, $lon)
    {
        try {
            $response = [];
            foreach ($reports as $report) {
                if ($report->report_type == self::TORNADO_REPORT) {
                    $type = "";
                    if ($report->phenom == 'SV') {
                        $type = 'SVR';
                    }
                    if ($report->phenom == 'TO' || $report->phenom == 'TOR' || $report->phenom == 'TR') {
                        $type = 'TOR';
                    }
                    if (!empty($type)) {
                        $is_inside = $this->contains($lat, $lon, $report->latlon);
                        if ($is_inside) {
                            $obj = [
                                "type"        => $type,
                                "description" => $report->remarks
                            ];
                            array_push($response, $obj);
                        }
                    }
                }
            }
            return $response;
        } catch (\Exception $ex) {
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
    function distance($lat1, $lon1, $lat2, $lon2)
    {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        } else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            return round($miles);
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
    function getBearing($lat1, $lon1, $lat2, $lon2)
    {
        return (rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * cos(deg2rad($lat2)), cos(deg2rad($lat1)) *
            sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360) % 360;
    }

    /**
     * Calculate compass direction from bearing
     *
     * @param $bearing
     *
     * @return string
     */
    function getCompassDirection($bearing)
    {
        $tmp = round($bearing / 22.5);
        switch ($tmp) {
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
    private function contains($lat, $lon, $latlons)
    {
        $_vertices = explode(':', $latlons);
        $lastPoint = $_vertices[count($_vertices) - 1];
        $lastPointLat = doubleval(explode(',', $lastPoint)[0]);
        $lastPointLon = $this->properLon(doubleval(explode(',', $lastPoint)[1]));
        $lastPointLat = $lastPointLat / 100;
        $lastPointLon = $lastPointLon / 100;

        $isInside = false;
        $x = $lon;
        foreach ($_vertices as $point) {
            $pointLat = doubleval(explode(',', $point)[0]);
            $pointLon = doubleval(explode(',', $point)[1]);
            $pointLon = $this->properLon($pointLon);

            $pointLat = $pointLat / 100;
            $pointLon = $pointLon / 100;

            $x1 = $lastPointLon;
            $x2 = $pointLon;
            $dx = $x2 - $x1;

            if (abs($dx) > 180.0) {
                if ($x > 0) {
                    while ($x1 < 0)
                        $x1 += 360;
                    while ($x2 < 0)
                        $x2 += 360;
                } else {
                    while ($x1 > 0)
                        $x1 -= 360;
                    while ($x2 > 0)
                        $x2 -= 360;
                }
                $dx = $x2 - $x1;
            }

            if (($x1 <= $x && $x2 > $x) || ($x1 >= $x && $x2 < $x)) {
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
    private function properLon($lon)
    {
        if ($lon < 0) return $lon;
        else return ($lon * -1);
    }


    /**
     * Checking the unix timestamp is one hour old or not
     *
     * @param $unix_timestamp
     *
     * @return bool
     */
    private function isOneHourOld($unix_timestamp)
    {
        $cd = new \DateTime(gmdate("Y-m-d H:i:s", intval($unix_timestamp)));
        $now = new \DateTime(gmdate("Y-m-d H:i:s"));
        $diff = $now->getTimestamp() - $cd->getTimestamp();
        return $diff <= self::ALLOWED_MINUTES;
    }

    /**
     * Is distance is near based on allowed miles
     *
     * @param $distance
     *
     * @return bool
     */
    private function isNear($distance)
    {
        return $distance <= self::ALLOWED_MILES;
    }
}
