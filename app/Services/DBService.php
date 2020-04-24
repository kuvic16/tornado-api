<?php
namespace App\Services;

use DB;
use Closure;
use stdClass;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DBService {

    public static function callProc($procName, $parameters = null, $isExecute = false)
    {
        $syntax = '';
        if($parameters) {
            for ($i = 0; $i < count($parameters); $i++) {
                $syntax .= (!empty($syntax) ? ',' : '') . '?';
            }
            $syntax = 'CALL ' . $procName . '(' . $syntax . ');';
        }else{
            $syntax = 'CALL ' . $procName . ';';
        }

        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        $stmt = $pdo->prepare($syntax,[\PDO::ATTR_CURSOR=>\PDO::CURSOR_SCROLL]);
        if($parameters) {
            for ($i = 0; $i < count($parameters); $i++) {
                $stmt->bindValue((1 + $i), $parameters[$i]);
            }
        }
        $exec = $stmt->execute();
        if (!$exec) return $pdo->errorInfo();
        if ($isExecute) return $exec;

        $results = [];
        do {
            try {
                $results[] = $stmt->fetchAll(\PDO::FETCH_OBJ);
            } catch (\Exception $ex) {

            }
        } while ($stmt->nextRowset());


        if (1 === count($results)) return $results[0];
        return $results;
    }
}