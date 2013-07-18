<?php
/**
 * Transform all json files to two big CSV files : recette.csv & depense.csv
 *
 * @author Mehdi Achour (tw: @mac_hour)
 */

define('JSON_DIR', '/tmp/json/');

define('CSV_DIR', '/tmp/');
define('CSV_PATTERN', '%s.csv');

foreach (array('recette', 'depense') as $type) {
    $buffer = 'Date;RegionId;RegionLabel;MunicipaliteId;MunicipaliteLabel;Nef9a;Joz2;9ism;Predicted;Current;Done' . "\n";
    foreach (glob(JSON_DIR . '/*-' . $type . '.json') as $e) {
        $data = json_decode(file_get_contents($e));
        foreach ($data->nef9at as $nef9a) {
            foreach ($nef9a->ejza2 as $joz2) {
                foreach ($joz2->a9sem as $_9ism) {
                    $csv = array($data->date, $data->region->id, $data->region->label, $data->municipalite->id, $data->municipalite->label);
                    $csv[] = $nef9a->title;
                    $csv[] = $joz2->title;
                    $csv[] = $_9ism->title;
                    $csv[] = $_9ism->predicted;
                    $csv[] = $_9ism->current;
                    $csv[] = $_9ism->done;
                    $buffer .= '"' . implode('";"', $csv) . "\"\n";
                }
            } 
        }
    }
    file_put_contents(CSV_DIR . '/' . sprintf(CSV_PATTERN, $type), $buffer);
}