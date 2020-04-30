<?php
namespace App\Services;

use DB;
use App\Report;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Process\Process;

/**
 * Report pull service handler
 * @package App\Services
 */
class ReportPullService
{
    const STORM_REPORT = "STORM_REPORT";
    const CIMMS_REPORT = "CIMMS_REPORT";
    const SPOTTER_REPORT = "SPOTTER_REPORT";
    const TORNADO_REPORT = "TORNADO_REPORT";

    /*
     * Initialize the class
     */
    public function __construct(){
    }

    /**
     * Run the service to pull the reports from different sources
     * and save into reports table
     *
     * @return void
     */
    public function run(){
        Log::info('ReportPullService:: Start');
        $client = new Client();
        $this->pullStormReports($client);
        $this->pullCimms($client);
        $this->pullSpotterNetworks($client);
        $this->pullTornadoWarning($client);
        Log::info('ReportPullService:: End');
    }

    /**
     * Pull Storm Reports data
     *
     * @return void
     */
    public function pullStormReports($client){
        try{
            $url = 'http://rs.allisonhouse.com/feeds/781bb370445428356172366d2f5c0507/lsr.php';
            $response = $this->get($client, $url);
            Report::where('report_type', self::STORM_REPORT)->delete();
            if(!empty($response)){
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
                    $obj = explode(";", $line);
                    if(count($obj) != 10){
                        continue;
                    }
                    //var_dump(count($obj));
                    $report = new Report();
                    $report->report_type = self::STORM_REPORT;
                    $report->object_id = $obj[0];
                    $report->event = strtolower($obj[1]);
                    $report->unix_timestamp = $obj[2];
                    $report->latitude = doubleval(explode(",", $obj[3])[0]);
                    $report->longitude = doubleval(explode(",", $obj[3])[1]);
                    $report->magnitude = $obj[4];
                    $report->city = $obj[5];
                    $report->county = $obj[6];
                    $report->state = $obj[7];
                    $report->source = $obj[8];
                    $report->remarks = $obj[9];
                    if(strpos($report->event, 'tornado') !== false || strpos($report->event, 'hail') !== false){
                        $cd = new \DateTime( gmdate("Y-m-d H:i:s", intval($report->unix_timestamp)) );
                        $now = new \DateTime( gmdate("Y-m-d H:i:s") );
                        $diff = $now->getTimestamp() - $cd->getTimestamp();
                        if($diff <= 3600){
                            $report->save();
                        }
                    }
                }
            }
        }catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }

    /**
     * Pull Spotter Networks data
     *
     * @return void
     */
    public function pullSpotterNetworks($client){
        try{
            $url = 'https://www.spotternetwork.org/reports';
            $response = $this->post($client, $url, "90993");
            Report::where('report_type', self::SPOTTER_REPORT)->delete();
            $objs = json_decode($response);
            if($objs && isset($objs->reports)){
                foreach($objs->reports as $obj){
                    $report = new Report();
                    $report->report_type = self::SPOTTER_REPORT;
                    $report->unix_timestamp = $obj->unix;
                    $report->latitude = $obj->lat;
                    $report->longitude = $obj->lon;
                    $report->city = $obj->city1;
                    $report->state = $obj->state;
                    $report->tornado = $obj->tornado;
                    $report->funnelcloud = $obj->funnelcloud;
                    $report->wallcloud = $obj->wallcloud;
                    $report->hail = $obj->hail;
                    $report->hailsize = $obj->hailsize;
                    $report->remarks = $obj->narrative;
                    $report->save();
                }
            }
        }catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }

    /**
     * Pull Tornado Warning data
     *
     * @return void
     */
    public function pullTornadoWarning($client){
        try{
            $url = 'https://rs.allisonhouse.com/ww.txt';
            $response = $this->get($client, $url);
            Report::where('report_type', self::TORNADO_REPORT)->delete();
            if(!empty($response)){
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
                    $obj = explode(";", $line);
                    if(count($obj) < 5){
                        continue;
                    }
                    $report = new Report();
                    $report->report_type = self::TORNADO_REPORT;
                    $report->phenom = explode(".", $obj[0])[0];
                    $report->significance = explode(".", $obj[0])[1];
                    $report->unix_timestamp = $obj[1];
                    $report->office = explode(":", $obj[2])[0];
                    $report->office_id = explode(":", $obj[2])[1];
                    $report->latlon = $obj[4];
                    $report->save();
                }
            }
        }catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }

    /**
     * Pull Cimms data
     *
     * @return void
     */
    public function pullCimms($client){
        try{
            $url = 'https://cimss.ssec.wisc.edu/severe_conv/NOAACIMSS_PROBSEVERE';
            $response = $this->get($client, $url);
            Report::where('report_type', self::CIMMS_REPORT)->delete();
            if(!empty($response)){
                $report = null; $newMessage = false; $skip = false;
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
                    if (strpos($line, "Line:") !== false) {
                        $newMessage = true;
                        preg_match("/(?<=ProbHail: )\d*(?=%)/", $line, $hail);
                        preg_match("/(?<=ProbTor: )\d*(?=%)/", $line, $tor);
                        preg_match("/(?<=ProbWind: )\d*(?=%)/", $line, $wind);
                        preg_match("/(?<=MESH: ).+?(?=\\\\n)/", $line, $mesh);


                        preg_match("/(?<=% ).*(?=\")/", $line, $m);
                        preg_match("/(?<=Object ID: )\d*/", $m[0], $id);
                        $report = new Report();
                        $report->report_type = self::CIMMS_REPORT;
                        $report->object_id = $id[0];
                        $report->prob_hail = $hail[0];
                        $report->prob_wind = $wind[0];
                        $report->prob_tor = $tor[0];
                        $report->mesh = $mesh[0];
                        $report->remarks = $m[0];
                        $report->latlon = '';
                        continue;
                    }elseif ($line == 'End:') {
                        $newMessage = false;
                        $report->save();
                    }elseif($newMessage) {
                        $report->latlon = empty($report->latlon)? $line : ($report->latlon . ':' . $line);
                    }
                }
            }
        }catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }

    /**
     * GET REQUEST: Get report data from url as string
     *
     * @param $client
     * @param $url
     *
     * @return string
     */
    private function get($client, $url){
        try {
            $res = $client->request("GET", $url, [
                'headers' => [
                    'User-Agent'   => isset($_SERVER['HTTP_USER_AGENT'])? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36',
                ]]);
            return $res->getBody()->getContents();
        }catch (\Exception $ex){
            Log::error($ex->getMessage());
        }
        return "";
    }

    /**
     * POST REQUEST: Get report data from url using post request
     *
     * @param $client
     * @param $url
     * @param $id
     *
     * @return string
     */
    private function post($client, $url, $id){
        try {
            $res = $client->request("POST", $url, [
                'headers' => [
                    'User-Agent'   => isset($_SERVER['HTTP_USER_AGENT'])? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode(
                    [
                        'id' => $id
                    ]
                )
            ]);
            return $res->getBody()->getContents();
        }catch (\Exception $ex){
            Log::error($ex->getMessage());
        }
        return "";
    }
}