<?php
/**
 * Data mining from the ministry of finances website : 
 *     Municipalities expenses & revenues
 *
 * @author Mehdi Achour (tw: @mac_hour)
 */

// User agent used for HTTP requests
define('_UA',  'OpenGovTn (http://opengovtn.org/)');

// Website url patterns
define('_URL', 'http://www.finances.gov.tn/applications/budget_collectivites/afficher_%s.php');
define('_MURL', 'http://www.finances.gov.tn/applications/budget_collectivites/municipalite.php?idr=%d');

// Json files name pattern & destination
define('JSON_DIR', '/tmp/json/');
define('JSON_PATTERN', '%s-%s-%s.json');

// global definition : types of data
$TYPES = array('recette', 'depense');



// MAIN
debug('Getting regions ..');
$regions = getRegions();
debug(' - got %d regions', count($regions));

if (!is_dir(JSON_DIR)) mkdir(JSON_DIR);

foreach ($regions as $r_id => $r_label) {
    debug('Getting municipalites for %s (%d)', $r_label, $r_id);
    foreach (getMunicipalites($r_id) as $m_id => $m_label) {
        debug('Getting data for %s %s (%d, %d)', $r_label, $m_label, $r_id, $m_id);        
        foreach ($TYPES as $type) {
            $json = getData($type, $r_id, $r_label, $m_id, $m_label);
            file_put_contents(JSON_DIR . '/' . sprintf(JSON_PATTERN, $r_id, $m_id, $type), $json);
        }
        die("Remove me to process more than a municipality. Please don't exhaust www.finances.gov.tn server :)");
    }
}


// FUNCTIONS

/**
 * Parse a set of <option> tags and return a hash of @value/text()
 *
 * @param string $url The url to get the HTML from
 *
 * @return array Returns an associative array of option's value => option's text
 */
function parseOptions($url) {
    $ctx = stream_context_create(array(
        'http' => array(
            'method' => "GET",
            'header' => "Accept-language: en\r\n" .
            sprintf("User-Agent: %s\r\n", _UA) .
            "Connection: close\r\n",
        )
    ));

    $html = str_replace("\n", "", tidy(file_get_contents($url, false, $ctx)));
    preg_match_all('!<option value="(\d+)">(.*)</option>!U', $html, $matches);

    $result = array();
    foreach ($matches[1] as $i => $id) {
        $result[$id] = trim($matches[2][$i]);
    }
    return $result;
}

/**
 * Gets all municipalities for given a region
 *
 * @param int $region The Region id
 *
 * @return array The list of municipalities
 */
function getMunicipalites($region) {
    return parseOptions(sprintf(_MURL, $region));
}

/**
 * Gets all regions
 *
 * @return array The list of regions
 */
function getRegions() {
    return parseOptions(sprintf(_URL, $GLOBALS['TYPES'][0]));
}


/**
 * Gets the data for a municipality from the website, and turn it into a json struct
 *
 * @param string $type The data type (recette|depense)
 * @param int $r_id The region id
 * @param string $r_label The region label
 * @param int $m_id The municipality id
 * @param string $m_label The municipality label
 *
 * @return string The json struct
 */
function getData($type, $r_id, $r_label, $m_id, $m_label) {

    $html = getHtmlData($type, $r_id, $m_id);
    $xml = simplexml_load_string($html);
    $table = $xml->body->table;

    $struct = array();
    $struct['region']       = array('id' => $r_id, 'label' => $r_label);
    $struct['municipalite'] = array('id' => $m_id, 'label' => $m_label);
    $struct['title']        = ts($xml->body->h2);

    $date = explode('-', substr(ts($xml->body->div[1]), -10));
    $struct['date']   = implode('-', array_reverse($date));
    $struct['nef9at'] = array();

    $c_nef9a = -1;

    foreach ($table->tr as $tr) {

        switch (strtolower($tr['bgcolor'])) {

            case '#d9edf7': // nef9a
            $struct['nef9at'][] = array(
                'title' => $tr->td->div->h3->strong . '',
                'ejza2' => array(),
            );
            $c_nef9a++;
            $c_joz2 = -1;
            break;

            case '#00a300': // joz2
            $struct['nef9at'][$c_nef9a]['ejza2'][] = array(
                'title' => $tr->td->div->strong . '',
                'a9sem' => array(),
            );
            $c_joz2++;
            break;

            case '':
            case '#fafafa': // 9ism
            if ($c_nef9a == -1) continue;
            $struct['nef9at'][$c_nef9a]['ejza2'][$c_joz2]['a9sem'][] = array(
                'title'     => ts($tr->td[0]->strong),
                'predicted' => ts($tr->td[1]),
                'current'   => ts($tr->td[2]),
                'done'      => ts($tr->td[3]),
            );
            break;

        }
    }

    return json_encode($struct);
}

/**
 * Gets the data for a given $municipalite and $region
 *
 * @param string $type The data type (depense|recette)
 * @param int $region The region id
 * @param int $municipalite The municipality id
 *
 * @return Returns the cleaned HTML for a municipality budget  
 */
function getHtmlData($type, $region, $municipalite) {

    $ctx = stream_context_create(array(
        'http' => array(
            'method' => "POST",
            'header' => "Accept-language: en\r\n" .
            sprintf("User-Agent: %s\r\n", _UA) .
            "Content-type: application/x-www-form-urlencoded\r\n" .
            "Connection: close\r\n",
            'content' => http_build_query(array(
                'region' => $region, 
                'municipalite' => $municipalite,
                'ok' => 'المصادقة',
            ))
        )
    ));

    $html = file_get_contents(sprintf(_URL, $type), false, $ctx);

    return tidy($html);
}



/**
 * Clean up the given html buffer using php5-tidy
 *
 * @param string $html The dirty crappy html we're dealing with
 *
 * @return string A well formed xhtml document
 */
function tidy($html) {

    $html = str_replace("&nbsp;", ' ', $html);

    $config = array(
        'indent'         => true,
        'output-xhtml'   => true,
        'wrap'           => 200
    );

    // Tidy
    $tidy = new Tidy();
    $tidy->parseString($html, $config, 'utf8');
    $tidy->cleanRepair();

    return ts($tidy); 
}

/**
 * Force toString() to be called on objects, and trim texts too
 *
 * @param mixed $s The object or string
 *
 * @return string A trimmed reprensentation of $s
 */
function ts($s) {
    return trim('' . $s);
}

/**
 * Debug utility
 *
 * @param string $s The string to echo, or printf using additional parameters
 */
function debug($s) {
    if (func_num_args() > 1) {
        $args = func_get_args();
        array_shift($args);
        echo vsprintf($s, $args) . "\n";
    } else {
        echo $s . "\n";
    }
}
