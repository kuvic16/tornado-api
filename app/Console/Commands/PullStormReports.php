<?php

namespace App\Console\Commands;

use App\Report;
use App\Services\HttpService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use App\Services\ReportPullService;
use Illuminate\Support\Facades\Log;

class PullStormReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:PullStormReports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull storm reports';

    /**
     * Report pull service instance
     * 
     * @var App\Services\ReportPullService
     */
    protected $reportPullService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ReportPullService $reportPullService)
    {
        parent::__construct();
        $this->reportPullService = $reportPullService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo 'Pull the data from Storm Report API \n';
        $this->reportPullService->pullStormReports(new Client());
        echo 'Completed';
    }
}
