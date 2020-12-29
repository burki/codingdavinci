<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController
{
    private function addWordCount (&$words, $text, $stemmer = NULL, $split = TRUE)
    {
      // see http://stackoverflow.com/questions/790596/split-a-text-into-single-words
        $tokens = $split
            ? preg_split('/((^\p{P}+)|(\p{P}*\s+\p{P}*)|(\p{P}+$))/u', $text, -1, PREG_SPLIT_NO_EMPTY)
            : [ $text ];

        foreach ($tokens as $match) {
            $match = mb_strtolower($match, 'UTF-8');
            if (isset($stemmer)) {
                $match = $stemmer->apply($match);
            }
            if (!isset($words[$match])) {
                $words[$match] = 0;
            }
            $words[$match]++;
        }
    }

    function cmpPercentage ($a, $b)
    {
        if ($a['percentage'] == $b['percentage']) {
            return 0;
        }

        return ($a['percentage'] > $b['percentage']) ? -1 : 1;
    }

    public function introAction(Request $request, BaseApplication $app)
    {
        // display the intro
        return $app['twig']->render('statistics.intro.twig');
    }

    public function tableauAction(Request $request, BaseApplication $app)
    {
        // display the intro
        return $app['twig']->render('statistics.tableau.twig');
    }

    public function yearAction(Request $request, BaseApplication $app)
    {
        // display the authors by birth-year, the publications by issued-year
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();
        $querystr = "SELECT 'active' AS type, COUNT(*) AS how_many FROM person"
                  . " WHERE status >= 0 AND date_of_birth IS NOT NULL"
                  // . "  AND sex IS NOT NULL"
                  ;
        $querystr .= " UNION SELECT 'total' AS type, COUNT(*) AS how_many"
                   . " FROM person WHERE status >= 0";
        $stmt = $dbconn->query($querystr);
        $subtitle_parts = [];
        while ($row = $stmt->fetch()) {
          if ('active' == $row['type']) {
            $total_active = $row['how_many'];
          }
          $subtitle_parts[] = $row['how_many'];
        }
        $subtitle = implode(' von ', $subtitle_parts) . ' Personen';

        $data = [];
        $max_year = $min_year = 0;
        foreach ([ 'birth', 'death' ] as $key) {
            $date_field = 'date_of_' . $key;
            $querystr = 'SELECT YEAR(' . $date_field . ') AS year'
                      // . ', sex'
                      . ', COUNT(*) AS how_many'
                      . ' FROM person WHERE status >= 0 AND ' . $date_field . ' IS NOT NULL'
                      // . ' AND sex IS NOT NULL'
                      . ' GROUP BY YEAR(' . $date_field. ')'
                      // . ', sex'
                      . ' ORDER BY YEAR(' . $date_field . ')'
                      //. ', sex'
                      ;
            $stmt = $dbconn->query($querystr);

            while ($row = $stmt->fetch()) {
                if (0 == $min_year || $row['year'] < $min_year) {
                    $min_year = $row['year'];
                }

                if ($row['year'] > $max_year) {
                    $max_year = $row['year'];
                }

                if (!isset($data[$row['year']])) {
                    $data[$row['year']] = [];
                }

                $data[$row['year']][$key] = $row['how_many'];
            }
        }

        if ($min_year < 1830) {
            $min_year = 1830;
        }

        if ($max_year > 2000) {
            $max_year = 2000;
        }

        $total_works = 0;

        $querystr = 'SELECT publicationperson.publication_ord AS year, publication.complete_works = 0 AS base, COUNT(DISTINCT publication.id) AS how_many'
                  . ' FROM person'
                  . ' LEFT OUTER JOIN publicationperson ON publicationperson.person_id=person.id'
                  . ' LEFT OUTER JOIN publication ON publication.id=publicationperson.publication_id AND publication.status >= 0'
                  . ' WHERE person.status >= 0 AND publicationperson.publication_ord IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY publicationperson.publication_ord, publication.complete_works = 0'
                  . ' ORDER BY publicationperson.publication_ord, publication.complete_works = 0';
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $key = $row['base'] ? 'works_issued_base' : 'works_issued_extended';
            $data[$row['year']][$key] = $row['how_many'];
        }

        $categories = [];
        for ($year = $min_year; $year <= $max_year; $year++) {
            $categories[] = 0 == $year % 5 ? $year : '';
            foreach ([ 'birth', 'death',
                           /* 'works', */ 'works_issued_base', 'works_issued_extended'
                ] as $key)
            {
                $total[$key][$year] = isset($data[$year][$key])
                    ? intval($data[$year][$key]) : 0;
            }
        }

        return $app['twig']->render('statistics.year.twig', [
            'subtitle' => json_encode($subtitle),
            'categories' => json_encode($categories),
            'person_birth' => json_encode(array_values($total['birth'])),
            'person_death' => json_encode(array_values($total['death'])),
            'works_base' => json_encode(array_values($total['works_issued_base'])),
            'works_extended' => json_encode(array_values($total['works_issued_extended'])),
        ]);
    }

    public function wordCountAction(Request $request, BaseApplication $app)
    {
        // display the most often words in title
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();

        // preset custom stopwords
        $stopwords = [
            'roman' => true,
            'gesammelte' => true, 'werke' => true,
            'schriften' => true, 'ausgewählte' => true,
            'the' => true, 'and' => true,
            'van' => true,
        ];

        $words = file($app['base_path'] . '/resources/data/stopwords_de.txt',
                      FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($words as $word) {
            $stopwords[$word] = true;
        }

        $fields = [ 'title', 'other_title_information' ];
        $querystr = "SELECT issued, " . implode(', ', $fields)
                  . " FROM publication"
                  . " WHERE publication.status >= 0"
            ;
        $stmt = $dbconn->query($querystr);

        $stemmer = new TrivialStemmer();

        $group_by_date = true;
        $words = [ 'total' => [] ];
        while ($row = $stmt->fetch()) {
            foreach ($fields as $field) {
                if (!empty($row[$field])) {
                    $this->addWordCount($words['total'], $row[$field], $stemmer);
                    if ($group_by_date) {
                        $cat = 'rest';
                        if (isset($row['issued'])) {
                            if ($row['issued'] >= 1900 && $row['issued'] < 1910) {
                                $cat = '00er';
                            }
                            else if ($row['issued'] >= 1910 && $row['issued'] < 1920) {
                                $cat = '10er';
                            }
                            else if ($row['issued'] >= 1920 && $row['issued'] < 1930) {
                                $cat = '20er';
                            }
                            else if ($row['issued'] >= 1930 && $row['issued'] < 1940) {
                                $cat = '30er';
                            }
                            else if ($row['issued'] >= 1940 && $row['issued'] <= 1945) {
                                $cat = '40er';
                            }
                        }
                        $this->addWordCount($words[$cat], $row[$field], $stemmer);
                    }
                }
            }
        }

        arsort($words['total']);
        $types = array_keys($words);

        $categories = $total = [];
        foreach ($words['total'] as $word => $count) {
            if (count($categories) > 60) {
                // 60 terms
                break;
            }

            if (isset($stopwords[$word]) && $word) {
                continue;
            }

            if (mb_strlen($word, 'UTF-8') <= 2) {
                // skip short works
                continue;
            }

            $categories[] = $word;
            $total['total'][] = $count;
            if ($group_by_date) {
                foreach ($types as $type) {
                    if ('total' == $type) {
                        continue;
                    }
                    if (!isset($total[$type])) {
                        $total[$type] = [];
                    }
                    $total[$type][] = isset($words[$type][$word]) ? $words[$type][$word] : 0;
                }
            }
        }

        $subtitle = $group_by_date ? 'nach Jahrzehnten' : '';

        $render_values = [
            'subtitle' => json_encode($subtitle),
            'categories' => json_encode($categories),
        ];

        if ($group_by_date) {
            foreach ($types as $type) {
                if ('total' == $type) {
                    continue;
                }
                $render_values['word_' . $type] = json_encode(array_values($total[$type]));
            }
        }
        else {
            $render_values['word_total'] = json_encode(array_values($total['total']));
        }


        return $app['twig']->render('statistics.wordcount.twig', $render_values);
    }

    public function placeCountAction(Request $request, BaseApplication $app)
    {
        // display the most often death-places
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();

        // preset custom stopwords
        $stopwords = [];

        $words = [];
        foreach ($words as $word) {
            $stopwords[$word] = true;
        }

        $keys = [ 'birth', 'death' ];
        $words = [
            'total' => [],
            'birth_rest' => [],
            'death_rest' => [],
        ];

        foreach ($keys as $key) {
            $fields = [ 'place_of_' . $key ];
            $querystr = "SELECT YEAR(date_of_" . $key . ") AS issued, "
                      . implode(', ', $fields) . " FROM person"
                      . " WHERE person.status >= 0"
                      ;
            $stmt = $dbconn->query($querystr);

            $stemmer = new TrivialStemmer();

            $group_by_date = true;
            while ($row = $stmt->fetch()) {
                foreach ($fields as $field) {
                    if (!empty($row[$field])) {
                        $this->addWordCount($words['total'], $row[$field], $stemmer, false);
                        if ($group_by_date) {
                            $cat = $key . '_rest';
                            if (isset($row['issued'])) {
                                if ('birth' == $key) {
                                    if ($row['issued'] < 1890) {
                                        $cat = $key . '_1890';
                                    }
                                    else if ($row['issued'] >= 1890) {
                                        $cat = $key . '1890_';
                                    }
                                }
                                else {
                                    if ($row['issued'] < 1933) {
                                        $cat = $key . '_33';
                                    }
                                    else if ($row['issued'] >= 1933 && $row['issued'] <= 1945) {
                                        $cat = $key . '33_45';
                                    }
                                    else if ($row['issued'] > 1945) {
                                        $cat = $key . '45_';
                                    }
                                }
                            }
                            $this->addWordCount($words[$cat], $row[$field], $stemmer, false);
                        }
                    }
                }
            }
        }

        arsort($words['total']);
        $types = array_keys($words);

        $categories = $total = [];
        foreach ($words['total'] as $word => $count) {
            if (count($categories) > 60) {
                // 60 places
                break;
            }
            if (isset($stopwords[$word]) && $word) {
                continue;
            }
            if (mb_strlen($word, 'UTF-8') <= 2) {
                // skip short works
                continue;
            }
            $categories[] = $word;
            $total['total'][] = $count;
            if ($group_by_date) {
                foreach ($types as $type) {
                    if ('total' == $type) {
                        continue;
                    }
                    if (!isset($total[$type])) {
                        $total[$type] = [];
                    }
                    $total[$type][] = isset($words[$type][$word]) ? $words[$type][$word] : 0;
                }
            }
        }

        $subtitle = $group_by_date ? 'nach Epochen' : '';

        $render_values = [
            'subtitle' => json_encode($subtitle),
            'categories' => json_encode($categories),
        ];

        if ($group_by_date) {
            foreach ($types as $type) {
                if ('total' == $type) {
                    continue;
                }
                $render_values[$type] = json_encode(array_values($total[$type]));
            }
        }
        else {
            $render_values['word_total'] = json_encode(array_values($total['total']));
        }

        return $app['twig']->render('statistics.placecount.twig', $render_values);
    }

    public function leafletOrtAction(Request $request, BaseApplication $app)
    {
        // density map of the publication-countries as described in
        // http://leafletjs.com/examples/choropleth.html
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();

        $querystr = 'SELECT country.iso3 AS country_code, country.name_de AS name_de'
                  . ', COUNT(DISTINCT publication.id) AS how_many'
                  . ' FROM publication'
                  . ' JOIN place ON publication.geonames_place_of_publication=place.geonames'
                  . ' JOIN country ON place.country_code=country.iso2'
                  . ' WHERE publication.status >= 0'
                  . ' GROUP BY country.iso3'
                  // . ' ORDER BY publicationperson.publication_ord, publication.complete_works = 0'
                  ;
        $stmt = $dbconn->query($querystr);
        $publications_by_country = [];
        $country_names_de = [];
        while ($row = $stmt->fetch()) {
            $publications_by_country[$row['country_code']] = $row['how_many'];
            $country_names_de[$row['country_code']] = $row['name_de'];
        }
        $file = $app['base_path'] . '/resources/data/countries.geo.json';
        $features = json_decode(file_get_contents($file), true);
        $remove = [];
        for ($i = 0; $i < count($features['features']); $i++) {
            $feature = & $features['features'][$i];
            if (array_key_exists($feature['id'], $publications_by_country)) {
                $feature['properties']['density'] = intval($publications_by_country[$feature['id']]);
                if (!empty($country_names_de[$feature['id']])) {
                    // set german instead of english name
                    $feature['properties']['name'] = $country_names_de[$feature['id']];
                }
            }
            else {
                $remove[] = $i;
            }
        }

        foreach ($features['features'] as $i => $dummy) {
            if (in_array($i, $remove)) {
                unset($features['features'][$i]);
            }
        }

        $features['features'] = array_values($features['features']); // re-index after removal
        $features_json = json_encode($features);

        $content = <<<EOT
    <div id="map" style="width:800px; height: 450px; position: relative;"></div>
    <script>
        var map = L.map('map');

        map.fitBounds([
            [-45, -130],
            [63, 150]
        ]);

        function getColor(d) {
            return d > 10000 ? '#800026' :
                   d > 1000  ? '#BD0026' :
                   d > 500  ? '#E31A1C' :
                   d > 100  ? '#FC4E2A' :
                   d > 50   ? '#FD8D3C' :
                   d > 10   ? '#FEB24C' :
                   d > 5   ? '#FED976' :
                              '#FFEDA0';
        }

        function style(feature) {
            return {
                fillColor: getColor(feature.properties.density),
                weight: 2,
                opacity: 1,
                color: 'white',
                dashArray: '3',
                fillOpacity: 0.7
            };
        }

        function highlightFeature(e) {
            var layer = e.target;

            layer.setStyle({
                weight: 5,
                color: '#666',
                dashArray: '',
                fillOpacity: 0.7
            });

            if (!L.Browser.ie && !L.Browser.opera) {
                layer.bringToFront();
            }

            info.update(layer.feature.properties);
        }

        var geojson;

        function resetHighlight(e) {
            geojson.resetStyle(e.target);
            info.update();
        }

        function zoomToFeature(e) {
            map.fitBounds(e.target.getBounds());
        }

        function onEachFeature(feature, layer) {
            layer.on({
                mouseover: highlightFeature,
                mouseout: resetHighlight,
                click: zoomToFeature
            });
        }

        var info = L.control();

        info.onAdd = function (map) {
            this._div = L.DomUtil.create('div', 'info'); // create a div with a class "info"
            this.update();
            return this._div;
        };

        // method that we will use to update the control based on feature properties passed
        info.update = function (props) {
            this._div.innerHTML = '<!--<h4>Verbotene Publikationen nach Publikationsland</h4>-->'
                + '<table id="legend">'
                +        '<tr>'
                +            '<td bgcolor="#800026" ></td><td>&gt; 10000&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#BD0026" ></td><td>&gt; 1000&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#E31A1C" ></td><td>&gt; 500&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#FC4E2A" ></td><td>&gt; 100&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#FD8D3C" ></td><td>&gt; 50&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#FEB24C" ></td><td>&gt; 10&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#FED976" ></td><td>&gt; 5&nbsp;&nbsp;</td>'
                +            '<td bgcolor="#FFEDA0" ></td><td>&le; 5</td>'
                +        '</tr>'
                +    '</table>'
                +  (props
                    ? '<b>' + props.name + '</b>: ' + props.density
                    : 'Bewegen Sie die Maus &uuml;ber ein Land');
        };

        info.addTo(map);

        L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
            tileSize: 512,
            maxZoom: 18,
            zoomOffset: -1,
            attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
                '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
                'Imagery &copy; <a href="http://mapbox.com">Mapbox</a>',
            id: 'verbrannte-verbannte/ck9myxibl0abt1io1e0u7joni', // 'mapbox/outdoors-v11',
            accessToken: 'pk.eyJ1IjoidmVyYnJhbm50ZS12ZXJiYW5udGUiLCJhIjoiUXUtM1ZhTSJ9.os53GU9yi7z-QQ5zv2vU-A'
        }).addTo(map);

        var countriesData = ${features_json};

        geojson = L.geoJson(countriesData,
            {
                style: style,
                onEachFeature: onEachFeature
            }).addTo(map);
    </script>

