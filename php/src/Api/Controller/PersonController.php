<?php

namespace Api\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\Tools\Pagination\Paginator;

class PersonController
{
    public function getAll(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];
        // Select your items.
        $dql = "SELECT P.id, P.surname, P.forename, P.dateOfBirth, P.dateOfDeath, IFNULL(P.surname, 'ZZ') HIDDEN surnameSort FROM Entities\Person P";

        $conditions = [];
        $q = $request->get('q');
        if (!empty($q)) {
            $conditions['q'] = $q;
        }

        if (!empty($conditions['q'])) {
          $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($conditions['q'], TRUE);
          $dql .= " WHERE MATCH (P.surname, P.forename) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        $dql .= ' ORDER BY surnameSort, P.forename';

        /*
        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get('page', 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        */


        // Create the query
        $query = $em->createQuery($dql);


        // $query->setFirstResult($offset)->setMaxResults($limit);

        return new JsonResponse($result = $query->getArrayResult());
    }

    public function getDataFromRequest(Request $request)
    {
        return $person = [
            'person' => $request->request->get('person')
        ];
    }
}
