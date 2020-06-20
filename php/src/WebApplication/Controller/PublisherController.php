<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublisherDTO
{
    var $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

class PublisherController
{
    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        // we set filter/order into route-params
        $route_params = $request->attributes->get('_route_params');

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], [ 'routeName' => 'publication' ]);

        // build where
        $sql_where = [ "publisher <> ''" ];

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
          $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
          $sql_where[] = "MATCH (P.publisher, Publisher.preferred_name) AGAINST ('" . $fulltext_condition . "' IN BOOLEAN MODE)";
        }

        // build order
        $searchwidget->addSortBy('publisher', [ 'label' => 'Verlag' ]);
        $searchwidget->addSortBy('publicationCount', [ 'label' => 'Anzahl Publikationen' ]);
        $sort_by = $searchwidget->getCurrentSortBy();
        $orders = [ 'publisherSort' => 'ASC', 'publicationCount' => 'DESC' ];
        if (false !== $sort_by) {
            $orders = [ $sort_by[0] . 'Sort' => $sort_by[1] ] + $orders;
        }
        // var_dump($orders);

        $queryBuilder = new \Doctrine\DBAL\Query\QueryBuilder($em->getConnection());
        $queryBuilder->select("MIN(Publisher.id) AS publisher_id, IFNULL(Publisher.preferred_name, P.publisher) AS name, COUNT(*) AS publicationCount, COUNT(*) AS publicationCountSort, IFNULL(IFNULL(Publisher.preferred_name, P.publisher), 'ZZ') AS publisherSort")
            ->from('Publication', 'P')
            ->leftJoin('P', 'Publisher', 'Publisher', 'Publisher.id = P.publisher_id')
            ->groupBy('name');

        if (!empty($sql_where)) {
            $queryBuilder->where(implode(' AND ', $sql_where));
        }

        // Create the query
        if (!empty($orders)) {
            foreach ($orders as $field => $direction) {
                $queryBuilder->addOrderBy($field, $direction);
            }
        }

        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get('page', 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        // $queryBuilder->setFirstResult($offset)->setMaxResults($limit);

        // create a pager
        // $adapter = new \Pagerfanta\Adapter\DoctrineORMAdapter($query, false, false);

        $countQueryBuilderModifier = function ($queryBuilder) {
            $queryBuilder->select('COUNT(DISTINCT IFNULL(Publisher.preferred_name, P.publisher)) AS total_results')
                ->resetQueryPart('groupBy')
                ->resetQueryPart('orderBy')
                ->setMaxResults(1)
                ;
        };
        $adapter = new \Pagerfanta\Adapter\DoctrineDbalAdapter($queryBuilder, $countQueryBuilderModifier);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        // set them for paging
        $request->attributes->set('_route_params', $route_params);

        // display the list
        return $app['twig']->render('publisher.index.twig', [
            'pageTitle' => 'Verlage',
            'searchwidget' => $searchwidget,
            'pager' => $pagerfanta,
            'entries' => $pagerfanta->getCurrentPageResults(),
        ]);
    }

    public function detailAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $id = $request->get('id');

        $entity = $em->getRepository('Entities\Publisher')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Publisher $id does not exist.");
        }

        $render_params = [
            'pageTitle' => $entity->preferredName . ' - Verlag',
            'entry' => $entity,
        ];

        if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $entity->gnd, $matches)) {
            $render_params['gnd'] = $matches[1];
        }

        return $app['twig']->render('publisher.detail.twig', $render_params);
    }
}