EOT;
        // display the static content
        return $app['twig']->render('static.leaflet.twig', [
            'content' => $content,
        ]);
    }

    public function personenWikipediaAction(Request $request, BaseApplication $app)
    {
        $file = $app['base_path'] . '/resources/data/personAndWikiStats.csv';

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $first = true;
        $data = [];
        foreach ($lines as $line) {
            if ($first) {
                // skip header
                $first = false;
                continue;
            }
            $values = preg_split('/\t/', $line);
            if (empty($values[1]) || ($values[2] <= 1)) {
                // no wiki article or no hits
                continue;
            }
            $single_data = [
                'name' => $values[1], // article
                'x' => (int)$values[2], // HITS (btw 2014/05/28 and 2014/06/26)
                'y' => (int)$values[5], // WORD_COUNT (on 2014/06/28)
            ];
            $data[] = $single_data;
        }

        // display the static content
        return $app['twig']->render('statistics.wikipedia.twig', [
            'data' => json_encode($data),
        ]);
    }

    public function d3jsOrtAction(Request $request, BaseApplication $app)
    {
        $content = '';
        // display the static content
        return $app['twig']->render('static.d3js.twig', [
            'content' => $content,
        ]);
    }
}

class TrivialStemmer
{
    static $map = [
        //'stillleben' => 'stilleben'
    ];

    function apply ($word) {
        return isset(self::$map[$word]) ? self::$map[$word] : $word;
    }
}
