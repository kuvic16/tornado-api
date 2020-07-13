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
    public function overlapping(Request $request)
    {
        $response = [
            'ranges'  => "",
            'results' => []
        ];

        if (isset($request->ranges) && !empty($request->ranges)) {
            $response['ranges'] = $request->ranges;
            $ranges = explode("\n", $request->ranges);
            $reports = [];
            foreach ($ranges as $range) {
                $fields = explode(",", trim($range));
                array_push($reports, ["distance" => trim($fields[0]), "range" => trim($fields[1]) . "-" . trim($fields[2])]); // 100-150
            }

            $response['results'] = $this->shortenDistanceRange($reports);
        }
        return view('overlapping', compact('response'));
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

    private function sort(&$reports, $sortColumn)
    {
        usort($reports, function ($first, $second) use ($sortColumn) {
            return $first[$sortColumn] > $second[$sortColumn];
        });
    }

    private function setCancelExtraPart(&$reports)
    {
        //var_dump($reports);
        //die;
        // set cancel for original range
        foreach ($reports as $report) {
            if ($report['extra'] == true && $report['range'] !== $report['min'] . "-" . $report['max']) {
                $reports = array_map(function ($item) use ($report) {
                    if ($item['extra'] === false && $item['original_range'] === $report['original_range']) {
                        $item['cancel'] = true;
                    }
                    return $item;
                }, $reports);
            } elseif ($report['extra'] == true && $report['cancel'] == true) {
                $reports = array_map(function ($item) use ($report) {
                    if ($item['extra'] === false && $item['original_range'] === $report['original_range']) {
                        $item['cancel'] = true;
                    }
                    return $item;
                }, $reports);
            }
        }

        // set cancel for extra part
        foreach ($reports as $report) {
            if ($report['consider'] == false && $report['cancel'] == false) {
                $reports = array_map(function ($item) use ($report) {
                    if ($item['extra'] === true && $item['original_range'] === $report['original_range']) {
                        $item['cancel'] = true;
                    }
                    return $item;
                }, $reports);
            }
        }
    }

    private function createExtraPartIfMinInLast(&$reports)
    {
        $newPart = [];
        for ($i = 0; $i < count($reports); $i++) {
            $report = $reports[$i];
            $range = array_map('intval', explode('-', $report['range']));

            $reports[$i]['consider'] = true;
            $reports[$i]['extra'] = false;
            $reports[$i]['original_range'] = $reports[$i]['range'];

            $max = $range[0];
            $min = $range[1];
            if ($min > $max) {
                $max = $range[1];
                $min = $range[0];
            } else {
                // preparing first part
                $reports[$i]['min'] = $max;
                $reports[$i]['max'] = 359;
                $reports[$i]['range'] = "$max-359";
                $reports[$i]['extra'] = true;
                array_push($newPart, $reports[$i]);
                if ($min > 0) {
                    // preparing second part
                    $reports[$i]['min'] = 0;
                    $reports[$i]['max'] = $min;
                    $reports[$i]['range'] = "0-$min";
                    array_push($newPart, $reports[$i]);
                }

                // keeping original
                $reports[$i]['consider'] = false;
                $reports[$i]['range'] = $reports[$i]['original_range'];
                $reports[$i]['extra'] = false;
            }
            $reports[$i]['min'] = $min;
            $reports[$i]['max'] = $max;
        }
        $reports = array_merge($reports, $newPart);
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
        if (count($reports) == 0) return [];

        $this->createExtraPartIfMinInLast($reports);
        $this->sort($reports, "distance");
        //var_dump($reports);
        //die;

        $duplicates = [];
        $correctRanges = [];
        for ($i = 0; $i < count($reports); $i++) {
            $reports[$i]['cancel'] = false;
            if ($reports[$i]['consider'] == false) continue;

            //$range = array_map('intval', explode('-', $reports[$i]['range']));
            //[$min, $max] = $this->getMinMax($range);

            $min = $reports[$i]['min'];
            $max = $reports[$i]['max'];

            if (empty($correctRanges)) {
                array_push($correctRanges, [$min, $max]);
                continue;
            }
            $this->sort($correctRanges, 0);


            $minValue = $correctRanges[0][0];
            $maxValue = $correctRanges[count($correctRanges) - 1][1];

            //var_dump($minValue . "-" . $maxValue);

            if ($max < $minValue || $min > $maxValue) {
                array_push($correctRanges, [$min, $max]);
                continue;
            }

            if ($min < $minValue) {
                $segments = [];
                $moreCorrectRanges = [];
                $tmpMax = $max;
                $tmpMin = $min;
                $prevMin = 0;
                $prevMax = 0;
                foreach ($correctRanges as $correctRange) {
                    $nMin = $correctRange[0];
                    $nMax = $correctRange[1];

                    if ($nMin - $prevMax <= 1) {
                        $prevMin = $nMin;
                        $prevMax = $nMax;
                        $tmpMin = $nMax + 1;
                        continue;
                    }

                    if ($tmpMax >= $nMin && $tmpMax <= $nMax) {
                        $minX = $tmpMin;
                        $maxX = $nMin - 1;
                        array_push($moreCorrectRanges, [$minX, $maxX]);
                        $reports[$i]['range'] =  "$minX-$maxX";
                        array_push($segments, $reports[$i]);
                        break;
                    } elseif ($tmpMax > $nMax) {
                        $minX = $tmpMin;
                        $maxX = $nMin - 1;
                        array_push($moreCorrectRanges, [$minX, $maxX]);
                        $reports[$i]['range'] =  "$minX-$maxX";
                        array_push($segments, $reports[$i]);

                        $tmpMin = $nMax + 1;
                    }
                    $prevMin = $nMin;
                    $prevMax = $nMax;
                }

                if ($max > $maxValue) {
                    $minX = $tmpMin;
                    $maxX = $max;
                    array_push($moreCorrectRanges, [$minX, $maxX]);
                    $reports[$i]['range'] =  "$minX-$maxX";
                    array_push($segments, $reports[$i]);
                }
                //var_dump($moreCorrectRanges);
                $correctRanges = array_merge($correctRanges, $moreCorrectRanges);
            } elseif ($min >= $minValue) {
                $segments = [];
                $moreCorrectRanges = [];
                $tmpMax = $max;
                $tmpMin = $min;
                $prevMin = 0;
                $prevMax = $max;
                foreach ($correctRanges as $correctRange) {
                    $nMin = $correctRange[0];
                    $nMax = $correctRange[1];

                    if ($nMin <= $tmpMax && $tmpMax <= $nMax) {
                        if ($nMin - $prevMax <= 1)
                            break;
                    }

                    if ($nMin <= $tmpMin && $tmpMin <= $nMax && $nMin <= $tmpMax && $tmpMax <= $nMax) {
                        break;
                    }

                    if ($tmpMin >= $nMin) {
                        if ($tmpMin <= $nMax)
                            $tmpMin = $nMax + 1;
                        $prevMin = $nMin;
                        $prevMax = $nMax;
                        continue;
                    }

                    if ($nMin - $prevMax <= 1) {
                        $prevMin = $nMin;
                        $prevMax = $nMax;
                        $tmpMin = $nMax + 1;
                        continue;
                    }

                    if ($tmpMax >= $nMin && $tmpMax <= $nMax) {
                        $minX = $tmpMin;
                        $maxX = $nMin - 1;
                        array_push($moreCorrectRanges, [$minX, $maxX]);
                        $reports[$i]['range'] =  "$minX-$maxX";
                        array_push($segments, $reports[$i]);
                        break;
                    } elseif ($tmpMax > $nMax) {
                        $minX = $tmpMin;
                        $maxX = $nMin - 1;
                        array_push($moreCorrectRanges, [$minX, $maxX]);
                        $reports[$i]['range'] =  "$minX-$maxX";
                        array_push($segments, $reports[$i]);

                        $tmpMin = $nMax + 1;
                    } elseif ($tmpMax < $nMin) {
                        $minX = $tmpMin;
                        $maxX = $tmpMax;
                        array_push($moreCorrectRanges, [$minX, $maxX]);
                        $reports[$i]['range'] =  "$minX-$maxX";
                        array_push($segments, $reports[$i]);
                        break;
                    }

                    $prevMin = $nMin;
                    $prevMax = $nMax;
                }

                if ($max > $maxValue) {
                    $minX = $tmpMin;
                    $maxX = $max;
                    array_push($moreCorrectRanges, [$minX, $maxX]);
                    $reports[$i]['range'] =  "$minX-$maxX";
                    array_push($segments, $reports[$i]);
                }
                //var_dump($moreCorrectRanges);
                $correctRanges = array_merge($correctRanges, $moreCorrectRanges);
            }

            $reports[$i]['cancel'] = true;
            if (count($segments) > 0) {
                $duplicates = array_merge($duplicates, $segments);
            }
        }

        $this->sort($correctRanges, 0);
        //var_dump($correctRanges);
        //die;

        $reports = array_merge($reports, $duplicates);
        $this->sort($reports, 'distance');
        $this->setCancelExtraPart($reports);
        //var_dump($reports);
        //die;


        $response = [];
        foreach ($reports as $report) {
            if ($report['cancel'] === false) {
                unset($report['cancel']);
                array_push($response, $report);
            }
        }
        //var_dump($response); die;

        return $response;
        //return $reports;
    }

    private function getMinMax($range)
    {
        $max = $range[0];
        $min = $range[1];
        if ($min > $max) {
            $max = $range[1];
            $min = $range[0];
        } else {
            $tmp = $max;
            $max = $max + $min;
            $min = $tmp;
        }
        return [$min, $max];
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