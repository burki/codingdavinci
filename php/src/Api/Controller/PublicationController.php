<?php

namespace Api\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicationController
{
    public function getAll(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];
        // Select your items.
        $dql = "SELECT P.id, P.title, P.publicationStatement, IFNULL(P.title, 'ZZ') HIDDEN titleSort FROM Entities\Publication P ORDER BY titleSort";
        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get('page', 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        // Create the query
        $query = $em->createQuery($dql);
        // $query->setFirstResult($offset)->setMaxResults($limit);

        return new JsonResponse($result = $query->getArrayResult());
    }

    /* public function getOne($id)
    {
        return new JsonResponse($this->personService->getOne($id));
    } */

    public function getDataFromRequest(Request $request)
    {
        return $person = [
            'person' => $request->request->get('person')
        ];
    }
}
