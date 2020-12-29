<?php

namespace Helper;


class Wikidata
{
    const BASE_URL = 'https://query.wikidata.org/sparql';

    static $WIKIDATA_PROPERTY_MAP = [
        'P19' => 'placeOfBirth',
        'P20' => 'placeOfDeath',
        'P21' => 'gender',
        'P569' => 'dateOfBirth',
        'P570' => 'dateOfDeath',

        'P227' => 'gnd',
        'P214' => 'viaf',
        'P244' => 'lc_naf',
    ];

    static function fetchByGnd($gnd, $lang = 'de', $properties = null)
    {
        $entity = null;

        $pid = array_search('gnd', self::$WIKIDATA_PROPERTY_MAP);
        if (false === $pid) {
            return $entity;
        }

        $qids = self::lookupQidByProperty($pid, $gnd);

        foreach ($qids as $qid) {
            $entity = new Wikidata();
            $entity->identifier = $qid;
            $entity->gnd = $gnd;

            $properties = self::lookupProperties($qid, array_keys(self::$WIKIDATA_PROPERTY_MAP), $lang);

            foreach ($properties as $property) {
                $propertyId = (string)$property->propertyId;
                $propertyValue = $property->property;
                if ($propertyValue instanceOf \EasyRdf_Literal) {
                    $propertyValue = $propertyValue->getValue();
                }

                if ($propertyValue instanceOf \DateTime) {
                    $propertyValue = $propertyValue->format('Y-m-d');
                }

                if (!empty($propertyValue) && array_key_exists($propertyId, self::$WIKIDATA_PROPERTY_MAP)) {
                    switch ($propertyId) {
                        case 'P21': // sex or gender
                            switch ((string)$propertyValue) {
                                case 'http://www.wikidata.org/entity/Q6581072':
                                    $entity->{self::$WIKIDATA_PROPERTY_MAP[$propertyId]} = 'female';
                                    break;

                                case 'http://www.wikidata.org/entity/Q6581097':
                                    $entity->{self::$WIKIDATA_PROPERTY_MAP[$propertyId]} = 'male';
                                    break;

                                default:
                                    die('TODO: handle P21: ' . $propertyValue);
                            }
                            break;

                        default:
                            if ($propertyValue instanceOf \EasyRdf_Resource) {
                                $propertyValue = $property->propertyLabel;
                            }
                            else if ($propertyValue instanceOf \EasyRdf_Literal) {
                                $propertyValue = (string)$propertyValue;
                            }

                            if (in_array($propertyId, [ 'P569', 'P570' ])) {
                                if (preg_match('/^t/', $propertyValue)) {
                                    // we don't currently handle values like t1920690516
                                    break;
                                }
                            }

                            $entity->{self::$WIKIDATA_PROPERTY_MAP[$propertyId]} = $propertyValue;
                            break;
                    }
                }
            }

            break; // we currently take only first match
        }

        return $entity;
    }

    private static function getSparqlClient()
    {
        return new \EasyRdf_Sparql_Client(self::BASE_URL);
    }

    /**
     * Executes a SPARQL query
     *
     * @param string $query
     * @param \EasyRdf\Sparql\Client $client
     *
     * @return Result $result
     */
    protected static function executeSparqlQuery($query, $sparqlClient = null)
    {
        if (is_null($sparqlClient)) {
            $sparqlClient = self::getSparqlClient();
        }

        return $sparqlClient->query($query);
    }

    protected static function lookupQidByProperty($pid, $value, $sparqlClient = null)
    {
        $query = sprintf("SELECT ?wd WHERE { ?wd wdt:%s '%s'. }",
                         $pid, addslashes($value));

        $result = self::executeSparqlQuery($query, $sparqlClient);

        $ret = [];
        foreach ($result as $row) {
            $uri = (string)$row->wd;

            if (preg_match('~/(Q\d+)$~', $uri, $matches)) {
                $ret[] = $matches[1];
            }
        }

        return $ret;
    }

    protected static function lookupProperties($qid, $propertyIds, $language = 'en', $sparqlClient = null)
    {
        $unionParts = [];

        foreach ($propertyIds as $pid) {
            $unionParts[] = sprintf('{ wd:%s wdt:%s ?property. BIND("%s" as ?propertyId) }',
                                    $qid, $pid, $pid);
        }

        if (empty($unionParts)) {
            return [];
        }

        $languages = [ sprintf('"%s"', $language) ];
        if ('en' != $language) {
            $languages[] = '"en"'; // fallback
        }

        $query = 'SELECT ?property ?propertyId ?propertyLabel WHERE {'
            . implode(' UNION ', $unionParts)
            . ' SERVICE wikibase:label { bd:serviceParam wikibase:language ' . implode(', ', $languages) . ' }'
            . '}';

        return self::executeSparqlQuery($query);
    }

    var $identifier = null;
    var $gnd;
    var $viaf = null;
    var $lc_naf = null;
    var $preferredName;
    var $gender;
    var $academicTitle;
    var $dateOfBirth;
    var $placeOfBirth;
    var $placeOfResidence;
    var $dateOfDeath;
    var $placeOfDeath;
}
