<?php

namespace App\Http\Controllers;

/**
 * Utility class
 */
class Util
{

    /**
     * String replace of first occurance
     * 
     * @param string $from
     * @param string $to
     * @param string $content
     * @return string
     */
    public static function str_replace_first($from, $to, $content)
    {
        $from = '/' . preg_quote($from, '/') . '/';
        return preg_replace($from, $to, $content, 1);
    }

    /**
     * Refactoring the CIMMS description data
     * 
     * @param string $data
     * 
     * @return array
     */
    public static function refactoring_description($data)
    {
        try {
            //var_dump($data);
            $data = self::str_replace_first("\\n- ", ";", 'TIME:' . $data);
            $data = str_replace("\\n- ", ";", $data);

            $labels["ENI Flash Rate"]                         = "Lightning Flash Rate";
            $labels["ENI Flash Density (max in last 30 min)"] = "Lightning Flash Density";
            $labels["Max LLAzShear"]                          = "MAX Low Level Shear";
            $labels["98% LLAzShear"]                          = "Low Level Shear";
            $labels["98% MLAzShear"]                          = "Mid Level Shear";
            $labels["Norm. vert. growth rate"]                = "Vertical Growth Rate";
            $labels["EBShear"]                                = "Effective Bulk Shear";
            $labels["SRH 0-1km AGL"]                          = "SRH 0-1km";
            //var_dump($labels);

            $shears = ["MAX Low Level Shear", "Low Level Shear", "Mid Level Shear"];

            $items = array_map('trim', explode(';', $data));
            //var_dump($items);
            // die;
            $excludes = ["GLM: max FED", "Avg beam height (ARL)", "sum FCD", "min flash area"];

            $formatted = [];
            foreach ($items as $item) {
                [$code, $value] = array_map('trim', explode(':', $item));
                if (!in_array($code, $excludes)) {
                    $code = isset($labels[$code]) ? $labels[$code] : $code;

                    if (in_array($code, $shears)) {
                        $pattern = "/[0-9.]*/i";
                        preg_match($pattern, $value, $refine_value);

                        $sevearity = "";
                        $refine_value = floatval($refine_value[0]);

                        if ($refine_value <= .004) {
                            $sevearity = "(LOW)";
                        } elseif ($refine_value >= .005 && $refine_value <= .009) {
                            $sevearity = "(MEDIUM)";
                        } elseif ($refine_value >= .010 && $refine_value <= .014) {
                            $sevearity = "(HIGH)";
                        } elseif ($refine_value >= .015) {
                            $sevearity = "(EXTREME)";
                        }
                        $value = "$refine_value $sevearity";
                    }

                    $formatted[$code] = $value;
                }
            }

            //var_dump($formatted);
            //die;
            return $formatted;
        } catch (\Exception $e) {
            return [];
        }
    }
}
