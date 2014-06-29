<?php

namespace Api\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\Tools\Pagination\Paginator;

class PlaceController
{
    public function getAll(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];
        // Select your items.
        $dql = "SELECT P.id, P.name, C.iso2, P.latitude, P.longitude, P.geonames, P.gnd FROM Entities\Place P JOIN P.country C";

        $conditions = array();
        $q = $request->get('q');
        if (!empty($q)) {
            $conditions['q'] = $q;
        }

        if (!empty($conditions['q'])) {
            $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($conditions['q'], TRUE);
            $dql .= " WHERE MATCH (P.name) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        $dql .= ' ORDER BY C.iso2, P.name';

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

    /* public function getOne($id)
    {
        return new JsonResponse($this->personService->getOne($id));
    } */

    /*
    public function save(Request $request)
    {

        $person = $this->getDataFromRequest($request);
        return new JsonResponse(array("id" => $this->personService->save($note)));

    }

    public function update($id, Request $request)
    {
        $note = $this->getDataFromRequest($request);
        $this->personService->update($id, $note);
        return new JsonResponse($note);

    }

    public function delete($id)
    {
        return new JsonResponse($this->personService->delete($id));
    }
    */

    public function getDataFromRequest(Request $request)
    {
        return $place = array(
            'place' => $request->request->get('place')
        );
    }
}
