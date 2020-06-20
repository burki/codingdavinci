<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PlaceController
{
    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        // we set filter/order into route-params
        $route_params = $request->attributes->get('_route_params');

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], [ 'routeName' => 'place' ]);

        // build where
        $dql_where = [];

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
            $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
            $dql_where[] = "MATCH (P.name) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        /*
        $searchwidget->addFilter('place', [
            'all' => 'Alle Orte',
            'missing' => 'Ohne Geonames',
        ]);
        */
        $filters = $searchwidget->getActiveFilters();

        // build order
        $searchwidget->addSortBy('name', [ 'label' => 'Name' ]);
        $searchwidget->addSortBy('country', [ 'label' => 'Land' ]);

        $sort_by = $searchwidget->getCurrentSortBy();
        $orders = [ 'nameSort' => 'ASC', 'C.germanName' => 'ASC' ];
        if (false !== $sort_by) {
            $orders = [ $sort_by[0] . 'Sort' => $sort_by[1] ] + $orders;
        }
        // var_dump($orders);

        // Select your items.
        $dql = "SELECT P, IFNULL(P.name, 'ZZ') HIDDEN nameSort, C.germanName HIDDEN countrySort, C"
             . " FROM Entities\Place P JOIN P.country C";
        if (array_key_exists('place', $filters)) {
            if ('missing' == $filters['place']) {
                $dql_where[] = "P.geonames IS NULL";
            }
            else {
                $dql_where[] = "P.gnd IS NOT NULL"; // currently only places connected to Person
            }
        }
        else {
            $dql_where[] = "P.gnd IS NOT NULL"; // currently only places connected to Person
        }

        if (!empty($dql_where)) {
            $dql .= ' WHERE ' . implode(' AND ', $dql_where);
        }

        // Create the query
        if (!empty($orders)) {
            $dql .= ' ORDER BY '
                  . implode(', ',
                            array_map(
                                      function($field) use ($orders) {
                                        return $field . ' ' . $orders[$field];
                                      },
                                      array_keys($orders)
                                     ));
        }
        $query = $em->createQuery($dql);

        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get('page', 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        $query->setFirstResult($offset)->setMaxResults($limit);

        // create a pager
        $adapter = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        // set them for paging
        $request->attributes->set('_route_params', $route_params);

        // display the list
        return $app['twig']->render('place.index.twig', [
            'pageTitle' => 'Orte',
            'searchwidget' => $searchwidget,
            'pager' => $pagerfanta,
            'entries' => $pagerfanta->getCurrentPageResults(),
        ]);
    }

    public function detailAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $id = $request->get('id');
        if (!isset($id)) {
            // check if we can get by gnd
            $gnd = $request->get('gnd');
            if (!empty($gnd)) {
                $qb = $em->createQueryBuilder();
                $qb->select([ 'P.id' ])
                    ->from('Entities\Place', 'P')
                    ->where($qb->expr()->like('P.gnd', ':gnd'))
                    ->setParameter('gnd', '%' . $gnd)
                    ->setMaxResults(1);
                $query = $qb->getQuery();
                $results = $query->getResult();
                foreach ($results as $result) {
                    $id = $result['id'];
                }
            }
        }

        $entity = $em->getRepository('Entities\Place')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Place $id does not exist.");
        }

        $render_params = [
            'pageTitle' => $entity->name . ' - Ort',
            'entry' => $entity,
        ];

        $placesOfBirth = $placesOfDeath = null;
        $lifePaths = [];
        $placesGnds = [];
        $personsByPlace = [];
        if (preg_match('/d\-nb\.info\/gnd\/([0-9xX\-]+)/', $entity->gnd, $matches)) {
            $placesGnds[$entity->gnd] = true;
            $render_params['gnd'] = $matches[1];

            // find related Person entries
            $placesOfBirth = $em->getRepository('Entities\Person')->findBy(
                [ 'gndPlaceOfBirth' => $entity->gnd ],
                [ 'surname' => 'ASC', 'forename' => 'ASC' ]
            );
            $placesOfDeath = $em->getRepository('Entities\Person')->findBy(
                [ 'gndPlaceOfDeath' => $entity->gnd ],
                [ 'surname' => 'ASC', 'forename' => 'ASC' ]
            );

            foreach ($placesOfBirth as $person) {
                if (!array_key_exists($entity->gnd, $personsByPlace)) {
                    $personsByPlace[$entity->gnd] = [];
                }
                $personsByPlace[$entity->gnd][$person->id] = $person;
                $gndPlaceOfDeath = $person->gndPlaceOfDeath;
                if (!empty($gndPlaceOfDeath)) {
                    if (!array_key_exists($gndPlaceOfDeath, $personsByPlace)) {
                        $personsByPlace[$gndPlaceOfDeath] = [];
                    }
                    $personsByPlace[$gndPlaceOfDeath][$person->id] = $person;
                    $placesGnds[$gndPlaceOfDeath] = true;
                    $lifePaths[$person->id] = [ $entity->gnd, $gndPlaceOfDeath ];
                }
            }

            foreach ($placesOfDeath as $person) {
                if (!array_key_exists($entity->gnd, $personsByPlace)) {
                    $personsByPlace[$entity->gnd] = [];
                }
                $personsByPlace[$entity->gnd][$person->id] = $person;
                $gndPlaceOfBirth = $person->gndPlaceOfBirth;
                if (!empty($gndPlaceOfBirth)) {
                    if (!array_key_exists($gndPlaceOfBirth, $personsByPlace)) {
                        $personsByPlace[$gndPlaceOfBirth] = [];
                    }
                    $personsByPlace[$gndPlaceOfBirth][$person->id] = $person;
                    $placesGnds[$gndPlaceOfBirth] = true;
                    $lifePaths[$person->id] = [ $gndPlaceOfBirth, $entity->gnd ];
                }
            }
        }

        $render_params['placesOfBirth'] = $placesOfBirth;
        $render_params['placesOfDeath'] = $placesOfDeath;

        $map = null;
        $places = [];
        $min_latitude = $min_longitude = 1000;
        $max_longitude = $max_latitude = -1000;
        if (count($placesGnds) > 1) {
            $qb = $em->createQueryBuilder();
            $qb->select([ 'P.id, P.name, P.gnd, P.latitude, P.longitude' ])
               ->from('Entities\Place', 'P')
               /* the following segfaults on PHP 7.x, see https://github.com/doctrine/dbal/issues/2330
               ->where('P.longitude IS NOT NULL AND P.latitude IS NOT NULL')
               ->andWhere('P.gnd IN (:gnds)')
               */
               ->where('P.longitude IS NOT NULL AND P.latitude IS NOT NULL AND P.gnd IN (:gnds)')
               ->setParameter('gnds', array_keys($placesGnds))
               ;

            $query = $qb->getQuery();
            $results = $query->getResult();
            $markers = $polylines = '';

            foreach ($results as $place) {
                $places[$place['gnd']] = $place;
                $persons = [];
                foreach ($personsByPlace[$place['gnd']] as $person) {
                    $persons[] = sprintf('<a href="%s">%s</a>',
                                         htmlspecialchars($app['url_generator']->generate('person-detail', [ 'id' => $person->id ])),
                                         htmlspecialchars($person->getFullname(true), ENT_COMPAT, 'utf-8'));
                }

                $markers .= 'L.marker(['
                          . $place['latitude']
                          . ', '
                          . $place['longitude'] . ']'
                          . ($entity->gnd == $place['gnd'] ? ', {icon: iconSelf}' : ', {icon: iconOther}')
                          . ').addTo(map)'
                          . '.bindPopup('
                          . json_encode('<b><a href="' . $app['url_generator']->generate('place-detail', [ 'id' => $place['id'] ]) . '">'
                                        . htmlspecialchars($place['name'], ENT_COMPAT, 'utf-8')
                                        . '</a></b>'
                                        . (!empty($persons) ? '<br />' . implode('<br />', $persons) : '')
                                       )
                          . ');';

                if ($place['latitude'] > $max_latitude) {
                    $max_latitude = $place['latitude'];
                }
                if ($place['latitude'] < $min_latitude) {
                    $min_latitude = $place['latitude'];
                }
                if ($place['longitude'] > $max_longitude) {
                    $max_longitude = $place['longitude'];
                }
                if ($place['longitude'] < $min_longitude) {
                    $min_longitude = $place['longitude'];
                }
            }

            foreach ($lifePaths as $person_id => $entry) {
                if ($entry[0] != $entry[1]
                    && isset($places[$entry[0]]) && isset($places[$entry[1]]))
                {
                    $from_latitude = $places[$entry[0]]['latitude'];
                    $from_longitude = $places[$entry[0]]['longitude'];
                    $to_latitude = $places[$entry[1]]['latitude'];
                    $to_longitude = $places[$entry[1]]['longitude'];

                    $color = $entity->gnd == $entry[0] ? '#d62728' : '#2ca02c';

                    $polylines .= <<<EOT
                    L.geodesicPolyline([[${from_latitude}, ${from_longitude}],
                               [${to_latitude}, ${to_longitude}]],
                               {color: '{$color}'}).addTo(map);
EOT;
                }
            }

            $map = '<h2>Lebenswege</h2>';
            $map .= '<div id="map" style="width:543px; height: 450px;"></div>';
            $map .= <<<EOT
    <script>
        var map = L.map('map');
        map.fitBounds([
            [${min_latitude}, ${min_longitude}],
            [${max_latitude}, ${max_longitude}]
        ]);
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
        var iconSelf = L.MakiMarkers.icon({icon: "circle", color: "#00be7b", size: "m"});
        var iconOther = L.MakiMarkers.icon({icon: "circle", color: "#1e90ff", size: "m"});

        ${markers}
        ${polylines}
    </script>
EOT;
        // var_dump($min_latitude . '<->' . $max_latitude . ' | ' . $min_longitude . '<->' . $max_longitude);

        }

        $render_params['map'] = $map;

        return $app['twig']->render('place.detail.twig', $render_params);
    }

    /*
    public function gndBeaconAction(Request $request, BaseApplication $app) {
        $em = $app['doctrine'];

        $ret = '#FORMAT: BEACON' . "\n" . '#PREFIX: http://d-nb.info/gnd/' . "\n";
        $ret .= sprintf('#TARGET: %s/gnd/{ID}',
                        $app['url_generator']->generate('place', [], true))
              . "\n";
        $ret .= '#NAME: Verbrannte und Verbannte' . "\n";
        $ret .= '#MESSAGE: Eintrag in der Liste der im Nationalsozialismus verbotenen Publikationen und Autoren' . "\n";

        $dql = "SELECT DISTINCT P.id, P.gnd FROM Entities\Place P WHERE P.status >= 0 AND P.gnd IS NOT NULL ORDER BY P.gnd";
        $query = $em->createQuery($dql);
        // $query->setMaxResults(10);
        foreach ($query->getResult() as $result) {
            if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)$/', $result['gnd'], $matches)) {
                $gnd_id = $matches[1];
                $ret .=  $gnd_id . "\n";
            }
        }

        return new Response($ret,
                            200,
                            [ 'Content-Type' => 'text/plain; charset=UTF-8' ]
                            );
    }
    */

    public function editAction(Request $request, BaseApplication $app)
    {
        $edit_fields = [ 'gnd', 'geonames', 'name' ];

        $em = $app['doctrine'];

        $id = $request->get('id');

        $entity = $em->getRepository('Entities\Place')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Place $id does not exist.");
        }

        $preset = [];
        foreach ($edit_fields as $key) {
            $preset[$key] = $entity->$key;
        }

        $form = $app['form.factory']->createBuilder('form', $preset)
            ->add('name', 'text', [
                'label' => 'Name',
                'required' => true,
            ])
            ->add('geonames', 'text', [
                'label' => 'Geonames',
                'required' => false,
            ])
            ->add('gnd', 'text', [
                'label' => 'GND',
                'required' => false,
            ])
            ->getForm()
            ;

        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            $persist = false;
            foreach ($edit_fields as $key) {
                $value_before =  $entity->$key;
                if ($value_before !== $data[$key]) {
                    $persist = true;
                    $entity->$key = $data[$key];
                }
            }
            if ($persist) {
                $em->persist($entity);
                $em->flush();
                return $app->redirect($app['url_generator']->generate('place-detail', [ 'id' => $id ]));
            }
        }

        // var_dump($entity);
        return $app['twig']->render('place.edit.twig', [
            'entry' => $entity,
            'form' => $form->createView(),
        ]);
    }
}
